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

function val($x){return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');}

function fetch_available_guides($conn,$start,$end,$excludeBookingId=0){
    $list=[];
    $sql="SELECT g.Guide_ID, CONCAT(g.F_Name,' ',g.L_Name) AS name 
          FROM guide g
          LEFT JOIN bookings b2 ON b2.Guide_ID=g.Guide_ID 
            AND b2.Status NOT IN ('Cancelled','Completed')
            AND b2.Booking_ID<>?
            AND NOT (b2.End_Date_Time < ? OR b2.Start_Date_Time > ?)
          WHERE b2.Booking_ID IS NULL AND g.Status='Available'
          ORDER BY name ASC";
    $st=$conn->prepare($sql);
    $st->bind_param("iss",$excludeBookingId,$start,$end);
    $st->execute();
    $rs=$st->get_result();
    while($r=$rs->fetch_assoc()) $list[]=$r;
    return $list;
}

function fetch_available_drivers($conn,$start,$end,$excludeBookingId=0){
    $list=[];
    $sql="SELECT d.Driver_ID, CONCAT(d.F_Name,' ',d.L_Name) AS name
          FROM driver d
          LEFT JOIN bookings b2 ON b2.Driver_ID=d.Driver_ID 
            AND b2.Status NOT IN ('Cancelled','Completed')
            AND b2.Booking_ID<>?
            AND NOT (b2.End_Date_Time < ? OR b2.Start_Date_Time > ?)
          WHERE b2.Booking_ID IS NULL AND d.Status='Available'
          ORDER BY name ASC";
    $st=$conn->prepare($sql);
    $st->bind_param("iss",$excludeBookingId,$start,$end);
    $st->execute();
    $rs=$st->get_result();
    while($r=$rs->fetch_assoc()) $list[]=$r;
    return $list;
}

if ($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    if ($action==='toggle_payment'){
        $bid=(int)($_POST['Booking_ID']??0);
        if($bid){
            $st=$conn->prepare("SELECT Payment_Method, Payment_Status FROM bookings WHERE Booking_ID=?");
            $st->bind_param("i",$bid);
            $st->execute();
            $r=$st->get_result()->fetch_assoc();
            if($r && strcasecmp($r['Payment_Method']??'','Cash')===0){
                $new = (strcasecmp($r['Payment_Status']??'','Paid')===0) ? 'Unpaid' : 'Paid';
                $u=$conn->prepare("UPDATE bookings SET Payment_Status=? WHERE Booking_ID=?");
                $u->bind_param("si",$new,$bid);
                if($u->execute()) $success="Payment status updated."; else $errors[]="Update failed.";
            }
        }
    } elseif ($action==='assign_driver'){
        $bid=(int)($_POST['Booking_ID']??0);
        $driver=(int)($_POST['Driver_ID']??0);
        if($bid && $driver){
            $s=$conn->prepare("SELECT Start_Date_Time, End_Date_Time FROM bookings WHERE Booking_ID=?");
            $s->bind_param("i",$bid);
            $s->execute();
            $bk=$s->get_result()->fetch_assoc();
            if($bk){
                $chk=$conn->prepare("SELECT 1 FROM bookings WHERE Driver_ID=? AND Booking_ID<>? AND Status NOT IN ('Cancelled','Completed') AND NOT (End_Date_Time < ? OR Start_Date_Time > ?) LIMIT 1");
                $chk->bind_param("iiss",$driver,$bid,$bk['Start_Date_Time'],$bk['End_Date_Time']);
                $chk->execute();
                $conf=$chk->get_result()->fetch_row();
                if($conf){ $errors[]="Selected driver is not available."; }
                else{
                    $u=$conn->prepare("UPDATE bookings SET Driver_ID=? WHERE Booking_ID=?");
                    $u->bind_param("ii",$driver,$bid);
                    if($u->execute()) $success="Driver assigned."; else $errors[]="Update failed.";
                }
            }
        }
    } elseif ($action==='assign_guide'){
        $bid=(int)($_POST['Booking_ID']??0);
        $guide=(int)($_POST['Guide_ID']??0);
        if($bid && $guide){
            $s=$conn->prepare("SELECT Start_Date_Time, End_Date_Time FROM bookings WHERE Booking_ID=?");
            $s->bind_param("i",$bid);
            $s->execute();
            $bk=$s->get_result()->fetch_assoc();
            if($bk){
                $chk=$conn->prepare("SELECT 1 FROM bookings WHERE Guide_ID=? AND Booking_ID<>? AND Status NOT IN ('Cancelled','Completed') AND NOT (End_Date_Time < ? OR Start_Date_Time > ?) LIMIT 1");
                $chk->bind_param("iiss",$guide,$bid,$bk['Start_Date_Time'],$bk['End_Date_Time']);
                $chk->execute();
                $conf=$chk->get_result()->fetch_row();
                if($conf){ $errors[]="Selected guide is not available."; }
                else{
                    $u=$conn->prepare("UPDATE bookings SET Guide_ID=? WHERE Booking_ID=?");
                    $u->bind_param("ii",$guide,$bid);
                    if($u->execute()) $success="Guide assigned."; else $errors[]="Update failed.";
                }
            }
        }
    }
}

$tab = $_GET['tab'] ?? 'upcoming';
if(!in_array($tab,['upcoming','completed','cancelled'],true)) $tab='upcoming';

$q = trim($_GET['q'] ?? '');
$where = [];
$params = [];
$types = '';

if ($tab==='upcoming'){
    $where[]="b.Status IN ('Pending','Confirmed','In_Progress')";
} elseif ($tab==='completed'){
    $where[]="b.Status='Completed'";
} else {
    $where[]="b.Status='Cancelled'";
}

if ($q!==''){
    if (ctype_digit($q)){
        $where[]="(b.Booking_ID = ? OR u.Username LIKE ? OR u.Email LIKE ?)";
        $params[]=(int)$q; $types.='i';
        $like='%'.$q.'%';
        $params[]=$like; $types.='s';
        $params[]=$like; $types.='s';
    } else{
        $where[]="(u.Username LIKE ? OR u.Email LIKE ?)";
        $like='%'.$q.'%';
        $params[]=$like; $types.='s';
        $params[]=$like; $types.='s';
    }
}

$sql = "SELECT b.*,
        u.Username AS CustomerDisplay,
        u.Email AS CustomerEmail,
        u.Phone_No AS CustomerPhone,
        CONCAT(d.F_Name,' ',d.L_Name) AS DriverName,
        CONCAT(g.F_Name,' ',g.L_Name) AS GuideName
        FROM bookings b
        LEFT JOIN user u ON b.User_ID=u.User_ID
        LEFT JOIN driver d ON b.Driver_ID=d.Driver_ID
        LEFT JOIN guide g ON b.Guide_ID=g.Guide_ID
        ".(count($where)?'WHERE '.implode(' AND ',$where):'')."
        ORDER BY b.Start_Date_Time DESC";
$st = $conn->prepare($sql);
if($types!==''){
    $st->bind_param($types,...$params);
}
$st->execute();
$rs = $st->get_result();
$rows = [];
while($r=$rs->fetch_assoc()) $rows[]=$r;

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Bookings</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../Styles/ManageBookings.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

<main class="main">
  <div class="topbar">
    <h1>Manage Bookings</h1>
    <form class="search" method="get">
      <input type="hidden" name="tab" value="<?= val($tab) ?>">
      <input type="text" name="q" class="form-control" placeholder="Search by Booking ID or Customer Name" value="<?= val($q) ?>">
      <button class="btn primary">Search</button>
    </form>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success"><?= val($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
  <div class="alert alert-danger"><?= val(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="layout">
    <aside class="tabs-sidebar">
      <a class="tab-link <?= $tab==='upcoming'?'active':'' ?>" href="?tab=upcoming">Upcoming</a>
      <a class="tab-link <?= $tab==='completed'?'active':'' ?>" href="?tab=completed">Completed</a>
      <a class="tab-link <?= $tab==='cancelled'?'active':'' ?>" href="?tab=cancelled">Cancelled</a>
    </aside>

    <section class="content-area">
      <div class="card shadow-sm p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Dates</th>
                <th>Route</th>
                <th>People</th>
                <th>Driver</th>
                <th>Guide</th>
                <th>Status</th>
                <th>Payment</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if(!$rows): ?>
              <tr><td colspan="10" class="text-center py-5 text-muted">No bookings found</td></tr>
            <?php else: ?>
            <?php foreach($rows as $row): 
                $bid=(int)$row['Booking_ID'];
                $start=$row['Start_Date_Time'];
                $end=$row['End_Date_Time'];
                $availDrivers = fetch_available_drivers($conn,$start,$end,$bid);
                $availGuides  = fetch_available_guides($conn,$start,$end,$bid);
                $cash = (strcasecmp($row['Payment_Method']??'','Cash')===0);
                $isPaid = (strcasecmp($row['Payment_Status']??'','Paid')===0);
            ?>
              <tr>
                <td>#<?= $bid ?></td>
                <td>
                  <div class="fw-semibold"><?= val($row['CustomerDisplay'] ?? '') ?></div>
                  <div class="text-muted small"><?= val($row['CustomerEmail'] ?? '') ?> <?= !empty($row['CustomerPhone'])?'• '.val($row['CustomerPhone']):'' ?></div>
                </td>
                <td>
                  <div><?= $row['Start_Date_Time']?val(date('Y-m-d H:i', strtotime($row['Start_Date_Time']))):'' ?> →</div>
                  <div><?= $row['End_Date_Time']?val(date('Y-m-d H:i', strtotime($row['End_Date_Time']))):'' ?></div>
                </td>
                <td>
                  <div class="small"><?= val($row['Pickup_Location'] ?? '') ?></div>
                  <div class="small text-muted">to <?= val($row['End_Location'] ?? '') ?></div>
                </td>
                <td><?= (int)($row['Number_of_People'] ?? 0) ?></td>
                <td>
                  <div class="small"><?= !empty($row['DriverName'])?val($row['DriverName']):'<span class="text-muted">Not assigned</span>' ?></div>
                </td>
                <td>
                  <div class="small"><?= !empty($row['GuideName'])?val($row['GuideName']):'<span class="text-muted">Not assigned</span>' ?></div>
                </td>
                <td><span class="badge-soft"><?= val($row['Status']) ?></span></td>
                <td>
                  <div class="small"><?= val($row['Payment_Method'] ?? '') ?></div>
                  <div class="small <?= $isPaid?'text-success':'text-danger' ?>"><?= val($row['Payment_Status'] ?? '') ?></div>
                </td>
                <td class="text-end">
                  <?php if($cash): ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="toggle_payment">
                    <input type="hidden" name="Booking_ID" value="<?= $bid ?>">
                    <button class="btn btn-outline-secondary btn-sm"><?= $isPaid?'Mark Unpaid':'Mark Paid' ?></button>
                  </form>
                  <?php endif; ?>
                  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignDriver<?= $bid ?>">Assign Driver</button>
                  <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignGuide<?= $bid ?>">Assign Guide</button>
                </td>
              </tr>

              <div class="modal fade" id="assignDriver<?= $bid ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="post">
                      <div class="modal-header">
                        <h5 class="modal-title">Assign Driver</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="action" value="assign_driver">
                        <input type="hidden" name="Booking_ID" value="<?= $bid ?>">
                        <select name="Driver_ID" class="form-select" required>
                          <option value="">Select driver</option>
                          <?php foreach($availDrivers as $d): ?>
                          <option value="<?= (int)$d['Driver_ID'] ?>"><?= val($d['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <?php if(!$availDrivers): ?>
                        <div class="text-muted small mt-2">No available drivers for this time range.</div>
                        <?php endif; ?>
                      </div>
                      <div class="modal-footer">
                        <button class="btn btn-primary">Save</button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              <div class="modal fade" id="assignGuide<?= $bid ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="post">
                      <div class="modal-header">
                        <h5 class="modal-title">Assign Guide</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="action" value="assign_guide">
                        <input type="hidden" name="Booking_ID" value="<?= $bid ?>">
                        <select name="Guide_ID" class="form-select" required>
                          <option value="">Select guide</option>
                          <?php foreach($availGuides as $g): ?>
                          <option value="<?= (int)$g['Guide_ID'] ?>"><?= val($g['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <?php if(!$availGuides): ?>
                        <div class="text-muted small mt-2">No available guides for this time range.</div>
                        <?php endif; ?>
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
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
                            