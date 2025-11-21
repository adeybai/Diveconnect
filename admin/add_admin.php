<?php
session_start();
include("../includes/db.php");

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])){
    header("Location: login_admin.php");
    exit;
}

// Initialize error/success messages
$success = $error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validation
    if(empty($email) || empty($password) || empty($confirm_password)){
        $error = "All fields are required.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $error = "Invalid email format.";
    } elseif($password !== $confirm_password){
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if($stmt->num_rows > 0){
            $error = "Email already exists.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert into database
            $stmt2 = $conn->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
            $stmt2->bind_param("ss", $email, $hashed_password);
            if($stmt2->execute()){
                $success = "Admin added successfully!";
            } else {
                $error = "Failed to add admin.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex h-screen bg-gray-100">
    <?php include('admin_sidebar.php'); ?>

    <main class="flex-1 p-6 overflow-y-auto">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Add New Admin</h2>

        <?php if($error): ?>
            <div class="bg-red-200 text-red-800 p-3 rounded mb-4"><?= $error ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="bg-green-200 text-green-800 p-3 rounded mb-4"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" class="bg-white p-6 rounded-lg shadow w-full max-w-md">
            <div class="mb-4">
                <label class="block mb-1">Email</label>
                <input type="email" name="email" class="w-full border rounded p-2" required>
            </div>
            <div class="mb-4">
                <label class="block mb-1">Password</label>
                <input type="password" name="password" class="w-full border rounded p-2" required>
            </div>
            <div class="mb-4">
                <label class="block mb-1">Confirm Password</label>
                <input type="password" name="confirm_password" class="w-full border rounded p-2" required>
            </div>
            <div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">➕ Add Admin</button>
            </div>
        </form>
    </main>
</body>
</html>
