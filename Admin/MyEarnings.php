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

$s = $conn->prepare("SELECT User_Type FROM user WHERE User_ID=?");
$s->bind_param("i",$uid);
$s->execute();
$u = $s->get_result()->fetch_assoc();
$role = $u ? strtolower(trim((string)$u['User_Type'])) : '';
if (!in_array($role, ['guide','driver'], true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

function h($x){ return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); }

if ($role==='guide') {
    $pstmt = $conn->prepare("SELECT g.*, u.Username FROM guide g JOIN user u ON u.User_ID=g.User_ID WHERE g.User_ID=? LIMIT 1");
} else {
    $pstmt = $conn->prepare("SELECT d.*, u.Username FROM driver d JOIN user u ON u.User_ID=d.User_ID WHERE d.User_ID=? LIMIT 1");
}
$pstmt->bind_param("i",$uid);
$pstmt->execute();
$profile = $pstmt->get_result()->fetch_assoc();

$entityId = 0;
$first = $last = $status = $username = '';
$totalIncome = 0;
$completedTrips = 0;

if ($role==='guide' && $profile){
    $entityId = (int)$profile['Guide_ID'];
    $first = (string)$profile['F_Name'];
    $last = (string)$profile['L_Name'];
    $status = (string)$profile['Status'];
    $username = (string)$profile['Username'];
    $totalIncome = (float)$profile['Total_Income'];
    $completedTrips = (int)$profile['Completed_trips'];
} elseif ($role==='driver' && $profile){
    $entityId = (int)$profile['Driver_ID'];
    $first = (string)$profile['F_Name'];
    $last = (string)$profile['L_Name'];
    $status = (string)$profile['Status'];
    $username = (string)$profile['Username'];
    $totalIncome = (float)$profile['Total_Income'];
    $completedTrips = (int)$profile['Completed_trips'];
}

$displayName = trim($first.' '.$last);
if ($displayName==='') $displayName = $username;

$ownerCol = $role==='guide' ? 'Guide_ID' : 'Driver_ID';
$earnCol  = $role==='guide' ? 'Guide_earning' : 'Driver_earning';

$hist = [];
if ($entityId>0) {
    $q = $conn->prepare("
        SELECT 
            b.Booking_ID,
            b.Booking_Type,
            b.Pickup_Location,
            b.End_Location,
            b.Start_Date_Time,
            b.End_Date_Time,
            b.Payment_Method,
            b.Payment_Status,
            b.Status,
            COALESCE($earnCol,0) AS Earn
        FROM bookings b
        WHERE b.$ownerCol=? 
        ORDER BY b.Start_Date_Time DESC
    ");
    $q->bind_param("i",$entityId);
    $q->execute();
    $r = $q->get_result();
    while($x=$r->fetch_assoc()) $hist[] = $x;
}

$sumCompleted = 0.0;
$tripCountCompleted = 0;
foreach($hist as $row){
    if (strcasecmp($row['Status'],'Completed')===0) {
        $sumCompleted += (float)$row['Earn'];
        $tripCountCompleted++;
    }
}
$completedTrips = $tripCountCompleted;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Earnings</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="../Styles/MyEarnings.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"/>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<main class="main">
  <div class="topbar">
    <h1>My Earnings</h1>
    <div class="badge status"><?= h($status) ?></div>
  </div>

  <section class="cards">
    <div class="card in">
      <div class="card-ico badge amber">💰</div>
      <div class="card-body">
        <div class="label">Total Income (All time)</div>
        <div class="value" id="totalIncome" data-count="<?= (int)$totalIncome ?>"><?= number_format($totalIncome,0) ?></div>
      </div>
    </div>

    <div class="card in" style="--delay:60ms">
      <div class="card-ico badge green">✅</div>
      <div class="card-body">
        <div class="label">Completed Trips</div>
        <div class="value" id="completedTrips" data-count="<?= (int)$completedTrips ?>"><?= (int)$completedTrips ?></div>
      </div>
    </div>

    <div class="card in" style="--delay:90ms">
      <div class="card-ico badge blue">📈</div>
      <div class="card-body">
        <div class="label">Earnings from Completed Trips (Listed)</div>
        <div class="value small"><?= number_format($sumCompleted,0) ?></div>
      </div>
    </div>
  </section>

  <section class="tablewrap">
    <div class="tw-head">
      <h2>Earnings by Trip</h2>
      <div class="muted"><?= count($hist) ?> total trips • <?= (int)$tripCountCompleted ?> completed</div>
    </div>

    <div class="table">
      <div class="thead">
        <div class="th">Booking</div>
        <div class="th">Type</div>
        <div class="th">Route</div>
        <div class="th">Dates</div>
        <div class="th">Payment</div>
        <div class="th right">Your Earning (USD$)</div>
        <div class="th">Status</div>
      </div>
      <?php if (!$hist): ?>
        <div class="row empty">No trips found.</div>
      <?php else: ?>
        <?php foreach($hist as $r): ?>
          <div class="row fadein">
            <div class="td"><span class="mono">#<?= (int)$r['Booking_ID'] ?></span></div>
            <div class="td"><span class="badge tiny"><?= h($r['Booking_Type']) ?></span></div>
            <div class="td"><?= h($r['Pickup_Location']) ?> → <?= h($r['End_Location']) ?></div>
            <div class="td small">
              <?= h(date('Y-m-d', strtotime($r['Start_Date_Time']))) ?> → <?= h(date('Y-m-d', strtotime($r['End_Date_Time']))) ?>
            </div>
            <div class="td small">
              <?= h($r['Payment_Method']) ?> • 
              <span class="<?= strcasecmp($r['Payment_Status'],'Paid')===0?'ok':'warn' ?>"><?= h($r['Payment_Status']) ?></span>
            </div>
            <div class="td right strong"><?= number_format((float)$r['Earn'],0) ?></div>
            <div class="td"><span class="badge <?= strcasecmp($r['Status'],'Completed')===0?'green':'gray' ?> tiny"><?= h($r['Status']) ?></span></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</main>

<script>
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
  document.querySelectorAll('[data-count]').forEach(el=>{
    const end = parseInt(el.getAttribute('data-count'),10)||0;
    animateCount(el, end, 1100);
  });

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!prefersReduced){
    document.querySelectorAll('.card').forEach((c,i)=>{
      const d = c.style.getPropertyValue('--delay') || (i*45)+'ms';
      c.style.transitionDelay = d;
      c.classList.add('in');
    });
  } else {
    document.querySelectorAll('.card').forEach(c=>c.classList.add('in'));
  }
</script>
</body>
</html>
