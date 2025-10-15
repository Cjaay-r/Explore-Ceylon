<?php
session_start();
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

function val($x){return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');}
function resolve_profile_img_admin($raw,$userId){
    $raw=(string)$raw;
    if($raw!==''){
        if(preg_match('~^https?://~',$raw)) return $raw;
        $clean=ltrim($raw,'/');
        if(strpos($clean,'uploads/UserProfiles/')===0) return '../'.$clean;
        return '../uploads/UserProfiles/'.$clean;
    }
    $root=dirname(__DIR__);
    $cands=[
        $root.'/uploads/UserProfiles/user_'.$userId.'.jpg',
        $root.'/uploads/UserProfiles/user_'.$userId.'.jpeg',
        $root.'/uploads/UserProfiles/user_'.$userId.'.png',
        $root.'/uploads/UserProfiles/user_'.$userId.'.webp'
    ];
    foreach($cands as $fs){
        if(file_exists($fs)){
            return '../uploads/UserProfiles/'.basename($fs);
        }
    }
    return '../Images/defaultuser.jpg';
}
function prog_parse($s){
    $s=(string)$s;
    $p=explode('|',$s,2);
    $pct = isset($p[0]) && is_numeric($p[0]) ? (int)$p[0] : null;
    $counts = isset($p[1]) ? $p[1] : '';
    $done=0; $total=0;
    if ($counts!==''){
        $parts = explode(' ', $counts, 2);
        if (isset($parts[0]) && strpos($parts[0],'/')!==false){
            [$a,$b]=explode('/',$parts[0],2);
            if (is_numeric($a)) $done=(int)$a;
            if (is_numeric($b)) $total=(int)$b;
        }
    }
    return [$pct,$done,$total];
}
function total_stops($conn,$row){
    if (strcasecmp($row['Booking_Type'],'Package')===0 && !empty($row['Package_ID'])){
        $q=$conn->prepare("SELECT COUNT(*) c FROM itinerary WHERE PackageID=?");
        $q->bind_param("i",$row['Package_ID']);
        $q->execute();
        $c=$q->get_result()->fetch_assoc();
        return (int)($c['c']??0);
    } else {
        $q=$conn->prepare("SELECT COUNT(*) c FROM booking_destinations WHERE Booking_ID=?");
        $q->bind_param("i",$row['Booking_ID']);
        $q->execute();
        $c=$q->get_result()->fetch_assoc();
        return (int)($c['c']??0);
    }
}
function done_customized($conn,$bid){
    $q=$conn->prepare("SELECT COUNT(*) c FROM booking_destinations WHERE Booking_ID=? AND Status='compleeted'");
    $q->bind_param("i",$bid);
    $q->execute();
    $c=$q->get_result()->fetch_assoc();
    return (int)($c['c']??0);
}

if ($role==='guide') {
    $pstmt = $conn->prepare("SELECT g.*, u.Username FROM guide g JOIN user u ON u.User_ID=g.User_ID WHERE g.User_ID=? LIMIT 1");
} else {
    $pstmt = $conn->prepare("SELECT d.*, u.Username FROM driver d JOIN user u ON u.User_ID=d.User_ID WHERE d.User_ID=? LIMIT 1");
}
$pstmt->bind_param("i",$uid);
$pstmt->execute();
$profile = $pstmt->get_result()->fetch_assoc();
$entityId = $role==='guide' ? (int)($profile['Guide_ID']??0) : (int)($profile['Driver_ID']??0);

$tab = $_GET['tab'] ?? 'inprogress';
if(!in_array($tab,['inprogress','upcoming','completed','cancelled'],true)) $tab='inprogress';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $act = $_POST['action'] ?? '';
    $bid = (int)($_POST['Booking_ID'] ?? 0);
    if ($bid>0) {
        $chk = $conn->prepare("SELECT * FROM bookings WHERE Booking_ID=? LIMIT 1");
        $chk->bind_param("i",$bid);
        $chk->execute();
        $b = $chk->get_result()->fetch_assoc();
        if ($b) {
            $isGuideOwner = (int)$b['Guide_ID'] === $entityId;
            $isDriverOwner = (int)$b['Driver_ID'] === $entityId;
            $canManage = ($role==='guide' && $isGuideOwner) || ($role==='driver' && !$b['Guide_ID'] && $isDriverOwner);
            $canCancelSelf = ($role==='guide' && $isGuideOwner) || ($role==='driver' && $isDriverOwner);

            if ($act==='start' && $canManage) {
                $today = date('Y-m-d');
                $pm = strtolower(trim((string)$b['Payment_Method']));
                $ps = strtolower(trim((string)$b['Payment_Status']));
                $cashGate = ($pm==='cash') ? ($ps==='paid') : true;
                $startDay = date('Y-m-d', strtotime((string)$b['Start_Date_Time'])) === $today;

                if ($cashGate && in_array($b['Status'],['Pending','Confirmed'],true) && $startDay) {
                    $tot = total_stops($conn,$b);
                    $msg = "0/{$tot} Started at ".date('Y-m-d H:i');
                    $prog = "0|{$msg}";
                    $u = $conn->prepare("UPDATE bookings SET Status='In_Progress', Progress=? WHERE Booking_ID=?");
                    $u->bind_param("si",$prog,$bid);
                    $u->execute();
                }
            } elseif ($act==='mark_dest' && $canManage && $b['Status']==='In_Progress') {
                $dest = trim((string)$_POST['Destination'] ?? '');
                $total = 0;
                if (strcasecmp($b['Booking_Type'],'Package')===0 && !empty($b['Package_ID'])) {
                    $qc=$conn->prepare("SELECT COUNT(*) c FROM itinerary WHERE PackageID=?");
                    $qc->bind_param("i",$b['Package_ID']);
                    $qc->execute();
                    $total=(int)($qc->get_result()->fetch_assoc()['c']??0);
                    [$pctPrev,$donePrev,$tprev] = prog_parse($b['Progress']);
                    $done = min($total, max(0,$donePrev)+1);
                } else {
                    $qc=$conn->prepare("SELECT COUNT(*) c FROM booking_destinations WHERE Booking_ID=?");
                    $qc->bind_param("i",$bid);
                    $qc->execute();
                    $total=(int)($qc->get_result()->fetch_assoc()['c']??0);
                    if ($dest!=='') {
                        $up=$conn->prepare("UPDATE booking_destinations SET Status='compleeted' WHERE Booking_ID=? AND Destination=? AND (Status IS NULL OR Status='upcomming') LIMIT 1");
                        $up->bind_param("is",$bid,$dest);
                        $up->execute();
                    }
                    $done = done_customized($conn,$bid);
                }
                $pct = $total>0 ? (int)floor($done*100/$total) : 100;
                if ($total>0 && $done>=$total) {
                    $note = "{$done}/{$total} All destinations completed. Proceed to end trip at ".(string)$b['End_Location']." ".date('Y-m-d H:i');
                } else {
                    $note = "{$done}/{$total} Completed ".($dest!==''?$dest:'destination')." at ".date('Y-m-d H:i');
                }
                $newProg = "{$pct}|{$note}";
                $u = $conn->prepare("UPDATE bookings SET Progress=? WHERE Booking_ID=?");
                $u->bind_param("si",$newProg,$bid);
                $u->execute();
            } elseif ($act==='end_trip' && $canManage && $b['Status']==='In_Progress') {
                if (!empty($b['Guide_ID'])) {
                    $earn = (float)$b['Guide_earning'];
                    if ($earn>0) {
                        $gg = $conn->prepare("UPDATE guide SET Total_Income=Total_Income+? WHERE Guide_ID=?");
                        $gg->bind_param("di",$earn,$b['Guide_ID']);
                        $gg->execute();
                    }
                }
                if (!empty($b['Driver_ID'])) {
                    $dearn = (float)$b['Driver_earning'];
                    if ($dearn>0) {
                        $dd = $conn->prepare("UPDATE driver SET Total_Income=Total_Income+? WHERE Driver_ID=?");
                        $dd->bind_param("di",$dearn,$b['Driver_ID']);
                        $dd->execute();
                    }
                }
                $u = $conn->prepare("UPDATE bookings SET Status='Completed', Completed_At=NOW() WHERE Booking_ID=?");
                $u->bind_param("i",$bid);
                $u->execute();
            } elseif ($act==='cancel' && $canCancelSelf && $b['Status']==='Pending') {
                $reason = trim((string)($_POST['Cancel_Reason'] ?? ''));
                $msg = $b['Progress'];
                $prefix = $msg!=='' ? ($msg.' ') : '';
                $note = $prefix."Released by ".($role==='guide'?'guide':'driver').": ".($reason!==''?$reason:'No reason provided')." at ".date('Y-m-d H:i');
                if ($role==='guide') {
                    $u = $conn->prepare("UPDATE bookings SET Progress=?, Guide_ID=NULL WHERE Booking_ID=?");
                } else {
                    $u = $conn->prepare("UPDATE bookings SET Progress=?, Driver_ID=NULL WHERE Booking_ID=?");
                }
                $u->bind_param("si",$note,$bid);
                $u->execute();
            }
        }
    }
}

