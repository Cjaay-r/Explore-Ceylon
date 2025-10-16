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
$ust = $conn->prepare("SELECT User_ID, Username, Email, Password, Phone_No, User_Profile, User_Type FROM user WHERE User_ID=? LIMIT 1");
$ust->bind_param("i", $uid);
$ust->execute();
$user = $ust->get_result()->fetch_assoc();
if (!$user) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$role = strtolower(trim((string)$user['User_Type']));
if (!in_array($role, ['guide','driver'], true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

if ($role === 'guide') {
    $pst = $conn->prepare("SELECT * FROM guide WHERE User_ID=? LIMIT 1");
    $pst->bind_param("i", $uid);
    $pst->execute();
    $profile = $pst->get_result()->fetch_assoc();
    if (!$profile) {
        $profile = [
            'Guide_ID'=>null,
            'F_Name'=>'',
            'L_Name'=>'',
            'NIC_or_Pass'=>'',
            'Description'=>'',
            'Price_per_Day'=>0,
            'Rating'=>0,
            'Status'=>'Available',
            'Total_Income'=>0,
            'Completed_trips'=>0,
            'User_ID'=>$uid
        ];
    }
} else {
    $pst = $conn->prepare("SELECT * FROM driver WHERE User_ID=? LIMIT 1");
    $pst->bind_param("i", $uid);
    $pst->execute();
    $profile = $pst->get_result()->fetch_assoc();
    if (!$profile) {
        $profile = [
            'Driver_ID'=>null,
            'F_Name'=>'',
            'L_Name'=>'',
            'NIC_or_Pass'=>'',
            'Description'=>'',
            'Vehicle_Category'=>'Car',
            'Vehicle_No'=>'',
            'Fixed_Price'=>0,
            'PricePer_Km'=>0,
            'Total_Income'=>0,
            'Status'=>'Available',
            'Rating'=>0,
            'Completed_trips'=>0,
            'User_ID'=>$uid
        ];
    }
}

$ok = "";
$err = "";

function safe_file_name($name) {
    $base = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', basename($name));
    return $base ?: ('file_'.time());
}

function resolve_profile_img_admin($uid, $raw){
    $raw = (string)$raw;
    if ($raw !== '') {
        if (preg_match('~^https?://~', $raw) || str_starts_with($raw, '../') || str_starts_with($raw, '/')) {
            return $raw;
        }
        if (strpos($raw, 'uploads/UserProfiles') !== false) {
            return '../' . ltrim($raw, '/');
        }
        return '../uploads/UserProfiles/' . ltrim($raw, '/');
    }
    $guess = "../uploads/UserProfiles/user_{$uid}";
    $cands = [$guess.".jpg",$guess.".jpeg",$guess.".png",$guess.".webp"];
    foreach($cands as $p){ if (file_exists($p)) return $p; }
    return 'Images/defaultuser.jpg';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_profile'])) {
        $username = trim((string)($_POST['Username'] ?? $user['Username']));
        $phone = trim((string)($_POST['Phone_No'] ?? $user['Phone_No']));
        $f = trim((string)($_POST['F_Name'] ?? $profile['F_Name']));
        $l = trim((string)($_POST['L_Name'] ?? $profile['L_Name']));
        $nic = trim((string)($_POST['NIC_or_Pass'] ?? $profile['NIC_or_Pass']));
        $desc = trim((string)($_POST['Description'] ?? $profile['Description']));

        $conn->begin_transaction();
        $u1 = $conn->prepare("UPDATE user SET Username=?, Phone_No=? WHERE User_ID=?");
        $u1->bind_param("ssi", $username, $phone, $uid);
        $ok1 = $u1->execute();

        if ($role === 'guide') {
            $u2 = $conn->prepare("UPDATE guide SET F_Name=?, L_Name=?, NIC_or_Pass=?, Description=? WHERE User_ID=?");
            $u2->bind_param("ssssi", $f, $l, $nic, $desc, $uid);
        } else {
            $vehCat = $_POST['Vehicle_Category'] ?? $profile['Vehicle_Category'];
            $vehNo = trim((string)($_POST['Vehicle_No'] ?? $profile['Vehicle_No']));
            $u2 = $conn->prepare("UPDATE driver SET F_Name=?, L_Name=?, NIC_or_Pass=?, Description=?, Vehicle_Category=?, Vehicle_No=? WHERE User_ID=?");
            $u2->bind_param("ssssssi", $f, $l, $nic, $desc, $vehCat, $vehNo, $uid);
        }
        $ok2 = $u2->execute();

        $imgOk = true;
        if (isset($_FILES['User_Profile']) && is_array($_FILES['User_Profile']) && $_FILES['User_Profile']['error'] === UPLOAD_ERR_OK) {
            $dir = realpath(__DIR__ . '/../uploads/UserProfiles');
            if ($dir === false) {
                $dir = __DIR__ . '/../uploads/UserProfiles';
                @mkdir($dir, 0777, true);
            }
            $ext = pathinfo($_FILES['User_Profile']['name'], PATHINFO_EXTENSION);
            $newName = 'user_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? ('.' . strtolower($ext)) : '');
            $dst = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $newName;
            if (move_uploaded_file($_FILES['User_Profile']['tmp_name'], $dst)) {
                $rel = 'uploads/UserProfiles/' . $newName;
                $u3 = $conn->prepare("UPDATE user SET User_Profile=? WHERE User_ID=?");
                $u3->bind_param("si", $rel, $uid);
                $imgOk = $u3->execute();
                if ($imgOk) $user['User_Profile'] = $rel;
            } else {
                $imgOk = false;
            }
        }

        if ($ok1 && $ok2 && $imgOk) {
            $conn->commit();
            $ok = "Profile updated";
            $user['Username'] = $username;
            $user['Phone_No'] = $phone;
            $profile['F_Name'] = $f;
            $profile['L_Name'] = $l;
            $profile['NIC_or_Pass'] = $nic;
            $profile['Description'] = $desc;
            if ($role === 'driver') {
                $profile['Vehicle_Category'] = $_POST['Vehicle_Category'] ?? $profile['Vehicle_Category'];
                $profile['Vehicle_No'] = $vehNo;
            }
        } else {
            $conn->rollback();
            $err = "Update failed";
        }
    }

    if (isset($_POST['change_password'])) {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if ($new === '' || $confirm === '') {
            $err = "New password required";
        } elseif ($new !== $confirm) {
            $err = "Passwords do not match";
        } else {
            $len = strlen($new);
            $hasUpper = preg_match('/[A-Z]/', $new);
            $hasLower = preg_match('/[a-z]/', $new);
            $hasDigit = preg_match('/\d/', $new);
            $hasSymbol = preg_match('/[^A-Za-z0-9]/', $new);
            if (!($len >= 8 && $hasUpper && $hasLower && $hasDigit && $hasSymbol)) {
                $err = "Password must be at least 8 characters and include uppercase, lowercase, number, and symbol";
            } else {
                $valid = false;
                if (password_verify($current, $user['Password'])) {
                    $valid = true;
                } elseif ($current === $user['Password']) {
                    $valid = true;
                }
                if (!$valid) {
                    $err = "Current password incorrect";
                } else {
                    if (password_verify($new, $user['Password']) || $new === $user['Password']) {
                        $err = "New password must be different";
                    } else {
                        $hash = password_hash($new, PASSWORD_BCRYPT);
                        $p = $conn->prepare("UPDATE user SET Password=? WHERE User_ID=?");
                        $p->bind_param("si", $hash, $uid);
                        if ($p->execute()) {
                            $ok = "Password updated";
                        } else {
                            $err = "Password update failed";
                        }
                    }
                }
            }
        }
    }
}

$displayName = trim(($profile['F_Name'] ?? '').' '.($profile['L_Name'] ?? ''));
if ($displayName === '') $displayName = (string)$user['Username'];
$profileImg = resolve_profile_img_admin($uid, $user['User_Profile'] ?? '');
$titleRole = $role === 'driver' ? 'Driver' : 'Guide';
$readonlyPrice = 'readonly';
$readonlyEmail = 'readonly';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($titleRole) ?> Profile Manager • Explore Ceylon</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../Styles/ProfileManager.css"/>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

  <main class="main">
    <header class="topbar">
      <h1><?= htmlspecialchars($titleRole) ?> Profile Manager</h1>
      <div class="actions">
        <a class="btn ghost" href="../index.php">Site</a>
        <a class="btn primary" href="<?= $role==='driver' ? '../Driver/DriverDashboard.php' : 'GuideDashboard.php' ?>">Back to Dashboard</a>
      </div>
    </header>

    <?php if($ok): ?><div class="alert ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <?php if($err): ?><div class="alert err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <section class="profile-overview card in">
      <img class="avatar" src="<?= htmlspecialchars($profileImg) ?>" alt="Profile"/>
      <div class="pinfo">
        <div class="pname"><?= htmlspecialchars($displayName) ?></div>
        <div class="prow"><span class="badge"><?= htmlspecialchars(ucfirst($role)) ?></span></div>
      </div>
      <div class="pstats">
        <div class="stat">
          <div class="label">Email</div>
          <div class="value small"><?= htmlspecialchars($user['Email']) ?></div>
        </div>
        <div class="stat">
          <div class="label"><?= $role==='driver' ? 'Fixed Price (LKR)' : 'Price/Day (LKR)' ?></div>
          <div class="value small"><?= number_format((float)($role==='driver' ? $profile['Fixed_Price'] : $profile['Price_per_Day'])) ?></div>
        </div>
        <div class="stat">
          <div class="label">Rating</div>
          <div class="value small"><?= htmlspecialchars((string)$profile['Rating']) ?></div>
        </div>
      </div>
    </section>

    <section class="grid">
      <div class="card form-card">
        <div class="form-col">
          <h2 class="section-title">Personal Information</h2>
          <form method="post" enctype="multipart/form-data" class="form">
            <input type="hidden" name="save_profile" value="1"/>
            <div class="form-row">
              <div class="field">
                <label>First Name</label>
                <input name="F_Name" type="text" value="<?= htmlspecialchars((string)$profile['F_Name']) ?>" required/>
              </div>
              <div class="field">
                <label>Last Name</label>
                <input name="L_Name" type="text" value="<?= htmlspecialchars((string)$profile['L_Name']) ?>" required/>
              </div>
            </div>
            <div class="form-row">
              <div class="field">
                <label>Username</label>
                <input name="Username" type="text" value="<?= htmlspecialchars((string)$user['Username']) ?>" required/>
              </div>
              <div class="field">
                <label>Email</label>
                <input name="Email" type="email" value="<?= htmlspecialchars((string)$user['Email']) ?>" <?= $readonlyEmail ?>/>
              </div>
            </div>
            <div class="form-row">
              <div class="field">
                <label>Phone</label>
                <input name="Phone_No" type="text" value="<?= htmlspecialchars((string)$user['Phone_No']) ?>" required/>
              </div>
              <div class="field">
                <label>NIC/Passport</label>
                <input name="NIC_or_Pass" type="text" value="<?= htmlspecialchars((string)$profile['NIC_or_Pass']) ?>" required/>
              </div>
            </div>
            <div class="field">
              <label>About</label>
              <textarea name="Description" rows="4"><?= htmlspecialchars((string)$profile['Description']) ?></textarea>
            </div>

            <?php if ($role === 'driver'): ?>
            <div class="form-row">
              <div class="field">
                <label>Vehicle Category</label>
                <select name="Vehicle_Category">
                  <?php
                    $opts = ['Bike','Tuk-Tuk','Mini-Car','Car','Van','Bus'];
                    foreach ($opts as $v) {
                      $sel = ($profile['Vehicle_Category']===$v) ? 'selected' : '';
                      echo '<option value="'.htmlspecialchars($v).'" '.$sel.'>'.htmlspecialchars($v).'</option>';
                    }
                  ?>
                </select>
              </div>
              <div class="field">
                <label>Vehicle No</label>
                <input name="Vehicle_No" type="text" value="<?= htmlspecialchars((string)$profile['Vehicle_No']) ?>"/>
              </div>
            </div>
            <div class="form-row">
              <div class="field">
                <label>Fixed Price (locked)</label>
                <input type="text" value="<?= htmlspecialchars((string)$profile['Fixed_Price']) ?>" <?= $readonlyPrice ?>/>
              </div>
              <div class="field">
                <label>Price per Km (locked)</label>
                <input type="text" value="<?= htmlspecialchars((string)$profile['PricePer_Km']) ?>" <?= $readonlyPrice ?>/>
              </div>
            </div>
            <div class="form-row">
              <div class="field">
                <label>Rating (locked)</label>
                <input type="text" value="<?= htmlspecialchars((string)$profile['Rating']) ?>" <?= $readonlyPrice ?>/>
              </div>
            </div>
            <?php else: ?>
            <div class="form-row">
              <div class="field">
                <label>Price per Day (locked)</label>
                <input type="text" value="<?= htmlspecialchars((string)$profile['Price_per_Day']) ?>" <?= $readonlyPrice ?>/>
              </div>
              <div class="field">
                <label>Rating (locked)</label>
                <input type="text" value="<?= htmlspecialchars((string)$profile['Rating']) ?>" <?= $readonlyPrice ?>/>
              </div>
            </div>
            <?php endif; ?>

            <div class="field">
              <label>Profile Photo</label>
              <input name="User_Profile" type="file" accept="image/*"/>
            </div>

            <div class="actions">
              <button class="btn primary" type="submit">Save Changes</button>
            </div>
          </form>
        </div>

        <div class="form-col">
          <h2 class="section-title">Change Password</h2>
          <form method="post" class="form">
            <input type="hidden" name="change_password" value="1"/>
            <div class="field">
              <label>Current Password</label>
              <input name="current_password" type="password" required/>
            </div>
            <div class="form-row">
              <div class="field">
                <label>New Password</label>
                <input name="new_password" type="password" required/>
              </div>
              <div class="field">
                <label>Confirm Password</label>
                <input name="confirm_password" type="password" required/>
              </div>
            </div>
            <div class="actions">
              <button class="btn ghost" type="submit">Update Password</button>
            </div>
          </form>
        </div>
      </div>
    </section>

    <section class="quicklinks">
      <a class="qbtn outline" href="<?= $role==='driver' ? '../Driver/MyTrips.php' : 'MyTrips.php' ?>">Go to My Trips</a>
      <a class="qbtn outline" href="<?= $role==='driver' ? '../Driver/MyEarnings.php' : 'MyEarnings.php' ?>">Go to My Earnings</a>
      <a class="qbtn outline" href="<?= $role==='driver' ? '../Driver/DriverDashboard.php' : 'GuideDashboard.php' ?>">Back to Dashboard</a>
    </section>
  </main>

  <script>
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('.card').forEach((c,i)=>{
      const d = i*50;
      if (!prefersReduced) setTimeout(()=>c.classList.add('in'), d);
      else c.classList.add('in');
    });
  </script>
</body>
</html>
