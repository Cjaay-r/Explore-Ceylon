<?php
session_start();
require_once __DIR__ . '/Includes/config.php';
require_once __DIR__ . '/Includes/dbconnect.php';
require_once __DIR__ . '/Includes/auth.php';
require_once __DIR__ . '/Includes/stripe.php';
require_once __DIR__ . '/Includes/CountryCodePicker.php';

if (!function_exists('isLoggedIn') ? !isset($_SESSION['User_ID']) : !isLoggedIn()) {
  header('Location: ' . (function_exists('url') ? url('login.php') : 'login.php'));
  exit;
}

$uid = (int)$_SESSION['User_ID'];
$redirectUrl = function_exists('url') ? url('index.php') : 'index.php';

$APP_BASE = 'http://localhost/ceylon';

function vehicle_caps()
{
  return ["Bike" => 1, "Tuk-Tuk" => 2, "Mini-Car" => 3, "Car" => 4, "Van" => 7, "Bus" => 30];
}
function allowed_cats($heads)
{
  $out = [];
  foreach (vehicle_caps() as $k => $v) if ($v >= $heads) $out[$k] = $v;
  return $out;
}
function have_overlap($conn, $col, $id, $start, $end)
{
  $q = $conn->prepare("SELECT 1 FROM bookings WHERE $col=? AND Status NOT IN ('Cancelled','Completed') AND NOT (End_Date_Time < ? OR Start_Date_Time > ?) LIMIT 1");
  $q->bind_param("iss", $id, $start, $end);
  $q->execute();
  $r = $q->get_result();
  $q->close();
  return $r && $r->num_rows > 0;
}
function pick_driver($conn, $vehicleCat, $heads, $start, $end)
{
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
function guide_price_per_day($conn, $guideId)
{
  if ($guideId <= 0) return 0.0;
  $g = $conn->prepare("SELECT Price_per_Day AS ppd FROM guide WHERE Guide_ID=? AND Status='Available' LIMIT 1");
  $g->bind_param("i", $guideId);
  $g->execute();
  $res = $g->get_result();
  $ppd = 0.0;
  if ($row = $res->fetch_assoc()) $ppd = (float)$row['ppd'];
  $g->close();
  return $ppd > 0 ? $ppd : 0.0;
}
function detect_itinerary_date_column_by_try(mysqli $conn)
{
  $candidates = [
    ['col' => 'Day',       'uses_day_number' => true],
    ['col' => 'Day_Date',  'uses_day_number' => false],
    ['col' => 'Date',      'uses_day_number' => false],
    ['col' => 'DayNumber', 'uses_day_number' => true],
  ];
  foreach ($candidates as $c) {
    $sql = "SELECT {$c['col']} FROM booking_destinations LIMIT 0";
    try {
      $stmt = @$conn->prepare($sql);
    } catch (Throwable $e) {
      $stmt = false;
    }
    if ($stmt) {
      $stmt->close();
      return $c;
    }
  }
  return ['col' => 'Day', 'uses_day_number' => true];
}
function resolve_profile_img_public($raw)
{
  $raw = (string)$raw;
  if ($raw !== '') {
    if (preg_match('~^https?://~', $raw) || str_starts_with($raw, '/')) {
      return $raw;
    }
    if (strpos($raw, 'uploads/UserProfiles') !== false) {
      return ltrim($raw, '/');
    }
    return 'uploads/UserProfiles/' . ltrim($raw, '/');
  }
  return 'uploads/UserProfiles/defaultuser.jpg';
}
function guide_languages_list($conn, $gid)
{
  $langs = [];
  $st = @$conn->prepare("SELECT Language FROM language WHERE Guide_ID=?");
  if ($st) {
    $st->bind_param("i", $gid);
    if ($st->execute()) {
      $rs = $st->get_result();
      while ($row = $rs->fetch_assoc()) {
        $langs[] = trim((string)$row['Language']);
      }
    }
    $st->close();
  }
  if (!$langs) {
    $stA = @$conn->prepare("SELECT Language FROM languages WHERE Guide_ID=?");
    if ($stA) {
      $stA->bind_param("i", $gid);
      if ($stA->execute()) {
        $rsA = $stA->get_result();
        while ($row = $rsA->fetch_assoc()) {
          $langs[] = trim((string)$row['Language']);
        }
      }
      $stA->close();
    }
  }
  if (!$langs) {
    $stB = @$conn->prepare("SELECT Language FROM guide_languages WHERE Guide_ID=?");
    if ($stB) {
      $stB->bind_param("i", $gid);
      if ($stB->execute()) {
        $rsB = $stB->get_result();
        while ($row = $rsB->fetch_assoc()) {
          $langs[] = trim((string)$row['Language']);
        }
      }
      $stB->close();
    }
  }
  return implode(', ', array_filter($langs));
}

if (isset($_GET['action']) && $_GET['action'] === 'guides') {
  header('Content-Type: application/json');
  $start = $_GET['start'] ?? '';
  $end = $_GET['end'] ?? '';
  if (!$start || !$end) {
    echo json_encode(["ok" => true, "html" => ""]);
    exit;
  }
  $html = "";
  $gs = $conn->prepare("
    SELECT g.Guide_ID, g.F_Name, g.L_Name, g.Rating, u.User_Profile, g.Price_per_Day,
           GROUP_CONCAT(DISTINCT l.Language ORDER BY l.Language SEPARATOR ', ') AS Langs
    FROM guide g
    LEFT JOIN user u ON u.User_ID = g.User_ID
    LEFT JOIN language l ON l.Guide_ID = g.Guide_ID
    WHERE g.Status='Available'
      AND NOT EXISTS (
        SELECT 1 FROM bookings b
        WHERE b.Guide_ID = g.Guide_ID
          AND b.Status NOT IN ('Cancelled','Completed')
          AND NOT (b.End_Date_Time < ? OR b.Start_Date_Time > ?)
      )
    GROUP BY g.Guide_ID, g.F_Name, g.L_Name, g.Rating, u.User_Profile, g.Price_per_Day
    ORDER BY CAST(NULLIF(g.Rating,'') AS DECIMAL(10,2)) DESC, g.Guide_ID ASC
  ");
  $gs->bind_param("ss", $start, $end);
  $gs->execute();
  $res = $gs->get_result();
  while ($g = $res->fetch_assoc()) {
    $full = trim(($g['F_Name'] ?? "") . " " . ($g['L_Name'] ?? ""));
    $imgPath = resolve_profile_img_public($g['User_Profile'] ?? '');
    $img = htmlspecialchars(function_exists('url') ? url($imgPath) : $imgPath);
    $name = htmlspecialchars($full ?: ("Guide #" . $g['Guide_ID']));
    $rate = htmlspecialchars((string)($g['Rating'] ?? '0'));
    $id = (int)$g['Guide_ID'];
    $ppdVal = (float)($g['Price_per_Day'] ?? 0);
    $ppd = number_format(max(0, $ppdVal), 2);
    $langs = trim((string)($g['Langs'] ?? ''));
    if ($langs === '') $langs = guide_languages_list($conn, (int)$g['Guide_ID']);
    $langsHtml = $langs !== '' ? '<div class="text-muted small">Speaks: ' . htmlspecialchars($langs) . '</div>' : '';
    $html .= '<label class="guide-card"><input type="radio" name="Guide_ID" value="' . $id . '"><div class="gc-body"><img src="' . $img . '" alt="Guide" class="gc-avatar"><div class="gc-meta"><div class="gc-name">' . $name . '</div><div class="gc-rating">⭐ ' . $rate . ' · USD ' . $ppd . '/day</div>' . $langsHtml . '</div></div></label>';
  }
  $gs->close();
  echo json_encode(["ok" => true, "html" => $html]);
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'quote') {
  header('Content-Type: application/json');
  $start = $_GET['start'] ?? '';
  $days = (int)($_GET['days'] ?? 1);
  $people = max(1, (int)($_GET['people'] ?? 1));
  $vehicle = $_GET['vehicle'] ?? '';
  $km = (float)($_GET['km'] ?? 0);
  $guide = (int)($_GET['guide'] ?? 0) === 1;
  $guideIdQ = (int)($_GET['guide_id'] ?? 0);
  $sd = DateTime::createFromFormat('Y-m-d', $start);
  if (!$sd || !$vehicle) {
    echo json_encode(["ok" => false]);
    exit;
  }
  $ed = clone $sd;
  $ed->modify(($days - 1) . " days");
  $end = $ed->format('Y-m-d');
  $heads = $people + 1;
  $allowed = allowed_cats($heads);
  if (!isset($allowed[$vehicle])) {
    echo json_encode(["ok" => false]);
    exit;
  }
  [$driverId, $fp, $ppk] = pick_driver($conn, $vehicle, $heads, $start, $end);
  if ($driverId === 0) {
    echo json_encode(["ok" => false]);
    exit;
  }
  $driverCost = ($fp * $days) + ($ppk * $km);
  $gppd = ($guide && $guideIdQ > 0) ? guide_price_per_day($conn, $guideIdQ) : 0.0;
  $guideCost = $guide ? ($gppd * $days) : 0.0;
  $subtotal = $driverCost + $guideCost;
  $total = $subtotal * 1.05;
  echo json_encode(["ok" => true, "driver_id" => $driverId, "driver_cost" => $driverCost, "guide_cost" => $guideCost, "total" => $total]);
  exit;
}

$final = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__step'] ?? '') === 'submit') {
  $f = trim($_POST['F_Name'] ?? "");
  $l = trim($_POST['L_Name'] ?? "");
  $email = trim($_POST['Email'] ?? "");
  $ccode = trim($_POST['Phone_Country'] ?? "");
  $plocal = trim($_POST['Phone_Local'] ?? "");
  $phone = $ccode . $plocal;
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

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $final = ["ok" => false, "msg" => "Please enter a valid email address."];
  }
  if (!$final) {
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) < 7 || strlen($digits) > 15 || strpos($ccode, '+') !== 0) {
      $final = ["ok" => false, "msg" => "Please enter a valid phone number."];
    }
  }

  $sd = DateTime::createFromFormat('Y-m-d', $startDate);
  if (!$sd) {
    $final = ["ok" => false, "msg" => "Invalid start date."];
  }
  if (!$final) {
    $ed = clone $sd;
    $ed->modify(($days - 1) . " days");
    $endDate = $ed->format('Y-m-d');
    $heads = $people + 1;
    $allowed = allowed_cats($heads);
    if (!isset($allowed[$vehicleCat])) {
      $final = ["ok" => false, "msg" => "Selected vehicle not suitable for group size."];
    }
  }
  if (!$final) {
    if ($wantGuide && $chosenGuideId <= 0) {
      $final = ["ok" => false, "msg" => "Select a guide or choose No Guide."];
    }
  }
  if (!$final && $wantGuide && have_overlap($conn, "Guide_ID", $chosenGuideId, $startDate, $endDate)) {
    $final = ["ok" => false, "msg" => "Guide is busy for these dates."];
  }
  if (!$final) {
    [$driverId, $fp, $ppk] = pick_driver($conn, $vehicleCat, $people + 1, $startDate, $endDate);
    if ($driverId === 0) {
      $final = ["ok" => false, "msg" => "No available driver for the selected vehicle."];
    }
  }
  if (!$final && have_overlap($conn, "Driver_ID", $driverId, $startDate, $endDate)) {
    $final = ["ok" => false, "msg" => "Driver is busy for these dates. Please change dates or vehicle."];
  }
  if (!$final) {
    $gppd = $wantGuide ? guide_price_per_day($conn, $chosenGuideId) : 0.0;
    $guideCost = $wantGuide ? ($gppd * $days) : 0.0;
    $driverCost = ($fp * $days) + ($ppk * $totalKm);
    $subtotal = $guideCost + $driverCost;
    $totalPrice = $subtotal * 1.05;

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
    $pGuidePref = (int)($wantGuide ? 1 : 0);
    $pPrice = (int)round($totalPrice);
    $pPayMethod = $payMethod;
    $pPayStatus = ($payMethod === 'Online' ? (!empty($_SESSION['paid_ok']) ? 'Paid' : 'Unpaid') : 'Unpaid');
    if ($payMethod === 'Online' && empty($_SESSION['paid_ok'])) {
      $final = ["ok" => false, "msg" => "Please complete the online payment first."];
    }
  }
  if (!$final) {
    $pDriverEarn = (int)round($driverCost);
    $pGuideEarn = (int)round($guideCost);
    $pUserId = (int)$uid;
    $pDriverId = (int)$driverId;
    $pGuideId = $wantGuide ? (int)$chosenGuideId : null;
    $pPackageId = null;

    $bp = $conn->prepare("
      INSERT INTO bookings
        (F_Name, L_Name, Email, Phone_No, NIC_or_Paasport,
         Start_Date_Time, End_Date_Time, Pickup_Location, End_Location,
         Number_of_People, Booking_Type, Guide_Preferences, Status, Progress,
         Price, Payment_Method, Payment_Status,
         Driver_earning, Guide_earning,
         User_ID, Driver_ID, Guide_ID, Package_ID)
      VALUES
        (?,?,?,?,?,
         ?, ?, ?, ?,
         ?, 'customize', ?, 'Pending', '',
         ?, ?, ?,
         ?, ?,
         ?, ?, ?, ?)
    ");
    if ($bp === false) {
      $final = ["ok" => false, "msg" => "Failed to prepare booking statement."];
    } else {
      $types = "sssssssssiiissiiiiii";
      $bp->bind_param(
        $types,
        $pFName,
        $pLName,
        $pEmail,
        $pPhone,
        $pNIC,
        $pStart,
        $pEnd,
        $pPickup,
        $pDrop,
        $pPeople,
        $pGuidePref,
        $pPrice,
        $pPayMethod,
        $pPayStatus,
        $pDriverEarn,
        $pGuideEarn,
        $pUserId,
        $pDriverId,
        $pGuideId,
        $pPackageId
      );

      if ($bp->execute()) {
        $newId = $bp->insert_id;

        $itArr = json_decode($itineraryJson, true);
        if (is_array($itArr)) {
          $meta = detect_itinerary_date_column_by_try($conn);
          $candidates = [
            ['col' => $meta['col'],       'uses_day_number' => $meta['uses_day_number']],
            ['col' => 'Day',              'uses_day_number' => true],
            ['col' => 'Day_Date',         'uses_day_number' => false],
            ['col' => 'Date',             'uses_day_number' => false],
            ['col' => 'DayNumber',        'uses_day_number' => true],
          ];
          $ins = null;
          $picked = null;
          foreach ($candidates as $c) {
            $trySql = "INSERT INTO booking_destinations (Booking_ID, {$c['col']}, Destination, Status) VALUES (?,?,?,'upcomming')";
            try {
              $ins = @$conn->prepare($trySql);
            } catch (Throwable $e) {
              $ins = false;
            }
            if ($ins) {
              $picked = $c;
              break;
            }
          }
          if ($ins && $picked) {
            $usesDayNumber = $picked['uses_day_number'];
            $dayMap = [];
            $i = 1;
            if ($usesDayNumber) {
              foreach ($itArr as $day) {
                $dayMap[$i] = $i;
                $i++;
              }
            }
            $dayIndex = 1;
            foreach ($itArr as $day) {
              $dDate = isset($day['date']) ? (string)$day['date'] : null;
              $dests = isset($day['destinations']) && is_array($day['destinations']) ? $day['destinations'] : [];
              foreach ($dests as $dest) {
                $destName = trim((string)$dest);
                if ($destName === '') continue;
                $bookingIdBind = (int)$newId;
                if ($picked['col'] === 'Day' || $usesDayNumber) {
                  $dayNumberBind = (int)$dayIndex;
                  $destBind = $destName;
                  $ins->bind_param("iis", $bookingIdBind, $dayNumberBind, $destBind);
                } else {
                  $dateBind = $dDate ?: '';
                  $destBind = $destName;
                  $ins->bind_param("iss", $bookingIdBind, $dateBind, $destBind);
                }
                $ins->execute();
              }
              $dayIndex++;
            }
            $ins->close();
          }
        }

        $itRows = [];
        $sel = null;
        foreach (['Day', 'Day_Date', 'Date', 'DayNumber'] as $col) {
          try {
            $try = @$conn->prepare("SELECT {$col} AS DCol, Destination FROM booking_destinations WHERE Booking_ID=? ORDER BY {$col}, Destination");
          } catch (Throwable $e) {
            $try = false;
          }
          if ($try) {
            $sel = $try;
            break;
          }
        }
        if ($sel) {
          $bid = (int)$newId;
          $sel->bind_param("i", $bid);
          $sel->execute();
          $rs = $sel->get_result();
          while ($r = $rs->fetch_assoc()) {
            $itRows[] = $r;
          }
          $sel->close();
        }

        $final = [
          "ok" => true,
          "ref" => $newId,
          "f" => $f,
          "l" => $l,
          "email" => $email,
          "phone" => $phone,
          "nic" => $nic,
          "pickup" => $pickup,
          "drop" => $drop,
          "start" => $startDate,
          "end" => $endDate,
          "people" => $people,
          "days" => $days,
          "vehicle" => $vehicleCat,
          "guide" => $wantGuide,
          "guideId" => $pGuideId,
          "totalKm" => $totalKm,
          "driverCost" => 0.0,
          "guideCost" => 0.0,
          "totalPrice" => $pPrice,
          "itinerary" => $itineraryJson,
          "itinerary_rows" => $itRows
        ];

        unset($_SESSION['pending_form'], $_SESSION['pending_total'], $_SESSION['paid_ok'], $_SESSION['pay_token'], $_SESSION['pending_error']);
      } else {
        $final = ["ok" => false, "msg" => "Failed to save booking."];
      }
      $bp->close();
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__step'] ?? '') === 'pay') {
  $f = trim($_POST['F_Name'] ?? "");
  $l = trim($_POST['L_Name'] ?? "");
  $email = trim($_POST['Email'] ?? "");
  $ccode = trim($_POST['Phone_Country'] ?? "");
  $plocal = trim($_POST['Phone_Local'] ?? "");
  $phone = $ccode . $plocal;
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

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['pending_error'] = 'Please enter a valid email address.';
    $_SESSION['pending_form'] = $_POST;
    header('Location: CustomisedBookings.php');
    exit;
  }
  $digits = preg_replace('/\D+/', '', $phone);
  if (strlen($digits) < 7 || strlen($digits) > 15 || strpos($ccode, '+') !== 0) {
    $_SESSION['pending_error'] = 'Please enter a valid phone number.';
    $_SESSION['pending_form'] = $_POST;
    header('Location: CustomisedBookings.php');
    exit;
  }

  $sd = DateTime::createFromFormat('Y-m-d', $startDate);
  if (!$sd) {
    $_SESSION['pending_error'] = 'Invalid start date.';
    $_SESSION['pending_form'] = $_POST;
    header('Location: CustomisedBookings.php');
    exit;
  }
  $ed = clone $sd;
  $ed->modify(($days - 1) . " days");
  $endDate = $ed->format('Y-m-d');
  $heads = $people + 1;
  $allowed = allowed_cats($heads);
  if (!isset($allowed[$vehicleCat])) {
    $_SESSION['pending_error'] = 'Selected vehicle not suitable for group size.';
    $_SESSION['pending_form'] = $_POST;
    header('Location: CustomisedBookings.php');
    exit;
  }
  if ($wantGuide && $chosenGuideId <= 0) {
    $_SESSION['pending_error'] = 'Select a guide or choose No Guide.';
    $_SESSION['pending_form'] = $_POST;
    header('Location: CustomisedBookings.php');
    exit;
  }
  if ($wantGuide && have_overlap($conn, "Guide_ID", $chosenGuideId, $startDate, $endDate)) {
    $_SESSION['pending_error'] = 'Guide is busy for these dates.';
    $_SESSION['pending_form'] = $_POST;
    header('Location: CustomisedBookings.php');
    exit;
  }
  [$driverId, $fp, $ppk] = pick_driver($conn, $vehicleCat, $heads, $startDate, $endDate);
  if ($driverId === 0) {
    $_SESSION['pending_error'] = 'No available driver for the selected vehicle.';
    $_SESSION['pending_form'] = $_POST;
    header('Location: CustomisedBookings.php');
    exit;
  }
  if (have_overlap($conn, "Driver_ID", $driverId, $startDate, $endDate)) {
    $_SESSION['pending_error'] = 'Driver is busy for these dates. Please change dates or vehicle.';
    $_SESSION['pending_form'] = $_POST;
    header('Location: CustomisedBookings.php');
    exit;
  }

  $gppd = $wantGuide ? guide_price_per_day($conn, $chosenGuideId) : 0.0;
  $guideCost = $wantGuide ? ($gppd * $days) : 0.0;
  $driverCost = ($fp * $days) + ($ppk * $totalKm);
  $subtotal = $guideCost + $driverCost;
  $totalPrice = $subtotal * 1.05;

  $_SESSION['pending_form'] = $_POST;
  $_SESSION['pending_total'] = (float)$totalPrice;

  try {
    $token = bin2hex(random_bytes(8));
    $_SESSION['pay_token'] = $token;
    $successUrl = $APP_BASE . '/CustomisedBookings.php?paid=1&tok=' . urlencode($token);
    $cancelUrl  = $APP_BASE . '/CustomisedBookings.php?cancelled=1';
    $session = stripe_create_checkout_session(0, (float)$totalPrice, $email, 'Customised booking', $successUrl, $cancelUrl);
    header('Location: ' . $session->url, true, 303);
    exit;
  } catch (Throwable $e) {
    $_SESSION['pending_error'] = 'Unable to start payment.';
    header('Location: CustomisedBookings.php');
    exit;
  }
}

if (isset($_GET['paid']) && $_GET['paid'] == '1' && !empty($_SESSION['pending_form']) && isset($_GET['tok']) && isset($_SESSION['pay_token']) && hash_equals($_SESSION['pay_token'], $_GET['tok'])) {
  $_SESSION['paid_ok'] = 1;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customizable Booking</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"/>
  <link rel="stylesheet" href="Styles/CustomisedBookings.css">
  <style>
    .steps {
      display: flex;
      gap: .5rem
    }

    .step {
      padding: .4rem .6rem;
      border-radius: 999px;
      background: #f1f3f5;
      color: #495057
    }

    .step.active {
      background: #0d6efd;
      color: #fff
    }

    .step-pane {
      margin-top: 1rem
    }

    .day-card {
      border: 1px solid #e9ecef;
      border-radius: .75rem;
      padding: 1rem;
      margin-bottom: .75rem
    }

    .dest-row {
      display: flex;
      gap: .5rem;
      margin-top: .5rem
    }

    .guide-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: .6rem
    }

    .guide-card {
      border: 1px solid #e9ecef;
      border-radius: .75rem;
      cursor: pointer
    }

    .guide-card input {
      display: none
    }

    .gc-body {
      display: flex;
      gap: .6rem;
      padding: .6rem
    }

    .gc-avatar {
      width: 44px;
      height: 44px;
      border-radius: 999px;
      object-fit: cover
    }

    .calc-box,
    .summary-box,
    .review-box {
      border: 1px solid #e9ecef;
      border-radius: .75rem;
      padding: 1rem
    }

    .it-row {
      display: flex;
      gap: 1rem;
      margin: .3rem 0
    }

    .it-dests span {
      background: #f1f3f5;
      border-radius: 999px;
      padding: .15rem .5rem;
      margin-right: .25rem
    }

    .btn-success {
      background-color: #16a34a;
      border-color: #16a34a
    }

    .phone-row {
      display: flex;
      gap: .5rem;
      align-items: end
    }

    .cc-wrap {
      min-width: 220px
    }

    .cc-search {
      position: relative
    }

    .cc-search input {
      padding-right: 2rem
    }

    .cc-search .cc-clear {
      position: absolute;
      right: .5rem;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent
    }
  </style>
</head>

<body>
  <?php include __DIR__ . '/Includes/header.php'; ?>
  <div class="container py-4">
    <?php if (!empty($_SESSION['pending_error'])): ?>
      <div class="alert alert-danger mb-3"><?= htmlspecialchars($_SESSION['pending_error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['paid']) && $_GET['paid'] == '1' && !empty($_SESSION['paid_ok'])): ?>
      <div class="alert alert-success mb-3">Payment successful. Please click Confirm Booking to save your booking.</div>
    <?php endif; ?>
    <?php if ($final && $final["ok"]): ?>
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="mb-3">Booking Submitted</h4>
          <div class="mb-2">Reference #<?= (int)$final["ref"] ?></div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="summary-box">
                <div><span>Name</span><strong><?= htmlspecialchars($final["f"] . " " . $final["l"]) ?></strong></div>
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
                <div><span>Guide</span><strong><?= $final["guide"] ? ("Yes (#" . (int)$final["guideId"] . ")") : "No" ?></strong></div>
                <div><span>Total Distance</span><strong><?= number_format((float)$final["totalKm"], 2) ?> km</strong></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="summary-box">
                <div><span>Total</span><strong class="text-success">USD <?= number_format((float)$final["totalPrice"], 0) ?></strong></div>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <details>
              <summary>Show Itinerary</summary>
              <pre class="itinerary-pre" id="itineraryPre"><?php
                                                            $lines = [];
                                                            if (!empty($final["itinerary_rows"]) && is_array($final["itinerary_rows"])) {
                                                              $byKey = [];
                                                              foreach ($final["itinerary_rows"] as $r) {
                                                                $key = $r["DCol"];
                                                                $byKey[$key][] = $r["Destination"];
                                                              }
                                                              ksort($byKey, SORT_NATURAL);
                                                              foreach ($byKey as $key => $arr) {
                                                                $lines[] = (string)$key;
                                                                foreach ($arr as $dest) $lines[] = "  - " . $dest;
                                                              }
                                                            }
                                                            echo htmlspecialchars(implode("\n", $lines));
                                                            ?></pre>
            </details>
          </div>
          <div class="mt-3 d-flex gap-2">
            <a class="btn btn-primary" href="<?= htmlspecialchars($redirectUrl) ?>">Done</a>
            <a class="btn btn-outline-secondary" href="CustomisedBookings.php">Create Another</a>
          </div>
          <?php if (!empty($final["msg"])): ?>
            <div class="alert alert-warning mt-3"><?= htmlspecialchars($final["msg"]) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php elseif ($final && !$final["ok"]): ?>
      <div class="alert alert-danger mb-3"><?= htmlspecialchars($final["msg"] ?? "Error") ?></div>
    <?php endif; ?>

    <?php
    $prefill = [
      'F_Name' => '',
      'L_Name' => '',
      'Email' => '',
      'Phone_Country' => '+94 ',
      'Phone_Local' => '',
      'NIC_or_Paasport' => '',
      'Number_of_People' => '1',
      'Start_Date' => '',
      'Duration_Days' => '1',
      'Pickup_Location' => '',
      'End_Location' => '',
      'Vehicle_Category' => '',
      'Want_Guide' => '0',
      'Guide_ID' => '',
      'Itinerary_JSON' => '[]',
      'Total_KM' => '0'
    ];
    if (!empty($_SESSION['pending_form'])) {
      foreach ($prefill as $k => $v) {
        if (isset($_SESSION['pending_form'][$k])) $prefill[$k] = $_SESSION['pending_form'][$k];
      }
    }
    ?>

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
            <input type="hidden" name="Total_KM" id="Total_KM" value="<?= htmlspecialchars($prefill['Total_KM']) ?>">
            <input type="hidden" name="Itinerary_JSON" id="Itinerary_JSON" value='<?= htmlspecialchars($prefill['Itinerary_JSON']) ?>'>
            <input type="hidden" name="Paid_Flag" id="Paid_Flag" value="<?= !empty($_SESSION['paid_ok']) ? '1' : '0' ?>">

            <div class="step-pane" data-step="1">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">First Name</label>
                  <input type="text" name="F_Name" class="form-control" required value="<?= htmlspecialchars($prefill['F_Name']) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Last Name</label>
                  <input type="text" name="L_Name" class="form-control" required value="<?= htmlspecialchars($prefill['L_Name']) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="Email" class="form-control" required value="<?= htmlspecialchars($prefill['Email']) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Contact Number</label>
                  <?php country_code_field('Phone_Country', 'Phone_Local', '', ''); ?>
                  <input type="hidden" class="form-control" id="contact" name="Contact_No" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">NIC / Passport</label>
                  <input type="text" name="NIC_or_Paasport" class="form-control" required value="<?= htmlspecialchars($prefill['NIC_or_Paasport']) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Number of People</label>
                  <input type="number" min="1" value="<?= htmlspecialchars($prefill['Number_of_People']) ?>" id="people" name="Number_of_People" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Starting Date</label>
                  <input type="date" id="startDate" name="Start_Date" class="form-control" required value="<?= htmlspecialchars($prefill['Start_Date']) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Duration (days)</label>
                  <input type="number" min="1" value="<?= htmlspecialchars($prefill['Duration_Days']) ?>" id="duration" name="Duration_Days" class="form-control" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Pickup Location</label>
                  <input type="text" id="pickup" name="Pickup_Location" class="form-control gmaps-place" placeholder="Search in Sri Lanka" required value="<?= htmlspecialchars($prefill['Pickup_Location']) ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">End Location</label>
                  <input type="text" id="drop" name="End_Location" class="form-control gmaps-place" placeholder="Search in Sri Lanka" required value="<?= htmlspecialchars($prefill['End_Location']) ?>">
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
                      <input class="form-check-input" type="radio" name="Want_Guide" id="wgNo" value="0" <?= $prefill['Want_Guide'] == '0' ? 'checked' : '' ?>>
                      <label class="form-check-label" for="wgNo">No</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="Want_Guide" id="wgYes" value="1" <?= $prefill['Want_Guide'] == '1' ? 'checked' : '' ?>>
                      <label class="form-check-label" for="wgYes">Yes</label>
                    </div>
                  </div>
                  <div class="small text-muted">If selected, an additional guide fee per day will be added to your total.</div>
                </div>
                <div class="col-12 <?= $prefill['Want_Guide'] == '1' ? '' : 'd-none' ?>" id="guidesWrap">
                  <label class="form-label">Available Guides</label>
                  <div class="guide-list" id="guideList">
                    <div class="text-muted">Pick dates to load guides</div>
                  </div>
                  <input type="hidden" name="Guide_ID" id="Guide_ID" value="<?= htmlspecialchars($prefill['Guide_ID']) ?>">
                </div>
                <div class="col-12">
                  <div class="calc-box">
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <div class="small text-muted">Estimated Distance</div>
                        <div class="h5 mb-0"><span id="kmOut"><?= htmlspecialchars($prefill['Total_KM'] ?: '0.00') ?></span> km</div>
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
                    <div><span>Total</span><strong class="text-success" id="revTotal">USD 0.00</strong></div>
                  </div>
                </div>
                <div class="col-md-8">
                  <label class="form-label">Payment Method</label>
                  <div class="d-flex align-items-center gap-3">
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="Payment_Method" id="pmCash" value="Cash" <?= empty($_SESSION['paid_ok']) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="pmCash">Cash</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="Payment_Method" id="pmOnline" value="Online" <?= !empty($_SESSION['paid_ok']) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="pmOnline">Online</label>
                    </div>
                    <button type="button" class="btn btn-outline-primary ms-auto" id="payNowBtn">Pay Now</button>
                  </div>
                </div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" id="backTo3">Back</button>
                <button type="submit" class="btn btn-success" id="confirmBtn">Confirm Booking</button>
              </div>
            </div>
          </form>

          <form id="payForm" method="post" class="d-none">
            <input type="hidden" name="__step" value="pay">
            <input type="hidden" name="F_Name">
            <input type="hidden" name="L_Name">
            <input type="hidden" name="Email">
            <input type="hidden" name="Phone_Country">
            <input type="hidden" name="Phone_Local">
            <input type="hidden" name="NIC_or_Paasport">
            <input type="hidden" name="Number_of_People">
            <input type="hidden" name="Start_Date">
            <input type="hidden" name="Duration_Days">
            <input type="hidden" name="Pickup_Location">
            <input type="hidden" name="End_Location">
            <input type="hidden" name="Vehicle_Category">
            <input type="hidden" name="Want_Guide">
            <input type="hidden" name="Guide_ID">
            <input type="hidden" name="Total_KM">
            <input type="hidden" name="Itinerary_JSON">
            <input type="hidden" name="Payment_Method" value="Online">
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const steps = Array.from(document.querySelectorAll('.step'));
    const panes = Array.from(document.querySelectorAll('.step-pane'));

    function goStep(n) {
      steps.forEach(s => s.classList.toggle('active', s.dataset.step == n));
      panes.forEach(p => p.classList.toggle('d-none', p.dataset.step != n));
    }
    document.getElementById('toStep2')?.addEventListener('click', () => {
      if (!document.querySelector('input[name="F_Name"]').value.trim()) return;
      if (!document.querySelector('input[name="L_Name"]').value.trim()) return;
      if (!document.querySelector('input[name="Email"]').value.trim()) return;
      const _pl = document.querySelector('[name="Phone_Local"]');
      if (!_pl || !_pl.value.trim()) return;

      if (!document.getElementById('pickup').value.trim()) return;
      if (!document.getElementById('drop').value.trim()) return;
      if (!document.getElementById('startDate').value) return;
      if (!document.getElementById('duration').value) return;
      buildDays();
      goStep(2);
    });
    document.getElementById('backTo1')?.addEventListener('click', () => goStep(1));
    document.getElementById('toStep3')?.addEventListener('click', () => {
      goStep(3);
      buildVehicleOptions();
      refreshGuides();
    });
    document.getElementById('backTo2')?.addEventListener('click', () => goStep(2));
    document.getElementById('toStep4')?.addEventListener('click', async () => {
      buildReview();
      await calcDistance();
      await updateQuote();
      goStep(4);
      syncConfirmState();
    });
    document.getElementById('backTo3')?.addEventListener('click', () => goStep(3));

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
    const paidFlag = document.getElementById('Paid_Flag');
    const confirmBtn = document.getElementById('confirmBtn');
    const payNowBtn = document.getElementById('payNowBtn');

    document.getElementById('wgYes')?.addEventListener('change', () => {
      guidesWrap.classList.remove('d-none');
      refreshGuides();
      updateQuote();
    });
    document.getElementById('wgNo')?.addEventListener('change', () => {
      guidesWrap.classList.add('d-none');
      guideIdHidden.value = "";
      updateQuote();
    });
    vehicleEl?.addEventListener('change', updateQuote);
    peopleEl?.addEventListener('input', () => {
      buildVehicleOptions();
      updateQuote();
    });
    durationEl?.addEventListener('input', () => {
      buildDays();
      updateQuote();
    });
    startDateEl?.addEventListener('change', () => {
      buildDays();
      refreshGuides();
      updateQuote();
    });

    function buildVehicleOptions() {
      const caps = {
        "Bike": 1,
        "Tuk-Tuk": 2,
        "Mini-Car": 3,
        "Car": 4,
        "Van": 7,
        "Bus": 30
      };
      const p = Math.max(1, parseInt(peopleEl.value || "1", 10));
      const total = p + 1;
      vehicleEl.innerHTML = "";
      Object.entries(caps).filter(([k, v]) => v >= total).sort((a, b) => a[1] - b[1]).forEach(([k, v]) => {
        const opt = document.createElement('option');
        opt.value = k;
        opt.textContent = k + " (up to " + v + ")";
        vehicleEl.appendChild(opt);
      });
    }
    peopleEl?.addEventListener('input', buildVehicleOptions);

    function buildDays() {
      const cont = document.getElementById('daysContainer');
      cont.innerHTML = "";
      const start = startDateEl.value;
      if (!start) return;
      const days = Math.max(1, parseInt(durationEl.value || "1", 10));
      const s = new Date(start);
      for (let i = 0; i < days; i++) {
        const d = new Date(s);
        d.setDate(d.getDate() + i);
        const iso = d.toISOString().slice(0, 10);
        const day = document.createElement('div');
        day.className = "day-card";
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
      const saved = tryParseJSON(itineraryField.value);
      if (saved && Array.isArray(saved) && saved.length) {
        saved.forEach((d, iidx) => {
          const pane = Array.from(cont.children)[iidx];
          if (!pane) return;
          const wrap = pane.querySelector('.dest-list');
          (d.destinations || []).forEach(dest => {
            const row = document.createElement('div');
            row.className = "dest-row";
            row.innerHTML = `<input type="text" class="form-control gmaps-place" placeholder="Search in Sri Lanka" value="${escapeHtml(dest)}"><button type="button" class="btn btn-sm btn-outline-danger remove-dest">Remove</button>`;
            wrap.appendChild(row);
            attachAutocomplete(row.querySelector('.gmaps-place'));
          });
        });
      }
    }
    document.getElementById('daysContainer')?.addEventListener('click', (e) => {
      if (e.target.classList.contains('add-dest')) {
        const wrap = e.target.closest('.day-card').querySelector('.dest-list');
        const row = document.createElement('div');
        row.className = "dest-row";
        row.innerHTML = `<input type="text" class="form-control gmaps-place" placeholder="Search in Sri Lanka"><button type="button" class="btn btn-sm btn-outline-danger remove-dest">Remove</button>`;
        wrap.appendChild(row);
        attachAutocomplete(row.querySelector('.gmaps-place'));
      }
      if (e.target.classList.contains('remove-dest')) {
        e.target.closest('.dest-row')?.remove();
      }
    });

    async function refreshGuides() {
      if (!startDateEl.value || !durationEl.value) return;
      const days = Math.max(1, parseInt(durationEl.value || "1", 10));
      const s = new Date(startDateEl.value);
      const e = new Date(s);
      e.setDate(e.getDate() + days - 1);
      const endIso = e.toISOString().slice(0, 10);
      const url = new URL(location.href);
      url.searchParams.set('action', 'guides');
      url.searchParams.set('start', startDateEl.value);
      url.searchParams.set('end', endIso);
      const res = await fetch(url.toString(), {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      let data = {};
      try {
        data = await res.json();
      } catch (e) {}
      if (data && data.ok !== false && typeof data.html === 'string') {
        guideList.innerHTML = data.html || '<div class="text-muted">No guides available.</div>';
      }
    }
    guideList?.addEventListener('change', (e) => {
      if (e.target && e.target.name === 'Guide_ID') guideIdHidden.value = e.target.value;
    });

    document.getElementById('calcBtn')?.addEventListener('click', async () => {
      await calcDistance();
      await updateQuote();
    });

    function collectItinerary() {
      const days = Array.from(document.querySelectorAll('.day-card')).map(dc => {
        const date = dc.dataset.date;
        const dests = Array.from(dc.querySelectorAll('.gmaps-place')).map(i => i.value.trim()).filter(Boolean);
        return {
          date,
          destinations: dests
        };
      });
      return days;
    }

    function buildReview() {
      const it = collectItinerary();
      itineraryField.value = JSON.stringify(it);
      const p = document.getElementById('reviewBox');
      const wantGuide = document.getElementById('wgYes').checked;
      const sDate = startDateEl.value;
      const days = Math.max(1, parseInt(durationEl.value || "1", 10));
      const s = new Date(sDate);
      const e = new Date(s);
      e.setDate(e.getDate() + days - 1);
      const endIso = e.toISOString().slice(0, 10);
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
          <div class="mt-2">${it.map(d=>`<div class="it-row"><div class="it-date">${d.date}</div><div class="it-dests">${d.destinations.map(x=>`<span>${escapeHtml(x)}</span>`).join('')}</div></div>`).join('')}</div>
        </details>
      </div>
    </div>
  `;
    }

    let mapApiLoaded = false;

    function attachAutocomplete(input) {
      if (!input) return;
      if (!mapApiLoaded) return;
      new google.maps.places.Autocomplete(input, {
        fields: ["formatted_address", "geometry", "name"],
        componentRestrictions: {
          country: ["lk"]
        }
      });
    }

    function initPlaces() {
      mapApiLoaded = true;
      document.querySelectorAll('.gmaps-place').forEach(attachAutocomplete);
    }
    window.initPlaces = initPlaces;

    async function calcDistance() {
      if (!mapApiLoaded) return;
      const itinerary = collectItinerary();
      const pickup = pickupEl.value.trim();
      const endLoc = dropEl.value.trim();
      const flatSeq = [];
      if (pickup) flatSeq.push(pickup);
      itinerary.forEach(day => {
        day.destinations.forEach(d => flatSeq.push(d));
      });
      if (endLoc) flatSeq.push(endLoc);
      if (flatSeq.length < 2) {
        kmOut.textContent = "0.00";
        totalKmField.value = "";
        return;
      }
      const dir = new google.maps.DirectionsService();
      let totalMeters = 0;
      for (let i = 0; i < flatSeq.length - 1; i++) {
        try {
          const res = await dir.route({
            origin: flatSeq[i],
            destination: flatSeq[i + 1],
            travelMode: google.maps.TravelMode.DRIVING
          });
          const leg = res.routes[0].legs[0];
          totalMeters += leg.distance.value;
        } catch (e) {}
      }
      const km = totalMeters / 1000.0;
      kmOut.textContent = km.toFixed(2);
      totalKmField.value = km.toFixed(2);
    }

    async function updateQuote() {
      const start = startDateEl.value;
      const days = Math.max(1, parseInt(durationEl.value || "1", 10));
      const people = Math.max(1, parseInt(peopleEl.value || "1", 10));
      const vehicle = vehicleEl.value;
      const km = parseFloat(totalKmField.value || "0");
      const guide = document.getElementById('wgYes').checked ? 1 : 0;
      const guideId = guideIdHidden.value ? parseInt(guideIdHidden.value, 10) : 0;
      if (!start || !vehicle) return;
      const url = new URL(location.href);
      url.searchParams.set('action', 'quote');
      url.searchParams.set('start', start);
      url.searchParams.set('days', String(days));
      url.searchParams.set('people', String(people));
      url.searchParams.set('vehicle', vehicle);
      url.searchParams.set('km', String(km));
      url.searchParams.set('guide', String(guide));
      if (guideId > 0) url.searchParams.set('guide_id', String(guideId));
      const res = await fetch(url.toString(), {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      let data = {};
      try {
        data = await res.json();
      } catch (e) {}
      if (data && data.ok) {
        revTotal.textContent = "USD " + Math.round(Number(data.total)).toFixed(0);
      }
    }

    document.getElementById('customForm')?.addEventListener('submit', () => {
      if (!totalKmField.value) totalKmField.value = "0";
    });

    (function() {
      const s = document.createElement('script');
      s.src = "https://maps.googleapis.com/maps/api/js?key=AIzaSyCYCblZmBwFlc_NfpJoS5bMWP87Pm3wM9w&libraries=places&callback=initPlaces";
      s.defer = true;
      s.async = true;
      document.head.appendChild(s);
    })();

    function syncConfirmState() {
      const pmOnline = document.getElementById('pmOnline').checked;
      const paid = paidFlag.value === '1';
      if (pmOnline) {
        confirmBtn.disabled = !paid;
        payNowBtn.classList.remove('d-none');
      } else {
        confirmBtn.disabled = false;
        payNowBtn.classList.add('d-none');
      }
    }
    document.getElementById('pmCash')?.addEventListener('change', syncConfirmState);
    document.getElementById('pmOnline')?.addEventListener('change', syncConfirmState);
    syncConfirmState();

    payNowBtn?.addEventListener('click', () => {
      if (!document.getElementById('pmOnline').checked) return;
      const src = document.getElementById('customForm');
      const dst = document.getElementById('payForm');
      ['F_Name', 'L_Name', 'Email', 'NIC_or_Paasport', 'Number_of_People', 'Start_Date', 'Duration_Days',
        'Pickup_Location', 'End_Location', 'Vehicle_Category', 'Guide_ID', 'Total_KM', 'Itinerary_JSON'
      ].forEach(k => {
        const v = src.querySelector(`[name="${k}"]`)?.value || '';
        dst.querySelector(`[name="${k}"]`).value = v;
      });
      dst.querySelector('[name="Want_Guide"]').value = document.getElementById('wgYes').checked ? '1' : '0';
      const ccEl = document.querySelector('[name="Phone_Country"]');
      dst.querySelector('[name="Phone_Country"]').value = ccEl ? ccEl.value : '+94 ';
      dst.querySelector('[name="Phone_Local"]').value = (document.querySelector('[name="Phone_Local"]')?.value || '');
      dst.querySelector('[name="__step"]').value = 'pay';
      dst.submit();
    });

    <?php if (!empty($_SESSION['pending_form'])): ?>
        (function restoreForm() {
          const pf = <?php echo json_encode($_SESSION['pending_form']); ?>;
          Object.entries(pf).forEach(([k, v]) => {
            const el = document.querySelector(`[name="${CSS.escape(k)}"]`);
            if (!el) return;
            if (el.type === 'radio' || el.type === 'checkbox') {
              const el2 = document.querySelector(`[name="${CSS.escape(k)}"][value="${CSS.escape(String(v))}"]`);
              if (el2) el2.checked = true;
            } else {
              el.value = v;
            }
          });
        })();
    <?php endif; ?>

    function afterRestoreJumpToReview() {
      const mainStep = document.querySelector('#customForm [name="__step"]');
      if (mainStep) mainStep.value = 'submit';
      if (paidFlag.value === '1' || new URLSearchParams(location.search).get('paid') === '1') {
        buildDays();
        buildVehicleOptions();
        refreshGuides();
        buildReview();
        updateQuote();
        goStep(4);
        syncConfirmState();
      }
    }
    window.addEventListener('load', afterRestoreJumpToReview);

    function tryParseJSON(s) {
      try {
        return JSON.parse(s);
      } catch (e) {
        return null;
      }
    }

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      } [m]));
    }
  </script>

  <?php include __DIR__ . '/Includes/footer.php'; ?>
</body>

</html>
<?php $conn->close(); ?>