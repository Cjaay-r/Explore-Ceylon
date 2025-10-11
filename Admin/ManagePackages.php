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

$mysqli = $conn;
$mysqli->set_charset('utf8mb4');

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function require_csrf(){
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
      http_response_code(400); die('Bad Request (CSRF).');
    }
  }
}
require_csrf();

function uploads_fs_dir(): string {
  return rtrim(dirname(__DIR__) . '/uploads/Packages', '/');
}
function uploads_web_dir(): string {
  return 'uploads/Packages';
}
function ensure_upload_dir(): string {
  $dir = uploads_fs_dir();
  if (!is_dir($dir)) @mkdir($dir, 0777, true);
  if (!is_writable($dir)) @chmod($dir, 0777);
  return $dir;
}
function sanitize_filename($name){
  $name = preg_replace('/[^\w\.\-\s]/','_',$name);
  $name = preg_replace('/\s+/','_',$name);
  return $name ?: ('file_'.time());
}
function to_int_or_null($v){ return (isset($v) && $v!=='' && is_numeric($v)) ? intval($v) : null; }
function to_float_or_null($v){ return (isset($v) && $v!=='') ? floatval($v) : null; }
function save_one_upload(array $file): array {
  if (empty($file['name']) || empty($file['tmp_name'])) {
    return [false, null, 'No file'];
  }
  $fsDir  = ensure_upload_dir();
  $webDir = uploads_web_dir();
  $fname  = time() . '_' . sanitize_filename($file['name']);
  $fsPath = $fsDir . '/' . $fname;
  $ok = @move_uploaded_file($file['tmp_name'], $fsPath);
  if (!$ok) return [false, null, 'Failed to move uploaded file'];
  $webPath = $webDir . '/' . $fname;
  return [true, $webPath, null];
}

function fetch_package($mysqli,$id){
  $stmt=$mysqli->prepare("SELECT Package_ID, Name, Subtitle, Description, Long_Des, DurationDays, Price, Root_img FROM packages WHERE Package_ID=?");
  $stmt->bind_param("i",$id); $stmt->execute(); $res=$stmt->get_result(); $row=$res->fetch_assoc(); $stmt->close(); return $row;
}
function fetch_images($mysqli,$pkgId){
  $stmt=$mysqli->prepare("SELECT ImageID, ImageUrl, AltText FROM packageimages WHERE Package_ID=? ORDER BY ImageID DESC");
  $stmt->bind_param("i",$pkgId); $stmt->execute(); $res=$stmt->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
}
function fetch_itinerary($mysqli,$pkgId){
  $stmt=$mysqli->prepare("SELECT ItineraryID, DayNumber, Location, Description FROM itinerary WHERE PackageID=? ORDER BY DayNumber ASC, ItineraryID ASC");
  $stmt->bind_param("i",$pkgId); $stmt->execute(); $res=$stmt->get_result(); $rows=$res->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
}
function count_related($mysqli){
  $sql = "SELECT p.Package_ID,
            (SELECT COUNT(*) FROM packageimages pi WHERE pi.Package_ID=p.Package_ID) AS img_count,
            (SELECT COUNT(*) FROM itinerary it WHERE it.PackageID=p.Package_ID) AS it_count
          FROM packages p";
  $map=[]; $res=$mysqli->query($sql);
  if($res){ while($r=$res->fetch_assoc()){ $map[(int)$r['Package_ID']] = $r; } $res->close(); }
  return $map;
}

