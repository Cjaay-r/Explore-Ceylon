<?php
session_start();
$conn = new mysqli("localhost", "root", "", "explore_ceylon_db");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
if (!isset($_SESSION['User_ID'])) { header("Location: login.php"); exit; }

$uid = (int)$_SESSION['User_ID'];
$redirectUrl = "packages.php";

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

function pick_driver($conn, $vehicleCat, $heads, $start, $end) {
  $sql = "SELECT d.Driver_ID AS did, d.Fixed_Price AS fp, d.PricePer_Km AS ppk
          FROM driver d
          WHERE d.Status='Available'
            AND REPLACE(REPLACE(d.Vehicle_Category,'-',' '),'_',' ') = REPLACE(REPLACE(?,'-',' '),'_',' ')
          ORDER BY d.Driver_ID ASC";
  $ds = $conn->prepare($sql);
  if ($ds === false) return [0, 0.0, 0.0];
  $ds->bind_param("s", $vehicleCat);
  $ds->execute();
  $res = $ds->get_result();
  while ($dr = $res->fetch_assoc()) {
    $did = (int)$dr['did'];
    if (!have_overlap($conn, "Driver_ID", $did, $start, $end)) {
      $fp = (float)$dr['fp'];
      $ppk = (float)$dr['ppk'];
      $ds->close();
      return [$did, $fp, $ppk];
    }
  }
  $ds->close();
  return [0, 0.0, 0.0];
}

function guide_price_per_day($conn, $guideId) {
  if ($guideId <= 0) return 0.0;
  $g = $conn->prepare("SELECT COALESCE(Price_per_Day, 3000.00) AS ppd FROM guide WHERE Guide_ID=? AND Status='Available' LIMIT 1");
  $g->bind_param("i", $guideId);
  $g->execute();
  $res = $g->get_result();
  $ppd = 0.0;
  if ($row = $res->fetch_assoc()) $ppd = (float)$row['ppd'];
  $g->close();
  return $ppd;
}

if (isset($_GET['action']) && $_GET['action']==='guides') {
  header('Content-Type: application/json');
  $start = $_GET['start'] ?? '';
  $end = $_GET['end'] ?? '';
  if (!$start || !$end) { echo json_encode(["ok"=>true,"html"=>""]); exit; }
  $html = "";
  $gs = $conn->prepare("
    SELECT g.Guide_ID, g.F_Name, g.L_Name, g.Rating, u.User_Profile, g.Price_per_Day
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
    $img = htmlspecialchars($g['User_Profile'] ?: 'Images/defaultuser.jpg');
    $name = htmlspecialchars($full ?: ("Guide #".$g['Guide_ID']));
    $rate = htmlspecialchars((string)($g['Rating'] ?? '0'));
    $id = (int)$g['Guide_ID'];
    $ppd = number_format((float)($g['Price_per_Day'] ?? 3000.0),2);
    $html .= '<label class="guide-card"><input type="radio" name="Guide_ID" value="'.$id.'"><div class="gc-body"><img src="'.$img.'" alt="Guide" class="gc-avatar"><div class="gc-meta"><div class="gc-name">'.$name.'</div><div class="gc-rating">⭐ '.$rate.' · LKR '.$ppd.'/day</div></div></div></label>';
  }
  $gs->close();
  echo json_encode(["ok"=>true,"html"=>$html]);
  exit;
}

if (isset($_GET['action']) && $_GET['action']==='quote') {
  header('Content-Type: application/json');
  $start = $_GET['start'] ?? '';
  $days = (int)($_GET['days'] ?? 1);
  $people = max(1,(int)($_GET['people'] ?? 1));
  $vehicle = $_GET['vehicle'] ?? '';
  $km = (float)($_GET['km'] ?? 0);
  $guide = (int)($_GET['guide'] ?? 0) === 1;
  $guideIdQ = (int)($_GET['guide_id'] ?? 0);
  $sd = DateTime::createFromFormat('Y-m-d', $start);
  if (!$sd || !$vehicle) { echo json_encode(["ok"=>false]); exit; }
  $ed = clone $sd; $ed->modify(($days-1)." days"); $end = $ed->format('Y-m-d');
  $heads = $people + 1;
  $allowed = allowed_cats($heads);
  if (!isset($allowed[$vehicle])) { echo json_encode(["ok"=>false]); exit; }
  [$driverId,$fp,$ppk] = pick_driver($conn, $vehicle, $heads, $start, $end);
  if ($driverId===0) { echo json_encode(["ok"=>false]); exit; }
  $driverCost = ($fp*$days) + ($ppk*$km);
  $gppd = $guide ? ($guideIdQ>0 ? guide_price_per_day($conn,$guideIdQ) : 3000.0) : 0.0;
  $guideCost = $guide ? ($gppd*$days) : 0.0;
  $subtotal = $driverCost + $guideCost;
  $total = $subtotal * 1.05;
  echo json_encode(["ok"=>true,"driver_id"=>$driverId,"driver_cost"=>$driverCost,"guide_cost"=>$guideCost,"total"=>$total]);
  exit;
}

