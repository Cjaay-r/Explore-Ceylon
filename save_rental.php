<?php
header('Content-Type: application/json');
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "explore_ceylon_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status"=>"error", "message"=>"DB Connection Failed"]);
    exit;
}

$category       = $_POST['Category']; // comes from modal
$name           = $_POST['Name'];
$email          = $_POST['Email'];
$nic_or_pass    = $_POST['NIC_or_Pass'];
$phone_no       = $_POST['Phone_No'];
$start_date     = $_POST['Start_Date'];
$end_date       = $_POST['End_Date'];
$start_location = $_POST['Start_Location'];

$user_id = 1; // Replace with session login later

// 🔎 Find first available vehicle in the selected category
$sql = "SELECT v.Vehicle_ID 
        FROM vehicle v
        WHERE v.Category = ?
        AND v.Vehicle_ID NOT IN (
            SELECT vr.Vehicle_ID FROM vehicle_rentals vr
            WHERE NOT (vr.End_Date < ? OR vr.Start_Date > ?)
        )
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $category, $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["status"=>"error", "message"=>"❌ No $category available for selected dates"]);
    exit;
}

$vehicle = $res->fetch_assoc();
$vehicle_id = $vehicle['Vehicle_ID'];

// ✅ Insert booking
$sql = "INSERT INTO vehicle_rentals 
        (Name, Email, NIC_or_Pass, Phone_No, Start_Date, End_Date, Start_Location, Vehicle_ID, User_ID)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssssii", $name, $email, $nic_or_pass, $phone_no, $start_date, $end_date, $start_location, $vehicle_id, $user_id);

if($stmt->execute()){
    echo json_encode(["status"=>"success", "message"=>"✅ Booking Confirmed!"]);
} else {
    echo json_encode(["status"=>"error", "message"=>"⚠️ Error: ".$stmt->error]);
}

$stmt->close();
$conn->close();
