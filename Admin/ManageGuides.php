;<?php
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

function guide_profile_src($val) {
    $v = trim((string)$val);
    if ($v === '' || strtolower($v) === 'defaultuser.jpg') {
        return url('Images/defaultuser.jpg');
    }
    if (strpos($v, '/') !== false) {
        return ($v[0] === '/') ? $v : url($v);
    }
    return url('uploads/UserProfiles/' . $v);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $Username = trim($_POST['Username'] ?? '');
        $Email = trim($_POST['Email'] ?? '');
        $Password = $_POST['Password'] ?? '';
        $Phone_No = trim($_POST['Phone_No'] ?? '');
        $F_Name = trim($_POST['F_Name'] ?? '');
        $L_Name = trim($_POST['L_Name'] ?? '');
        $NIC_or_Pass = trim($_POST['NIC_or_Pass'] ?? '');
        $Description = trim($_POST['Description'] ?? '');
        $Status = trim($_POST['Status'] ?? '');
        if ($Username === '' || $Email === '' || $Password === '' || $Phone_No === '' || $F_Name === '' || $L_Name === '' || $NIC_or_Pass === '' || $Status === '') {
            $errors[] = 'All required fields must be filled.';
        } else {
            $User_Profile = save_profile_image('User_Profile') ?? 'defaultuser.jpg';
            $hash = password_hash($Password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO user (Username, Email, Password, Phone_No, User_Profile, User_Type) VALUES (?, ?, ?, ?, ?, 'Guide')");
            $stmt->bind_param("sssss", $Username, $Email, $hash, $Phone_No, $User_Profile);
            if ($stmt->execute()) {
                $uid = $stmt->insert_id;
                $ratingDefault = 5.0;
                $stmt2 = $conn->prepare("INSERT INTO guide (F_Name, L_Name, NIC_or_Pass, Description, Status, Rating, User_ID) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt2->bind_param("sssssdi", $F_Name, $L_Name, $NIC_or_Pass, $Description, $Status, $ratingDefault, $uid);
                if ($stmt2->execute()) {
                    $success = 'Guide created.';
                } else {
                    $errors[] = 'Failed creating guide.';
                }
            } else {
                $errors[] = 'Failed creating user.';
            }
        }
    } elseif ($action === 'update') {
        $Guide_ID = (int)($_POST['Guide_ID'] ?? 0);
        $User_ID = (int)($_POST['User_ID'] ?? 0);
        $Username = trim($_POST['Username'] ?? '');
        $Email = trim($_POST['Email'] ?? '');
        $Phone_No = trim($_POST['Phone_No'] ?? '');
        $F_Name = trim($_POST['F_Name'] ?? '');
        $L_Name = trim($_POST['L_Name'] ?? '');
        $NIC_or_Pass = trim($_POST['NIC_or_Pass'] ?? '');
        $Description = trim($_POST['Description'] ?? '');
        if (!$Guide_ID || !$User_ID || $Username === '' || $Email === '' || $Phone_No === '' || $F_Name === '' || $L_Name === '' || $NIC_or_Pass === '') {
            $errors[] = 'All required fields must be filled.';
        } else {
            $newImg = save_profile_image('User_Profile');
            if ($newImg) {
                $stmt = $conn->prepare("UPDATE user SET Username=?, Email=?, Phone_No=?, User_Profile=? WHERE User_ID=?");
                $stmt->bind_param("ssssi", $Username, $Email, $Phone_No, $newImg, $User_ID);
            } else {
                $stmt = $conn->prepare("UPDATE user SET Username=?, Email=?, Phone_No=? WHERE User_ID=?");
                $stmt->bind_param("sssi", $Username, $Email, $Phone_No, $User_ID);
            }
            $ok1 = $stmt->execute();
            $stmt2 = $conn->prepare("UPDATE guide SET F_Name=?, L_Name=?, NIC_or_Pass=?, Description=? WHERE Guide_ID=?");
            $stmt2->bind_param("ssssi", $F_Name, $L_Name, $NIC_or_Pass, $Description, $Guide_ID);
            $ok2 = $stmt2->execute();
            if ($ok1 && $ok2) $success = 'Guide updated.'; else $errors[] = 'Update failed.';
        }
    } elseif ($action === 'delete') {
        $Guide_ID = (int)($_POST['Guide_ID'] ?? 0);
        if ($Guide_ID) {
            $stmt = $conn->prepare("DELETE FROM guide WHERE Guide_ID=?");
            $stmt->bind_param("i", $Guide_ID);
            if ($stmt->execute()) $success = 'Guide deleted.'; else $errors[] = 'Delete failed.';
        }
    }
}

$rows = [];
$q = $conn->query("SELECT g.*, u.Username, u.Email, u.Phone_No, u.User_Profile, u.User_ID FROM guide g LEFT JOIN user u ON g.User_ID=u.User_ID ORDER BY g.Guide_ID DESC");
while ($r = $q->fetch_assoc()) $rows[] = $r;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Guides</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../Styles/ManageGuides.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<main class="main">
  <div class="topbar">
    <h1>Manage Guides</h1>
    <button class="btn primary" data-bs-toggle="modal" data-bs-target="#createModal">Add Guide</button>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
  <div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Guide</th>
            <th>NIC/Passport</th>
            <th>Availability</th>
            <th>User</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= (int)$row['Guide_ID'] ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img src="<?= htmlspecialchars(guide_profile_src($row['User_Profile'])) ?>" class="avatar" alt="">
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars($row['F_Name'].' '.$row['L_Name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($row['Description']) ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($row['NIC_or_Pass']) ?></td>
            <td><span class="badge-soft"><?= htmlspecialchars($row['Status']) ?></span></td>
            <td>
              <div class="small"><?= htmlspecialchars($row['Username']) ?></div>
              <div class="text-muted small"><?= htmlspecialchars($row['Email']) ?> • <?= htmlspecialchars($row['Phone_No']) ?></div>
            </td>
            <td class="text-end">
              <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$row['Guide_ID'] ?>">Edit</button>
              <form method="post" class="d-inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="Guide_ID" value="<?= (int)$row['Guide_ID'] ?>">
                <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this guide?')">Delete</button>
              </form>
            </td>
          </tr>
          <div class="modal fade" id="edit<?= (int)$row['Guide_ID'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit Guide</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="Guide_ID" value="<?= (int)$row['Guide_ID'] ?>">
                    <input type="hidden" name="User_ID" value="<?= (int)$row['User_ID'] ?>">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input type="text" name="F_Name" class="form-control" value="<?= htmlspecialchars($row['F_Name']) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="L_Name" class="form-control" value="<?= htmlspecialchars($row['L_Name']) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">NIC/Passport</label>
                        <input type="text" name="NIC_or_Pass" class="form-control" value="<?= htmlspecialchars($row['NIC_or_Pass']) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Availability</label>
                        <select class="form-select" disabled>
                          <option <?= $row['Status']==='Available'?'selected':'' ?>>Available</option>
                          <option <?= $row['Status']==='Busy'?'selected':'' ?>>Busy</option>
                        </select>
                      </div>
                      <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="Description" class="form-control" rows="3"><?= htmlspecialchars($row['Description']) ?></textarea>
                      </div>
                      <div class="col-12">
                        <label class="form-label">Rating</label>
                        <div class="d-flex align-items-center gap-2">
                          <?php
                            $r = (float)($row['Rating'] ?? 0);
                            $full = (int)floor($r);
                            $half = ($r - $full) >= 0.5 ? 1 : 0;
                            $empty = 5 - $full - $half;
                          ?>
                          <div class="text-warning fs-5">
                            <?php for($i=0;$i<$full;$i++) echo '★'; ?>
                            <?php if($half) echo '☆'; ?>
                            <?php for($i=0;$i<$empty;$i++) echo '☆'; ?>
                          </div>
                          <span class="text-muted small">(<?= number_format($r,1) ?>)</span>
                        </div>
                      </div>
                      <hr class="mt-3">
                      <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input type="text" name="Username" class="form-control" value="<?= htmlspecialchars($row['Username']) ?>" required>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="Email" class="form-control" value="<?= htmlspecialchars($row['Email']) ?>" required>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="Phone_No" class="form-control" value="<?= htmlspecialchars($row['Phone_No']) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Profile Image</label>
                        <input type="file" name="User_Profile" class="form-control">
                      </div>
                      <div class="col-md-6 d-flex align-items-end">
                        <img src="<?= htmlspecialchars(guide_profile_src($row['User_Profile'])) ?>" class="avatar-md" alt="">
                      </div>
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

</div>

<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Add Guide</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="create">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">First Name</label>
              <input type="text" name="F_Name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Last Name</label>
              <input type="text" name="L_Name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">NIC/Passport</label>
              <input type="text" name="NIC_or_Pass" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Availability</label>
              <select name="Status" class="form-select" required>
                <option value="Available">Available</option>
                <option value="Busy">Busy</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="Description" class="form-control" rows="3"></textarea>
            </div>
            <hr class="mt-3">
            <div class="col-md-4">
              <label class="form-label">Username</label>
              <input type="text" name="Username" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Email</label>
              <input type="email" name="Email" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Password</label>
              <input type="password" name="Password" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="Phone_No" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Profile Image</label>
              <input type="file" name="User_Profile" class="form-control">
            </div>
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