$notice = ""; $error = "";
$mode = $_GET['mode'] ?? 'list';
$pkgId = isset($_GET['id']) ? intval($_GET['id']) : null;

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';

  if($action==='create' || $action==='update'){
    $id          = to_int_or_null($_POST['id'] ?? null);
    $Name        = trim($_POST['name'] ?? '');
    $Subtitle    = trim($_POST['subtitle'] ?? '');
    $Description = trim($_POST['description'] ?? '');
    $Long_Des    = trim($_POST['long_des'] ?? '');
    $Duration    = to_int_or_null($_POST['duration'] ?? null);
    $Price       = to_float_or_null($_POST['price'] ?? null);

    if($Name==='' || $Description==='' || $Duration===null || $Price===null){
      $error = "Please fill required fields: Name, Short Description, Duration, Price.";
      $mode = ($action==='create') ? 'list' : 'edit';
      $pkgId = $id;
    } else {
      $rootPath = null;
      if(!empty($_FILES['root_img']['name'])){
        [$ok,$webPath,$err] = save_one_upload($_FILES['root_img']);
        if(!$ok){
          $error = "Failed to upload the main image.";
          $mode = ($action==='create') ? 'list' : 'edit';
          $pkgId = $id;
        } else {
          $rootPath = $webPath;
        }
      }

      if(!$error){
        if($action==='create'){
          if($rootPath===null) $rootPath = '';
          $stmt=$mysqli->prepare("INSERT INTO packages (Name, Subtitle, Description, Long_Des, DurationDays, Price, Root_img, User_ID) VALUES (?,?,?,?,?,?,?,1)");
          $stmt->bind_param("ssssids", $Name, $Subtitle, $Description, $Long_Des, $Duration, $Price, $rootPath);
          if($stmt->execute()){
            $newId = $stmt->insert_id;
            $stmt->close();
            $notice = "Package #{$newId} created.";

            if(!empty($_FILES['images']['name'][0])){
              foreach($_FILES['images']['name'] as $i=>$n){
                if(empty($_FILES['images']['tmp_name'][$i])) continue;
                $file = [
                  'name' => $_FILES['images']['name'][$i],
                  'tmp_name' => $_FILES['images']['tmp_name'][$i]
                ];
                [$ok,$webPath,$err] = save_one_upload($file);
                if($ok){
                  $stmt2=$mysqli->prepare("INSERT INTO packageimages (ImageUrl, AltText, Package_ID) VALUES (?,?,?)");
                  $alt='Package Image';
                  $stmt2->bind_param("ssi",$webPath,$alt,$newId);
                  $stmt2->execute(); $stmt2->close();
                }
              }
            }

            $days = $_POST['day'] ?? [];
            $locs = $_POST['location'] ?? [];
            $descs= $_POST['it_desc'] ?? [];
            if(!empty($days)){
              $stmt3=$mysqli->prepare("INSERT INTO itinerary (DayNumber, Location, Description, PackageID) VALUES (?,?,?,?)");
              foreach($days as $i=>$d){
                $dnum = to_int_or_null($d); $loc = trim($locs[$i] ?? ''); $desc = trim($descs[$i] ?? '');
                if($dnum!==null && $loc!=='' && $desc!==''){
                  $stmt3->bind_param("issi",$dnum,$loc,$desc,$newId);
                  $stmt3->execute();
                }
              }
              $stmt3->close();
            }

            header("Location: ".$_SERVER['PHP_SELF']."?notice=".urlencode($notice));
            exit;
          } else {
            $error = "Create failed: ".h($stmt->error);
            $stmt->close();
            $mode='list';
          }

        } else {
          if($id===null){ $error="Missing package id."; $mode='list'; }
          else {
            if($rootPath!==null){
              $stmt=$mysqli->prepare("UPDATE packages SET Name=?, Subtitle=?, Description=?, Long_Des=?, DurationDays=?, Price=?, Root_img=? WHERE Package_ID=?");
              $stmt->bind_param("ssssidsi",$Name,$Subtitle,$Description,$Long_Des,$Duration,$Price,$rootPath,$id);
            } else {
              $stmt=$mysqli->prepare("UPDATE packages SET Name=?, Subtitle=?, Description=?, Long_Des=?, DurationDays=?, Price=? WHERE Package_ID=?");
              $stmt->bind_param("ssssidi",$Name,$Subtitle,$Description,$Long_Des,$Duration,$Price,$id);
            }
            if($stmt->execute()){
              $stmt->close();

              if(!empty($_FILES['images']['name'][0])){
                foreach($_FILES['images']['name'] as $i=>$n){
                  if(empty($_FILES['images']['tmp_name'][$i])) continue;
                  $file = [
                    'name' => $_FILES['images']['name'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i]
                  ];
                  [$ok,$webPath,$err] = save_one_upload($file);
                  if($ok){
                    $stmt2=$mysqli->prepare("INSERT INTO packageimages (ImageUrl, AltText, Package_ID) VALUES (?,?,?)");
                    $alt='Package Image';
                    $stmt2->bind_param("ssi",$webPath,$alt,$id);
                    $stmt2->execute(); $stmt2->close();
                  }
                }
              }

              if(isset($_POST['day'])){
                $mysqli->query("DELETE FROM itinerary WHERE PackageID=".(int)$id);
                $days = $_POST['day'] ?? [];
                $locs = $_POST['location'] ?? [];
                $descs= $_POST['it_desc'] ?? [];
                if(!empty($days)){
                  $stmt3=$mysqli->prepare("INSERT INTO itinerary (DayNumber, Location, Description, PackageID) VALUES (?,?,?,?)");
                  foreach($days as $i=>$d){
                    $dnum = to_int_or_null($d); $loc = trim($locs[$i] ?? ''); $desc = trim($descs[$i] ?? '');
                    if($dnum!==null && $loc!=='' && $desc!==''){
                      $stmt3->bind_param("issi",$dnum,$loc,$desc,$id);
                      $stmt3->execute();
                    }
                  }
                  $stmt3->close();
                }
              }

              $notice = "Package #{$id} updated.";
              header("Location: ".$_SERVER['PHP_SELF']."?notice=".urlencode($notice)."&mode=edit&id=".$id);
              exit;

            } else {
              $error = "Update failed: ".h($stmt->error);
              $stmt->close(); $mode='edit'; $pkgId=$id;
            }
          }
        }
      }
    }
  }
  elseif($action==='delete_image'){
    $imgId = to_int_or_null($_POST['image_id'] ?? null);
    $pid   = to_int_or_null($_POST['pkg_id'] ?? null);
    if($imgId!==null){
      $stmt=$mysqli->prepare("DELETE FROM packageimages WHERE ImageID=?");
      $stmt->bind_param("i",$imgId); $stmt->execute(); $stmt->close();
      $notice = "Image #{$imgId} deleted.";
    }
    header("Location: ".$_SERVER['PHP_SELF']."?mode=images&id=".$pid."&notice=".urlencode($notice));
    exit;
  }
  elseif($action==='delete_root_image'){
    $pid = to_int_or_null($_POST['pkg_id'] ?? null);
    if($pid!==null){
      $stmt=$mysqli->prepare("UPDATE packages SET Root_img='' WHERE Package_ID=?");
      $stmt->bind_param("i",$pid); $stmt->execute(); $stmt->close();
      $notice = "Main image removed.";
    }
    header("Location: ".$_SERVER['PHP_SELF']."?mode=images&id=".$pid."&notice=".urlencode($notice));
    exit;
  }
  elseif($action==='delete_package'){
    $pid = to_int_or_null($_POST['pkg_id'] ?? null);
    if($pid!==null){
      $stmt=$mysqli->prepare("DELETE FROM packages WHERE Package_ID=?");
      $stmt->bind_param("i",$pid); $stmt->execute(); $stmt->close();
      $notice = "Package #{$pid} deleted.";
    }
    header("Location: ".$_SERVER['PHP_SELF']."?notice=".urlencode($notice));
    exit;
  }
}

