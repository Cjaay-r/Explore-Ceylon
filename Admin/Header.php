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
$stmt = $conn->prepare("SELECT User_Type FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$u = $res->fetch_assoc();
$type = $u ? strtolower(trim((string)$u['User_Type'])) : '';

?>
<style>
:root{
  --bg:#ffffff;
  --panel:#ffffff;
  --panel-2:#f9fafc;
  --text:#1e1e2c;
  --muted:#5b6576;
  --primary:#f29f67;
  --dark:#1e1e2c;
  --blue:#3b8ff3;
  --teal:#34b1aa;
  --yellow:#e0b50f;
  --radius:16px;
  --shadow:0 10px 24px rgba(15,23,42,.06), 0 2px 6px rgba(2,6,23,.06);
}
*{box-sizing:border-box}
.wrap{
  display:grid;
  grid-template-columns:280px 1fr;
  min-height:100dvh;
}
.sidebar{
  background:linear-gradient(180deg,var(--panel),var(--panel-2));
  border-right:1px solid rgba(2,6,23,.08);
  padding:22px 16px;
  position:sticky;
  top:0;
  height:100dvh;
  display:flex;
  flex-direction:column;
  gap:18px;
}
.brand{
  font-weight:700;
  font-size:20px;
  display:flex;
  align-items:center;
  gap:10px;
  color:var(--primary);
}
.menu{
  list-style:none;
  padding:0;
  margin:0;
  display:flex;
  flex-direction:column;
  gap:6px;
}
.menu a{
  display:flex;
  align-items:center;
  gap:10px;
  padding:12px;
  border-radius:12px;
  color:var(--text);
  text-decoration:none;
  transition:transform .2s ease,background .2s ease,box-shadow .2s ease;
}
.menu a .ico{width:22px;text-align:center;opacity:.9}
.menu a:hover{
  background:rgba(242,159,103,.15);
  transform:translateY(-1px);
  box-shadow:0 8px 18px rgba(15,23,42,.06);
}
.menu a.active{
  background:linear-gradient(180deg,rgba(242,159,103,.2),rgba(59,143,243,.15));
  outline:1px solid rgba(2,6,23,.06);
  box-shadow:inset 0 0 0 1px rgba(2,6,23,.03),0 12px 26px rgba(15,23,42,.08);
}
.foot{
  margin-top:auto;
  font-size:12px;
  color:var(--muted);
}
@media(max-width:880px){
  .wrap{grid-template-columns:84px 1fr}
  .brand{font-size:0}
  .brand::after{content:"⚙️";font-size:20px;color:var(--dark)}
  .menu a span:last-child{display:none}
  .menu a{justify-content:center}
}
</style>

<div class="wrap">
  <?php if ($type === 'admin'): ?>
    <nav class="sidebar">
      <div class="brand">Admin Panel</div>
      <ul class="menu">
        <li><a class="<?php echo basename($_SERVER['PHP_SELF'])==='AdminDashboard.php'?'active':''; ?>" href="AdminDashboard.php"><span class="ico">⚡</span><span>Dashboard</span></a></li>
        <li><a href="ManagePackages.php"><span class="ico">🧳</span><span>Manage Packages</span></a></li>
        <li><a href="ManageDestinations.php"><span class="ico">📍</span><span>Manage Destinations</span></a></li>
        <li><a href="ManageGuides.php"><span class="ico">🧭</span><span>Manage Guides</span></a></li>
        <li><a href="ManageDrivers.php"><span class="ico">🚗</span><span>Manage Drivers</span></a></li>
        <li><a href="ManageVehicles.php"><span class="ico">🚐</span><span>Manage Vehicles</span></a></li>
        <li><a href="ManageBookings.php"><span class="ico">📆</span><span>Manage Bookings</span></a></li>
        <li><a href="ManageUsers.php"><span class="ico">👥</span><span>Manage Users</span></a></li>
      </ul>
      <div class="foot">Explore Ceylon</div>
    </nav>
  <?php elseif ($type === 'guide'): ?>
    <nav class="sidebar">
      <div class="brand">Guide Panel</div>
      <ul class="menu">
        <li><a class="<?php echo basename($_SERVER['PHP_SELF'])==='GuideDashboard.php'?'active':''; ?>" href="GuideDashboard.php"><span class="ico">⚡</span><span>Dashboard</span></a></li>
        <li><a href="GuideDashboard.php"><span class="ico">🏠</span><span>My Home</span></a></li>
        <li><a href="GuideProfile.php"><span class="ico">👤</span><span>My Profile</span></a></li>
        <li><a href="MyTrips.php"><span class="ico">🧳</span><span>My Trips</span></a></li>
        <li><a href="MyEarnings.php"><span class="ico">💰</span><span>My Earnings</span></a></li>
      </ul>
      <div class="foot">Explore Ceylon</div>
    </nav>
  <?php elseif ($type === 'driver'): ?>
    <nav class="sidebar">
      <div class="brand">Driver Panel</div>
      <ul class="menu">
        <li><a class="<?php echo basename($_SERVER['PHP_SELF'])==='DriverDashboard.php'?'active':''; ?>" href="../Driver/DriverDashboard.php"><span class="ico">🚗</span><span>Dashboard</span></a></li>
        <li><a href="../Driver/MyTrips.php"><span class="ico">🗺️</span><span>My Trips</span></a></li>
        <li><a href="../Driver/MyEarnings.php"><span class="ico">💸</span><span>My Earnings</span></a></li>
        <li><a href="../Driver/Profile.php"><span class="ico">👤</span><span>My Profile</span></a></li>
        <li><a href="../index.php"><span class="ico">🏠</span><span>Site</span></a></li>
      </ul>
      <div class="foot">Explore Ceylon</div>
    </nav>
  <?php else: ?>
    <nav class="sidebar">
      <div class="brand">Staff</div>
      <ul class="menu">
        <li><a href="../index.php"><span class="ico">🏠</span><span>Home</span></a></li>
      </ul>
      <div class="foot">Explore Ceylon</div>
    </nav>
  <?php endif; ?>
