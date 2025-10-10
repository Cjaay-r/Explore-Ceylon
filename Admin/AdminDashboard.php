<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../Includes/config.php';
require_once __DIR__ . '/../Includes/dbconnect.php';
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
if (!$u || strcasecmp((string)$u['User_Type'], 'Admin') !== 0) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}
function count_table(mysqli $conn, string $sql): int {
    $r = $conn->query($sql);
    if ($r && ($row = $r->fetch_row())) return (int)$row[0];
    return 0;
}
$destinationsCount = count_table($conn, "SELECT COUNT(*) FROM destinations");
$packagesCount     = count_table($conn, "SELECT COUNT(*) FROM packages");
$guidesCount       = count_table($conn, "SELECT COUNT(*) FROM guide");
$driversCount      = count_table($conn, "SELECT COUNT(*) FROM driver");
$customersCount    = count_table($conn, "SELECT COUNT(*) FROM user WHERE LOWER(User_Type) = 'user'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard • Explore Ceylon</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../Styles/AdminDashboard.css"/>
</head>
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

      <div class="live-indicator" aria-live="polite">Live counts from database</div>

      <section class="grid">
        <div class="card" data-delay="0">
          <div class="card-ico badge blue">📍</div>
          <div class="card-body">
            <div class="label">Destinations</div>
            <div class="value" data-count="<?php echo (int)$destinationsCount; ?>">0</div>
          </div>
        </div>

        <div class="card" data-delay="50">
          <div class="card-ico badge green">🧳</div>
          <div class="card-body">
            <div class="label">Packages</div>
            <div class="value" data-count="<?php echo (int)$packagesCount; ?>">0</div>
          </div>
        </div>

        <div class="card" data-delay="100">
          <div class="card-ico badge cyan">🧭</div>
          <div class="card-body">
            <div class="label">Guides</div>
            <div class="value" data-count="<?php echo (int)$guidesCount; ?>">0</div>
          </div>
        </div>

        <div class="card" data-delay="150">
          <div class="card-ico badge amber">🚗</div>
          <div class="card-body">
            <div class="label">Drivers</div>
            <div class="value" data-count="<?php echo (int)$driversCount; ?>">0</div>
          </div>
        </div>

        <div class="card" data-delay="200">
          <div class="card-ico badge gray">👥</div>
          <div class="card-body">
            <div class="label">Customers</div>
            <div class="value" data-count="<?php echo (int)$customersCount; ?>">0</div>
          </div>
        </div>
      </section>

      <section class="quicklinks">
        <a class="qbtn outline" href="ManagePackages.php">Add / Manage Packages</a>
        <a class="qbtn outline" href="ManageDestinations.php">Add / Manage Destinations</a>
        <a class="qbtn outline" href="ManageUsers.php">Manage Users</a>
      </section>
    </main>

  </div>

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
  </script>

</body>
</html>
