<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/dbconnect.php';

$user = null;
$message = "";

if (isLoggedIn()) {
    $uid = (int)$_SESSION['User_ID'];
    $stmt = $conn->prepare("SELECT * FROM user WHERE User_ID=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['logout'])) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            header("Location: " . url('index.php'));
            exit;
        }

        if (isset($_POST['save_profile'])) {
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $phone    = trim($_POST['phone'] ?? '');
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';

            $profile_picture = $user['User_Profile'];
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
                $safeBase = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', basename($_FILES['profile_pic']['name']));
                $file_name = time() . '_' . $safeBase;
                $target = __DIR__ . '/../uploads/UserProfiles/' . $file_name;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target)) {
                    $profile_picture = $file_name;
                } else {
                    $message = "Failed to upload profile picture.";
                }
            }

            if ($message === "") {
                if (!empty($old_password) && !empty($new_password)) {
                    if (password_verify($old_password, $user['Password'])) {
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE user SET Username=?, Email=?, Phone_No=?, Password=?, User_Profile=? WHERE User_ID=?");
                        $stmt->bind_param("sssssi", $username, $email, $phone, $hashed, $profile_picture, $uid);
                    } else {
                        $message = "Old password is incorrect!";
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE user SET Username=?, Email=?, Phone_No=?, User_Profile=? WHERE User_ID=?");
                    $stmt->bind_param("ssssi", $username, $email, $phone, $profile_picture, $uid);
                }

                if (!isset($message) || $message === "") {
                    if ($stmt->execute()) {
                        $message = "Profile updated successfully!";
                        $stmt = $conn->prepare("SELECT * FROM user WHERE User_ID=?");
                        $stmt->bind_param("i", $uid);
                        $stmt->execute();
                        $user = $stmt->get_result()->fetch_assoc();
                        $_SESSION['Username'] = $user['Username'];
                    }
                }
            }
        }
    }
}

