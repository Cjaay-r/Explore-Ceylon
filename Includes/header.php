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

// ✅ Updated to use "Profile img def.jpg" from Images folder as default
function profile_img_src(array $user): string
{
  $file = trim((string)($user['User_Profile'] ?? ''));
  if ($file === '' || !file_exists(__DIR__ . '/../uploads/UserProfiles/' . $file)) {
    return url('Images/Profile img def.jpg');
  }
  return url('uploads/UserProfiles/' . $file);
}
?>
<style>
  .excy-site-header {
    width: 100%;
    position: sticky;
    top: 0;
    z-index: 1000
  }

  .excy-topbar {
    width: 100%;
    background: #111;
    color: #fff;
    display: flex;
    justify-content: center;
    gap: 25px;
    padding: 8px 0;
    font-size: 14px
  }

  .excy-topbar a {
    color: #ccc;
    text-decoration: none
  }

  .excy-topbar a:hover {
    color: #fff
  }

  .excy-navbar {
    width: 100%;
    background: #00bcd4;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px
  }

  .excy-logo a {
    color: #fff;
    text-decoration: none;
    font-size: 20px;
    font-weight: 700
  }

  #excy-nav-toggle {
    display: none
  }

  .excy-links {
    list-style: none;
    display: flex;
    gap: 18px;
    align-items: center;
    margin: 0;
    padding: 0
  }

  .excy-links a {
    color: #fff;
    text-decoration: none;
    font-size: 15px
  }

  .excy-links a:hover {
    color: #ffeb3b
  }

  .excy-has-dropdown {
    position: relative
  }

  .excy-has-dropdown>.excy-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: #00bcd4;
    min-width: 180px;
    border-radius: 6px;
    overflow: hidden
  }

  .excy-has-dropdown:hover>.excy-dropdown {
    display: block
  }

  .excy-dropdown li a {
    display: block;
    padding: 10px 14px;
    border-top: 1px solid rgba(255, 255, 255, .2)
  }

  .excy-auth-area {
    display: flex;
    align-items: center;
    gap: 10px
  }

  .excy-btn {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #fff;
    color: #fff;
    text-decoration: none;
    font-size: 14px
  }

  .excy-btn-signup {
    color: #ffffffff;
    border-color: #fff
  }

  .excy-profile-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #00bcd4, #009688);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    text-decoration: none
  }

  .excy-hamburger {
    display: none;
    cursor: pointer
  }

  .excy-hamburger span {
    height: 3px;
    width: 24px;
    background: #fff;
    display: block;
    margin: 5px 0
  }

  @media(max-width:900px) {
    .excy-links {
      position: absolute;
      left: 0;
      right: 0;
      top: 90px;
      background: #00bcd4;
      flex-direction: column;
      display: none;
      padding: 10px 0
    }

    #excy-nav-toggle:checked~.excy-links {
      display: flex
    }

    .excy-hamburger {
      display: block
    }
  }

  .excy-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, .75);
    justify-content: center;
    align-items: center;
    z-index: 2000;
    backdrop-filter: blur(5px)
  }

  .excy-modal-content {
    background: #fff;
    padding: 35px 45px;
    border-radius: 20px;
    width: 450px;
    max-width: 95%;
    text-align: center;
    position: relative;
    box-shadow: 0 15px 40px rgba(0, 0, 0, .4)
  }

  .excy-modal-content .excy-close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 26px;
    cursor: pointer;
    color: #444
  }

  .excy-modal-content img {
    width: 130px;
    height: 130px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 18px;
    border: 4px solid #00bcd4
  }

  .excy-info-line {
    font-size: 18px;
    color: #444;
    margin: 12px 0;
    text-align: left;
    padding: 10px;
    border-radius: 8px;
    background: #f9f9f9
  }

  .excy-info-line i {
    color: #00bcd4;
    margin-right: 10px
  }

  .excy-info-line span {
    font-weight: 600;
    color: #222
  }

  .excy-modal-content input[type="text"],
  .excy-modal-content input[type="email"],
  .excy-modal-content input[type="password"],
  .excy-modal-content input[type="file"] {
    width: 95%;
    padding: 12px 14px;
    margin: 10px 0;
    border-radius: 10px;
    border: 1px solid #ccc;
    font-size: 15px;
    box-sizing: border-box
  }

  .excy-modal-buttons {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 20px
  }

  .excy-modal-buttons button {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 15px;
    color: #fff
  }

  .excy-edit-btn {
    background: linear-gradient(135deg, #00bcd4, #009688)
  }

  .excy-logout-btn {
    background: linear-gradient(135deg, #f44336, #d32f2f)
  }

  .excy-save-btn {
    background: linear-gradient(135deg, #4caf50, #2e7d32)
  }

  .excy-cancel-btn {
    background: linear-gradient(135deg, #9e9e9e, #616161)
  }

  .excy-message {
    color: green;
    margin-bottom: 10px;
    font-weight: 600
  }
</style>

<header class="excy-site-header">
  <div class="excy-topbar">
    <a href="#">Phone: 0764129912</a>
    <a href="#">Email: exploreceylon5015@gmail.com</a>
  </div>
  <nav class="excy-navbar">
    <div class="excy-logo"><a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Explore Ceylon</a></div>
    <input id="excy-nav-toggle" type="checkbox" />
    <ul class="excy-links">
      <li><a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Home</a></li>
      <li><a href="<?= htmlspecialchars(url('about.php'), ENT_QUOTES, 'UTF-8') ?>">About Us</a></li>
      <li><a href="<?= htmlspecialchars(url('destination.php'), ENT_QUOTES, 'UTF-8') ?>">Destinations</a></li>
      <li><a href="<?= htmlspecialchars(url('Packages.php'), ENT_QUOTES, 'UTF-8') ?>">Packages</a></li>
      <li><a href="<?= htmlspecialchars(url('CustomisedBookings.php'), ENT_QUOTES, 'UTF-8') ?>">Customize Your Trip</a></li>
      <?php if (isset($_SESSION['User_ID'])) echo '<li><a href="' . htmlspecialchars(url('MyBookings.php'), ENT_QUOTES, 'UTF-8') . '">My Bookings</a></li>'; ?>
      <li><a href="<?= htmlspecialchars(url('Tickets.php'), ENT_QUOTES, 'UTF-8') ?>">Buy Tickets For Destinations</a></li>
      <li><a href="<?= htmlspecialchars(url('rent_vehicle.php'), ENT_QUOTES, 'UTF-8') ?>">Rent a Vehicle</a></li>
      <li><a href="<?= htmlspecialchars(url('Services.php'), ENT_QUOTES, 'UTF-8') ?>">Services</a></li>
    </ul>

    <div class="excy-auth-area">
      <?php if (isLoggedIn()): ?>
        <?php if (isAdmin()): ?>
          <a class="excy-btn" href="<?= htmlspecialchars(url('Admin/AdminDashboard.php'), ENT_QUOTES, 'UTF-8') ?>">Admin</a>
        <?php endif; ?>
        <a class="excy-profile-icon" href="javascript:void(0)" onclick="openModal()" aria-label="Profile">
          <i class="fa fa-user"></i>
        </a>
      <?php else: ?>
        <a class="excy-btn excy-btn-signup" href="<?= htmlspecialchars(url('login.php'), ENT_QUOTES, 'UTF-8') ?>">Sign in / Sign up</a>
      <?php endif; ?>
      <label for="excy-nav-toggle" class="excy-hamburger"><span></span><span></span><span></span></label>
    </div>
  </nav>
</header>

<?php if (isLoggedIn() && $user): ?>
  <div class="excy-modal" id="profileModal">
    <div class="excy-modal-content">
      <span class="excy-close" onclick="closeModal()">&times;</span>
      <?php if (!empty($message)) echo "<div class='excy-message'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</div>"; ?>

      <div id="viewMode">
        <img src="<?= htmlspecialchars(profile_img_src($user), ENT_QUOTES, 'UTF-8') ?>" alt="Profile Picture">
        <div class="excy-info-line"><i class="fa fa-user"></i> <span>Username :-</span> <?= htmlspecialchars($user['Username'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="excy-info-line"><i class="fa fa-envelope"></i> <span>Email :-</span> <?= htmlspecialchars($user['Email'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="excy-info-line"><i class="fa fa-phone"></i> <span>Contact No :-</span> <?= htmlspecialchars($user['Phone_No'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="excy-modal-buttons">
          <button type="button" class="excy-edit-btn" onclick="switchToEdit()">Edit Profile</button>
          <form method="POST" style="display:inline;">
            <button type="submit" class="excy-logout-btn" name="logout">Log Out</button>
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
          <div class="excy-modal-buttons">
            <button type="submit" class="excy-save-btn" name="save_profile">Save</button>
            <button type="button" class="excy-cancel-btn" onclick="switchToView()">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <script>
    function openModal() {
      document.getElementById('profileModal').style.display = 'flex'
    }

    function closeModal() {
      document.getElementById('profileModal').style.display = 'none'
    }

    function switchToEdit() {
      document.getElementById('viewMode').style.display = 'none';
      document.getElementById('editMode').style.display = 'block'
    }

    function switchToView() {
      document.getElementById('editMode').style.display = 'none';
      document.getElementById('viewMode').style.display = 'block'
    }
    window.addEventListener('click', function(e) {
      const m = document.getElementById('profileModal');
      if (e.target === m) {
        closeModal()
      }
    })
  </script>
<?php endif; ?>