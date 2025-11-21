<?php
session_start();
include("../includes/db.php");

if(!isset($_SESSION['admin_id'])){
    header("Location: login_admin.php");
    exit;
}

if(isset($_GET['id'])){
    $admin_id = intval($_GET['id']);

    // Prevent deleting yourself (optional)
    if($admin_id == $_SESSION['admin_id']){
        die("You cannot delete your own account.");
    }

    // Delete permanently from archive
    $stmt = $conn->prepare("DELETE FROM admins_archive WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();

    header("Location: admin_dashboard.php?section=admins&view=archive");
    exit;
}
?>
