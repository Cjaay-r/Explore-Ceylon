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
$user = $res->fetch_assoc();
$type = $user ? strtolower(trim($user['User_Type'])) : '';

if ($type !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$loggedIn = function_exists('isLoggedIn') ? isLoggedIn() : isset($_SESSION['User_ID']);
if (!$loggedIn) {
  header('Location: ' . url('login.php'));
  exit;
}
$isAdmin = false;
if (isset($_SESSION['User_Type'])) {
  $isAdmin = (strtolower((string)$_SESSION['User_Type']) === 'admin');
} elseif (function_exists('currentUserRole')) {
  $isAdmin = (strtolower((string)currentUserRole()) === 'admin');
}
if (!$isAdmin) {
  header('Location: ' . url('login.php'));
  exit;
}

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];
function require_csrf($token) {
  if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token ?? '')) {
    http_response_code(400);
    echo "<h1>Bad Request</h1><p>Invalid CSRF token.</p>";
    exit;
  }
}

$mysqli = $conn;
$mysqli->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function to_nullable_numeric($v) {
  $v = trim((string)$v);
  if ($v === '') return null;
  if (!is_numeric($v)) return null;
  return $v;
}
function ensure_upload_dir($dir) {
  if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
  return is_dir($dir) && is_writable($dir);
}
function sanitize_filename($name) {
  $name = preg_replace('/[^\w.\-]+/u', '_', $name);
  $name = trim($name, '._');
  if ($name === '') $name = 'file';
  return $name;
}
function detect_image_ext_from_mime($mime) {
  static $map = [
    'image/jpeg'   => 'jpg',
    'image/png'    => 'png',
    'image/gif'    => 'gif',
    'image/webp'   => 'webp',
    'image/svg+xml'=> 'svg',
  ];
  return $map[strtolower($mime)] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $op = $_POST['op'] ?? '';
  require_csrf($_POST['csrf'] ?? '');

  if ($op === 'create_dest') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $lat = to_nullable_numeric($_POST['latitude'] ?? '');
    $lon = to_nullable_numeric($_POST['longitude'] ?? '');

    if ($name === '' || $desc === '' || $district === '') {
      $_SESSION['flash'] = ['type'=>'danger','msg'=>'Name, Description and District are required.'];
      header("Location: ".$_SERVER['PHP_SELF']);
      exit;
    }

    $mysqli->begin_transaction();
    try {
      $stmt = $mysqli->prepare("INSERT INTO destinations (Name, Description, District, latitude, longitude) VALUES (?,?,?,?,?)");
      $stmt->bind_param("sssdd", $name, $desc, $district, $lat, $lon);
      $stmt->execute();
      $newId = $stmt->insert_id;
      $stmt->close();

      $saved = 0; $failed = 0;
      $maxSize = 5 * 1024 * 1024;
      $uploadDir = rtrim(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'Destinations', DIRECTORY_SEPARATOR);
      $publicPrefix = 'uploads/Destinations/';

      if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
        if (!ensure_upload_dir($uploadDir)) {
          $failed = count(array_filter($_FILES['images']['name'], fn($n) => $n !== ''));
        } else {
          $f_names = $_FILES['images']['name'];
          $f_tmp   = $_FILES['images']['tmp_name'];
          $f_err   = $_FILES['images']['error'];
          $f_size  = $_FILES['images']['size'];
          $f_type  = $_FILES['images']['type'];
          $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

          for ($i=0; $i<count($f_names); $i++) {
            if ($f_names[$i] === '' && $f_tmp[$i] === '') continue;
            if (!isset($f_err[$i]) || $f_err[$i] !== UPLOAD_ERR_OK) { $failed++; continue; }
            if ((int)$f_size[$i] > $maxSize) { $failed++; continue; }

            $mime = $finfo ? finfo_file($finfo, $f_tmp[$i]) : ($f_type[$i] ?? '');
            $ext = detect_image_ext_from_mime($mime);
            if (!$ext) { $failed++; continue; }

            $base = pathinfo($f_names[$i], PATHINFO_FILENAME);
            $base = sanitize_filename($base);
            $unique = $base . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $unique;

            if (!@move_uploaded_file($f_tmp[$i], $destPath)) { $failed++; continue; }

            $url = $publicPrefix . $unique;
            $alt = 'Photo of ' . $name;
            $stmtImg = $mysqli->prepare("INSERT INTO destination_imgs (Image_Url, AltText, Destination_ID) VALUES (?,?,?)");
            $stmtImg->bind_param("ssi", $url, $alt, $newId);
            $stmtImg->execute();
            $stmtImg->close();
            $saved++;
          }
          if ($finfo) finfo_close($finfo);
        }
      }

      $mysqli->commit();

      $msg = "Destination created.";
      if ($saved || $failed) {
        $msg .= " Images uploaded: {$saved}".($failed? " (failed: {$failed})":"");
      }
      $_SESSION['flash'] = ['type'=>'success','msg'=>$msg];
      header("Location: ".$_SERVER['PHP_SELF']);
      exit;

    } catch (Throwable $t) {
      $mysqli->rollback();
      $_SESSION['flash'] = ['type'=>'danger','msg'=>'Error creating destination.'];
      header("Location: ".$_SERVER['PHP_SELF']);
      exit;
    }
  }

  if ($op === 'update_dest') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $lat = to_nullable_numeric($_POST['latitude'] ?? '');
    $lon = to_nullable_numeric($_POST['longitude'] ?? '');

    if ($id<=0 || $name === '' || $desc === '' || $district === '') {
      $_SESSION['flash'] = ['type'=>'danger','msg'=>'All fields are required.'];
      header("Location: ".$_SERVER['PHP_SELF']."?edit=".$id);
      exit;
    }

    $stmt = $mysqli->prepare("UPDATE destinations SET Name=?, Description=?, District=?, latitude=?, longitude=? WHERE Destination_ID=?");
    $stmt->bind_param("sssddi", $name, $desc, $district, $lat, $lon, $id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash'] = ['type'=>'success','msg'=>'Destination updated.'];
    header("Location: ".$_SERVER['PHP_SELF']."?edit=".$id);
    exit;
  }

  if ($op === 'delete_dest') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) {
      $stmt = $mysqli->prepare("DELETE FROM destinations WHERE Destination_ID=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();
      $_SESSION['flash'] = ['type'=>'success','msg'=>'Destination deleted.'];
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
  }

  if ($op === 'add_img') {
    $dest_id = (int)($_POST['dest_id'] ?? 0);
    if ($dest_id<=0) {
      $_SESSION['flash'] = ['type'=>'danger','msg'=>'Invalid destination.'];
      header("Location: ".$_SERVER['PHP_SELF']."?images=".$dest_id);
      exit;
    }

    $saved = 0; $failed = 0;
    $maxSize = 5 * 1024 * 1024;
    $uploadDir = rtrim(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'Destinations', DIRECTORY_SEPARATOR);
    $publicPrefix = 'uploads/Destinations/';

    if (!ensure_upload_dir($uploadDir)) {
      $_SESSION['flash'] = ['type'=>'danger','msg'=>'Upload folder is not writable.'];
      header("Location: ".$_SERVER['PHP_SELF']."?images=".$dest_id);
      exit;
    }

    if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
      $f_names = $_FILES['images']['name'];
      $f_tmp   = $_FILES['images']['tmp_name'];
      $f_err   = $_FILES['images']['error'];
      $f_size  = $_FILES['images']['size'];
      $f_type  = $_FILES['images']['type'];
      $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

      for ($i=0; $i<count($f_names); $i++) {
        if ($f_names[$i] === '' && $f_tmp[$i] === '') continue;
        if (!isset($f_err[$i]) || $f_err[$i] !== UPLOAD_ERR_OK) { $failed++; continue; }
        if ((int)$f_size[$i] > $maxSize) { $failed++; continue; }

        $mime = $finfo ? finfo_file($finfo, $f_tmp[$i]) : ($f_type[$i] ?? '');
        $ext = detect_image_ext_from_mime($mime);
        if (!$ext) { $failed++; continue; }

        $base = pathinfo($f_names[$i], PATHINFO_FILENAME);
        $base = sanitize_filename($base);
        $unique = $base . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destPath = $uploadDir . DIRECTORY_SEPARATOR . $unique;

        if (!@move_uploaded_file($f_tmp[$i], $destPath)) { $failed++; continue; }

        $stmt = $mysqli->prepare("SELECT Name FROM destinations WHERE Destination_ID=?");
        $stmt->bind_param("i", $dest_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $rowName = $res->fetch_assoc();
        $stmt->close();
        $alt = $rowName ? ('Photo of '.$rowName['Name']) : null;

        $url = $publicPrefix . $unique;
        $stmtImg = $mysqli->prepare("INSERT INTO destination_imgs (Image_Url, AltText, Destination_ID) VALUES (?,?,?)");
        $stmtImg->bind_param("ssi", $url, $alt, $dest_id);
        $stmtImg->execute();
        $stmtImg->close();
        $saved++;
      }
      if ($finfo) finfo_close($finfo);
    }

    $msg = "Images uploaded: {$saved}";
    if ($failed) $msg .= " (failed: {$failed})";
    $_SESSION['flash'] = ['type'=> $saved ? 'success' : 'warning', 'msg'=>$msg];
    header("Location: ".$_SERVER['PHP_SELF']."?images=".$dest_id);
    exit;
  }

  if ($op === 'del_img') {
    $img_id = (int)($_POST['img_id'] ?? 0);
    $dest_id = (int)($_POST['dest_id'] ?? 0);
    if ($img_id>0) {
      $stmt = $mysqli->prepare("DELETE FROM destination_imgs WHERE Image_ID=?");
      $stmt->bind_param("i", $img_id);
      $stmt->execute();
      $stmt->close();
      $_SESSION['flash'] = ['type'=>'success','msg'=>'Image removed.'];
    }
    header("Location: ".$_SERVER['PHP_SELF']."?images=".$dest_id);
    exit;
  }
}

