<?php
session_start();
include("../includes/db.php");

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])){
    header("Location: login_admin.php");
    exit;
}

// Get admin ID from URL
$admin_id = $_GET['id'] ?? null;
if(!$admin_id){
    die("Admin ID is required.");
}

// Fetch admin info
$stmt = $conn->prepare("SELECT admin_id, email FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if(!$admin){
    die("Admin not found.");
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $_POST['email'];
    $password = $_POST['password'];

    if(!empty($password)){
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt2 = $conn->prepare("UPDATE admins SET email = ?, password = ? WHERE admin_id = ?");
        $stmt2->bind_param("ssi", $email, $hashedPassword, $admin_id);
    } else {
        $stmt2 = $conn->prepare("UPDATE admins SET email = ? WHERE admin_id = ?");
        $stmt2->bind_param("si", $email, $admin_id);
    }

    if($stmt2->execute()){
        header("Location: admin_dashboard.php?section=admins");
        exit;
    } else {
        $error = "Failed to update admin.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center items-start p-10">
    <div class="bg-white p-6 rounded-lg shadow w-full max-w-md">
        <h2 class="text-2xl font-bold mb-4">Edit Admin</h2>
        <?php if(isset($error)): ?>
            <p class="text-red-500 mb-4"><?= $error ?></p>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-4">
                <label class="block mb-1 font-medium">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required class="w-full border p-2 rounded">
            </div>
            <div class="mb-4">
                <label class="block mb-1 font-medium">Password <span class="text-gray-500 text-sm">(leave blank to keep current)</span></label>
                <input type="password" name="password" class="w-full border p-2 rounded">
            </div>
            <div class="flex justify-between">
                <a href="admin_dashboard.php?section=admins" class="px-4 py-2 bg-gray-400 text-white rounded hover:bg-gray-500">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Update Admin</button>
            </div>
        </form>
    </div>
</body>
</html>
