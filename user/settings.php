<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../header.php';
require '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login_user.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user information with fields from registration only
$stmt = $conn->prepare("SELECT fullname, email, whatsapp, profile_pic, certify_agency, certification_level, diver_id_number FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $whatsapp = trim($_POST['whatsapp']);
    $certify_agency = trim($_POST['certify_agency']);
    $certify_agency_other = trim($_POST['certify_agency_other'] ?? '');
    $certification_level = trim($_POST['certification_level']);
    $certification_level_other = trim($_POST['certification_level_other'] ?? '');
    $diver_id_number = trim($_POST['diver_id_number']);

    // Use other agency name if "Other" is selected
    if ($certify_agency === 'Other' && !empty($certify_agency_other)) {
        $certify_agency = $certify_agency_other;
    }

    // Use other certification level if "Other" is selected
    if ($certification_level === 'Other' && !empty($certification_level_other)) {
        $certification_level = $certification_level_other;
    }

    $profile_pic = $user['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['profile_pic']['name']);
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($file_tmp, $file_path)) {
            $profile_pic = 'uploads/' . $file_name;
        }
    }

    $update = $conn->prepare("UPDATE users SET fullname = ?, email = ?, whatsapp = ?, profile_pic = ?, certify_agency = ?, certification_level = ?, diver_id_number = ? WHERE id = ?");
    $update->bind_param("sssssssi", $fullname, $email, $whatsapp, $profile_pic, $certify_agency, $certification_level, $diver_id_number, $user_id);

    if ($update->execute()) {
        $success = "Profile updated successfully!";
        $user['fullname'] = $fullname;
        $user['email'] = $email;
        $user['whatsapp'] = $whatsapp;
        $user['profile_pic'] = $profile_pic;
        $user['certify_agency'] = $certify_agency;
        $user['certification_level'] = $certification_level;
        $user['diver_id_number'] = $diver_id_number;
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

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
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
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed, $user_id);
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
<title>User Settings | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    body {
        background-image: url('../assets/images/dive background.jpg');
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
        background-attachment: fixed;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideIn {
        from { opacity: 0; transform: translateX(-20px); }
        to { opacity: 1; transform: translateX(0); }
    }

    @keyframes scaleIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }

    .animate-fadeIn {
        animation: fadeIn 0.4s ease-out;
    }

    .animate-slideIn {
        animation: slideIn 0.5s ease-out;
    }

    .animate-scaleIn {
        animation: scaleIn 0.3s ease-out;
    }

    .card-hover {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .btn-primary {
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn-primary:hover::before {
        width: 300px;
        height: 300px;
    }

    .profile-img {
        transition: transform 0.3s ease;
    }

    .profile-img:hover {
        transform: scale(1.05);
    }

    .glass-effect {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }

    .mobile-menu-transition {
        transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
    }

    .input-focus {
        transition: all 0.3s ease;
    }

    .input-focus:focus {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .section-divider {
        background: linear-gradient(to right, transparent, #3b82f6, transparent);
        height: 2px;
        margin: 2rem 0;
    }
</style>
</head>
<body class="min-h-screen flex flex-col">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow-lg sticky top-0 z-40 animate-fadeIn">
    <div class="container mx-auto flex items-center justify-between p-4">
        <a href="user_dashboard.php" class="flex items-center gap-2 hover:opacity-90 transition-opacity">
            <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-12">
           
        </a>

        <nav class="hidden md:flex items-center gap-2">
            <a href="user_dashboard.php" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Dashboard</a>
            <a href="settings.php" class="bg-white text-blue-700 px-4 py-2 rounded-lg font-semibold hover:bg-blue-50 transition-all">Settings</a>
            <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Tidechart</a>
            <a href="../explore.php" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Explore</a>
            <!--<a href="../book.php" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Book</a>-->
            <a href="../about.php" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">About</a>
            <a href="#" onclick="confirmLogout(event)" class="block bg-red-500 hover:bg-red-600 px-4 py-3 rounded-lg transition-all">Logout</a>
        </nav>

        <button id="mobileMenuBtn" class="md:hidden p-2 hover:bg-blue-800 rounded-lg transition-all">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>
</header>

<!-- MOBILE MENU -->
<div id="mobileMenu" class="hidden md:hidden bg-blue-700 text-white shadow-lg mobile-menu-transition overflow-hidden">
    <div class="p-4 space-y-2">
        <a href="user_dashboard.php" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">Dashboard</a>
        <a href="settings.php" class="block bg-white text-blue-700 px-4 py-3 rounded-lg font-semibold">Settings</a>
        <a href="../tidechart.php" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">Tidechart</a>
        <a href="../explore.php" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">Explore</a>
        <a href="../book.php" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">Book</a>
        <a href="../about.php" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">About</a>
        <a href="#" onclick="confirmLogout(event)" class="block bg-red-500 hover:bg-red-600 px-4 py-3 rounded-lg transition-all">Logout</a>
    </div>
</div>

<!-- MAIN -->
<main class="flex-grow container mx-auto p-6 animate-fadeIn">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-white flex items-center gap-3">
            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"/>
            </svg>
            Account Settings
        </h1>
        <p class="text-white mt-2">Manage your profile information and security settings</p>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 border-2 border-green-400 p-4 rounded-lg mb-6 flex items-center gap-3 animate-slideIn">
            <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
            </svg>
            <span class="font-semibold"><?= htmlspecialchars($success) ?></span>
        </div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 text-red-700 border-2 border-red-400 p-4 rounded-lg mb-6 flex items-center gap-3 animate-slideIn">
            <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
            </svg>
            <span class="font-semibold"><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- PROFILE SETTINGS -->
    <div class="glass-effect rounded-2xl shadow-xl p-8 mb-8 card-hover animate-scaleIn">
        <div class="flex items-center gap-3 mb-6">
            <svg class="w-7 h-7 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
            </svg>
            <h2 class="text-2xl font-bold text-gray-800">Profile Information</h2>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="flex flex-col md:flex-row gap-8 items-start">
                <!-- Profile Picture -->
                <div class="flex flex-col items-center">
                    <div class="w-40 h-40 rounded-full overflow-hidden bg-gradient-to-br from-blue-400 to-blue-600 p-1 mb-4 profile-img">
                        <div class="w-full h-full rounded-full overflow-hidden bg-white">
                            <img src="<?= htmlspecialchars($user['profile_pic'] ? 'uploads/' . basename($user['profile_pic']) : '../assets/images/default.png') ?>" 
                                 alt="Profile" class="object-cover w-full h-full">
                        </div>
                    </div>
                    <label class="cursor-pointer bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-lg font-semibold btn-primary text-sm">
                        <input type="file" name="profile_pic" accept="image/*" class="hidden" id="profilePicInput">
                        Change Photo
                    </label>
                    <p class="text-xs text-gray-500 mt-2">JPG, PNG (Max 5MB)</p>
                </div>

                <!-- Form Fields -->
                <div class="flex-1 space-y-5 w-full">
                    <div>
                        <label class="block font-semibold text-gray-800 mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                            </svg>
                            Full Name
                        </label>
                        <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" 
                               required class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus">
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-800 mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                            </svg>
                            Email Address
                        </label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                               required class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus">
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-800 mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            WhatsApp
                        </label>
                        <input type="text" name="whatsapp" value="<?= htmlspecialchars($user['whatsapp']) ?>" 
                               placeholder="+63 123 456 7890" class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus">
                    </div>

                    <!-- Diver Certification Fields -->
                    <div class="section-divider"></div>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                                Certifying Agency
                            </label>
                            <select name="certify_agency" id="certify_agency" required 
                                    class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus">
                                <option value="">Select Organization</option>
                                <option value="PADI" <?= $user['certify_agency'] === 'PADI' ? 'selected' : '' ?>>PADI</option>
                                <option value="NAUI" <?= $user['certify_agency'] === 'NAUI' ? 'selected' : '' ?>>NAUI</option>
                                <option value="SSI" <?= $user['certify_agency'] === 'SSI' ? 'selected' : '' ?>>SSI</option>
                                <option value="CMAS" <?= $user['certify_agency'] === 'CMAS' ? 'selected' : '' ?>>CMAS</option>
                                <option value="Other" <?= !in_array($user['certify_agency'], ['PADI', 'NAUI', 'SSI', 'CMAS']) && !empty($user['certify_agency']) ? 'selected' : '' ?>>Other</option>
                            </select>
                            <input type="text" name="certify_agency_other" id="certify_agency_other" 
                                   value="<?= !in_array($user['certify_agency'], ['PADI', 'NAUI', 'SSI', 'CMAS']) && !empty($user['certify_agency']) ? htmlspecialchars($user['certify_agency']) : '' ?>" 
                                   placeholder="Type other organization" 
                                   class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus mt-2 <?= !in_array($user['certify_agency'], ['PADI', 'NAUI', 'SSI', 'CMAS']) && !empty($user['certify_agency']) ? '' : 'hidden' ?>">
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z"/>
                                </svg>
                                Certification Level
                            </label>
                            <select name="certification_level" id="certification_level" required 
                                    class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus">
                                <option value="">Select Certification Level</option>
                            </select>
                            <input type="text" name="certification_level_other" id="certification_level_other" 
                                   value="<?= $user['certification_level'] && !in_array($user['certification_level'], array_merge(...array_values($certificationLevels))) ? htmlspecialchars($user['certification_level']) : '' ?>" 
                                   placeholder="Specify your certification level" 
                                   class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus mt-2 <?= $user['certification_level'] && !in_array($user['certification_level'], array_merge(...array_values($certificationLevels))) ? '' : 'hidden' ?>">
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-gray-800 mb-2 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z"/>
                            </svg>
                            Diver ID Number
                        </label>
                        <input type="text" name="diver_id_number" value="<?= htmlspecialchars($user['diver_id_number']) ?>" 
                               required class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus"
                               placeholder="Enter your official Diver ID">
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" name="update_profile" class="bg-blue-700 hover:bg-blue-800 text-white px-8 py-3 rounded-lg font-bold btn-primary shadow-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- PASSWORD SETTINGS -->
    <div class="glass-effect rounded-2xl shadow-xl p-8 card-hover animate-scaleIn">
        <div class="flex items-center gap-3 mb-6">
            <svg class="w-7 h-7 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
            </svg>
            <h2 class="text-2xl font-bold text-gray-800">Change Password</h2>
        </div>
        
        <form method="POST" class="space-y-5">
            <div>
                <label class="block font-semibold text-gray-800 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 a1 1 0 102 0 4 4 0 00-4-4z"/>
                    </svg>
                    Current Password
                </label>
                <input type="password" name="current_password" required 
                       class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus"
                       placeholder="Enter your current password">
            </div>

            <div class="section-divider"></div>

            <div>
                <label class="block font-semibold text-gray-800 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/>
                    </svg>
                    New Password
                </label>
                <input type="password" name="new_password" required 
                       class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus"
                       placeholder="Enter new password">
                <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters</p>
            </div>

            <div>
                <label class="block font-semibold text-gray-800 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                    </svg>
                    Confirm New Password
                </label>
                <input type="password" name="confirm_password" required 
                       class="border-2 border-gray-300 rounded-lg px-4 py-3 w-full focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus"
                       placeholder="Confirm your new password">
            </div>

            <div class="flex justify-end pt-4">
                <button type="submit" name="change_password" class="bg-blue-700 hover:bg-blue-800 text-white px-8 py-3 rounded-lg font-bold btn-primary shadow-lg flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    Change Password
                </button>
            </div>
        </form>
    </div>
</main>

<footer class="bg-blue-700 text-white text-center py-3 mt-auto">
    <p>&copy; <?= date('Y') ?> DiveConnect. All rights reserved.</p>
</footer>

<script>
// Certification levels data
const certificationLevels = {
    'PADI': ['Open Water Diver', 'Advanced Open Water Diver', 'Rescue Diver', 'Divemaster', 'Instructor'],
    'SSI': ['Open Water Diver', 'Advanced Adventurer', 'Stress & Rescue', 'Divemaster / Dive Guide', 'Instructor'],
    'NAUI': ['Scuba Diver (Open Water)', 'Advanced Scuba Diver', 'Rescue Scuba Diver', 'Divemaster', 'Instructor'],
    'CMAS': ['1-Star Diver', '2-Star Diver', '3-Star Diver', 'Instructor'],
    'Other': []
};

// Function to update certification levels based on selected agency
function updateCertificationLevels(agency, currentLevel = '') {
    const levelSelect = document.getElementById('certification_level');
    const levelOther = document.getElementById('certification_level_other');
    
    // Clear existing options except the first one
    while (levelSelect.options.length > 1) {
        levelSelect.remove(1);
    }
    
    if (agency === 'Other') {
        const otherOption = document.createElement('option');
        otherOption.value = 'Other';
        otherOption.textContent = 'Other (Specify below)';
        levelSelect.appendChild(otherOption);
        levelOther.classList.remove('hidden');
        levelOther.required = true;
        
        // If current level is not in standard options, select "Other"
        if (currentLevel && !certificationLevels['Other'].includes(currentLevel)) {
            levelSelect.value = 'Other';
            levelOther.value = currentLevel;
        }
    } else if (agency && certificationLevels[agency]) {
        certificationLevels[agency].forEach(level => {
            const option = document.createElement('option');
            option.value = level;
            option.textContent = level;
            option.selected = (level === currentLevel);
            levelSelect.appendChild(option);
        });
        levelOther.classList.add('hidden');
        levelOther.required = false;
        levelOther.value = '';
    } else {
        levelOther.classList.add('hidden');
        levelOther.required = false;
        levelOther.value = '';
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    const certifyAgency = document.getElementById('certify_agency');
    const certificationLevel = document.getElementById('certification_level');
    const currentAgency = '<?= $user['certify_agency'] ?>';
    const currentLevel = '<?= $user['certification_level'] ?>';
    
    // Initialize certification levels based on current agency
    updateCertificationLevels(currentAgency, currentLevel);
    
    // Show/hide other input for certify agency
    certifyAgency.addEventListener('change', function() {
        const certifyAgencyOther = document.getElementById('certify_agency_other');
        if (this.value === 'Other') {
            certifyAgencyOther.classList.remove('hidden');
            certifyAgencyOther.required = true;
        } else {
            certifyAgencyOther.classList.add('hidden');
            certifyAgencyOther.required = false;
            certifyAgencyOther.value = '';
        }
        updateCertificationLevels(this.value);
    });
    
    // Show/hide other input for certification level
    certificationLevel.addEventListener('change', function() {
        const certificationLevelOther = document.getElementById('certification_level_other');
        if (this.value === 'Other') {
            certificationLevelOther.classList.remove('hidden');
            certificationLevelOther.required = true;
        } else {
            certificationLevelOther.classList.add('hidden');
            certificationLevelOther.required = false;
            certificationLevelOther.value = '';
        }
    });
    
    // Mobile menu toggle
    document.getElementById('mobileMenuBtn').addEventListener('click', () => {
        document.getElementById('mobileMenu').classList.toggle('hidden');
    });
});

// Confirm logout -> go to logout.php
function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure you want to logout?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1d4ed8',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
}
</script>

</body>
</html>