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
$stmt = $conn->prepare("SELECT User_Type FROM user WHERE User_ID = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$type = $user ? strtolower(trim($user['User_Type'])) : '';

if ($type !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$errors = [];
$success = "";

function save_profile_image($field) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $safeBase = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', basename($_FILES[$field]['name']));
    $name = time() . '_' . substr(bin2hex(random_bytes(6)), 0, 12) . '_' . $safeBase;
    $baseDir = __DIR__ . '/../uploads/UserProfiles';
    if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);
    if (!is_dir($baseDir)) return null;
    $destFs = $baseDir . DIRECTORY_SEPARATOR . $name;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $destFs)) return $name;
    return null;
}

function profile_src($val) {
    $v = trim((string)$val);
    if ($v === '' || strtolower($v) === 'defaultuser.jpg') return url('Images/defaultuser.jpg');
    if (strpos($v, '/') !== false) return ($v[0] === '/') ? $v : url($v);
    return url('uploads/UserProfiles/' . $v);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $Username = trim($_POST['Username'] ?? '');
        $Email = trim($_POST['Email'] ?? '');
        $Password = $_POST['Password'] ?? '';
        $Phone_No = trim($_POST['Phone_No'] ?? '');
        if ($Username === '' || $Email === '' || $Password === '' || $Phone_No === '') {
            $errors[] = 'All required fields must be filled.';
        } else {
            $User_Profile = save_profile_image('User_Profile') ?? 'defaultuser.jpg';
            $hash = password_hash($Password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO user (Username, Email, Password, Phone_No, User_Profile, User_Type) VALUES (?, ?, ?, ?, ?, 'User')");
            $stmt->bind_param("sssss", $Username, $Email, $hash, $Phone_No, $User_Profile);
            if ($stmt->execute()) {
                $success = 'User created.';
            } else {
                $errors[] = 'Create failed.';
            }
        }
    } elseif ($action === 'update') {
        $User_ID = (int)($_POST['User_ID'] ?? 0);
        $Username = trim($_POST['Username'] ?? '');
        $Email = trim($_POST['Email'] ?? '');
        $Phone_No = trim($_POST['Phone_No'] ?? '');
        if (!$User_ID || $Username === '' || $Email === '' || $Phone_No === '') {
            $errors[] = 'All required fields must be filled.';
        } else {
            $newImg = save_profile_image('User_Profile');
            if ($newImg) {
                $stmt = $conn->prepare("UPDATE user SET Username=?, Email=?, Phone_No=?, User_Profile=? WHERE User_ID=? AND User_Type='User'");
                $stmt->bind_param("ssssi", $Username, $Email, $Phone_No, $newImg, $User_ID);
            } else {
                $stmt = $conn->prepare("UPDATE user SET Username=?, Email=?, Phone_No=? WHERE User_ID=? AND User_Type='User'");
                $stmt->bind_param("sssi", $Username, $Email, $Phone_No, $User_ID);
            }
            if ($stmt->execute()) {
                $success = 'User updated.';
            } else {
                $errors[] = 'Update failed.';
            }
        }
    } elseif ($action === 'delete') {
        $User_ID = (int)($_POST['User_ID'] ?? 0);
        if ($User_ID) {
            $stmt = $conn->prepare("DELETE FROM user WHERE User_ID=? AND User_Type='User'");
            $stmt->bind_param("i", $User_ID);
            if ($stmt->execute()) {
                $success = 'User deleted.';
            } else {
                $errors[] = 'Delete failed.';
            }
        }
    }
}

$rows = [];
$res = $conn->query("SELECT * FROM user WHERE User_Type='User' ORDER BY User_ID DESC");
while ($r = $res->fetch_assoc()) $rows[] = $r;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../Styles/ManageUsers.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<main class="main">
  <div class="topbar">
    <h1>Manage Users</h1>
    <button class="btn primary" data-bs-toggle="modal" data-bs-target="#createModal">Add User</button>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors)) ?></div><?php endif; ?>

  <div class="card shadow-sm p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Profile</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= (int)$row['User_ID'] ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($row['Username']) ?></td>
            <td><?= htmlspecialchars($row['Email']) ?></td>
            <td><?= htmlspecialchars($row['Phone_No']) ?></td>
            <td><img src="<?= htmlspecialchars(profile_src($row['User_Profile'])) ?>" class="avatar" alt=""></td>
            <td class="text-end">
              <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$row['User_ID'] ?>">Edit</button>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="User_ID" value="<?= (int)$row['User_ID'] ?>">
                <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this user?')">Delete</button>
              </form>
            </td>
          </tr>
          <div class="modal fade" id="edit<?= (int)$row['User_ID'] ?>" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="User_ID" value="<?= (int)$row['User_ID'] ?>">
                    <div class="mb-3">
                      <label class="form-label">Username</label>
                      <input type="text" name="Username" class="form-control" value="<?= htmlspecialchars($row['Username']) ?>" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Email</label>
                      <input type="email" name="Email" class="form-control" value="<?= htmlspecialchars($row['Email']) ?>" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Phone</label>
                      <input type="text" name="Phone_No" class="form-control" value="<?= htmlspecialchars($row['Phone_No']) ?>" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Profile Image</label>
                      <input type="file" name="User_Profile" class="form-control">
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                      <img src="<?= htmlspecialchars(profile_src($row['User_Profile'])) ?>" class="avatar-md" alt="">
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
</main>

<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Add User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="Username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="Email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="Password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="Phone_No" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Profile Image</label>
            <input type="file" name="User_Profile" class="form-control">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
