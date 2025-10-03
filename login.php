<?php
session_start();

// Redirect logged-in users to index.php
if (isset($_SESSION['User_ID'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/database.php';
try {
    $conn = getMySQLiConnection();
} catch (Exception $e) {
    die("Connection Failed: " . $e->getMessage());
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ===== REGISTER =====
    if (isset($_POST['register'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $profile = 'default.png'; // Default profile image
        $user_type = 'User';

        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM user WHERE Email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Email already registered.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO user (Username, Email, Password, Phone_No, User_Profile, User_Type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $email, $hashed_password, $phone, $profile, $user_type);
                if ($stmt->execute()) {
                    $success = "Registration successful. Please login.";
                } else {
                    $error = "Something went wrong. Try again.";
                }
            }
        }
    }

    // ===== LOGIN =====
    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT * FROM user WHERE Email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['Password'])) {
                $_SESSION['User_ID'] = $user['User_ID'];
                $_SESSION['Username'] = $user['Username'];
                $_SESSION['User_Type'] = $user['User_Type'];
                header("Location: index.php"); // Redirect after login
                exit;
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Email not registered.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Explore Ceylon - Login & Register</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script type="module" src="https://unpkg.com/@splinetool/viewer@1.10.38/build/spline-viewer.js"></script>
  <link rel="stylesheet" href="Styles/login.css">
</head>
<body>
  <!-- Background Layers -->
  <div id="background-blur"></div>
  <div id="background-overlay"></div>

  <div class="wrapper">
    <div class="container" id="formBox">

      <!-- Login Form -->
      <div class="form-container login-container">
        <?php
        if ($error) echo '<div class="message" style="color:white;text-align:center;">'.$error.'</div>';
        if ($success) echo '<div class="message" style="color:green;text-align:center;margin-bottom:10px;">'.$success.'</div>';
        ?>
        <form method="POST">
          <h2>Login</h2>
          <input type="email" name="email" placeholder="Email" required />
          <input type="password" name="password" placeholder="Password" required />
          <button type="submit" name="login" class="submit-bt">Login</button>
          <button type="button" class="toggle-btn" onclick="showRegister()">Don't have an account? Register</button>
        </form>
      </div>

      <!-- Register Form -->
      <div class="form-container register-container">
        <form method="POST">
          <h2>Register</h2>
          <input type="text" name="username" placeholder="Username" required />
          <input type="email" name="email" placeholder="Email" required />
          <input type="tel" name="phone" placeholder="Contact Number"/>
          <input type="password" name="password" placeholder="Password" required />
          <input type="password" name="confirm_password" placeholder="Confirm Password" required />
          <button type="submit" name="register" class="submit-bt">Register</button>
          <button type="button" class="toggle-btn" onclick="showLogin()">Already have an account? Login</button>
        </form>
      </div>

    </div>

    <div class="branding-container">
      <div class="spline-wrapper">
        <spline-viewer url="https://prod.spline.design/vqPMnC03AuaXUuVX/scene.splinecode"></spline-viewer>
      </div>
      <h1>EXPLORE<br>CEYLON</h1>
      <div class="box"></div>
    </div>
  </div>

  <script>
    const formBox = document.getElementById('formBox');
    function showRegister() { formBox.classList.add('active'); }
    function showLogin() { formBox.classList.remove('active'); }
  </script>
</body>
</html>