if(isset($_GET['notice'])) $notice = $_GET['notice'];
$counts = count_related($mysqli);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Packages Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?php echo h(url('Styles/ManagePackages.css')); ?>">
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<main class="main">
  <nav class="navbar navbar-dark">
    <div class="container-fluid">
      <span class="navbar-brand mb-0 h1">Packages Admin</span>
      <span class="ms-auto brand-note">Admin access</span>
    </div>
  </nav>

  <div class="container my-4">
    <?php if($notice): ?>
      <div class="alert alert-success"><?php echo h($notice); ?></div>
    <?php endif; ?>
    <?php if($error): ?>
      <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>

    <?php if($mode==='list'): ?>
      <div class="row g-4">
        <div class="col-12 col-lg-5">
          <div class="card">
            <div class="card-header">Create Package</div>
            <div class="card-body">
              <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
                <input type="hidden" name="action" value="create">

                <div class="mb-3">
                  <label class="form-label">Name</label>
                  <input type="text" name="name" class="form-control" required>
                </div>

                <div class="mb-3">
                  <label class="form-label">Short Description</label>
                  <textarea name="description" class="form-control" rows="3" required></textarea>
                </div>

                <div class="mb-3">
                  <label class="form-label">Subtitle</label>
                  <input type="text" name="subtitle" class="form-control">
                </div>

                <div class="row g-3">
                  <div class="col">
                    <label class="form-label">Duration (days)</label>
                    <input type="number" name="duration" min="1" step="1" class="form-control" required>
                  </div>
                  <div class="col">
                    <label class="form-label">Price (Rs.)</label>
                    <input type="number" name="price" step="0.01" min="0" class="form-control" required>
                  </div>
                </div>

                <div class="mb-3 mt-3">
                  <label class="form-label">Long Description</label>
                  <textarea name="long_des" class="form-control" rows="5"></textarea>
                </div>

                <div class="mb-4">
                  <label class="form-label">Main Image (Root) (optional)</label>
                  <input type="file" name="root_img" class="form-control" accept="image/*">
                </div>
                
                <div class="mb-3">
                  <label class="form-label">Images (optional)</label>
                  <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                  <div class="g-note mt-1">Choose multiple images. Max 5 MB each. Saved to <span class="text-decoration-underline">uploads/Packages/</span>.</div>
                </div>

                <hr class="mt-2 mb-3">

                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="fw-semibold">Itinerary</div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addItRow()">Add Day</button>
                </div>

                <div id="itineraries" class="vstack gap-3 mb-3"></div>

                <button class="btn btn-primary" type="submit">Create</button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-7">
          <div class="card">
            <div class="card-header">All Packages</div>
            <div class="card-body p-0">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th style="width:70px;">ID</th>
                    <th>Name</th>
                    <th style="width:110px;">Duration</th>
                    <th style="width:140px;">Price</th>
                    <th style="width:90px;">Images</th>
                    <th style="width:145px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                  $sql="SELECT Package_ID, Name, Subtitle, DurationDays, Price FROM packages ORDER BY Package_ID DESC";
                  $res=$mysqli->query($sql);
                  $counts = $counts ?? [];
                  if($res && $res->num_rows){
                    while($r=$res->fetch_assoc()){
                      $pid=(int)$r['Package_ID'];
                      $imgc = $counts[$pid]['img_count'] ?? 0;
                      echo '<tr>';
                      echo '<td>'.h($pid).'</td>';
                      echo '<td><div class="fw-semibold">'.h($r['Name']).'</div><div class="small muted">'.h($r['Subtitle']).'</div></td>';
                      echo '<td>'.h((int)$r['DurationDays']).' days</td>';
                      echo '<td>Rs. '.number_format((float)$r['Price'],2).'</td>';
                      echo '<td>'.(int)$imgc.'</td>';
                      echo '<td class="text-nowrap">
                              <a class="btn btn-sm btn-outline-secondary me-1" href="?mode=images&id='.$pid.'">Images</a>
                              <a class="btn btn-sm btn-outline-primary me-1" href="?mode=edit&id='.$pid.'">Edit</a>
                              <a class="btn btn-sm btn-outline-danger" href="?mode=delete&id='.$pid.'">Delete</a>
                            </td>';
                      echo '</tr>';
                    }
                  } else {
                    echo '<tr><td colspan="6" class="p-4 muted">No packages yet. Create one on the left.</td></tr>';
                  }
                  if($res) $res->close();
                ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    <?php elseif($mode==='create' || $mode==='edit' || $mode==='images' || $mode==='itinerary'): 
        $editing = ($mode!=='create');
        $row = ['Package_ID'=>null,'Name'=>'','Subtitle'=>'','Description'=>'','Long_Des'=>'','DurationDays'=>'','Price'=>'','Root_img'=>''];
        $existingImgs=[]; $existingIt=[]; $pid=null;
        if($editing){
          if(!$pkgId){ echo '<div class="alert alert-danger">Missing package id.</div>'; $mode='list'; }
          else {
            $row = fetch_package($mysqli,$pkgId);
            if(!$row){ echo '<div class="alert alert-danger">Package not found.</div>'; $mode='list'; }
            else {
              $pid=(int)$row['Package_ID'];
              $existingImgs = fetch_images($mysqli,$pid);
              $existingIt   = fetch_itinerary($mysqli,$pid);
            }
          }
        }
        if($mode!=='list'):
    ?>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <a class="btn btn-sm btn-outline-secondary me-2" href="<?php echo h($_SERVER['PHP_SELF']); ?>">← Back</a>
          <span class="muted"><?php echo $editing ? 'Editing Package #'.h($row['Package_ID']) : 'Create a new Package'; ?></span>
        </div>
        <?php if($editing): ?>
          <div>
            <a class="btn btn-sm btn-outline-secondary me-2" href="?mode=edit&id=<?php echo $pid; ?>">Details</a>
            <a class="btn btn-sm btn-outline-secondary me-2" href="?mode=images&id=<?php echo $pid; ?>">Images</a>
            <a class="btn btn-sm btn-outline-secondary" href="?mode=itinerary&id=<?php echo $pid; ?>">Itinerary</a>
          </div>
        <?php endif; ?>
      </div>

      <?php if($mode==='create' || $mode==='edit'): ?>
        <div class="card">
          <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
              <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
              <input type="hidden" name="action" value="<?php echo $editing ? 'update':'create'; ?>">
              <?php if($editing): ?><input type="hidden" name="id" value="<?php echo (int)$row['Package_ID']; ?>"><?php endif; ?>

              <div class="col-md-6">
                <label class="form-label">Package Name *</label>
                <input type="text" class="form-control" name="name" required value="<?php echo h($row['Name']); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Subtitle</label>
                <input type="text" class="form-control" name="subtitle" value="<?php echo h($row['Subtitle']); ?>">
              </div>

              <div class="col-md-3">
                <label class="form-label">Duration (days) *</label>
                <input type="number" class="form-control" name="duration" min="1" step="1" required value="<?php echo h($row['DurationDays']); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Price (Rs.) *</label>
                <input type="number" class="form-control" name="price" step="0.01" min="0" required value="<?php echo h($row['Price']); ?>">
              </div>

              <div class="col-12">
                <label class="form-label">Short Description *</label>
                <textarea class="form-control" name="description" rows="3" required><?php echo h($row['Description']); ?></textarea>
              </div>

              <div class="col-12">
                <label class="form-label">Long Description</label>
                <textarea class="form-control" name="long_des" rows="6"><?php echo h($row['Long_Des']); ?></textarea>
              </div>

              <div class="col-md-6">
                <label class="form-label">Main Image (Root) <?php echo $editing?'(leave empty to keep current)':''; ?></label>
                <input type="file" class="form-control" name="root_img" accept="image/*">
                <?php if($editing && $row['Root_img']!==''): ?>
                  <div class="g-note mt-2">Current main image:</div>
                  <img class="img-thumb mt-1" src="<?php echo h(url($row['Root_img'])); ?>" alt="Root">
                <?php endif; ?>
              </div>

              <div class="col-md-6">
                <label class="form-label">Gallery Images (add more)</label>
                <input type="file" class="form-control" name="images[]" accept="image/*" multiple>
                <?php if($editing && $existingImgs): ?>
                  <div class="g-note mt-2">Use the Images tab to remove existing ones.</div>
                <?php endif; ?>
              </div>

              <div class="col-12">
                <hr class="mt-2 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <div class="fw-semibold">Itinerary</div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addItRow()">Add Day</button>
                </div>
                <div id="itineraries" class="vstack gap-3">
                  <?php
                    $prefill = ($editing && $existingIt) ? $existingIt : [];
                    if(!$prefill){
                      echo '<div class="muted">No itinerary rows yet. Click <span class="badge text-bg-secondary">Add Day</span> to start.</div>';
                    } else {
                      foreach($prefill as $it){
                        echo '<div class="card"><div class="card-body">
                                <div class="row g-3 align-items-end">
                                  <div class="col-12 col-md-2">
                                    <label class="form-label">Day</label>
                                    <input type="number" class="form-control" name="day[]" min="1" value="'.h($it['DayNumber']).'">
                                  </div>
                                  <div class="col-12 col-md-4">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location[]" value="'.h($it['Location']).'">
                                  </div>
                                  <div class="col-12 col-md-5">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="it_desc[]" value="'.h($it['Description']).'">
                                  </div>
                                  <div class="col-12 col-md-auto">
                                    <label class="form-label d-none d-md-block">&nbsp;</label>
                                    <button type="button" class="btn btn-outline-danger text-nowrap" onclick="this.closest(\'.card\').remove()">Remove</button>
                                  </div>
                                </div>
                              </div></div>';
                      }
                    }
                  ?>
                </div>
              </div>

              <div class="col-12 d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="<?php echo h($_SERVER['PHP_SELF']); ?>">Cancel</a>
                <button class="btn btn-primary" type="submit"><?php echo $editing?'Save Changes':'Create Package'; ?></button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <?php if($mode==='images' && $editing): ?>
        <div class="card">
          <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
              <div>
                <div class="fw-semibold">Images for: <span class="fw-bold"><?php echo h($row['Name']); ?></span></div>
                <div class="g-note">Manage main image and gallery images.</div>
              </div>
              <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo (int)$row['Package_ID']; ?>">
                <input type="file" class="form-control" name="images[]" accept="image/*" multiple>
                <button class="btn btn-primary" type="submit">Upload</button>
              </form>
            </div>

            <div class="mb-4">
              <div class="fw-semibold mb-2">Main Image (Root)</div>
              <?php if($row['Root_img']!==''): ?>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <img class="img-thumb" src="<?php echo h(url($row['Root_img'])); ?>" alt="Root">
                  <form method="post" onsubmit="return confirm('Remove the main image?')" class="d-flex gap-2">
                    <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
                    <input type="hidden" name="action" value="delete_root_image">
                    <input type="hidden" name="pkg_id" value="<?php echo (int)$row['Package_ID']; ?>">
                    <button class="btn btn-outline-danger" type="submit">Remove Main Image</button>
                  </form>
                </div>
              <?php else: ?>
                <div class="muted">No main image set.</div>
              <?php endif; ?>
            </div>

            <hr class="my-3">

            <div class="fw-semibold mb-2">Gallery Images</div>
            <?php if(!$existingImgs): ?>
              <div class="muted">No gallery images yet.</div>
            <?php else: ?>
              <div class="row g-3">
                <?php foreach($existingImgs as $im): ?>
                  <div class="col-6 col-sm-4 col-md-3">
                    <div class="card h-100">
                      <img src="<?php echo h(url($im['ImageUrl'])); ?>" class="card-img-top" alt="<?php echo h($im['AltText']); ?>">
                      <div class="card-body p-2">
                        <form method="post" onsubmit="return confirm('Delete this image?')">
                          <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
                          <input type="hidden" name="action" value="delete_image">
                          <input type="hidden" name="image_id" value="<?php echo (int)$im['ImageID']; ?>">
                          <input type="hidden" name="pkg_id" value="<?php echo (int)$row['Package_ID']; ?>">
                          <button class="btn btn-sm btn-outline-danger w-100" type="submit">Delete</button>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

    <?php endif; ?>

    <?php elseif($mode==='delete' && $pkgId): 
        $row=fetch_package($mysqli,$pkgId);
        if(!$row){ echo '<div class="alert alert-danger">Package not found.</div>'; }
        else { ?>
        <div class="card">
          <div class="card-body">
            <div class="muted mb-2">You are about to delete the following package. This action cannot be undone.</div>
            <h5 class="fw-bold mb-1"><?php echo h($row['Name']); ?></h5>
            <div class="small muted mb-2">Subtitle: <?php echo h($row['Subtitle']); ?></div>
            <div class="small muted mb-2">Duration: <?php echo h($row['DurationDays']); ?> days</div>
            <div class="small muted mb-3">Price: Rs. <?php echo number_format((float)$row['Price'],2); ?></div>
            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
              <input type="hidden" name="action" value="delete_package">
              <input type="hidden" name="pkg_id" value="<?php echo (int)$row['Package_ID']; ?>">
              <a class="btn btn-outline-secondary" href="<?php echo h($_SERVER['PHP_SELF']); ?>">Cancel</a>
              <button class="btn btn-outline-danger" type="submit" onclick="return confirm('Delete this package? This will remove images & itinerary as well.')">Delete</button>
            </form>
          </div>
        </div>
    <?php } endif; ?>
  </div>
</main>

<script>
  function makeItineraryRow() {
    return `
      <div class="card">
        <div class="card-body">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-md-2">
              <label class="form-label">Day</label>
              <input type="number" class="form-control" name="day[]" min="1" value="">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Location</label>
              <input type="text" class="form-control" name="location[]" value="">
            </div>
            <div class="col-12 col-md-5">
              <label class="form-label">Description</label>
              <input type="text" class="form-control" name="it_desc[]" value="">
            </div>
            <div class="col-12 col-md-auto">
              <label class="form-label d-none d-md-block">&nbsp;</label>
              <button type="button" class="btn btn-outline-danger text-nowrap" onclick="this.closest('.card').remove()">Remove</button>
            </div>
          </div>
        </div>
      </div>`;
  }
  function addItRow(){
    const wrap = document.getElementById('itineraries');
    wrap.insertAdjacentHTML('beforeend', makeItineraryRow());
  }
  (()=>{'use strict';const forms=document.querySelectorAll('.needs-validation');Array.from(forms).forEach(form=>{form.addEventListener('submit',event=>{if(!form.checkValidity()){event.preventDefault();event.stopPropagation();}form.classList.add('was-validated');});});})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
