<?php
session_start();
include("../includes/db.php");

if(!isset($_SESSION['admin_id'])){
    header("Location: login_admin.php");
    exit;
}

if(isset($_GET['id'])){
    $admin_id = intval($_GET['id']);

    // Fetch from archive
    $stmt = $conn->prepare("SELECT * FROM admins_archive WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if($admin){
        // Insert back to admins
        $stmt2 = $conn->prepare("INSERT INTO admins (admin_id, email, password, created_at) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("isss", $admin['admin_id'], $admin['email'], $admin['password'], $admin['created_at']);
        $stmt2->execute();

        // Delete from archive
        $stmt3 = $conn->prepare("DELETE FROM admins_archive WHERE admin_id = ?");
        $stmt3->bind_param("i", $admin_id);
        $stmt3->execute();
    }

    header("Location: admin_dashboard.php?section=admins&view=archive");
    exit;
}
?>
