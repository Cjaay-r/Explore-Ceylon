<?php
ob_start();
session_start();
require_once __DIR__ . '/Includes/config.php';
require_once __DIR__ . '/Includes/dbconnect.php';
require_once __DIR__ . '/Includes/auth.php';

if (!function_exists('isLoggedIn') ? !isset($_SESSION['User_ID']) : !isLoggedIn()) {
  header('Location: ' . (function_exists('url') ? url('login.php') : 'login.php'));
  exit;
}

$uid = (int)$_SESSION['User_ID'];
$redirectUrl = function_exists('url') ? url('packages.php') : 'packages.php';

$packageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$package = null; $itinerary = [];

if ($packageId > 0) {
  $ps = $conn->prepare("SELECT Package_ID, Name, Subtitle, Long_Des, DurationDays, Price, Root_img FROM packages WHERE Package_ID=?");
  $ps->bind_param("i", $packageId);
  $ps->execute();
  $package = $ps->get_result()->fetch_assoc();
  $ps->close();

  $its = $conn->prepare("SELECT DayNumber, Location, Description FROM itinerary WHERE PackageID=? ORDER BY DayNumber ASC");
  $its->bind_param("i", $packageId);
  $its->execute();
  $ir = $its->get_result();
  while ($row = $ir->fetch_assoc()) $itinerary[] = $row;
  $its->close();
}

function vehicle_caps() {
  return ["Bike"=>1,"Tuk-Tuk"=>2,"Mini-Car"=>3,"Car"=>4,"Van"=>7,"Bus"=>30];
}
function allowed_cats($heads) {
  $out=[]; foreach (vehicle_caps() as $k=>$v) if ($v >= $heads) $out[$k]=$v; return $out;
}
function have_overlap($conn, $col, $id, $start, $end) {
  $q = $conn->prepare("SELECT 1 FROM bookings WHERE $col=? AND Status NOT IN ('Cancelled','Completed') AND NOT (End_Date_Time < ? OR Start_Date_Time > ?) LIMIT 1");
  $q->bind_param("iss", $id, $start, $end);
  $q->execute(); $r=$q->get_result(); $q->close();
  return $r && $r->num_rows > 0;
}
function pick_driver($conn, $vehicleCat, $start, $end) {
  $sql = "SELECT d.Driver_ID AS did
          FROM driver d
          WHERE d.Status='Available'
            AND REPLACE(REPLACE(d.Vehicle_Category,'-',' '),'_',' ') = REPLACE(REPLACE(?,'-',' '),'_',' ')
          ORDER BY d.Driver_ID ASC";
  $ds = $conn->prepare($sql);
  if ($ds === false) return 0;
  $ds->bind_param("s", $vehicleCat);
  $ds->execute();
  $res = $ds->get_result();
  while ($dr = $res->fetch_assoc()) {
    $did = (int)$dr['did'];
    if (!have_overlap($conn, "Driver_ID", $did, $start, $end)) {
      $ds->close();
      return $did;
    }
  }
  $ds->close();
  return 0;
}

