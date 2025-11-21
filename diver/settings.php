<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require '../includes/db.php';
include '../header.php';

// Check if diver is logged in
if (!isset($_SESSION['diver_id'])) {
    header("Location: login_diver.php");
    exit;
}

$diver_id = $_SESSION['diver_id'];

// Fetch diver information
$stmt = $conn->prepare("SELECT fullname, email, specialty, profile_pic FROM divers WHERE id = ?");
$stmt->bind_param("i", $diver_id);
$stmt->execute();
$diver = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $specialty = trim($_POST['specialty']);

    // Update profile image if uploaded
    $profile_pic = $diver['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        // Use the admin/uploads directory (same as where diver pics are stored)
        $upload_dir = __DIR__ . '/../admin/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['profile_pic']['name']);
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($file_tmp, $file_path)) {
            // Store just the filename (not the full path)
            // This matches how the dashboard displays images: ../admin/uploads/{filename}
            $profile_pic = $file_name;
        }
    }

    // Update diver record
    $update = $conn->prepare("UPDATE divers SET fullname = ?, email = ?, specialty = ?, profile_pic = ? WHERE id = ?");
    $update->bind_param("ssssi", $fullname, $email, $specialty, $profile_pic, $diver_id);

    if ($update->execute()) {
        $success = "Profile updated successfully!";
        $diver['fullname'] = $fullname;
        $diver['email'] = $email;
        $diver['specialty'] = $specialty;
        $diver['profile_pic'] = $profile_pic;
    } else {
        $error = "Failed to update profile.";
    }
    $update->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM divers WHERE id = ?");
    $stmt->bind_param("i", $diver_id);
    $stmt->execute();
    $stmt->bind_result($db_password);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($current_password, $db_password)) {
        $error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE divers SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed, $diver_id);
        $update->execute();
        $update->close();
        $success = "Password changed successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diver Settings | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

<header class="bg-blue-700 text-white shadow-md">
    <div class="container mx-auto flex items-center justify-between p-4">
        <div class="flex items-center gap-2">
            <a href="../diver/diver_dashboard.php">
                <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-12">
            </a>
        </div>

        <nav class="hidden md:flex items-center gap-4">
            <a href="../diver/diver_dashboard.php" class="hover:bg-blue-800 px-3 py-2 rounded">Dashboard</a>
            <a href="settings.php" class="bg-white text-blue-700 px-3 py-2 rounded font-semibold">Settings</a>
            <a href="#" onclick="confirmLogout(event)" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition-all shadow-md">Logout</a>
        </nav>
        <button id="mobileMenuBtn" class="md:hidden">
            <svg class="w-6 h-6" fill="white" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
</header>

<div id="mobileMenu" class="hidden md:hidden bg-blue-700 text-white p-4 space-y-2">
    <a href="diver_dashboard.php" class="block hover:bg-blue-800 px-3 py-2 rounded">Dashboard</a>
    <a href="settings.php" class="block bg-white text-blue-700 px-3 py-2 rounded font-semibold">Settings</a>
    <a href="#" onclick="confirmLogout(event)" class="block bg-red-500 hover:bg-red-600 px-4 py-3 rounded-lg transition-all">Logout</a>
</div>

<main class="flex-grow container mx-auto p-6">
    <h1 class="text-2xl font-bold text-blue-700 mb-4">Account Settings</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 border border-green-300 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 text-red-700 border border-red-300 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- PROFILE SETTINGS -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-lg font-semibold text-blue-700 mb-4">Profile Information</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div class="flex flex-col md:flex-row gap-6 items-center">
                <div class="w-32 h-32 rounded-full overflow-hidden bg-gray-200">
                    <img src="../admin/uploads/<?= htmlspecialchars($diver['profile_pic'] ?: 'default.png') ?>" alt="Profile" class="object-cover w-full h-full">
                </div>
                <div class="flex flex-col flex-1 space-y-3">
                    <label class="font-semibold">Full Name</label>
                    <input type="text" name="fullname" value="<?= htmlspecialchars($diver['fullname']) ?>" required class="border rounded px-3 py-2 w-full">

                    <label class="font-semibold">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($diver['email']) ?>" required class="border rounded px-3 py-2 w-full">

                    <label class="font-semibold">Specialty</label>
                    <input type="text" name="specialty" value="<?= htmlspecialchars($diver['specialty']) ?>" placeholder="e.g., Open Water, Deep Dive" class="border rounded px-3 py-2 w-full">

                    <label class="font-semibold">Profile Picture</label>
                    <input type="file" name="profile_pic" accept="image/*" class="border rounded px-3 py-2 w-full">
                </div>
            </div>

            <button type="submit" name="update_profile" class="bg-blue-700 hover:bg-blue-800 text-white px-6 py-2 rounded font-semibold mt-4">
                Save Changes
            </button>
        </form>
    </div>

    <!-- PASSWORD SETTINGS -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-blue-700 mb-4">Change Password</h2>
        <form method="POST" class="space-y-4">
            <div>
                <label class="font-semibold">Current Password</label>
                <input type="password" name="current_password" required class="border rounded px-3 py-2 w-full">
            </div>
            <div>
                <label class="font-semibold">New Password</label>
                <input type="password" name="new_password" required class="border rounded px-3 py-2 w-full">
            </div>
            <div>
                <label class="font-semibold">Confirm New Password</label>
                <input type="password" name="confirm_password" required class="border rounded px-3 py-2 w-full">
            </div>
            <button type="submit" name="change_password" class="bg-blue-700 hover:bg-blue-800 text-white px-6 py-2 rounded font-semibold">
                Change Password
            </button>
        </form>
    </div>
</main>

<footer class="bg-blue-700 text-white text-center py-3 mt-auto">
    <p>&copy; <?= date('Y') ?> DiveConnect. All rights reserved.</p>
</footer>

<script>
document.getElementById('mobileMenuBtn').addEventListener('click', () => {
    document.getElementById('mobileMenu').classList.toggle('hidden');
});

function confirmLogout(e) {
    e.preventDefault();
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}
</script>

</body>
</html>