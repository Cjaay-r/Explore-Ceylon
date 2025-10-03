<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = ""; // default for XAMPP/WAMP/MAMP
$database = "ceylon"; // use your DB name

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Connected successfully!";

/**
 * Get MySQLi Database Connection
 * @return mysqli
 */
function getMySQLiConnection() {
    global $conn;
    return $conn;
}

/**
 * Get PDO Database Connection
 * @return PDO
 */
function getPDOConnection() {
    global $servername, $username, $password, $database;
    
    $dsn = "mysql:host=$servername;dbname=$database;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    try {
        $pdo = new PDO($dsn, $username, $password, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("PDO Connection failed: " . $e->getMessage());
    }
}

/**
 * Close MySQLi connection (useful for cleanup)
 */
function closeMySQLiConnection() {
    global $conn;
    if ($conn && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
