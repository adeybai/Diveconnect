<?php
session_start();
include("../includes/db.php");

if(!isset($_SESSION['admin_id'])){
    header("Location: login_admin.php");
    exit;
}

// ---- Helper: check if a column exists
function columnExists(mysqli $conn, string $table, string $column): bool {
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $res = $conn->query($sql);
    return ($res && $res->num_rows === 1);
}

// Determine if current logged-in admin is SUPER
$isRoleColumn = columnExists($conn, 'admins', 'role');
$isSuper = false;

if ($isRoleColumn) {
    $stmt = $conn->prepare("SELECT role FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $cur = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $isSuper = isset($cur['role']) && $cur['role'] === 'super';
} else {
    // Fallback: treat admin_id = 1 as super admin
    $isSuper = ($_SESSION['admin_id'] == 1);
}

if(!$isSuper){
    die("Only Super Admin can delete accounts.");
}

if(!isset($_GET['id'])){
    header("Location: admin_dashboard.php?section=admins&view=active");
    exit;
}

$admin_id = intval($_GET['id']);

// Prevent deleting yourself
if($admin_id == $_SESSION['admin_id']){
    die("You cannot delete your own account.");
}

// Fetch target admin
$selectCols = "admin_id, email, password, created_at";
if ($isRoleColumn) $selectCols .= ", role";
$stmt = $conn->prepare("SELECT $selectCols FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if(!$admin){
    header("Location: admin_dashboard.php?section=admins&view=active");
    exit;
}

// Never allow deleting a super admin account
$targetIsSuper = false;
if ($isRoleColumn) {
    $targetIsSuper = (isset($admin['role']) && $admin['role'] === 'super');
} else {
    $targetIsSuper = ($admin['admin_id'] == 1);
}
if($targetIsSuper){
    die("You cannot delete a Super Admin account.");
}

// Insert to archive
$ins = $conn->prepare("INSERT INTO admins_archive (admin_id, email, password, created_at) VALUES (?, ?, ?, ?)");
$ins->bind_param("isss", $admin['admin_id'], $admin['email'], $admin['password'], $admin['created_at']);
$ins->execute();
$ins->close();

// Delete from main table
$del = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
$del->bind_param("i", $admin_id);
$del->execute();
$del->close();

header("Location: admin_dashboard.php?section=admins&view=active");
exit;
