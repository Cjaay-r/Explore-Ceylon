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
$s = $conn->prepare("SELECT User_Type FROM user WHERE User_ID = ?");
$s->bind_param("i", $uid);
$s->execute();
$u = $s->get_result()->fetch_assoc();
$type = $u ? strtolower(trim($u['User_Type'])) : '';
if ($type !== 'admin') {
    header("Location: ../index.php");
    exit;
}
function val($x){return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');}

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rid = (int)($_POST['Rental_ID'] ?? 0);
    if ($rid && $action === 'set_status') {
        $new = $_POST['new_status'] ?? '';
        if (in_array($new, ['Completed','Cancel'], true)) {
            $chk = $conn->prepare("SELECT Status FROM vehicle_rentals WHERE Rental_ID=?");
            $chk->bind_param("i",$rid);
            $chk->execute();
            $cur = $chk->get_result()->fetch_assoc();
            if ($cur && !in_array($cur['Status'], ['Completed','Cancel'], true)) {
                $u = $conn->prepare("UPDATE vehicle_rentals SET Status=? WHERE Rental_ID=?");
                $u->bind_param("si",$new,$rid);
                if ($u->execute()) $success = "Rental updated."; else $errors[] = "Update failed.";
            }
        }
    }
}

$tab = $_GET['tab'] ?? 'upcoming';
if (!in_array($tab, ['upcoming','completed','cancelled'], true)) $tab = 'upcoming';

$q = trim($_GET['q'] ?? '');
$where = [];
$params = [];
$types = '';

if ($tab === 'upcoming') $where[] = "r.Status IN ('Pendding','Started')";
elseif ($tab === 'completed') $where[] = "r.Status='Completed'";
else $where[] = "r.Status='Cancel'";

if ($q !== '') {
    if (ctype_digit($q)) {
        $where[] = "(r.Rental_ID=? OR r.Name LIKE ? OR r.Email LIKE ?)";
        $params[] = (int)$q; $types .= 'i';
        $like = '%'.$q.'%';
        $params[] = $like; $types .= 's';
        $params[] = $like; $types .= 's';
    } else {
        $where[] = "(r.Name LIKE ? OR r.Email LIKE ?)";
        $like = '%'.$q.'%';
        $params[] = $like; $types .= 's';
        $params[] = $like; $types .= 's';
    }
}

$sql = "SELECT r.*,
        v.Category AS VehicleCategory,
        v.Vehicle_Number AS VehicleNumber
        FROM vehicle_rentals r
        LEFT JOIN vehicle v ON r.Vehicle_ID = v.Vehicle_ID
        ".(count($where)?'WHERE '.implode(' AND ', $where):'')."
        ORDER BY r.Start_Date DESC, r.Rental_ID DESC";
$st = $conn->prepare($sql);
if ($types !== '') $st->bind_param($types, ...$params);
$st->execute();
$rs = $st->get_result();
$rows = [];
while ($row = $rs->fetch_assoc()) $rows[] = $row;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Rentals</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../Styles/ManageRentals.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<main class="main">
  <div class="topbar">
    <h1>Manage Rentals</h1>
    <form class="search" method="get">
      <input type="hidden" name="tab" value="<?= val($tab) ?>">
      <input type="text" name="q" class="form-control" placeholder="Search by Rental ID, Name or Email" value="<?= val($q) ?>">
      <button class="btn primary">Search</button>
    </form>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success"><?= val($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
  <div class="alert alert-danger"><?= val(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="tabs">
    <a class="tab-link <?= $tab==='upcoming'?'active':'' ?>" href="?tab=upcoming">Upcoming</a>
    <a class="tab-link <?= $tab==='completed'?'active':'' ?>" href="?tab=completed">Completed</a>
    <a class="tab-link <?= $tab==='cancelled'?'active':'' ?>" href="?tab=cancelled">Cancelled</a>
  </div>

  <section class="content-area">
    <div class="card shadow-sm p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Customer</th>
              <th>Dates</th>
              <th>Pickup</th>
              <th>Vehicle</th>
              <th>Payment</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center py-5 text-muted">No rentals found</td></tr>
          <?php else: ?>
          <?php foreach ($rows as $r): 
              $rid = (int)$r['Rental_ID'];
              $isFinal = in_array($r['Status'], ['Completed','Cancel'], true);
          ?>
            <tr>
              <td>#<?= $rid ?></td>
              <td>
                <div class="fw-semibold"><?= val($r['Name']) ?></div>
                <div class="text-muted small"><?= val($r['Email']) ?> <?= $r['Phone_No'] ? '• '.val($r['Phone_No']) : '' ?></div>
                <div class="text-muted small"><?= val($r['NIC_or_Pass']) ?></div>
              </td>
              <td>
                <div><?= val($r['Start_Date']) ?> →</div>
                <div><?= val($r['End_Date']) ?></div>
              </td>
              <td>
                <div class="small"><?= val($r['Start_Location']) ?></div>
              </td>
              <td>
                <div class="small"><?= val($r['VehicleCategory'] ?? '') ?></div>
                <div class="small text-muted"><?= val($r['VehicleNumber'] ?? '') ?></div>
              </td>
              <td>
                <div class="small"><?= val($r['Payment_method']) ?></div>
                <div class="small <?= strcasecmp($r['Payment_Status'],'Paid')===0?'text-success':'text-danger' ?>"><?= val($r['Payment_Status']) ?></div>
              </td>
              <td><span class="badge-soft"><?= val($r['Status']) ?></span></td>
              <td class="text-end">
                <?php if (!$isFinal): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="Rental_ID" value="<?= $rid ?>">
                  <input type="hidden" name="new_status" value="Completed">
                  <button class="btn btn-outline-primary btn-sm">Mark Completed</button>
                </form>
                <form method="post" class="d-inline ms-1">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="Rental_ID" value="<?= $rid ?>">
                  <input type="hidden" name="new_status" value="Cancel">
                  <button class="btn btn-outline-danger btn-sm">Cancel</button>
                </form>
                <?php else: ?>
                <span class="text-muted small">No actions</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