if (isset($_GET['action']) && $_GET['action']==='guides') {
  header('Content-Type: application/json');
  $pid = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
  $start = $_GET['start'] ?? '';
  $ps = $conn->prepare("SELECT DurationDays FROM packages WHERE Package_ID=?");
  $ps->bind_param("i", $pid);
  $ps->execute();
  $pkg = $ps->get_result()->fetch_assoc();
  $ps->close();
  if (!$pkg) { ob_clean(); echo json_encode(["ok"=>false,"html"=>""]); exit; }
  $sd = DateTime::createFromFormat('Y-m-d', $start);
  if (!$sd) { ob_clean(); echo json_encode(["ok"=>true,"html"=>""]); exit; }
  $ed = clone $sd; $ed->modify(((int)$pkg['DurationDays'] - 1) . " days"); $end = $ed->format('Y-m-d');

  $html = "";
  $gs = $conn->prepare("
    SELECT g.Guide_ID, g.F_Name, g.L_Name, g.Rating, u.User_Profile
    FROM guide g
    LEFT JOIN user u ON u.User_ID = g.User_ID
    WHERE g.Status='Available'
      AND NOT EXISTS (
        SELECT 1 FROM bookings b
        WHERE b.Guide_ID = g.Guide_ID
          AND b.Status NOT IN ('Cancelled','Completed')
          AND NOT (b.End_Date_Time < ? OR b.Start_Date_Time > ?)
      )
    ORDER BY CAST(NULLIF(g.Rating,'') AS DECIMAL(10,2)) DESC, g.Guide_ID ASC
  ");
  $gs->bind_param("ss", $start, $end);
  $gs->execute();
  $res = $gs->get_result();
  while ($g = $res->fetch_assoc()) {
    $full = trim(($g['F_Name']??"")." ".($g['L_Name']??""));
    $imgRaw = $g['User_Profile'] ?: 'Images/defaultuser.jpg';
    $img = htmlspecialchars(function_exists('url') ? url($imgRaw) : $imgRaw);
    $name = htmlspecialchars($full ?: ("Guide #".$g['Guide_ID']));
    $rate = htmlspecialchars((string)($g['Rating'] ?? '0'));
    $id = (int)$g['Guide_ID'];
    $html .= '<label class="guide-card"><input type="radio" name="Guide_ID" value="'.$id.'"><div class="gc-body"><img src="'.$img.'" alt="Guide" class="gc-avatar"><div class="gc-meta"><div class="gc-name">'.$name.'</div><div class="gc-rating">⭐ '.$rate.'</div></div></div></label>';
  }
  $gs->close();
  ob_clean(); echo json_encode(["ok"=>true,"html"=>$html]); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest';

  $packageId = (int)($_POST['Package_ID'] ?? 0);
  $f = trim($_POST['F_Name'] ?? "");
  $l = trim($_POST['L_Name'] ?? "");
  $email = trim($_POST['Email'] ?? "");
  $phone = trim($_POST['Phone_No'] ?? "");
  $nic = trim($_POST['NIC_or_Paasport'] ?? "");
  $pickup = trim($_POST['Pickup_Location'] ?? "");
  $drop = trim($_POST['End_Location'] ?? "");
  $startDate = $_POST['Start_Date_Time'] ?? "";
  $people = (int)($_POST['Number_of_People'] ?? 1);
  $chosenGuideId = (int)($_POST['Guide_ID'] ?? 0);
  $chosenVehicle = trim($_POST['Vehicle_Category'] ?? "");
  $payMethod = ($_POST['Payment_Method'] ?? 'Cash') === 'Online' ? 'Online' : 'Cash';

  $resp = ["ok"=>false,"msg"=>"","redirect"=>$redirectUrl];

  $ps = $conn->prepare("SELECT DurationDays, Price FROM packages WHERE Package_ID=?");
  $ps->bind_param("i", $packageId);
  $ps->execute();
  $pkg = $ps->get_result()->fetch_assoc();
  $ps->close();
  if (!$pkg) { $resp["msg"]="Invalid package."; if($isAjax){ header('Content-Type:application/json'); ob_clean(); echo json_encode($resp); exit; } }

  $duration = (int)$pkg['DurationDays'];
  $price = (float)$pkg['Price'];
  $sd = DateTime::createFromFormat('Y-m-d', $startDate);
  if (!$sd) { $resp["msg"]="Invalid start date."; if($isAjax){ header('Content-Type:application/json'); ob_clean(); echo json_encode($resp); exit; } }

  $ed = clone $sd; $ed->modify(($duration - 1) . " days"); $endDate = $ed->format('Y-m-d');
  $totalHeads = max(1,$people) + 1;
  $allowed = allowed_cats($totalHeads);
  if (!isset($allowed[$chosenVehicle])) { $resp["msg"]="Selected vehicle not suitable for group size."; if($isAjax){ header('Content-Type:application/json'); ob_clean(); echo json_encode($resp); exit; } }

  if ($chosenGuideId<=0) { $resp["msg"]="Please select a guide."; if($isAjax){ header('Content-Type:application/json'); ob_clean(); echo json_encode($resp); exit; } }
  if (have_overlap($conn,"Guide_ID",$chosenGuideId,$startDate,$endDate)) { $resp["msg"]="Guide is busy for these dates."; if($isAjax){ header('Content-Type:application/json'); ob_clean(); echo json_encode($resp); exit; } }

  $driverId = pick_driver($conn, $chosenVehicle, $startDate, $endDate);
  if ($driverId===0) { $resp["msg"]="No available driver for the selected vehicle."; if($isAjax){ header('Content-Type:application/json'); ob_clean(); echo json_encode($resp); exit; } }
  if (have_overlap($conn,"Driver_ID",$driverId,$startDate,$endDate)) { $resp["msg"]="Driver is busy for these dates. Please change dates or vehicle."; if($isAjax){ header('Content-Type:application/json'); ob_clean(); echo json_encode($resp); exit; } }

  // EXACTLY 19 placeholders to match 19 variables bound below.
  $bp = $conn->prepare("
    INSERT INTO bookings
      (F_Name, L_Name, Email, Phone_No, NIC_or_Paasport, Start_Date_Time, End_Date_Time, Pickup_Location, End_Location,
       Number_of_People, Booking_Type, Guide_Preferences, Status, Completed_At, Price, Payment_Method, Payment_Status,
       Driver_earning, Guide_earning, User_ID, Driver_ID, Guide_ID, Package_ID)
    VALUES
      (?,?,?,?,?,?,?,?,?,?,'Package',?,'Pending',?, ?, ?, ?, 0, 0, ?, ?, ?, ?)
  ");

  if ($bp === false) {
    $resp["msg"] = "Failed to prepare booking statement.";
    if ($isAjax) { header('Content-Type: application/json'); ob_clean(); echo json_encode($resp); exit; }
    header("Location: ".$redirectUrl); exit;
  }

  $guidePref = 1;
  $completedAt = '0000-00-00 00:00:00';
  $priceVal = (int)round($price);
  $payStatus = 'Unpaid';

  // 19 vars => types: 9s, i, i, s, i, s, s, i, i, i, i
  $types = "sssssssssiisissiiii";
  $bp->bind_param(
    $types,
    $f,$l,$email,$phone,$nic,$startDate,$endDate,$pickup,$drop,$people,
    $guidePref,$completedAt,$priceVal,$payMethod,$payStatus,
    $uid,$driverId,$chosenGuideId,$packageId
  );

  if ($bp->execute()) {
    $newId = $bp->insert_id;
    $resp["ok"]=true;
    $resp["msg"]="Booking confirmed. Reference #".$newId.".";
    $resp["reference"]=$newId;
  } else {
    $resp["msg"]="Failed to save booking.";
  }
  $bp->close();

  if ($isAjax) { header('Content-Type: application/json'); ob_clean(); echo json_encode($resp); exit; }

  header("Location: ".$redirectUrl);
  exit;
}

$guides = [];
$gr = $conn->query("SELECT g.Guide_ID, g.F_Name, g.L_Name, g.Rating, u.User_Profile FROM guide g LEFT JOIN user u ON u.User_ID=g.User_ID WHERE g.Status='Available' ORDER BY CAST(NULLIF(g.Rating,'') AS DECIMAL(10,2)) DESC, g.Guide_ID ASC");
if ($gr) { while ($g = $gr->fetch_assoc()) $guides[] = $g; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Book Package</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="Styles/BookinPpackage.css">
</head>
<body>
  <div class="container py-4">
    <?php if (!$package): ?>
      <div class="alert alert-warning">Package not found.</div>
    <?php else: ?>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-body">
            <h3 class="mb-1"><?= htmlspecialchars($package['Name']) ?></h3>
            <div class="text-muted mb-2"><?= htmlspecialchars($package['Subtitle']) ?></div>
            <div class="pkg-meta d-flex flex-wrap gap-3 mb-3">
              <span class="badge bg-primary">Duration: <?= (int)$package['DurationDays'] ?> Days</span>
              <span class="badge bg-success">Price: $<?= number_format((float)$package['Price'], 2) ?></span>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-12">
                <img class="w-100 rounded object-cover" style="height:220px" src="<?= htmlspecialchars(function_exists('url') ? url($package['Root_img']) : $package['Root_img']) ?>" alt="Package">
              </div>
            </div>
            <h5 class="mb-2">Itinerary</h5>
            <div class="accordion" id="itineraryAcc">
              <?php foreach ($itinerary as $idx => $it): ?>
                <div class="accordion-item">
                  <h2 class="accordion-header" id="hd<?= $idx ?>">
                    <button class="accordion-button <?= $idx>0?'collapsed':'' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#cl<?= $idx ?>">
                      Day <?= (int)$it['DayNumber'] ?> — <?= htmlspecialchars($it['Location']) ?>
                    </button>
                  </h2>
                  <div id="cl<?= $idx ?>" class="accordion-collapse collapse <?= $idx===0?'show':'' ?>" data-bs-parent="#itineraryAcc">
                    <div class="accordion-body">
                      <?= nl2br(htmlspecialchars($it['Description'])) ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (count($itinerary)===0): ?>
                <div class="text-muted">No itinerary details available.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-body">
            <h4 class="mb-3">Booking Details</h4>
            <form id="bookForm" method="post" class="row g-3" novalidate>
              <input type="hidden" name="Package_ID" value="<?= (int)$package['Package_ID'] ?>">
              <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" name="F_Name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" name="L_Name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="Email" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input type="text" name="Phone_No" class="form-control" required>
              </div>
              <div class="col-12">
                <label class="form-label">NIC / Passport</label>
                <input type="text" name="NIC_or_Paasport" class="form-control" required>
              </div>
              <div class="col-12">
                <label class="form-label">Pickup Location</label>
                <input type="text" name="Pickup_Location" class="form-control gmaps-place" required>
              </div>
              <div class="col-12">
                <label class="form-label">End Location</label>
                <input type="text" name="End_Location" class="form-control gmaps-place" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Starting Date</label>
                <input type="date" id="startDate" name="Start_Date_Time" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">End Date</label>
                <input type="date" id="endDate" class="form-control" readonly>
              </div>

              <div class="col-md-6">
                <label class="form-label">Number of People</label>
                <input type="number" min="1" value="1" id="people" name="Number_of_People" class="form-control" required>
              </div>

              <div class="col-12">
                <label class="form-label">Vehicle Category</label>
                <select id="vehicleCategory" name="Vehicle_Category" class="form-select" required></select>
                <div class="small text-muted mt-1">Options show all categories that can seat your group or larger.</div>
              </div>

              <div class="col-12">
                <label class="form-label">Choose a Guide</label>
                <div class="guide-list" id="guideList">
                  <?php if (count($guides)===0): ?>
                    <div class="text-muted">No guides available.</div>
                  <?php else: ?>
                    <?php foreach ($guides as $g): $full = trim(($g['F_Name']??"")." ".($g['L_Name']??"")); $imgRaw = $g['User_Profile'] ?: 'Images/defaultuser.jpg'; ?>
                      <label class="guide-card">
                        <input type="radio" name="Guide_ID" value="<?= (int)$g['Guide_ID'] ?>">
                        <div class="gc-body">
                          <img src="<?= htmlspecialchars(function_exists('url') ? url($imgRaw) : $imgRaw) ?>" alt="Guide" class="gc-avatar">
                          <div class="gc-meta">
                            <div class="gc-name"><?= htmlspecialchars($full ?: "Guide #".$g['Guide_ID']) ?></div>
                            <div class="gc-rating">⭐ <?= htmlspecialchars((string)($g['Rating'] ?? '0')) ?></div>
                          </div>
                        </div>
                      </label>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
                <div class="small text-muted mt-1">Guides filter after you pick a start date.</div>
              </div>

              <div class="col-md-6">
                <label class="form-label">Payment Method</label>
                <select name="Payment_Method" class="form-select" required>
                  <option value="Cash">Cash</option>
                  <option value="Online">Online</option>
                </select>
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-primary w-100">Confirm Booking</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title">Booking Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p id="confirmText" class="mb-0"></p>
          <pre id="debugText" class="small text-muted mt-2" style="white-space:pre-wrap;display:none;"></pre>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-primary" id="goBtn">OK</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const durationDays = <?= $package ? (int)$package['DurationDays'] : 0 ?>;
    const startDateEl = document.getElementById('startDate');
    const endDateEl = document.getElementById('endDate');
    const peopleEl = document.getElementById('people');
    const vehicleEl = document.getElementById('vehicleCategory');
    const formEl = document.getElementById('bookForm');
    const guideList = document.getElementById('guideList');
    const caps = {"Bike":1,"Tuk-Tuk":2,"Mini-Car":3,"Car":4,"Van":7,"Bus":30};
    const redirectUrl = "<?= htmlspecialchars($redirectUrl) ?>";
    const pkgId = <?= (int)$packageId ?>;

    function computeEndDate() {
      if (!startDateEl.value || durationDays <= 0) { endDateEl.value = ""; return; }
      const d = new Date(startDateEl.value);
      d.setDate(d.getDate() + (durationDays - 1));
      const iso = d.toISOString().slice(0,10);
      endDateEl.value = iso;
    }
    function buildVehicleOptions() {
      const p = Math.max(1, parseInt(peopleEl.value || "1", 10));
      const total = p + 1;
      vehicleEl.innerHTML = "";
      const entries = Object.entries(caps).filter(([k,v]) => v >= total).sort((a,b)=>a[1]-b[1]);
      entries.forEach(([k,v])=>{
        const opt = document.createElement('option');
        opt.value = k; opt.textContent = k + " (up to " + v + ")";
        vehicleEl.appendChild(opt);
      });
    }
    async function refreshGuides() {
      if (!startDateEl.value) return;
      const url = new URL(location.href);
      url.searchParams.set('action','guides');
      url.searchParams.set('package_id', String(pkgId));
      url.searchParams.set('start', startDateEl.value);
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With':'XMLHttpRequest' } });
      let data = {};
      try { data = await res.json(); } catch(e){}
      if (data && data.ok !== false && typeof data.html === 'string') {
        guideList.innerHTML = data.html || '<div class="text-muted">No guides available.</div>';
      }
    }

    startDateEl && startDateEl.addEventListener('change', ()=>{ computeEndDate(); refreshGuides(); });
    peopleEl && peopleEl.addEventListener('input', buildVehicleOptions);
    computeEndDate(); buildVehicleOptions();

    formEl && formEl.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(formEl);
      let text = '';
      try {
        const res = await fetch(location.href, { method:'POST', body: fd, headers:{'X-Requested-With':'XMLHttpRequest'} });
        text = await res.text();
      } catch(err) {
        text = '';
      }
      let data;
      try { data = JSON.parse(text); } catch(e) { data = { ok:false, msg:'Unexpected response', _raw:text }; }
      const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
      const dbg = document.getElementById('debugText');
      if (data.ok) {
        document.getElementById('confirmText').textContent = data.msg;
        dbg.style.display = 'none';
        document.getElementById('goBtn').onclick = ()=>{ window.location.href = data.redirect || redirectUrl; };
        modal.show();
        setTimeout(()=>{ window.location.href = data.redirect || redirectUrl; }, 2000);
      } else {
        document.getElementById('confirmText').textContent = data.msg || "Something went wrong.";
        if (data._raw) { dbg.textContent = String(data._raw); dbg.style.display = 'block'; } else { dbg.style.display = 'none'; }
        document.getElementById('goBtn').onclick = ()=>{ modal.hide(); };
        modal.show();
      }
    });

    let mapApiLoaded = false;
    function attachAutocomplete(input){
      if (!input) return;
      if (!mapApiLoaded) return;
      new google.maps.places.Autocomplete(input, {
        fields: ["formatted_address","geometry","name"],
        componentRestrictions: { country: ["lk"] }
      });
    }
    function initPlaces(){
      mapApiLoaded = true;
      document.querySelectorAll('.gmaps-place').forEach(attachAutocomplete);
    }
    window.initPlaces = initPlaces;

    (function(){
      const s=document.createElement('script');
      s.src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCYCblZmBwFlc_NfpJoS5bMWP87Pm3wM9w&libraries=places&callback=initPlaces";
      s.defer=true; s.async=true; document.head.appendChild(s);
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
