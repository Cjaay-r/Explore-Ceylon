<?php
session_start();
require_once __DIR__ . '/Includes/config.php';
require_once __DIR__ . '/Includes/dbconnect.php';
require_once __DIR__ . '/Includes/stripe.php';
if (!isset($_SESSION['User_ID'])) {
    header('Location: login.php');
    exit;
}
use Stripe\Stripe;
use Stripe\Checkout\Session;
if (isset($_GET['cancel'])) {
    unset($_SESSION['rental_data']);
    $_SESSION['rv_flash'] = 'cancel';
    header('Location: rent_vehicle.php');
    exit;
}
if (isset($_GET['session_id']) && isset($_SESSION['rental_data'])) {
    Stripe::setApiKey(STRIPE_SECRET_KEY);
    try {
        $session = Session::retrieve($_GET['session_id']);
    } catch (Exception $e) {
        unset($_SESSION['rental_data']);
        $_SESSION['rv_flash'] = 'error';
        header('Location: rent_vehicle.php');
        exit;
    }
    if ($session && $session->payment_status === 'paid') {
        $d = $_SESSION['rental_data'];
        $uid = (int)$_SESSION['User_ID'];
        $stmt = $conn->prepare("
            INSERT INTO vehicle_rentals
            (Name, Email, NIC_or_Pass, Phone_No, Start_Date, End_Date, Start_Location, Payment_method, Payment_Status, Status, Vehicle_ID, User_ID)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Online', 'Paid', 'Pendding', ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param(
                "sssssssii",
                $d['Name'],
                $d['Email'],
                $d['NIC_or_Pass'],
                $d['Phone_No'],
                $d['Start_Date'],
                $d['End_Date'],
                $d['Start_Location'],
                $d['Vehicle_ID'],
                $uid
            );
            if ($stmt->execute()) {
                $_SESSION['rv_flash'] = 'success';
            } else {
                $_SESSION['rv_flash'] = 'error';
            }
            $stmt->close();
        } else {
            $_SESSION['rv_flash'] = 'error';
        }
        unset($_SESSION['rental_data']);
    } else {
        unset($_SESSION['rental_data']);
        $_SESSION['rv_flash'] = 'error';
    }
    header('Location: rent_vehicle.php');
    exit;
}
$_SESSION['rv_flash'] = 'error';
header('Location: rent_vehicle.php');
exit;
