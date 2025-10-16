<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/dbconnect.php';
require_once __DIR__ . '/../Includes/auth.php';


if (!isset($_SESSION['User_ID'])) {
    header("Location: ../Login.php");
    exit;
}

$uid = (int)$_SESSION['User_ID'];
$stmt = $conn->prepare("SELECT User_Type FROM user WHERE User_ID=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

$role = $u ? strtolower(trim((string)$u['User_Type'])) : '';
if (!in_array($role, ['guide','driver'], true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

if ($role === 'guide') {
    $pstmt = $conn->prepare("SELECT g.*, u.Username, u.User_Profile FROM guide g JOIN user u ON u.User_ID=g.User_ID WHERE g.User_ID=? LIMIT 1");
} else {
    $pstmt = $conn->prepare("SELECT d.*, u.Username, u.User_Profile FROM driver d JOIN user u ON u.User_ID=d.User_ID WHERE d.User_ID=? LIMIT 1");
}
$pstmt->bind_param("i", $uid);
$pstmt->execute();
$profile = $pstmt->get_result()->fetch_assoc();

$entityId = null;
$first = $last = $status = '';
$totalIncome = 0;
$completedTrips = 0;
$username = '';
$profileImg = '';

function resolve_profile_img($uid, $raw){
    $raw = (string)$raw;
    if ($raw !== '') {
        if (preg_match('~^https?://~', $raw) || str_starts_with($raw, '../') || str_starts_with($raw, '/')) {
            return $raw;
        }
        if (strpos($raw, 'uploads/UserProfiles') !== false) {
            return '../' . ltrim($raw, '/');
        }
        return '../uploads/UserProfiles/' . ltrim($raw, '/');
    }
    $guess = "../uploads/UserProfiles/user_{$uid}";
    $cands = [$guess.".jpg",$guess.".jpeg",$guess.".png",$guess.".webp"];
    foreach($cands as $p){ if (file_exists($p)) return $p; }
    return 'Images/defaultuser.jpg';
}

if ($role === 'guide') {
    if ($profile) {
        $entityId = (int)$profile['Guide_ID'];
        $first = (string)$profile['F_Name'];
        $last = (string)$profile['L_Name'];
        $status = (string)$profile['Status'];
        $totalIncome = (float)$profile['Total_Income'];
        $completedTrips = (int)$profile['Completed_trips'];
        $username = (string)$profile['Username'];
        $profileImg = resolve_profile_img($uid, $profile['User_Profile'] ?? '');
    }
} else {
    if ($profile) {
        $entityId = (int)$profile['Driver_ID'];
        $first = (string)$profile['F_Name'];
        $last = (string)$profile['L_Name'];
        $status = (string)$profile['Status'];
        $totalIncome = (float)$profile['Total_Income'];
        $completedTrips = (int)$profile['Completed_trips'];
        $username = (string)$profile['Username'];
        $profileImg = resolve_profile_img($uid, $profile['User_Profile'] ?? '');
    }
}

if (!$profile) {
    $entityId = null;
    $first = $last = '';
    $status = 'Available';
    $totalIncome = 0;
    $completedTrips = 0;
    $username = '';
    $profileImg = 'Images/defaultuser.jpg';
}

function parse_progress_val($s){
    $s = (string)$s;
    $parts = explode('|',$s,2);
    $pct = isset($parts[0]) && is_numeric($parts[0]) ? (int)$parts[0] : 0;
    $rest = $parts[1] ?? '';
    $counts = 0; $total = 0; $msg = '';
    if ($rest !== '') {
        $spacePos = strpos($rest,' ');
        $ct = $spacePos === false ? $rest : substr($rest,0,$spacePos);
        if (strpos($ct,'/') !== false) {
            list($a,$b) = explode('/',$ct,2);
            if (is_numeric($a)) $counts = (int)$a;
            if (is_numeric($b)) $total = (int)$b;
        }
        $msg = $spacePos === false ? '' : trim(substr($rest,$spacePos+1));
    }
    return [$pct,$counts,$total,$msg];
}

function total_stops_for_booking($conn,$row){
    if (strcasecmp($row['Booking_Type'],'Package')===0 && !empty($row['Package_ID'])) {
        $q=$conn->prepare("SELECT COUNT(*) c FROM itinerary WHERE PackageID=?");
        $pid=(int)$row['Package_ID'];
        $q->bind_param("i",$pid);
        $q->execute();
        $c=$q->get_result()->fetch_assoc();
        return (int)($c['c']??0);
    } else {
        $q=$conn->prepare("SELECT COUNT(*) c FROM booking_destinations WHERE Booking_ID=?");
        $bid=(int)$row['Booking_ID'];
        $q->bind_param("i",$bid);
        $q->execute();
        $c=$q->get_result()->fetch_assoc();
        return (int)($c['c']??0);
    }
}

$dash_success = "";
$dash_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status_update']) && $entityId) {
    $newStatus = $_POST['status'] === 'Un_available' ? 'Un_available' : 'Available';
    if ($role === 'guide') {
        $ust = $conn->prepare("UPDATE guide SET Status=? WHERE Guide_ID=?");
    } else {
        $ust = $conn->prepare("UPDATE driver SET Status=? WHERE Driver_ID=?");
    }
    $ust->bind_param("si", $newStatus, $entityId);
    $ust->execute();

    if ($newStatus === 'Un_available') {
        $mode = $_POST['unavail_mode'] ?? 'today';
        $start = null;
        $end = null;
        if ($mode === 'today') {
            $start = date('Y-m-d');
            $end = null;
        } else {
            $start = $_POST['start_date'] ?: date('Y-m-d');
            $end = $_POST['end_date'] ?: null;
        }
        if ($role === 'guide') {
            $conn->query("CREATE TABLE IF NOT EXISTS guide_unavailability (
                ID INT AUTO_INCREMENT PRIMARY KEY,
                Guide_ID INT NOT NULL,
                Start_Date DATE NOT NULL,
                End_Date DATE NULL,
                Created_At DATETIME NOT NULL,
                INDEX(Guide_ID),
                FOREIGN KEY (Guide_ID) REFERENCES guide(Guide_ID) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ins = $conn->prepare("INSERT INTO guide_unavailability (Guide_ID, Start_Date, End_Date, Created_At) VALUES (?,?,?,NOW())");
            $ins->bind_param("iss", $entityId, $start, $end);
            $ins->execute();
        } else {
            $conn->query("CREATE TABLE IF NOT EXISTS driver_unavailability (
                ID INT AUTO_INCREMENT PRIMARY KEY,
                Driver_ID INT NOT NULL,
                Start_Date DATE NOT NULL,
                End_Date DATE NULL,
                Created_At DATETIME NOT NULL,
                INDEX(Driver_ID),
                FOREIGN KEY (Driver_ID) REFERENCES driver(Driver_ID) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $ins = $conn->prepare("INSERT INTO driver_unavailability (Driver_ID, Start_Date, End_Date, Created_At) VALUES (?,?,?,NOW())");
            $ins->bind_param("iss", $entityId, $start, $end);
            $ins->execute();
        }
    }
    header("Location: GuideDashboard.php");
    exit;
}

$ownerCol = ($role === 'guide') ? 'Guide_ID' : 'Driver_ID';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trip_action']) && $entityId) {
    $act = $_POST['trip_action'];
    $bid = (int)($_POST['Booking_ID'] ?? 0);
    if ($bid>0) {
        $chkSql = "SELECT * FROM bookings WHERE Booking_ID=? AND {$ownerCol}=? LIMIT 1";
        $chk = $conn->prepare($chkSql);
        $chk->bind_param("ii",$bid,$entityId);
        $chk->execute();
        $b = $chk->get_result()->fetch_assoc();
        if ($b) {
            if ($role === 'driver' && !empty($b['Guide_ID'])) {
                $dash_error = "Trip progress is handled by the assigned guide.";
            } else {
                $today = date('Y-m-d');
                if ($act==='start') {
                    $cashGate = (strcasecmp($b['Payment_Method'],'Cash')===0) ? (strcasecmp($b['Payment_Status'],'Paid')===0) : true;
                    if ($cashGate && in_array($b['Status'],['Pending','Confirmed'],true) && substr((string)$b['Start_Date_Time'],0,10)===$today) {
                        $total = total_stops_for_booking($conn,$b);
                        $msg = "0/{$total} Started at ".date('Y-m-d H:i');
                        $progress = "0|{$msg}";
                        $u = $conn->prepare("UPDATE bookings SET Status='In_Progress', Progress=? WHERE Booking_ID=?");
                        $u->bind_param("si",$progress,$bid);
                        if ($u->execute()) { $dash_success="Trip started."; } else { $dash_error="Failed to start."; }
                    } else {
                        $dash_error="Cannot start; check date or payment.";
                    }
                } elseif ($act==='complete' && $b['Status']==='In_Progress') {
                    $dest = trim((string)($_POST['Destination'] ?? ''));
                    list($pct,$done,$total,$oldmsg)=parse_progress_val($b['Progress']);
                    if ($total<=0) $total = total_stops_for_booking($conn,$b);
                    if (strcasecmp($b['Booking_Type'],'customize')===0 && $dest!=='') {
                        $bd = $conn->prepare("UPDATE booking_destinations SET Status='compleeted' WHERE Booking_ID=? AND Destination=? AND (Status IS NULL OR Status='upcomming') LIMIT 1");
                        $bd->bind_param("is",$bid,$dest);
                        $bd->execute();
                        $cntQ = $conn->prepare("SELECT COUNT(*) c FROM booking_destinations WHERE Booking_ID=? AND Status='compleeted'");
                        $cntQ->bind_param("i",$bid);
                        $cntQ->execute();
                        $done = (int)($cntQ->get_result()->fetch_assoc()['c'] ?? 0);
                    } else {
                        $done = min(max(0,$done)+1, max(1,$total));
                    }
                    $pct = $total>0 ? (int)floor($done*100/$total) : 100;
                    if ($done >= $total) {
                        $msg = "{$done}/{$total} All destinations completed. Proceed to end trip at ".(string)$b['End_Location']." ".date('Y-m-d H:i');
                    } else {
                        $msg = "{$done}/{$total} Completed ".($dest!==''?$dest:'destination')." at ".date('Y-m-d H:i');
                    }
                    $newProg = "{$pct}|{$msg}";
                    $u = $conn->prepare("UPDATE bookings SET Progress=? WHERE Booking_ID=?");
                    $u->bind_param("si",$newProg,$bid);
                    if ($u->execute()) { $dash_success=$done>=$total?"All destinations completed. End at final location.":"Progress updated."; } else { $dash_error="Update failed."; }
                } elseif ($act==='end' && $b['Status']==='In_Progress') {
                    if (!empty($b['Guide_ID'])) {
                        $ginc = $conn->prepare("UPDATE guide SET Total_Income = Total_Income + (SELECT COALESCE(Guide_earning,0) FROM bookings WHERE Booking_ID=?) WHERE Guide_ID=?");
                        $ginc->bind_param("ii",$bid,$b['Guide_ID']);
                        $ginc->execute();
                    }
                    if (!empty($b['Driver_ID'])) {
                        $dinc = $conn->prepare("UPDATE driver SET Total_Income = Total_Income + (SELECT COALESCE(Driver_earning,0) FROM bookings WHERE Booking_ID=?) WHERE Driver_ID=?");
                        $dinc->bind_param("ii",$bid,$b['Driver_ID']);
                        $dinc->execute();
                    }
                    $newProg = $b['Progress'];
                    $u = $conn->prepare("UPDATE bookings SET Status='Completed', Completed_At=NOW(), Progress=? WHERE Booking_ID=?");
                    $u->bind_param("si",$newProg,$bid);
                    if ($u->execute()) { $dash_success="Trip completed."; } else { $dash_error="Update failed."; }
                }
            }
        }
    }
}

$completed = 0;
if ($entityId) {
    $c = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE {$ownerCol}=? AND Status='Completed'");
    $c->bind_param("i", $entityId);
    $c->execute();
    $completed = (int)$c->get_result()->fetch_row()[0];
}

$nextTrip = null;
if ($entityId) {
    $q = $conn->prepare("SELECT * FROM bookings 
        WHERE {$ownerCol}=? 
          AND Status IN ('Pending','Confirmed','In_Progress') 
          AND Start_Date_Time>=? 
        ORDER BY CASE WHEN Status='In_Progress' THEN 0 ELSE 1 END, Start_Date_Time ASC 
        LIMIT 1");
    $today = date('Y-m-d');
    $q->bind_param("is",$entityId,$today);
    $q->execute();
    $nextTrip = $q->get_result()->fetch_assoc();
}

$displayName = trim(($first ?? '').' '.($last ?? ''));
if ($displayName==='') $displayName = $username;

$destOptions = [];
$dayOptions = [];
if ($nextTrip) {
    if (strcasecmp($nextTrip['Booking_Type'],'Package')===0 && !empty($nextTrip['Package_ID'])) {
        $qd=$conn->prepare("SELECT DISTINCT DayNumber FROM itinerary WHERE PackageID=? ORDER BY DayNumber");
        $qd->bind_param("i",$nextTrip['Package_ID']);
        $qd->execute();
        $rd=$qd->get_result();
        while($x=$rd->fetch_assoc()) $dayOptions[]=(int)$x['DayNumber'];
        $qe=$conn->prepare("SELECT Location FROM itinerary WHERE PackageID=? ORDER BY DayNumber, ItineraryID");
        $qe->bind_param("i",$nextTrip['Package_ID']);
        $qe->execute();
        $re=$qe->get_result();
        while($x=$re->fetch_assoc()) $destOptions[]=$x['Location'];
    } else {
        $qd=$conn->prepare("SELECT DISTINCT `Day` AS DayNum FROM booking_destinations WHERE Booking_ID=? ORDER BY `Day`");
        $qd->bind_param("i",$nextTrip['Booking_ID']);
        $qd->execute();
        $rd=$qd->get_result();
        while($x=$rd->fetch_assoc()) $dayOptions[]=(int)$x['DayNum'];
        $qe=$conn->prepare("SELECT Destination FROM booking_destinations WHERE Booking_ID=? AND (Status IS NULL OR Status='upcomming') ORDER BY `Day`, Destination_ID");
        $qe->bind_param("i",$nextTrip['Booking_ID']);
        $qe->execute();
        $re=$qe->get_result();
        while($x=$re->fetch_assoc()) $destOptions[]=$x['Destination'];
        if (!$destOptions){
            $qe2=$conn->prepare("SELECT Destination FROM booking_destinations WHERE Booking_ID=? ORDER BY `Day`, Destination_ID");
            $qe2->bind_param("i",$nextTrip['Booking_ID']);
            $qe2->execute();
            $re2=$qe2->get_result();
            while($x=$re2->fetch_assoc()) $destOptions[]=$x['Destination'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Guide Dashboard • Explore Ceylon</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../Styles/GuideDashboard.css"/></head>
<body>
<?php require __DIR__ . '/header.php'; ?>

    <main class="main">
      <header class="topbar">
        <h1>Dashboard Overview</h1>
        <div class="actions">
          <a class="btn ghost" href="../index.php">Site</a>
          <button class="btn primary" id="refreshBtn">Refresh</button>
        </div>
      </header>

      <div class="live-indicator" aria-live="polite">Live overview</div>

      <?php if(!empty($dash_success)): ?>
      <div class="alert ok"><?= htmlspecialchars($dash_success) ?></div>
      <?php endif; ?>
      <?php if(!empty($dash_error)): ?>
      <div class="alert err"><?= htmlspecialchars($dash_error) ?></div>
      <?php endif; ?>

      <section class="profile-overview card in">
        <img class="avatar" src="<?= htmlspecialchars($profileImg) ?>" alt="Profile"/>
        <div class="pinfo">
          <div class="pname"><?= htmlspecialchars($displayName) ?></div>
          <div class="prow">
            <span class="badge <?= $status==='Available'?'green':($status==='On_trip'?'blue':'amber') ?>"><?= htmlspecialchars($status) ?></span>
          </div>
        </div>
        <div class="pstats">
          <div class="stat">
            <div class="label">Completed Trips</div>
            <div class="value" data-count="<?= (int)$completed ?>">0</div>
          </div>
          <div class="stat">
            <div class="label">Total Income ()</div>
            <div class="value small"><?= number_format((float)$totalIncome) ?></div>
          </div>
        </div>
      </section>

      <section class="grid">
        <div class="card" data-delay="0">
          <div class="card-ico badge cyan">🧭</div>
          <div class="card-body">
            <div class="label">My Trips</div>
            <div class="value linklike">
              <a href="./MyTrips.php">Open</a>
            </div>
          </div>
        </div>

        <div class="card" data-delay="50">
          <div class="card-ico badge green">💼</div>
          <div class="card-body">
            <div class="label">My Earnings</div>
            <div class="value linklike">
              <a href="./MyEarnings.php">Open</a>
            </div>
          </div>
        </div>

        <div class="card" data-delay="100">
          <div class="card-ico badge blue">👤</div>
          <div class="card-body">
            <div class="label">My Profile</div>
            <div class="value linklike">
              <a href="<?= $role==='driver' ? './Profile.php' : './GuideProfile.php' ?>">Open</a>
            </div>
          </div>
        </div>
      </section>

      <?php if ($nextTrip): ?>
      <section class="nexttrip card in">
        <div class="nt-head">
          <div class="nt-title">Next Trip</div>
          <div class="nt-meta">#<?= (int)$nextTrip['Booking_ID'] ?> • <?= htmlspecialchars($nextTrip['Pickup_Location']) ?> → <?= htmlspecialchars($nextTrip['End_Location']) ?></div>
        </div>
        <div class="nt-row">
          <div class="nt-dates"><?= htmlspecialchars($nextTrip['Start_Date_Time']) ?> → <?= htmlspecialchars($nextTrip['End_Date_Time']) ?></div>
          <div class="nt-status"><span class="badge"><?= htmlspecialchars($nextTrip['Status']) ?></span></div>
        </div>
        <div class="nt-actions">
          <?php
            $canStart = in_array($nextTrip['Status'],['Pending','Confirmed'],true) && substr((string)$nextTrip['Start_Date_Time'],0,10)===date('Y-m-d');
            $inProg = ($nextTrip['Status']==='In_Progress');
            $tripHasGuide = !empty($nextTrip['Guide_ID']);
            $driverLocked = ($role==='driver' && $tripHasGuide);
            $doneCnt = 0; $totalCnt = 0;
            if ($inProg) {
                $pp = parse_progress_val((string)$nextTrip['Progress']);
                $doneCnt = $pp[1];
                $totalCnt = $pp[2];
                if ($totalCnt<=0) $totalCnt = total_stops_for_booking($conn,$nextTrip);
            }
          ?>
          <?php if ($canStart && !$driverLocked): ?>
          <form method="post" class="inline">
            <input type="hidden" name="trip_action" value="start">
            <input type="hidden" name="Booking_ID" value="<?= (int)$nextTrip['Booking_ID'] ?>">
            <button class="btn primary">Start Trip</button>
          </form>
          <?php endif; ?>
          <?php if ($inProg && !$driverLocked && $doneCnt < $totalCnt): ?>
          <form method="post" class="inline nt-mark">
            <input type="hidden" name="trip_action" value="complete">
            <input type="hidden" name="Booking_ID" value="<?= (int)$nextTrip['Booking_ID'] ?>">
            <select name="Destination" class="nt-input">
              <?php foreach($destOptions as $d): ?>
              <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn ghost">Complete Destination</button>
          </form>
          <?php endif; ?>
          <?php if ($inProg && !$driverLocked && $totalCnt>0 && $doneCnt >= $totalCnt): ?>
          <div class="badge gray">End at: <?= htmlspecialchars((string)$nextTrip['End_Location']) ?></div>
          <form method="post" class="inline">
            <input type="hidden" name="trip_action" value="end">
            <input type="hidden" name="Booking_ID" value="<?= (int)$nextTrip['Booking_ID'] ?>">
            <button class="btn primary">End Trip</button>
          </form>
          <?php endif; ?>
          <?php if ($driverLocked): ?>
          <div class="badge gray">Progress handled by guide</div>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="availability">
        <h2>Set Availability</h2>
        <form method="post" class="avail-form" id="availForm">
          <input type="hidden" name="status_update" value="1"/>
          <div class="row">
            <label class="radio">
              <input type="radio" name="status" value="Available" <?= $status==='Available'?'checked':'' ?>/>
              <span>Available</span>
            </label>
            <label class="radio">
              <input type="radio" name="status" value="Un_available" <?= $status==='Un_available'?'checked':'' ?>/>
              <span>Unavailable</span>
            </label>
            <button type="button" class="btn ghost" id="markUnavailableBtn">Mark Unavailable</button>
          </div>

          <div class="unavail-box">
            <div class="row">
              <label class="radio">
                <input type="radio" name="unavail_mode" value="today" checked/>
                <span>From today onwards</span>
              </label>
              <label class="radio">
                <input type="radio" name="unavail_mode" value="custom"/>
                <span>Custom period</span>
              </label>
            </div>
            <div class="row dates">
              <div class="field">
                <label>Start date</label>
                <input type="date" name="start_date"/>
              </div>
              <div class="field">
                <label>End date</label>
                <input type="date" name="end_date"/>
              </div>
            </div>
          </div>

          <button class="btn primary" type="submit">Update Status</button>
        </form>
      </section>

      <section class="quicklinks">
        <a class="qbtn outline" href="./MyTrips.php">Go to My Trips</a>
        <a class="qbtn outline" href="./MyEarnings.php">Go to My Earnings</a>
        <a class="qbtn outline" href="<?= $role==='driver' ? './Profile.php' : './GuideProfile.php' ?>">Edit My Profile</a>
      </section>
    </main>
  <script>
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.card').forEach((c)=>{
      const d = parseInt(c.dataset.delay||0,10);
      if (!prefersReduced) {
        setTimeout(()=>c.classList.add('in'), d);
      } else {
        c.classList.add('in');
      }
    });
    function animateCount(el, end, dur=900){
      const start = 0;
      const t0 = performance.now();
      function step(t){
        const p = Math.min(1, (t - t0)/dur);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.floor(start + (end - start) * eased).toLocaleString();
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = end.toLocaleString();
      }
      requestAnimationFrame(step);
    }
    document.querySelectorAll('.value[data-count]').forEach(el=>{
      const end = parseInt(el.getAttribute('data-count'),10)||0;
      animateCount(el, end);
    });
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', ()=>{
        refreshBtn.classList.add('spin');
        location.reload();
      });
    }
    const modeRadios = document.querySelectorAll('input[name="unavail_mode"]');
    const datesBox = document.querySelector('.unavail-box .dates');
    function syncDates(){
      const v = document.querySelector('input[name="unavail_mode"]:checked')?.value;
      datesBox.style.display = v==='custom' ? 'grid' : 'none';
    }
    modeRadios.forEach(r=>r.addEventListener('change', syncDates));
    syncDates();

    const markBtn = document.getElementById('markUnavailableBtn');
    if (markBtn) {
      markBtn.addEventListener('click', ()=>{
        const sUn = document.querySelector('input[name="status"][value="Un_available"]');
        const custom = document.querySelector('input[name="unavail_mode"][value="custom"]');
        if (sUn) sUn.checked = true;
        if (custom) custom.checked = true;
        syncDates();
        const start = document.querySelector('input[name="start_date"]');
        if (start && !start.value) start.focus();
      });
    }
  </script>
  <?php include __DIR__ . '/../Includes/message.php'; ?>
</body>
</html>