$edit_id   = isset($_GET['edit'])   ? (int)$_GET['edit']   : 0;
$images_id = isset($_GET['images']) ? (int)$_GET['images'] : 0;

$edit_row = null;
if ($edit_id > 0) {
  $stmt = $mysqli->prepare("SELECT * FROM destinations WHERE Destination_ID=?");
  $stmt->bind_param("i", $edit_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $edit_row = $res->fetch_assoc();
  $stmt->close();
}

$imgs = [];
$dest_for_imgs = null;
if ($images_id > 0) {
  $stmt = $mysqli->prepare("SELECT * FROM destinations WHERE Destination_ID=?");
  $stmt->bind_param("i", $images_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $dest_for_imgs = $res->fetch_assoc();
  $stmt->close();

  $stmt = $mysqli->prepare("SELECT * FROM destination_imgs WHERE Destination_ID=? ORDER BY Image_ID DESC");
  $stmt->bind_param("i", $images_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) { $imgs[] = $row; }
  $stmt->close();
}

$all = [];
$q = "SELECT d.Destination_ID, d.Name, d.District, d.latitude, d.longitude,
             (SELECT COUNT(*) FROM destination_imgs di WHERE di.Destination_ID = d.Destination_ID) AS image_count
      FROM destinations d
      ORDER BY d.Name ASC";
$res = $mysqli->query($q);
if ($res) { while($r=$res->fetch_assoc()) { $all[] = $r; } $res->close(); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Destinations · Manage Destinations</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?php echo h(url('Styles/ManageDestinations.css')); ?>">
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<main class="main">
  <div class="container container-narrow">
    <?php if (!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
      <div class="alert alert-<?php echo h($f['type']); ?> alert-dismissible fade show" role="alert">
        <?php echo h($f['msg']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header"><?php echo $edit_row ? 'Edit Destination' : 'Create Destination'; ?></div>
          <div class="card-body">
            <form method="post" novalidate enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
              <?php if($edit_row): ?>
                <input type="hidden" name="op" value="update_dest">
                <input type="hidden" name="id" value="<?php echo (int)$edit_row['Destination_ID']; ?>">
              <?php else: ?>
                <input type="hidden" name="op" value="create_dest">
              <?php endif; ?>

              <div class="mb-3">
                <label class="form-label">Name</label>
                <input name="name" class="form-control" maxlength="300" required
                       value="<?php echo h($edit_row['Name'] ?? ''); ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4" maxlength="2000" required><?php
                  echo h($edit_row['Description'] ?? '');
                ?></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">District</label>
                <input name="district" class="form-control" maxlength="200" required
                       value="<?php echo h($edit_row['District'] ?? ''); ?>">
              </div>

              <div class="row">
                <div class="col">
                  <label class="form-label">Latitude</label>
                  <input name="latitude" type="text" inputmode="decimal" class="form-control"
                         placeholder="e.g., 6.9271"
                         value="<?php echo h($edit_row['latitude'] ?? ''); ?>">
                </div>
                <div class="col">
                  <label class="form-label">Longitude</label>
                  <input name="longitude" type="text" inputmode="decimal" class="form-control"
                         placeholder="e.g., 79.8612"
                         value="<?php echo h($edit_row['longitude'] ?? ''); ?>">
                </div>
              </div>

              <?php if(!$edit_row): ?>
              <div class="mb-3 mt-3">
                <label class="form-label">Images (optional)</label>
                <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                <div class="form-text">Choose multiple images. Max 5 MB each. Saved to <code>uploads/</code>.</div>
              </div>
              <?php endif; ?>

              <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?php echo $edit_row ? 'Update' : 'Create'; ?></button>
                <?php if($edit_row): ?>
                  <a class="btn btn-secondary" href="<?php echo h($_SERVER['PHP_SELF']); ?>">Cancel</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>

        <?php if ($images_id>0 && !empty($dest_for_imgs)): ?>
        <div class="card mt-4">
          <div class="card-header">Images · <?php echo h($dest_for_imgs['Name']); ?></div>
          <div class="card-body">
            <form method="post" class="row g-2" enctype="multipart/form-data">
              <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
              <input type="hidden" name="op" value="add_img">
              <input type="hidden" name="dest_id" value="<?php echo (int)$images_id; ?>">

              <div class="col-12">
                <label class="form-label">Add images</label>
                <input type="file" name="images[]" class="form-control" multiple accept="image/*" required>
                <div class="form-text">You can select multiple files. Max 5 MB each.</div>
              </div>

              <div class="col-12">
                <button class="btn btn-outline-primary">Upload</button>
                <a class="btn btn-secondary" href="<?php echo h($_SERVER['PHP_SELF']); ?>">Done</a>
              </div>
            </form>

            <hr>
            <?php if (!$imgs): ?>
              <p class="text-muted mb-0">No images yet.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead><tr><th>ID</th><th>Preview</th><th>URL</th><th>Alt</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach($imgs as $img): ?>
                      <tr>
                        <td><?php echo (int)$img['Image_ID']; ?></td>
                        <td style="width:120px"><img src="<?php echo h(url($img['Image_Url'])); ?>" alt="" class="img-fluid rounded border"></td>
                        <td class="text-break" style="max-width:280px"><?php echo h($img['Image_Url']); ?></td>
                        <td><?php echo h($img['AltText']); ?></td>
                        <td class="text-end">
                          <form method="post" onsubmit="return confirm('Delete this image?');">
                            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                            <input type="hidden" name="op" value="del_img">
                            <input type="hidden" name="img_id" value="<?php echo (int)$img['Image_ID']; ?>">
                            <input type="hidden" name="dest_id" value="<?php echo (int)$images_id; ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">All Destinations</div>
          <div class="card-body">
            <?php if (!$all): ?>
              <p class="text-muted mb-0">No destinations yet.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th style="width:60px">ID</th>
                      <th>Name</th>
                      <th>District</th>
                      <th class="text-nowrap">Lat / Lon</th>
                      <th>Images</th>
                      <th class="text-end" style="width:220px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($all as $row): ?>
                      <tr>
                        <td><?php echo (int)$row['Destination_ID']; ?></td>
                        <td><?php echo h($row['Name']); ?></td>
                        <td><?php echo h($row['District']); ?></td>
                        <td class="text-muted">
                          <?php echo h($row['latitude']); ?>,
                          <?php echo h($row['longitude']); ?>
                        </td>
                        <td><span class="badge rounded-pill text-bg-light badge-soft"><?php echo (int)$row['image_count']; ?></span></td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-outline-secondary" href="<?php echo h($_SERVER['PHP_SELF']."?images=".$row['Destination_ID']); ?>">Images</a>
                          <a class="btn btn-sm btn-outline-primary" href="<?php echo h($_SERVER['PHP_SELF']."?edit=".$row['Destination_ID']); ?>">Edit</a>
                          <form method="post" class="d-inline" onsubmit="return confirm('Delete this destination?');">
                            <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
                            <input type="hidden" name="op" value="delete_dest">
                            <input type="hidden" name="id" value="<?php echo (int)$row['Destination_ID']; ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