$final = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__step'] ?? '')==='submit') {
  $f = trim($_POST['F_Name'] ?? "");
  $l = trim($_POST['L_Name'] ?? "");
  $email = trim($_POST['Email'] ?? "");
  $phone = trim($_POST['Phone_No'] ?? "");
  $nic = trim($_POST['NIC_or_Paasport'] ?? "");
  $pickup = trim($_POST['Pickup_Location'] ?? "");
  $drop = trim($_POST['End_Location'] ?? "");
  $startDate = $_POST['Start_Date'] ?? "";
  $days = max(1, (int)$_POST['Duration_Days']);
  $people = max(1, (int)$_POST['Number_of_People']);
  $wantGuide = (int)($_POST['Want_Guide'] ?? 0) === 1;
  $chosenGuideId = (int)($_POST['Guide_ID'] ?? 0);
  $vehicleCat = trim($_POST['Vehicle_Category'] ?? "");
  $totalKm = (float)($_POST['Total_KM'] ?? 0);
  $itineraryJson = trim($_POST['Itinerary_JSON'] ?? "[]");
  $payMethod = ($_POST['Payment_Method'] ?? 'Cash') === 'Online' ? 'Online' : 'Cash';

  $sd = DateTime::createFromFormat('Y-m-d', $startDate);
  if (!$sd) { $final = ["ok"=>false,"msg"=>"Invalid start date."]; }
  if (!$final) {
    $ed = clone $sd; $ed->modify(($days - 1) . " days"); $endDate = $ed->format('Y-m-d');
    $heads = $people + 1;
    $allowed = allowed_cats($heads);
    if (!isset($allowed[$vehicleCat])) { $final = ["ok"=>false,"msg"=>"Selected vehicle not suitable for group size."]; }
  }
  if (!$final) {
    if ($wantGuide && $chosenGuideId<=0) { $final = ["ok"=>false,"msg"=>"Select a guide or choose No Guide."]; }
  }
  if (!$final && $wantGuide && have_overlap($conn,"Guide_ID",$chosenGuideId,$startDate,$endDate)) {
    $final = ["ok"=>false,"msg"=>"Guide is busy for these dates."]; 
  }
  if (!$final) {
    [$driverId,$fp,$ppk] = pick_driver($conn, $vehicleCat, $heads, $startDate, $endDate);
    if ($driverId===0) { $final = ["ok"=>false,"msg"=>"No available driver for the selected vehicle."]; }
  }
  if (!$final && have_overlap($conn,"Driver_ID",$driverId,$startDate,$endDate)) {
    $final = ["ok"=>false,"msg"=>"Driver is busy for these dates. Please change dates or vehicle."];
  }
  if (!$final) {
    $gppd = $wantGuide ? guide_price_per_day($conn,$chosenGuideId) : 0.0;
    $guideCost = $wantGuide ? ($gppd * $days) : 0.0;
    $driverCost = ($fp * $days) + ($ppk * $totalKm);
    $subtotal = $guideCost + $driverCost;
    $totalPrice = $subtotal * 1.05;

    $gPref = $wantGuide ? 1 : 0;
    $gId = $wantGuide ? $chosenGuideId : null;
    $pkgId = null;

    $bp = $conn->prepare("INSERT INTO bookings (F_Name, L_Name, Email, Phone_No, NIC_or_Paasport, Start_Date_Time, End_Date_Time, Pickup_Location, End_Location, Number_of_People, Booking_Type, Guide_Preferences, Status, Progress, Price, Payment_Method, Payment_Status, Driver_earning, Guide_earning, User_ID, Driver_ID, Guide_ID, Package_ID) VALUES (?,?,?,?,?,?,?,?,?,?,'customize',?,'Pending','',?,?,?,?,?,?,?,?,?)");
    if ($bp === false) {
      $final = ["ok"=>false,"msg"=>"Failed to prepare booking statement."]; 
    } else {
      $priceInt = (int)round($totalPrice);
      $driverEarn = (int)round($driverCost);
      $guideEarn = (int)round($guideCost);
      $paymentStatus = 'Unpaid';

      $pFName = $f;
      $pLName = $l;
      $pEmail = $email;
      $pPhone = $phone;
      $pNIC = $nic;
      $pStart = $startDate;
      $pEnd = $endDate;
      $pPickup = $pickup;
      $pDrop = $drop;
      $pPeople = (int)$people;
      $pGuidePref = (int)$gPref;
      $pPrice = (int)$priceInt;
      $pPayMethod = $payMethod;
      $pPayStatus = $paymentStatus;
      $pDriverEarn = (int)$driverEarn;
      $pGuideEarn = (int)$guideEarn;
      $pUserId = (int)$uid;
      $pDriverId = (int)$driverId;
      $pGuideId = $gId !== null ? (int)$gId : null;
      $pPackageId = $pkgId !== null ? (int)$pkgId : null;

      $types = "sssssssssiiissiiiiii";
      $bp->bind_param(
        $types,
        $pFName, $pLName, $pEmail, $pPhone, $pNIC, $pStart, $pEnd, $pPickup, $pDrop,
        $pPeople,
        $pGuidePref,
        $pPrice, $pPayMethod, $pPayStatus,
        $pDriverEarn, $pGuideEarn,
        $pUserId, $pDriverId, $pGuideId, $pPackageId
      );

      if ($bp->execute()) {
        $newId = $bp->insert_id;

        $itArr = json_decode($itineraryJson, true);
        if (is_array($itArr)) {
          $ins = $conn->prepare("INSERT INTO booking_destinations (Booking_ID, Day_Date, Destination) VALUES (?,?,?)");
          if ($ins) {
            foreach ($itArr as $idx => $day) {
              $dDate = isset($day['date']) ? $day['date'] : null;
              if (!$dDate) continue;
              $dests = isset($day['destinations']) && is_array($day['destinations']) ? $day['destinations'] : [];
              foreach ($dests as $dest) {
                $destName = trim((string)$dest);
                if ($destName==='') continue;
                $ins->bind_param("iss", $newId, $dDate, $destName);
                $ins->execute();
              }
            }
            $ins->close();
          }
        }

        $itRows = [];
        $sel = $conn->prepare("SELECT Day_Date, Destination FROM booking_destinations WHERE Booking_ID=? ORDER BY Day_Date, Destination");
        if ($sel) {
          $sel->bind_param("i", $newId);
          $sel->execute();
          $rs = $sel->get_result();
          while ($r = $rs->fetch_assoc()) {
            $itRows[] = $r;
          }
          $sel->close();
        }

        $final = [
          "ok"=>true,
          "ref"=>$newId,
          "f"=>$f,"l"=>$l,"email"=>$email,"phone"=>$phone,"nic"=>$nic,
          "pickup"=>$pickup,"drop"=>$drop,"start"=>$startDate,"end"=>$endDate,
          "people"=>$people,"days"=>$days,"vehicle"=>$vehicleCat,
          "guide"=>$wantGuide, "guideId"=>$gId,
          "totalKm"=>$totalKm, "driverCost"=>0.0, "guideCost"=>0.0, "totalPrice"=>$priceInt,
          "itinerary"=>$itineraryJson,
          "itinerary_rows"=>$itRows
        ];
      } else {
        $final = ["ok"=>false,"msg"=>"Failed to save booking."]; 
      }
      $bp->close();
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customizable Booking</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="Styles/CustomisedBookings.css">
</head>
<body>
<div class="container py-4">
<?php if ($final && $final["ok"]): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-3">Booking Submitted</h4>
      <div class="mb-2">Reference #<?= (int)$final["ref"] ?></div>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="summary-box">
            <div><span>Name</span><strong><?= htmlspecialchars($final["f"]." ".$final["l"]) ?></strong></div>
            <div><span>Email</span><strong><?= htmlspecialchars($final["email"]) ?></strong></div>
            <div><span>Phone</span><strong><?= htmlspecialchars($final["phone"]) ?></strong></div>
            <div><span>NIC/Passport</span><strong><?= htmlspecialchars($final["nic"]) ?></strong></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="summary-box">
            <div><span>Pickup</span><strong><?= htmlspecialchars($final["pickup"]) ?></strong></div>
            <div><span>End</span><strong><?= htmlspecialchars($final["drop"]) ?></strong></div>
            <div><span>Dates</span><strong><?= htmlspecialchars($final["start"]) ?> → <?= htmlspecialchars($final["end"]) ?></strong></div>
            <div><span>People</span><strong><?= (int)$final["people"] ?></strong></div>
          </div>
        </div>
      </div>
      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <div class="summary-box">
            <div><span>Vehicle</span><strong><?= htmlspecialchars($final["vehicle"]) ?></strong></div>
            <div><span>Guide</span><strong><?= $final["guide"] ? ("Yes (#".(int)$final["guideId"].")") : "No" ?></strong></div>
            <div><span>Total Distance</span><strong><?= number_format((float)$final["totalKm"],2) ?> km</strong></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="summary-box">
            <div><span>Total</span><strong class="text-success">LKR <?= number_format((float)$final["totalPrice"],0) ?></strong></div>
          </div>
        </div>
      </div>
      <div class="mt-3">
        <details>
          <summary>Show Itinerary</summary>
          <pre class="itinerary-pre" id="itineraryPre"><?php
            $lines = [];
            if (!empty($final["itinerary_rows"]) && is_array($final["itinerary_rows"])) {
              $byDate = [];
              foreach ($final["itinerary_rows"] as $r) {
                $d = $r["Day_Date"];
                $byDate[$d][] = $r["Destination"];
              }
              ksort($byDate);
              $i=1;
              foreach ($byDate as $d => $arr) {
                $lines[] = "Day ".$i." · ".$d;
                foreach ($arr as $dest) {
                  $lines[] = "  - ".$dest;
                }
                $i++;
              }
            }
            echo htmlspecialchars(implode("\n", $lines));
          ?></pre>
        </details>
      </div>
      <div class="mt-3 d-flex gap-2">
        <a class="btn btn-primary" href="<?= htmlspecialchars($redirectUrl) ?>">Done</a>
        <a class="btn btn-outline-secondary" href="booking_custom.php">Create Another</a>
      </div>
    </div>
  </div>
<?php elseif ($final && !$final["ok"]): ?>
  <div class="alert alert-danger mb-3"><?= htmlspecialchars($final["msg"] ?? "Error") ?></div>
<?php endif; ?>

<?php if (!$final || !$final["ok"]): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-3">Customize Your Trip</h4>
      <div class="steps">
        <div class="step active" data-step="1">Basics</div>
        <div class="step" data-step="2">Itinerary</div>
        <div class="step" data-step="3">Extras</div>
        <div class="step" data-step="4">Review</div>
      </div>

      <form id="customForm" method="post" class="mt-3">
        <input type="hidden" name="__step" value="submit">
        <input type="hidden" name="Total_KM" id="Total_KM" value="0">
        <input type="hidden" name="Itinerary_JSON" id="Itinerary_JSON" value="[]">

        <div class="step-pane" data-step="1">
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
              <label class="form-label">Email</label>
              <input type="email" name="Email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Number</label>
              <input type="text" name="Phone_No" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">NIC / Passport</label>
              <input type="text" name="NIC_or_Paasport" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Number of People</label>
              <input type="number" min="1" value="1" id="people" name="Number_of_People" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Starting Date</label>
              <input type="date" id="startDate" name="Start_Date" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Duration (days)</label>
              <input type="number" min="1" value="1" id="duration" name="Duration_Days" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Pickup Location</label>
              <input type="text" id="pickup" name="Pickup_Location" class="form-control gmaps-place" placeholder="Search in Sri Lanka" required>
            </div>
            <div class="col-12">
              <label class="form-label">End Location</label>
              <input type="text" id="drop" name="End_Location" class="form-control gmaps-place" placeholder="Search in Sri Lanka" required>
            </div>
          </div>
          <div class="mt-3 d-flex justify-content-end">
            <button type="button" class="btn btn-primary" id="toStep2">Next</button>
          </div>
        </div>

        <div class="step-pane d-none" data-step="2">
          <div class="d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Plan Your Itinerary</h5>
            <div class="text-muted small">Add destinations for each date</div>
          </div>
          <div id="daysContainer" class="mt-3"></div>
          <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary" id="backTo1">Back</button>
            <button type="button" class="btn btn-primary" id="toStep3">Next</button>
          </div>
        </div>

        <div class="step-pane d-none" data-step="3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Vehicle Category</label>
              <select id="vehicleCategory" name="Vehicle_Category" class="form-select" required></select>
              <div class="small text-muted mt-1">Shown categories can seat your group size or larger</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Guide</label>
              <div class="d-flex gap-2">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="Want_Guide" id="wgNo" value="0" checked>
                  <label class="form-check-label" for="wgNo">No</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="Want_Guide" id="wgYes" value="1">
                  <label class="form-check-label" for="wgYes">Yes</label>
                </div>
              </div>
              <div class="small text-muted">If selected, LKR 3000/day will be added</div>
            </div>
            <div class="col-12 d-none" id="guidesWrap">
              <label class="form-label">Available Guides</label>
              <div class="guide-list" id="guideList"><div class="text-muted">Pick dates to load guides</div></div>
              <input type="hidden" name="Guide_ID" id="Guide_ID">
            </div>
            <div class="col-12">
              <div class="calc-box">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="small text-muted">Estimated Distance</div>
                    <div class="h5 mb-0"><span id="kmOut">0.00</span> km</div>
                  </div>
                  <button type="button" class="btn btn-outline-primary" id="calcBtn">Recalculate</button>
                </div>
                <div class="small text-muted mt-2">Distance is computed from pickup through all selected destinations across days, ending at your end location</div>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary" id="backTo2">Back</button>
            <button type="button" class="btn btn-primary" id="toStep4">Next</button>
          </div>
        </div>

        <div class="step-pane d-none" data-step="4">
          <h5 class="mb-3">Review & Confirm</h5>
          <div id="reviewBox" class="review-box"></div>
          <div class="row g-3 mt-1">
            <div class="col-md-4">
              <div class="summary-box">
                <div><span>Total</span><strong class="text-success" id="revTotal">LKR 0.00</strong></div>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Payment Method</label>
              <select name="Payment_Method" class="form-select" required>
                <option value="Cash">Cash</option>
                <option value="Online">Online</option>
              </select>
            </div>
          </div>
          <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary" id="backTo3">Back</button>
            <button type="submit" class="btn btn成功 btn-success">Confirm Booking</button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const steps = Array.from(document.querySelectorAll('.step'));
const panes = Array.from(document.querySelectorAll('.step-pane'));
function goStep(n){
  steps.forEach(s=>s.classList.toggle('active', s.dataset.step==n));
  panes.forEach(p=>p.classList.toggle('d-none', p.dataset.step!=n));
}
document.getElementById('toStep2')?.addEventListener('click', ()=>{
  if (!document.querySelector('input[name="F_Name"]').value.trim()) return;
  if (!document.querySelector('input[name="L_Name"]').value.trim()) return;
  if (!document.getElementById('pickup').value.trim()) return;
  if (!document.getElementById('drop').value.trim()) return;
  if (!document.getElementById('startDate').value) return;
  if (!document.getElementById('duration').value) return;
  buildDays();
  goStep(2);
});
document.getElementById('backTo1')?.addEventListener('click', ()=>goStep(1));
document.getElementById('toStep3')?.addEventListener('click', ()=>{
  goStep(3);
  buildVehicleOptions();
  refreshGuides();
});
document.getElementById('backTo2')?.addEventListener('click', ()=>goStep(2));
document.getElementById('toStep4')?.addEventListener('click', async ()=>{
  buildReview();
  await calcDistance();
  await updateQuote();
  goStep(4);
});
document.getElementById('backTo3')?.addEventListener('click', ()=>goStep(3));

const peopleEl = document.getElementById('people');
const vehicleEl = document.getElementById('vehicleCategory');
const startDateEl = document.getElementById('startDate');
const durationEl = document.getElementById('duration');
const pickupEl = document.getElementById('pickup');
const dropEl = document.getElementById('drop');
const kmOut = document.getElementById('kmOut');
const totalKmField = document.getElementById('Total_KM');
const itineraryField = document.getElementById('Itinerary_JSON');
const guidesWrap = document.getElementById('guidesWrap');
const guideList = document.getElementById('guideList');
const guideIdHidden = document.getElementById('Guide_ID');

const revTotal = document.getElementById('revTotal');

document.getElementById('wgYes')?.addEventListener('change', ()=>{ guidesWrap.classList.remove('d-none'); refreshGuides(); updateQuote(); });
document.getElementById('wgNo')?.addEventListener('change', ()=>{ guidesWrap.classList.add('d-none'); guideIdHidden.value=""; updateQuote(); });
vehicleEl?.addEventListener('change', updateQuote);
peopleEl?.addEventListener('input', ()=>{ buildVehicleOptions(); updateQuote(); });
durationEl?.addEventListener('input', ()=>{ buildDays(); updateQuote(); });
startDateEl?.addEventListener('change', ()=>{ buildDays(); refreshGuides(); updateQuote(); });

function buildVehicleOptions(){
  const caps = {"Bike":1,"Tuk-Tuk":2,"Mini-Car":3,"Car":4,"Van":7,"Bus":30};
  const p = Math.max(1, parseInt(peopleEl.value||"1",10));
  const total = p + 1;
  vehicleEl.innerHTML = "";
  Object.entries(caps).filter(([k,v])=>v>=total).sort((a,b)=>a[1]-b[1]).forEach(([k,v])=>{
    const opt=document.createElement('option'); opt.value=k; opt.textContent=k+" (up to "+v+")"; vehicleEl.appendChild(opt);
  });
}
peopleEl?.addEventListener('input', buildVehicleOptions);

function buildDays(){
  const cont = document.getElementById('daysContainer');
  cont.innerHTML = "";
  const start = startDateEl.value;
  if (!start) return;
  const days = Math.max(1, parseInt(durationEl.value||"1",10));
  const s = new Date(start);
  for (let i=0;i<days;i++){
    const d = new Date(s); d.setDate(d.getDate()+i);
    const iso = d.toISOString().slice(0,10);
    const day = document.createElement('div');
    day.className="day-card";
    day.dataset.date = iso;
    day.innerHTML = `
      <div class="d-flex justify-content-between align-items-center">
        <div class="h6 mb-0">Day ${i+1} · ${iso}</div>
        <button type="button" class="btn btn-sm btn-outline-primary add-dest">Add Destination</button>
      </div>
      <div class="dest-list"></div>
    `;
    cont.appendChild(day);
  }
}
document.getElementById('daysContainer')?.addEventListener('click', (e)=>{
  if (e.target.classList.contains('add-dest')){
    const wrap = e.target.closest('.day-card').querySelector('.dest-list');
    const row = document.createElement('div');
    row.className = "dest-row";
    row.innerHTML = `<input type="text" class="form-control gmaps-place" placeholder="Search in Sri Lanka"><button type="button" class="btn btn-sm btn-outline-danger remove-dest">Remove</button>`;
    wrap.appendChild(row);
    attachAutocomplete(row.querySelector('.gmaps-place'));
  }
  if (e.target.classList.contains('remove-dest')){
    e.target.closest('.dest-row')?.remove();
  }
});

async function refreshGuides(){
  if (!startDateEl.value || !durationEl.value) return;
  const days = Math.max(1, parseInt(durationEl.value||"1",10));
  const s = new Date(startDateEl.value);
  const e = new Date(s); e.setDate(e.getDate()+days-1);
  const endIso = e.toISOString().slice(0,10);
  const url = new URL(location.href);
  url.searchParams.set('action','guides');
  url.searchParams.set('start', startDateEl.value);
  url.searchParams.set('end', endIso);
  const res = await fetch(url.toString(), { headers: { 'X-Requested-With':'XMLHttpRequest' } });
  let data = {}; try { data = await res.json(); } catch(e){}
  if (data && data.ok !== false && typeof data.html === 'string') {
    guideList.innerHTML = data.html || '<div class="text-muted">No guides available.</div>';
  }
}
guideList?.addEventListener('change', (e)=>{
  if (e.target && e.target.name==='Guide_ID') guideIdHidden.value = e.target.value;
});

document.getElementById('calcBtn')?.addEventListener('click', async ()=>{
  await calcDistance();
  await updateQuote();
});

function collectItinerary(){
  const days = Array.from(document.querySelectorAll('.day-card')).map(dc=>{
    const date = dc.dataset.date;
    const dests = Array.from(dc.querySelectorAll('.gmaps-place')).map(i=>i.value.trim()).filter(Boolean);
    return {date, destinations: dests};
  });
  return days;
}

function buildReview(){
  const it = collectItinerary();
  itineraryField.value = JSON.stringify(it);
  const p = document.getElementById('reviewBox');
  const wantGuide = document.getElementById('wgYes').checked;
  const sDate = startDateEl.value;
  const days = Math.max(1, parseInt(durationEl.value||"1",10));
  const s = new Date(sDate); const e = new Date(s); e.setDate(e.getDate()+days-1);
  const endIso = e.toISOString().slice(0,10);
  p.innerHTML = `
    <div class="row g-3">
      <div class="col-md-6">
        <div class="summary-box">
          <div><span>Pickup</span><strong>${pickupEl.value}</strong></div>
          <div><span>End</span><strong>${dropEl.value}</strong></div>
          <div><span>Dates</span><strong>${sDate} → ${endIso}</strong></div>
          <div><span>People</span><strong>${peopleEl.value}</strong></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="summary-box">
          <div><span>Vehicle</span><strong>${vehicleEl.value||'-'}</strong></div>
          <div><span>Guide</span><strong>${wantGuide?'Yes':'No'}</strong></div>
          <div><span>Distance</span><strong><span id="revKm">${kmOut.textContent}</span> km</strong></div>
        </div>
      </div>
      <div class="col-12">
        <details>
          <summary>Show Day-by-Day Destinations</summary>
          <div class="mt-2">${it.map(d=>`<div class="it-row"><div class="it-date">${d.date}</div><div class="it-dests">${d.destinations.map(x=>`<span>${x}</span>`).join('')}</div></div>`).join('')}</div>
        </details>
      </div>
    </div>
  `;
}

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

async function calcDistance(){
  if (!mapApiLoaded) return;
  const itinerary = collectItinerary();
  const pickup = pickupEl.value.trim();
  const endLoc = dropEl.value.trim();
  const flatSeq = [];
  if (pickup) flatSeq.push(pickup);
  itinerary.forEach(day=>{ day.destinations.forEach(d=>flatSeq.push(d)); });
  if (endLoc) flatSeq.push(endLoc);
  if (flatSeq.length < 2){ kmOut.textContent = "0.00"; totalKmField.value=""; return; }

  const dir = new google.maps.DirectionsService();
  let totalMeters = 0;
  for (let i=0;i<flatSeq.length-1;i++){
    try{
      const res = await dir.route({origin: flatSeq[i], destination: flatSeq[i+1], travelMode: google.maps.TravelMode.DRIVING});
      const leg = res.routes[0].legs[0];
      totalMeters += leg.distance.value;
    }catch(e){}
  }
  const km = totalMeters/1000.0;
  kmOut.textContent = km.toFixed(2);
  totalKmField.value = km.toFixed(2);
}

async function updateQuote(){
  const start = startDateEl.value;
  const days = Math.max(1, parseInt(durationEl.value||"1",10));
  const people = Math.max(1, parseInt(peopleEl.value||"1",10));
  const vehicle = vehicleEl.value;
  const km = parseFloat(totalKmField.value||"0");
  const guide = document.getElementById('wgYes').checked ? 1 : 0;
  const guideId = guideIdHidden.value ? parseInt(guideIdHidden.value,10) : 0;
  if (!start || !vehicle) return;
  const url = new URL(location.href);
  url.searchParams.set('action','quote');
  url.searchParams.set('start', start);
  url.searchParams.set('days', String(days));
  url.searchParams.set('people', String(people));
  url.searchParams.set('vehicle', vehicle);
  url.searchParams.set('km', String(km));
  url.searchParams.set('guide', String(guide));
  if (guideId > 0) url.searchParams.set('guide_id', String(guideId));
  const res = await fetch(url.toString(), { headers: { 'X-Requested-With':'XMLHttpRequest' } });
  let data = {}; try { data = await res.json(); } catch(e){}
  if (data && data.ok){
    revTotal.textContent = "LKR " + Math.round(Number(data.total)).toFixed(0);
  }
}

document.getElementById('customForm')?.addEventListener('submit', ()=>{
  if (!totalKmField.value) totalKmField.value = "0";
});

(function(){
  const s=document.createElement('script');
  s.src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCYCblZmBwFlc_NfpJoS5bMWP87Pm3wM9w&libraries=places&callback=initPlaces";
  s.defer=true; s.async=true; document.head.appendChild(s);
})();
</script>
</body>
</html>
<?php $conn->close(); ?>
