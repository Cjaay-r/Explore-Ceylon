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
if (!$u || strcasecmp($u['User_Type'], 'Admin') !== 0) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $Category = trim($_POST['Category'] ?? '');
        $Price_Per_Day = trim($_POST['Price_Per_Day'] ?? '');
        $Seating_Capacity = (int)($_POST['Seating_Capacity'] ?? 0);
        $Vehicle_Number = trim($_POST['Vehicle_Number'] ?? '');
        $Status = trim($_POST['Status'] ?? '');
        $User_ID = (int)($_POST['User_ID'] ?? 0);
        if ($Category === '' || $Price_Per_Day === '' || !$Seating_Capacity || $Vehicle_Number === '' || $Status === '' || !$User_ID) {
            $errors[] = 'All required fields must be filled.';
        } else {
            $stmt = $conn->prepare("INSERT INTO vehicle (Category, Price_Per_Day, Seating_Capacity, Vehicle_Number, Status, User_ID) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissi", $Category, $Price_Per_Day, $Seating_Capacity, $Vehicle_Number, $Status, $User_ID);
            if ($stmt->execute()) $success = 'Vehicle created.';
            else $errors[] = 'Database error.';
        }
    } elseif ($action === 'update') {
        $Vehicle_ID = (int)($_POST['Vehicle_ID'] ?? 0);
        $Category = trim($_POST['Category'] ?? '');
        $Price_Per_Day = trim($_POST['Price_Per_Day'] ?? '');
        $Seating_Capacity = (int)($_POST['Seating_Capacity'] ?? 0);
        $Vehicle_Number = trim($_POST['Vehicle_Number'] ?? '');
        $Status = trim($_POST['Status'] ?? '');
        $User_ID = (int)($_POST['User_ID'] ?? 0);
        if (!$Vehicle_ID || $Category === '' || $Price_Per_Day === '' || !$Seating_Capacity || $Vehicle_Number === '' || $Status === '' || !$User_ID) {
            $errors[] = 'All required fields must be filled.';
        } else {
            $stmt = $conn->prepare("UPDATE vehicle SET Category=?, Price_Per_Day=?, Seating_Capacity=?, Vehicle_Number=?, Status=?, User_ID=? WHERE Vehicle_ID=?");
            $stmt->bind_param("ssissii", $Category, $Price_Per_Day, $Seating_Capacity, $Vehicle_Number, $Status, $User_ID, $Vehicle_ID);
            if ($stmt->execute()) $success = 'Vehicle updated.';
            else $errors[] = 'Database error.';
        }
    } elseif ($action === 'delete') {
        $Vehicle_ID = (int)($_POST['Vehicle_ID'] ?? 0);
        if ($Vehicle_ID) {
            $stmt = $conn->prepare("DELETE FROM vehicle WHERE Vehicle_ID=?");
            $stmt->bind_param("i", $Vehicle_ID);
            if ($stmt->execute()) $success = 'Vehicle deleted.';
            else $errors[] = 'Database error.';
        }
    }
}
$users = [];
$usrq = $conn->query("SELECT User_ID, Username FROM user ORDER BY Username ASC");
while ($row = $usrq->fetch_assoc()) $users[] = $row;
$vehicles = [];
$q = $conn->query("SELECT v.*, u.Username FROM vehicle v LEFT JOIN user u ON v.User_ID=u.User_ID ORDER BY v.Vehicle_ID DESC");
while ($row = $q->fetch_assoc()) $vehicles[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Vehicles • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="../Styles/ManageVehicles.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<main class="main">
  <header class="topbar">
    <h1>Manage Vehicles</h1>
    <div class="actions">
      <a class="btn ghost" href="../index.php">Site</a>
      <button class="btn primary" data-bs-toggle="modal" data-bs-target="#createModal">Add Vehicle</button>
    </div>
  </header>
  <section class="card shadow-sm">
    <div class="card-body p-0">
      <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($errors): ?><div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors)) ?></div><?php endif; ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Category</th>
              <th>Price/Day</th>
              <th>Seats</th>
              <th>Number</th>
              <th>Status</th>
              <th>User</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vehicles as $v): ?>
            <tr>
              <td><?= (int)$v['Vehicle_ID'] ?></td>
              <td><?= htmlspecialchars($v['Category']) ?></td>
              <td><?= htmlspecialchars($v['Price_Per_Day']) ?></td>
              <td><?= htmlspecialchars($v['Seating_Capacity']) ?></td>
              <td><?= htmlspecialchars($v['Vehicle_Number']) ?></td>
              <td><span class="badge badge-soft"><?= htmlspecialchars(trim($v['Status'])) ?></span></td>
              <td><?= htmlspecialchars($v['Username'] ?? '') ?></td>
              <td class="text-end">
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$v['Vehicle_ID'] ?>">Edit</button>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="Vehicle_ID" value="<?= (int)$v['Vehicle_ID'] ?>">
                  <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this vehicle?')">Delete</button>
                </form>
              </td>
            </tr>
            <div class="modal fade" id="editModal<?= (int)$v['Vehicle_ID'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form method="post">
                    <div class="modal-header">
                      <h5 class="modal-title">Edit Vehicle</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="Vehicle_ID" value="<?= (int)$v['Vehicle_ID'] ?>">
                      <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="Category" class="form-select" required>
                          <?php
                            $cats = ['Tuk','Bike','Mini_Car','Car','Mini_Van','Van'];
                            foreach ($cats as $c) {
                              $sel = ($v['Category']===$c)?'selected':'';
                              echo '<option value="'.htmlspecialchars($c).'" '.$sel.'>'.htmlspecialchars($c).'</option>';
                            }
                          ?>
                        </select>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Price Per Day</label>
                        <input type="text" name="Price_Per_Day" class="form-control" value="<?= htmlspecialchars($v['Price_Per_Day']) ?>" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Seating Capacity</label>
                        <input type="number" name="Seating_Capacity" class="form-control" value="<?= htmlspecialchars($v['Seating_Capacity']) ?>" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Vehicle Number</label>
                        <input type="text" name="Vehicle_Number" class="form-control" value="<?= htmlspecialchars($v['Vehicle_Number']) ?>" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">Availability</label>
                        <select name="Status" class="form-select" required>
                          <option value="Available" <?= trim($v['Status'])==='Available'?'selected':'' ?>>Available</option>
                          <option value="Busy" <?= trim($v['Status'])==='Busy'?'selected':'' ?>>Busy</option>
                        </select>
                      </div>
                      <div class="mb-3">
                        <label class="form-label">User</label>
                        <select name="User_ID" class="form-select" required>
                          <option value="">Select user</option>
                          <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['User_ID'] ?>" <?= ((int)$u['User_ID'] === (int)$v['User_ID'])?'selected':'' ?>><?= htmlspecialchars($u['Username']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button class="btn btn-primary">Save</button>
                      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title">Add Vehicle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="Category" class="form-select" required>
              <option value="Tuk">Tuk</option>
              <option value="Bike">Bike</option>
              <option value="Mini_Car">Mini_Car</option>
              <option value="Car">Car</option>
              <option value="Mini_Van">Mini_Van</option>
              <option value="Van">Van</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Price Per Day</label>
            <input type="text" name="Price_Per_Day" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Seating Capacity</label>
            <input type="number" name="Seating_Capacity" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Vehicle Number</label>
            <input type="text" name="Vehicle_Number" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Availability</label>
            <select name="Status" class="form-select" required>
              <option value="Available">Available</option>
              <option value="Busy">Busy</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">User</label>
            <select name="User_ID" class="form-select" required>
              <option value="">Select user</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['User_ID'] ?>"><?= htmlspecialchars($u['Username']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">Create</button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
