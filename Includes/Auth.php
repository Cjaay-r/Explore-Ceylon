<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['User_ID']);
}

function isAdmin(): bool {
    return isset($_SESSION['User_Type']) && $_SESSION['User_Type'] === 'Admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header("Location: /login.php?next={$next}");
        exit;
    }
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header("Location: /index.php");
        exit;
    }
}
