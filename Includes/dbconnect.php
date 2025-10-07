<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = ""; // default for XAMPP/WAMP/MAMP
$database = "explore_ceylon_db"; // use your DB name

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