$where = [];
if ($tab==='inprogress') $where[] = "b.Status='In_Progress'";
if ($tab==='upcoming') $where[] = "b.Status IN ('Pending','Confirmed')";
if ($tab==='completed') $where[] = "b.Status='Completed'";
if ($tab==='cancelled') $where[] = "b.Status='Cancelled'";

if ($role==='guide') {
    $where[] = "b.Guide_ID=".(int)$entityId;
} else {
    $where[] = "b.Driver_ID=".(int)$entityId;
}

$sql = "SELECT b.*, u.User_ID AS CUser, u.Username AS CustomerName, u.Email AS CustomerEmail, u.Phone_No AS CustomerPhone, u.User_Profile AS CustomerProfile
        FROM bookings b
        JOIN user u ON u.User_ID=b.User_ID
        WHERE ".implode(' AND ',$where)."
        ORDER BY b.Start_Date_Time DESC";
$rs = $conn->query($sql);
$rows = [];
while($r=$rs->fetch_assoc()) $rows[]=$r;

function itinerary_for_booking($conn,$row){
    $out = [];
    if (strcasecmp($row['Booking_Type'],'Package')===0 && !empty($row['Package_ID'])){
        $q = $conn->prepare("SELECT DayNumber AS Day, Location, Description FROM itinerary WHERE PACKAGEID=? ORDER BY DayNumber ASC, ItineraryID ASC");
        $q->bind_param("i",$row['Package_ID']);
        $q->execute();
        $res = $q->get_result();
        while($x=$res->fetch_assoc()) $out[]=$x;
    } else {
        $q = $conn->prepare("SELECT `Day`, Destination AS Location, Status FROM booking_destinations WHERE Booking_ID=? ORDER BY `Day` ASC, Destination_ID ASC");
        $q->bind_param("i",$row['Booking_ID']);
        $q->execute();
        $res = $q->get_result();
        while($x=$res->fetch_assoc()){$x['Description']=''; $out[]=$x;}
    }
    return $out;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Trips</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../Styles/MyTrips.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<main class="main">
  <div class="topbar">
    <h1>My Trips</h1>
    <nav class="yt-tabs">
      <a class="yt-tab <?= $tab==='inprogress'?'active':'' ?>" href="?tab=inprogress">In Progress</a>
      <a class="yt-tab <?= $tab==='upcoming'?'active':'' ?>" href="?tab=upcoming">Upcoming</a>
      <a class="yt-tab <?= $tab==='completed'?'active':'' ?>" href="?tab=completed">Completed</a>
      <a class="yt-tab <?= $tab==='cancelled'?'active':'' ?>" href="?tab=cancelled">Cancelled</a>
    </nav>
  </div>

  <?php
    $inprog = null;
    foreach($rows as $r){ if($r['Status']==='In_Progress'){ $inprog=$r; break; } }
  ?>
  <?php if ($inprog && $tab==='inprogress'): ?>
  <section class="progress-card card shadow-sm">
    <div class="card-body">
      <div class="d-flex align-items-center gap-3">
        <div>
          <div class="badge-soft">In Progress</div>
          <h5 class="mt-2 mb-1">#<?= (int)$inprog['Booking_ID'] ?> • <?= val($inprog['Pickup_Location']) ?> → <?= val($inprog['End_Location']) ?></h5>
          <div class="text-muted small"><?= val(date('Y-m-d',strtotime($inprog['Start_Date_Time']))) ?> to <?= val(date('Y-m-d',strtotime($inprog['End_Date_Time']))) ?></div>
        </div>
      </div>
      <?php [$pb,$dn,$tt] = prog_parse($inprog['Progress']); $pbar = is_null($pb)?null:max(0,min(100,$pb)); ?>
      <div class="mt-3">
        <?php if ($pbar!==null): ?>
        <div class="progress"><div class="progress-bar" role="progressbar" style="width: <?= $pbar ?>%"></div></div>
        <div class="small mt-1"><?= $pbar ?>%</div>
        <?php elseif(!empty($inprog['Progress'])): ?>
        <div class="text-muted small"><?= val($inprog['Progress']) ?></div>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-4 align-items-center mt-3">
        <div class="d-flex align-items-center gap-2">
          <img class="avatar-md" src="<?= resolve_profile_img_admin($inprog['CustomerProfile'] ?? '', (int)($inprog['CUser'] ?? 0)) ?>" alt="">
          <div class="small">
            <div class="fw-semibold"><?= val($inprog['CustomerName'] ?? '') ?></div>
            <div class="text-muted">Customer</div>
          </div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="./GuideDashboard.php">Open Dashboard</a>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="content-list">
    <?php if(!$rows): ?>
      <div class="card p-5 text-center text-muted">No trips in this category.</div>
    <?php else: ?>
    <?php foreach($rows as $row):
        if ($tab==='inprogress' && $inprog && (int)$row['Booking_ID']===(int)$inprog['Booking_ID']) continue;
        $bid=(int)$row['Booking_ID']; $it=itinerary_for_booking($conn,$row);

        $canManage = ($role==='guide' && (int)$row['Guide_ID']===$entityId)
                  || ($role==='driver' && ((int)$row['Guide_ID']===0 || $row['Guide_ID']===null) && (int)$row['Driver_ID']===$entityId);
        $canCancelSelf = ($role==='guide' && (int)$row['Guide_ID']===$entityId)
                      || ($role==='driver' && (int)$row['Driver_ID']===$entityId);

        $pm = strtolower(trim((string)$row['Payment_Method']));
        $ps = strtolower(trim((string)$row['Payment_Status']));
        $cashGate = ($pm==='cash') ? ($ps==='paid') : true;
        $startDayIsToday = (date('Y-m-d', strtotime((string)$row['Start_Date_Time'])) === date('Y-m-d'));

        $canStart = $canManage && $cashGate && in_array($row['Status'],['Pending','Confirmed'],true) && $startDayIsToday;
        $inProg = ($row['Status']==='In_Progress');

        $destOptions = [];
        $dayOptions = [];
        if (strcasecmp($row['Booking_Type'],'Package')===0 && !empty($row['Package_ID'])) {
            $qd=$conn->prepare("SELECT DISTINCT DayNumber FROM itinerary WHERE PackageID=? ORDER BY DayNumber");
            $qd->bind_param("i",$row['Package_ID']);
            $qd->execute();
            $rd=$qd->get_result();
            while($x=$rd->fetch_assoc()) $dayOptions[]=(int)$x['DayNumber'];
            $qe=$conn->prepare("SELECT Location FROM itinerary WHERE PackageID=? ORDER BY DayNumber, ItineraryID");
            $qe->bind_param("i",$row['Package_ID']);
            $qe->execute();
            $re=$qe->get_result();
            while($x=$re->fetch_assoc()) $destOptions[]=$x['Location'];
        } else {
            $qd=$conn->prepare("SELECT DISTINCT `Day` AS DayNum FROM booking_destinations WHERE Booking_ID=? ORDER BY `Day`");
            $qd->bind_param("i",$bid);
            $qd->execute();
            $rd=$qd->get_result();
            while($x=$rd->fetch_assoc()) $dayOptions[]=(int)$x['DayNum'];
            $qe=$conn->prepare("SELECT Destination FROM booking_destinations WHERE Booking_ID=? AND (Status IS NULL OR Status='upcomming') ORDER BY `Day`, Destination_ID");
            $qe->bind_param("i",$bid);
            $qe->execute();
            $re=$qe->get_result();
            while($x=$re->fetch_assoc()) $destOptions[]=$x['Destination'];
            if (!$destOptions){
                $qe2=$conn->prepare("SELECT Destination FROM booking_destinations WHERE Booking_ID=? ORDER BY `Day`, Destination_ID");
                $qe2->bind_param("i",$bid);
                $qe2->execute();
                $re2=$qe2->get_result();
                while($x=$re2->fetch_assoc()) $destOptions[]=$x['Destination'];
            }
        }
        [$pb2,$dn2,$tt2] = prog_parse($row['Progress']);
        $pbar2 = is_null($pb2)?null:max(0,min(100,$pb2));
        $totalStops = total_stops($conn,$row);
    ?>
    <div class="card trip-card shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
          <div class="d-flex align-items-center gap-3">
            <img class="avatar-md" src="<?= resolve_profile_img_admin($row['CustomerProfile'] ?? '', (int)($row['CUser'] ?? 0)) ?>" alt="">
            <div>
              <div class="fw-semibold"><?= val($row['CustomerName'] ?? '') ?></div>
              <div class="text-muted small">Customer</div>
            </div>
          </div>
          <div class="route">
            <div class="fw-semibold"><?= val($row['Pickup_Location']) ?> → <?= val($row['End_Location']) ?></div>
            <div class="text-muted small"><?= val(date('Y-m-d',strtotime($row['Start_Date_Time']))) ?> to <?= val(date('Y-m-d',strtotime($row['End_Date_Time']))) ?></div>
          </div>
          <div class="text-end">
            <div><span class="badge-soft"><?= val($row['Status']) ?></span></div>
            <div class="small mt-1"><?= val($row['Booking_Type']) ?> • <?= (int)$row['Number_of_People'] ?> people</div>
            <div class="small"><?= val($row['Payment_Method']) ?> • <span class="<?= strcasecmp($row['Payment_Status'],'Paid')===0?'text-success':'text-danger' ?>"><?= val($row['Payment_Status']) ?></span></div>
          </div>
        </div>

        <div class="mt-3">
          <?php if ($pbar2!==null): ?>
          <div class="progress"><div class="progress-bar" role="progressbar" style="width: <?= $pbar2 ?>%"></div></div>
          <div class="small mt-1"><?= $pbar2 ?>%</div>
          <?php elseif(!empty($row['Progress'])): ?>
          <div class="text-muted small"><?= val($row['Progress']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mt-3 d-flex flex-wrap gap-2">
          <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#it<?= $bid ?>">View Itinerary</button>

          <?php if($canManage && $canStart): ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="start">
            <input type="hidden" name="Booking_ID" value="<?= $bid ?>">
            <button class="btn btn-outline-success btn-sm">Start Trip</button>
          </form>
          <?php endif; ?>

          <?php if($canManage && $inProg && $dn2 < $totalStops): ?>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="mark_dest">
            <input type="hidden" name="Booking_ID" value="<?= $bid ?>">
            <select name="Destination" class="form-select form-select-sm d-inline-block" style="width:auto">
              <?php foreach($destOptions as $d): ?>
              <option value="<?= val($d) ?>"><?= val($d) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-secondary btn-sm">Complete Destination</button>
          </form>
          <?php endif; ?>

          <?php if($canManage && $inProg && $totalStops>0 && $dn2 >= $totalStops): ?>
          <div class="btn btn-outline-dark btn-sm disabled">End at: <?= val((string)$row['End_Location']) ?></div>
          <form method="post" class="d-inline">
            <input type="hidden" name="action" value="end_trip">
            <input type="hidden" name="Booking_ID" value="<?= $bid ?>">
            <button class="btn btn-outline-success btn-sm">End Trip</button>
          </form>
          <?php endif; ?>

          <?php if($canCancelSelf && $row['Status']==='Pending'): ?>
          <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancel<?= $bid ?>">Cancel</button>
          <?php endif; ?>

          <?php if($inProg): ?>
          <a class="btn btn-outline-primary btn-sm" href="./GuideDashboard.php">Open Dashboard</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="modal fade" id="it<?= $bid ?>" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Itinerary • #<?= $bid ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <?php if(!$it): ?>
              <div class="text-muted">No itinerary available.</div>
            <?php else:
              $day=0;
              foreach($it as $seg){
                if((int)$seg['Day']!==$day){
                  if($day!==0) echo "</div>";
                  $day=(int)$seg['Day'];
                  echo "<div class=\"it-day\"><div class=\"it-day-title\">Day ".val($day)."</div>";
                }
                echo "<div class=\"it-item\"><div class=\"it-loc\">".val($seg['Location'])."</div>";
                if(!empty($seg['Description'])) echo "<div class=\"it-desc\">".val($seg['Description'])."</div>";
                if(isset($seg['Status']) && $seg['Status']!==''){ echo "<div class=\"small text-muted\">Status: ".val($seg['Status'])."</div>"; }
                echo "</div>";
              }
              if($day!==0) echo "</div>";
            endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="cancel<?= $bid ?>" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post">
            <div class="modal-header">
              <h5 class="modal-title">Cancel Booking #<?= $bid ?></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="Booking_ID" value="<?= $bid ?>">
              <label class="form-label">Reason</label>
              <textarea name="Cancel_Reason" class="form-control" rows="3" placeholder="Enter reason"></textarea>
              <div class="form-text">This releases you from this booking. The trip remains pending for reassignment.</div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button class="btn btn-danger">Confirm Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