function profile_img_src(array $user): string {
    $file = trim((string)($user['User_Profile'] ?? ''));
    if ($file === '' || strtolower($file) === 'defaultuser.jpg') {
        return url('Images/defaultuser.jpg');
    }
    return url('uploads/UserProfiles/' . $file);
}
?>
<style>
.site-header{width:100%;position:sticky;top:0;z-index:1000}
.topbar{width:100%;background:#111;color:#fff;display:flex;justify-content:center;gap:25px;padding:8px 0;font-size:14px}
.topbar a{color:#ccc;text-decoration:none}
.topbar a:hover{color:#fff}
.navbar{width:100%;background:#00bcd4;display:flex;align-items:center;justify-content:space-between;padding:12px 18px}
.logo a{color:#fff;text-decoration:none;font-size:20px;font-weight:700}
#nav-toggle{display:none}
.links{list-style:none;display:flex;gap:18px;align-items:center;margin:0;padding:0}
.links a{color:#fff;text-decoration:none;font-size:15px}
.links a:hover{color:#ffeb3b}
.has-dropdown{position:relative}
.has-dropdown>.dropdown{display:none;position:absolute;top:100%;left:0;background:#00bcd4;min-width:180px;border-radius:6px;overflow:hidden}
.has-dropdown:hover>.dropdown{display:block}
.dropdown li a{display:block;padding:10px 14px;border-top:1px solid rgba(255,255,255,.2)}
.auth-area{display:flex;align-items:center;gap:10px}
.btn{padding:8px 12px;border-radius:6px;border:1px solid #fff;color:#fff;text-decoration:none;font-size:14px}
.btn-signup{background:#fff;color:#00bcd4;border-color:#fff}
.profile-icon{width:40px;height:40px;background:linear-gradient(135deg,#00bcd4,#009688);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;text-decoration:none}
.hamburger{display:none;cursor:pointer}
.hamburger span{height:3px;width:24px;background:#fff;display:block;margin:5px 0}
@media(max-width:900px){
  .links{position:absolute;left:0;right:0;top:90px;background:#00bcd4;flex-direction:column;display:none;padding:10px 0}
  #nav-toggle:checked ~ .links{display:flex}
  .hamburger{display:block}
}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.75);justify-content:center;align-items:center;z-index:2000;backdrop-filter:blur(5px)}
.modal-content{background:#fff;padding:35px 45px;border-radius:20px;width:450px;max-width:95%;text-align:center;position:relative;box-shadow:0 15px 40px rgba(0,0,0,.4)}
.modal-content .close{position:absolute;top:15px;right:20px;font-size:26px;cursor:pointer;color:#444}
.modal-content img{width:130px;height:130px;border-radius:50%;object-fit:cover;margin-bottom:18px;border:4px solid #00bcd4}
.info-line{font-size:18px;color:#444;margin:12px 0;text-align:left;padding:10px;border-radius:8px;background:#f9f9f9}
.info-line i{color:#00bcd4;margin-right:10px}
.info-line span{font-weight:600;color:#222}
.modal-content input[type="text"],.modal-content input[type="email"],.modal-content input[type="password"],.modal-content input[type="file"]{width:95%;padding:12px 14px;margin:10px 0;border-radius:10px;border:1px solid #ccc;font-size:15px;box-sizing:border-box}
.modal-buttons{display:flex;justify-content:center;gap:12px;margin-top:20px}
.modal-buttons button{padding:12px 24px;border:none;border-radius:8px;cursor:pointer;font-size:15px;color:#fff}
.edit-btn{background:linear-gradient(135deg,#00bcd4,#009688)}
.logout-btn{background:linear-gradient(135deg,#f44336,#d32f2f)}
.save-btn{background:linear-gradient(135deg,#4caf50,#2e7d32)}
.cancel-btn{background:linear-gradient(135deg,#9e9e9e,#616161)}
.message{color:green;margin-bottom:10px;font-weight:600}
</style>

<header class="site-header">
  <div class="topbar">
    <a href="#">Phone: 0764129912</a>
    <a href="#">Email: Explore@gmail.com</a>
  </div>
  <nav class="navbar">
    <div class="logo"><a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Explore Ceylon</a></div>
    <input id="nav-toggle" type="checkbox" />
    <ul class="links">
      <li><a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Home</a></li>
      <li><a href="<?= htmlspecialchars(url('about.php'), ENT_QUOTES, 'UTF-8') ?>">About Us</a></li>

      <?php if (isAdmin()): ?>
      <li class="has-dropdown">
        <a href="<?= htmlspecialchars(url('destination.php'), ENT_QUOTES, 'UTF-8') ?>">Destinations</a>
        <ul class="dropdown">
          <li><a href="#">Add Destinations</a></li>
          <li><a href="#">Update Destinations</a></li>
          <li><a href="#">Delete Destinations</a></li>
        </ul>
      </li>
      <li class="has-dropdown">
        <a href="<?= htmlspecialchars(url('Packages.php'), ENT_QUOTES, 'UTF-8') ?>">Packages</a>
        <ul class="dropdown">
          <li><a href="<?= htmlspecialchars(url('form.php'), ENT_QUOTES, 'UTF-8') ?>">Add Package</a></li>
          <li><a href="<?= htmlspecialchars(url('Update_package.php'), ENT_QUOTES, 'UTF-8') ?>">Update Package</a></li>
          <li><a href="<?= htmlspecialchars(url('Package_Delete.php'), ENT_QUOTES, 'UTF-8') ?>">Delete Package</a></li>
        </ul>
      </li>
      <li class="has-dropdown">
        <a href="<?= htmlspecialchars(url('rent_vehicle.php'), ENT_QUOTES, 'UTF-8') ?>">Rent a Vehicle</a>
        <ul class="dropdown">
          <li><a href="<?= htmlspecialchars(url('vehicle_add.php'), ENT_QUOTES, 'UTF-8') ?>">Add Vehicle</a></li>
          <li><a href="<?= htmlspecialchars(url('vehicle_update.php'), ENT_QUOTES, 'UTF-8') ?>">Update Vehicle</a></li>
          <li><a href="<?= htmlspecialchars(url('vehicle_delete.php'), ENT_QUOTES, 'UTF-8') ?>">Delete Vehicle</a></li>
        </ul>
      </li>
      <?php else: ?>
      <li><a href="<?= htmlspecialchars(url('Packages.php'), ENT_QUOTES, 'UTF-8') ?>">Packages</a></li>
      <li><a href="<?= htmlspecialchars(url('rent_vehicle.php'), ENT_QUOTES, 'UTF-8') ?>">Rent a Vehicle</a></li>
      <?php endif; ?>

      <li><a href="#">Services</a></li>
      <li><a href="#">Travel Booking</a></li>
      <li><a href="<?= htmlspecialchars(url('contact.php'), ENT_QUOTES, 'UTF-8') ?>">Contact Us</a></li>
    </ul>

    <div class="auth-area">
      <?php if (isLoggedIn()): ?>
        <?php if (isAdmin()): ?>
          <a class="btn" href="<?= htmlspecialchars(url('Admin/AdminDashboard.php'), ENT_QUOTES, 'UTF-8') ?>">Admin</a>
        <?php endif; ?>
        <a class="profile-icon" href="javascript:void(0)" onclick="openModal()" aria-label="Profile">
          <i class="fa fa-user"></i>
        </a>
      <?php else: ?>
        <a class="btn btn-signin" href="<?= htmlspecialchars(url('login.php'), ENT_QUOTES, 'UTF-8') ?>">Sign in / Sign up</a>
      <?php endif; ?>
      <label for="nav-toggle" class="hamburger"><span></span><span></span><span></span></label>
    </div>
  </nav>
</header>

<?php if (isLoggedIn() && $user): ?>
<div class="modal" id="profileModal">
  <div class="modal-content">
    <span class="close" onclick="closeModal()">&times;</span>
    <?php if (!empty($message)) echo "<div class='message'>".htmlspecialchars($message, ENT_QUOTES, 'UTF-8')."</div>"; ?>

    <div id="viewMode">
      <img src="<?= htmlspecialchars(profile_img_src($user), ENT_QUOTES, 'UTF-8') ?>" alt="Profile Picture">
      <div class="info-line"><i class="fa fa-user"></i> <span>Username :-</span> <?= htmlspecialchars($user['Username'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="info-line"><i class="fa fa-envelope"></i> <span>Email :-</span> <?= htmlspecialchars($user['Email'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="info-line"><i class="fa fa-phone"></i> <span>Contact No :-</span> <?= htmlspecialchars($user['Phone_No'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="modal-buttons">
        <button type="button" class="edit-btn" onclick="switchToEdit()">Edit Profile</button>
        <form method="POST" style="display:inline;">
          <button type="submit" class="logout-btn" name="logout">Log Out</button>
        </form>
      </div>
    </div>

    <div id="editMode" style="display:none;">
      <form method="POST" enctype="multipart/form-data">
        <img src="<?= htmlspecialchars(profile_img_src($user), ENT_QUOTES, 'UTF-8') ?>" alt="Profile Picture">
        <input type="file" name="profile_pic" accept="image/*">
        <input type="text" name="username" value="<?= htmlspecialchars($user['Username'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Username" required>
        <input type="email" name="email" value="<?= htmlspecialchars($user['Email'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Email" required>
        <input type="text" name="phone" value="<?= htmlspecialchars($user['Phone_No'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Phone Number" required>
        <input type="password" name="old_password" placeholder="Old Password">
        <input type="password" name="new_password" placeholder="New Password">
        <div class="modal-buttons">
          <button type="submit" class="save-btn" name="save_profile">Save</button>
          <button type="button" class="cancel-btn" onclick="switchToView()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openModal(){document.getElementById('profileModal').style.display='flex'}
function closeModal(){document.getElementById('profileModal').style.display='none'}
function switchToEdit(){document.getElementById('viewMode').style.display='none';document.getElementById('editMode').style.display='block'}
function switchToView(){document.getElementById('editMode').style.display='none';document.getElementById('viewMode').style.display='block'}
window.addEventListener('click',function(e){const m=document.getElementById('profileModal');if(e.target===m){closeModal()}})
</script>
<?php endif; ?>
