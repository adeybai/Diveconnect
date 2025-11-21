<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require '../includes/db.php';
require '../library/mailer.php';
include '../header.php';
use DiveConnect\Mailer;

if (!isset($_SESSION['user_id'])) {
    header("Location: login_user.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$vat_query = $conn->query("SELECT vat_percent FROM admins LIMIT 1");
$vatRow = $vat_query->fetch_assoc();
$vatPercent = 12.00;

// FIXED: Removed 'status' column from query since it doesn't exist in users table
$stmt = $conn->prepare("SELECT fullname, email, phone, whatsapp FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = $error = null;

// Step 1: Diver selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['diver_id']) && !isset($_POST['availability_id'])) {
    $diver_id = intval($_POST['diver_id']);

    $stmt = $conn->prepare("SELECT fullname, specialty, nationality, profile_pic, level, qr_code, price, max_pax FROM divers WHERE id = ?");
    $stmt->bind_param("i", $diver_id);
    $stmt->execute();
    $diver = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $diver['price'] = floatval($diver['price']);

    $gear_stmt = $conn->prepare("SELECT id, gear_name, price FROM diver_gears WHERE diver_id = ?");
    $gear_stmt->bind_param("i", $diver_id);
    $gear_stmt->execute();
    $gears = $gear_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $gear_stmt->close();

    // ✅ FIXED: Get available slots with destination information
    $check_destination = $conn->query("SHOW COLUMNS FROM availability LIKE 'destination_id'");
    if ($check_destination->num_rows > 0) {
        // New structure with destination
        $availability = $conn->prepare("
            SELECT a.id, a.available_date, a.available_time, a.start_time, a.status, 
                   a.max_slots, a.available_slots, a.booked_slots,
                   d.title AS destination_title, d.location AS destination_location
            FROM availability a 
            LEFT JOIN destinations d ON a.destination_id = d.destination_id
            WHERE a.diver_id = ? 
            AND a.available_slots > 0
            AND (
                (a.available_date > CURDATE()) 
                OR 
                (
                    a.available_date = CURDATE() 
                    AND ADDTIME(a.start_time, '-01:00:00') > CURTIME()
                )
            )
            ORDER BY a.available_date ASC, a.start_time ASC
        ");
    } else {
        // Old structure without destination
        $availability = $conn->prepare("
            SELECT a.id, a.available_date, a.available_time, a.start_time, a.status, 
                   a.max_slots, a.available_slots, a.booked_slots
            FROM availability a 
            WHERE a.diver_id = ? 
            AND a.available_slots > 0
            AND (
                (a.available_date > CURDATE()) 
                OR 
                (
                    a.available_date = CURDATE() 
                    AND ADDTIME(a.start_time, '-01:00:00') > CURTIME()
                )
            )
            ORDER BY a.available_date ASC, a.start_time ASC
        ");
    }
    $availability->bind_param("i", $diver_id);
    $availability->execute();
    $slots = $availability->get_result()->fetch_all(MYSQLI_ASSOC);
    $availability->close();
}

// Step 2: Booking confirmation - FIXED SLOT CALCULATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['availability_id'])) {
    $availability_id = intval($_POST['availability_id']);
    $diver_id = intval($_POST['diver_id']);
    $payment_method = $_POST['payment_method'] ?? '';
    $grand_total = isset($_POST['grand_total']) ? floatval($_POST['grand_total']) : 0;
    $pax_count = isset($_POST['pax_count']) ? intval($_POST['pax_count']) : 1;

    // ===== ENHANCED REAL-TIME VALIDATION =====
    $slot_query = $conn->prepare("
        SELECT available_date, available_time, start_time, max_slots, available_slots, booked_slots 
        FROM availability 
        WHERE id = ? AND available_slots >= ?
        AND (
            available_date > CURDATE() 
            OR 
            (
                available_date = CURDATE() 
                AND ADDTIME(start_time, '-01:00:00') > CURTIME()
            )
        )
    ");
    $slot_query->bind_param("ii", $availability_id, $pax_count);
    $slot_query->execute();
    $slot = $slot_query->get_result()->fetch_assoc();
    $slot_query->close();

    if (!$slot) {
        $error = "Selected time slot is no longer available or doesn't have enough capacity.";
    } else {
        // Check if selected slot is in the past
        $slot_time = !empty($slot['start_time']) ? $slot['start_time'] : explode(' - ', $slot['available_time'])[0];
        $slot_datetime = $slot['available_date'] . ' ' . $slot_time;
        
        if (strtotime($slot_datetime) <= time()) {
            $error = "Cannot book past time slots. Please select a future date and time.";
            // Update the slot status to completed
            $update_slot = $conn->prepare("UPDATE availability SET status = 'completed' WHERE id = ?");
            $update_slot->bind_param("i", $availability_id);
            $update_slot->execute();
            $update_slot->close();
        }
    }

    // If no validation errors, proceed with booking
    if (!$error) {
        // Validate PAX count against diver's max capacity
        $max_pax_check = $conn->prepare("SELECT max_pax FROM divers WHERE id = ?");
        $max_pax_check->bind_param("i", $diver_id);
        $max_pax_check->execute();
        $max_pax_result = $max_pax_check->get_result()->fetch_assoc();
        $max_pax_check->close();

        if ($pax_count > $max_pax_result['max_pax']) {
            $error = "Number of divers exceeds the maximum capacity of " . $max_pax_result['max_pax'] . " divers.";
        } else {
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

            $userSignature = '';
            $gcashReceipt = '';

            if (!empty($_FILES['user_signature']['name'])) {
                $userSignature = time() . '_sig_' . basename($_FILES['user_signature']['name']);
                move_uploaded_file($_FILES['user_signature']['tmp_name'], $targetDir . $userSignature);
            }
            if (!empty($_FILES['gcash_receipt']['name'])) {
                $gcashReceipt = time() . '_receipt_' . basename($_FILES['gcash_receipt']['name']);
                move_uploaded_file($_FILES['gcash_receipt']['tmp_name'], $targetDir . $gcashReceipt);
            }

            // Start transaction for atomic operations
            $conn->begin_transaction();

            try {
                // First calculate the new slot values
                $new_booked_slots = $slot['booked_slots'] + $pax_count;
                $new_available_slots = $slot['max_slots'] - $new_booked_slots;
                
                // ✅ FIXED: Only mark as fully_booked if no slots left, otherwise keep as available
                $new_status = ($new_available_slots <= 0) ? 'fully_booked' : 'available';

                // Insert booking
                $insert = $conn->prepare("INSERT INTO bookings (user_id, diver_id, booking_date, pax_count, status, user_signature, gcash_receipt, payment_method, grand_total)
                                          VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?)");
                $insert->bind_param("iisisssd", $user_id, $diver_id, $slot['available_date'], $pax_count, $userSignature, $gcashReceipt, $payment_method, $grand_total);

                if ($insert->execute()) {
                    $booking_id = $conn->insert_id;

                    // Add selected gears to booking
                    if (!empty($_POST['selected_gears'])) {
                        $gear_stmt = $conn->prepare("INSERT INTO booking_gears (booking_id, gear_id) VALUES (?, ?)");
                        foreach ($_POST['selected_gears'] as $gear_id) {
                            $gear_id = intval($gear_id);
                            $gear_stmt->bind_param("ii", $booking_id, $gear_id);
                            $gear_stmt->execute();
                        }
                        $gear_stmt->close();
                    }

                    // ✅ FIXED: UPDATE SLOT CAPACITY - CORRECT LOGIC
                    $update_slots = $conn->prepare("
                        UPDATE availability 
                        SET booked_slots = ?, 
                            available_slots = ?,
                            status = ?
                        WHERE id = ?
                    ");
                    $update_slots->bind_param("iisi", $new_booked_slots, $new_available_slots, $new_status, $availability_id);
                    $update_slots->execute();
                    
                    if ($update_slots->affected_rows === 0) {
                        throw new Exception("Failed to update availability slots.");
                    }
                    $update_slots->close();

                    // Get diver info for notification
                    $diver_query = $conn->prepare("SELECT email, fullname FROM divers WHERE id = ?");
                    $diver_query->bind_param("i", $diver_id);
                    $diver_query->execute();
                    $diver_info = $diver_query->get_result()->fetch_assoc();
                    $diver_query->close();

                    // Send notification email to diver
                    if ($diver_info) {
                        $mailer = new Mailer();
                        try {
                            $mailer->sendNewBookingNotification(
                                $diver_info['email'],
                                $diver_info['fullname'],
                                $user['fullname'],
                                $slot['available_date'],
                                $pax_count,
                                $grand_total
                            );
                        } catch (Exception $e) {
                            error_log('Booking notification email failed: ' . $e->getMessage());
                        }
                    }

                    $conn->commit();
                    $success = "Booking successfully submitted! Please wait for confirmation.";
                } else {
                    throw new Exception("Failed to submit booking.");
                }

                $insert->close();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to submit booking. Please try again. Error: " . $e->getMessage();
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
<title>Book Diver | DiveConnect</title>
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
        transform: translateY(-8px);
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

    .gear-checkbox-label {
        transition: all 0.3s ease;
    }

    .gear-checkbox-label:hover {
        background-color: #eff6ff;
        border-color: #3b82f6;
    }

    .input-focus {
        transition: all 0.3s ease;
    }

    .input-focus:focus {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .pax-button {
        transition: all 0.2s ease;
    }

    .pax-button:active {
        transform: scale(0.95);
    }

    .slot-info {
        font-size: 0.75rem;
        padding: 2px 6px;
        border-radius: 8px;
        margin-left: 8px;
    }
    
    .slot-available { background: #dcfce7; color: #166534; }
    .slot-limited { background: #fef3c7; color: #92400e; }
    .slot-full { background: #fee2e2; color: #991b1b; }
</style>
</head>
<body class="min-h-screen flex flex-col">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow-lg sticky top-0 z-40 animate-fadeIn">
    <div class="container mx-auto flex items-center justify-between p-4">
        <a href="../index.php" class="flex items-center gap-2 hover:opacity-90 transition-opacity">
            <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-12">
        </a>

        <nav class="hidden md:flex items-center gap-2">
            <a href="../explore.php" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Explore</a>
            <a href="../user/user_dashboard.php" class="bg-white text-blue-700 px-4 py-2 rounded-lg font-semibold hover:bg-blue-50 transition-all">Dashboard</a>
            <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Tidechart</a>
            <a href="../about.php" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">About</a>
            <a href="#" onclick="confirmLogout(event)" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition-all shadow-md">Logout</a>
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
        <a href="../explore.php" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">Explore</a>
        <a href="../user/user_dashboard.php" class="block bg-white text-blue-700 px-4 py-3 rounded-lg font-semibold">Dashboard</a>
        <a href="../tidechart.php" class="block hover:bg-blue-800 px:4 py-3 rounded-lg transition-all">Tidechart</a>
        <a href="../book.php" class="block hover:bg-blue-800 px:4 py-3 rounded-lg transition-all">Book</a>
        <a href="../about.php" class="block hover:bg-blue-800 px:4 py-3 rounded-lg transition-all">About</a>
        <a href="#" onclick="confirmLogout(event)" class="block bg-red-500 hover:bg-red-600 px-4 py-3 rounded-lg transition-all">Logout</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<main class="flex-grow flex justify-center items-start p-6 animate-fadeIn">
    <div class="w-full max-w-2xl glass-effect shadow-2xl rounded-2xl p-8 mt-6 animate-scaleIn">
        <a href="../user/user_dashboard.php" class="text-blue-700 font-semibold hover:text-blue-800 flex items-center gap-2 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Dashboard
        </a>
        
        <h1 class="text-3xl font-bold text-center mt-4 mb-6 text-blue-700">Book a Dive Session</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 text-red-700 border-2 border-red-400 rounded-lg p-4 mb-6 flex items-center gap-3 animate-slideIn">
                <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293-1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php elseif (isset($success)): ?>
            <div class="bg-green-100 text-green-700 border-2 border-green-400 rounded-lg p-4 mb-6 text-center animate-slideIn">
                <svg class="w-12 h-12 mx-auto mb-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="font-bold text-lg mb-2"><?= htmlspecialchars($success) ?></p>
                <p class="text-sm text-green-600">We'll notify you once the diver confirms your booking.</p>
            </div>
            <div class="text-center mt-6">
                <a href="../user/user_dashboard.php" class="bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-lg font-semibold btn-primary inline-flex items-center gap-2 shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        <?php elseif (isset($diver)): ?>
            <!-- DIVER INFO CARD -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl p-6 mb-6 text-white shadow-lg animate-slideIn">
                <div class="flex flex-col md:flex-row items-center gap-6">
                    <img src="<?= !empty($diver['profile_pic']) ? '../admin/uploads/' . htmlspecialchars($diver['profile_pic']) : '../assets/images/diver_default.png' ?>" 
                         class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-lg profile-img" alt="Diver">
                    <div class="flex-1 text-center md:text-left">
                        <h2 class="text-2xl font-bold mb-2"><?= htmlspecialchars($diver['fullname']) ?></h2>
                        <p class="text-blue-100 font-medium mb-2"><?= htmlspecialchars($diver['specialty'] ?? 'Professional Diver') ?></p>
                        <div class="flex flex-wrap gap-3 justify-center md:justify-start text-sm">
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">Level: <?= htmlspecialchars($diver['level'] ?? 'N/A') ?></span>
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">Nationality: <?= htmlspecialchars($diver['nationality'] ?? 'N/A') ?></span>
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full">Max Capacity: <?= intval($diver['max_pax'] ?? 6) ?> divers</span>
                        </div>
                        <div class="mt-3">
                            <span class="text-3xl font-bold">₱<?= number_format($diver['price'] ?? 0, 2) ?></span>
                            <span class="text-blue-100 text-sm ml-2">Base Rate (per person)</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($slots) > 0): ?>
                <form method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="return validateBooking()">
                    <input type="hidden" name="diver_id" value="<?= $diver_id ?>">

                    <!-- NUMBER OF DIVERS (PAX) -->
                    <div class="animate-fadeIn border-2 border-blue-300 rounded-xl p-5 bg-gradient-to-r from-blue-50 to-indigo-50">
                        <label class="block font-bold text-gray-800 mb-3 flex items-center gap-2">
                            <svg class="w-6 h-6 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                            Number of Divers (PAX)
                        </label>
                        <div class="flex items-center gap-4 bg-white rounded-lg p-4 shadow-md">
                            <button type="button" onclick="decreasePax()" class="pax-button bg-blue-600 hover:bg-blue-700 text-white w-12 h-12 rounded-full font-bold text-xl flex items-center justify-center shadow-md">
                                −
                            </button>
                            <input type="number" name="pax_count" id="paxCount" value="1" min="1" max="<?= intval($diver['max_pax'] ?? 6) ?>" required 
                                   class="w-24 text-center text-3xl font-bold text-blue-700 border-2 border-gray-300 rounded-lg py-2" readonly>
                            <button type="button" onclick="increasePax()" class="pax-button bg-blue-600 hover:bg-blue-700 text-white w-12 h-12 rounded-full font-bold text-xl flex items-center justify-center shadow-md">
                                +
                            </button>
                            <div class="ml-4">
                                <p class="text-sm text-gray-600">Maximum capacity:</p>
                                <p class="text-lg font-bold text-blue-700"><?= intval($diver['max_pax'] ?? 6) ?> divers</p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">💡 Price will be calculated per diver</p>
                    </div>

                    <!-- DATE & TIME SELECTION -->
                    <div class="animate-fadeIn">
                        <label class="block font-bold text-gray-800 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                            </svg>
                            Select Date, Time & Destination <span class="text-red-500">*</span>
                        </label>
                        <select name="availability_id" id="availabilitySelect" required 
                                class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus transition-all"
                                onchange="updateSlotInfo()">
                            <option value="">Choose a time slot</option>
                            <?php foreach ($slots as $slot): 
                                // Check slot capacity and status
                                $available_slots = $slot['available_slots'] ?? $slot['max_slots'] ?? 6;
                                $max_slots = $slot['max_slots'] ?? 6;
                                
                                // Determine slot status
                                if ($available_slots <= 0) {
                                    $slot_class = 'slot-full';
                                    $slot_text = 'Full';
                                } elseif ($available_slots <= 2) {
                                    $slot_class = 'slot-limited';
                                    $slot_text = 'Limited';
                                } else {
                                    $slot_class = 'slot-available';
                                    $slot_text = 'Available';
                                }
                                
                                // Get destination information
                                $destination_info = '';
                                if (isset($slot['destination_title']) && !empty($slot['destination_title'])) {
                                    $destination_info = " - " . htmlspecialchars($slot['destination_title']);
                                    if (isset($slot['destination_location']) && !empty($slot['destination_location'])) {
                                        $destination_info .= " (" . htmlspecialchars($slot['destination_location']) . ")";
                                    }
                                }
                            ?>
                                <option value="<?= $slot['id'] ?>" 
                                        data-available="<?= $available_slots ?>"
                                        data-max="<?= $max_slots ?>"
                                        data-status="<?= $slot_class ?>">
                                    <?= date('F d, Y', strtotime($slot['available_date'])) ?> - <?= htmlspecialchars($slot['available_time']) ?><?= $destination_info ?>
                                    <span class="slot-info <?= $slot_class ?>"><?= $available_slots ?>/<?= $max_slots ?> <?= $slot_text ?></span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="slotCapacityInfo" class="mt-2 text-sm text-gray-600 hidden">
                            Available slots: <span id="availableSlotsCount" class="font-semibold"></span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">📅 Select your preferred diving date, time and destination</p>
                    </div>
                    
                    <?php if (!empty($gears)): ?>
                    <div class="border-t-2 border-gray-200 pt-6 animate-fadeIn">
                        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2 text-lg">
                            <svg class="w-6 h-6 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"/>
                                <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"/>
                                <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"/>
                            </svg>
                            Optional Diving Gears
                        </h3>
                        <div class="space-y-3">
                        <?php foreach ($gears as $gear): ?>
                            <label class="flex justify-between items-center bg-gray-50 border-2 border-gray-200 rounded-lg px-4 py-3 cursor-pointer gear-checkbox-label">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" name="selected_gears[]" value="<?= $gear['id'] ?>" data-price="<?= $gear['price'] ?>" class="gear-checkbox w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($gear['gear_name']) ?></span>
                                </div>
                                <span class="text-blue-700 font-bold text-lg">₱<?= number_format($gear['price'], 2) ?></span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- SUMMARY -->
                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-6 border-2 border-gray-200 animate-fadeIn">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <svg class="w-6 h-6 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                                <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"/>
                            </svg>
                            Booking Summary
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between text-gray-700">
                                <span>Number of Divers (PAX):</span>
                                <span class="font-semibold"><span id="paxDisplay">1</span> diver(s)</span>
                            </div>
                            <div class="flex justify-between text-gray-700">
                                <span>Diver's Fee:</span>
                                <span class="font-semibold">₱<span id="diverFee"><?= number_format($diver['price'], 2) ?></span></span>
                            </div>
                            <div class="flex justify-between text-gray-700">
                                <span>Gears Total:</span>
                                <span class="font-semibold">₱<span id="gearTotal">0.00</span></span>
                            </div>
                            <div class="border-t-2 border-gray-300 pt-3 flex justify-between items-center">
                                <span class="text-xl font-bold text-gray-900">Grand Total:</span>
                                <span class="text-3xl font-bold text-blue-700">₱<span id="grandTotal"><?= number_format($diver['price'], 2) ?></span></span>
                            </div>
                        </div>
                    </div>

                    <div class="animate-fadeIn">
                        <label class="block font-bold text-gray-800 mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z"/>
                            </svg>
                            Payment Method
                        </label>
                        <select name="payment_method" id="payment_method" required class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus transition-all">
                            <option value="">Select Payment Method</option>
                            <option value="In Person Payment">💵 In Person Payment</option>
                            <option value="GCash">📱 GCash</option>
                        </select>
                    </div>
                    
                    <!-- IN PERSON PAYMENT SECTION -->
                    <div id="inPersonSection" class="hidden border-2 border-green-400 rounded-xl p-6 bg-gradient-to-r from-green-50 to-green-100 animate-slideIn">
                        <div class="flex items-start gap-4">
                            <svg class="w-12 h-12 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z"/>
                            </svg>
                            <div>
                                <h3 class="font-bold text-green-800 mb-2 text-lg">In-Person Payment</h3>
                                <p class="text-gray-700">Please proceed with the payment directly at the dive site on the day of your booking. Make sure to bring the exact amount or payment confirmation.</p>
                            </div>
                        </div>
                    </div>

                    <!-- GCASH PAYMENT SECTION -->
                    <div id="gcashSection" class="hidden border-2 border-blue-400 rounded-xl p-6 bg-gradient-to-r from-blue-50 to-blue-100 animate-slideIn">
                        <h3 class="font-bold text-blue-800 mb-4 text-lg flex items-center gap-2">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                            </svg>
                            GCash Payment
                        </h3>
                        
                        <?php if (!empty($diver['qr_code'])): ?>
                            <div class="mb-6 text-center bg-white rounded-lg p-4 shadow-md">
                                <p class="text-gray-700 mb-3 font-semibold">Scan this GCash QR Code to pay:</p>
                                <img src="../admin/uploads/<?= htmlspecialchars($diver['qr_code']) ?>" alt="GCash QR" class="w-80 h-80 object-contain mx-auto border-4 border-blue-300 rounded-lg shadow-lg">
                            </div>
                        <?php else: ?>
                            <div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4 mb-4 flex items-center gap-3">
                                <svg class="w-6 h-6 text-yellow-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"/>
                                </svg>
                                <p class="text-gray-700">No GCash QR code uploaded by this diver. Please contact them directly.</p>
                            </div>
                        <?php endif; ?>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z"/>
                                    </svg>
                                    Upload Your Signature (Image) <span class="text-red-500">*</span>
                                </label>
                                <input type="file" name="user_signature" accept="image/*" class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus transition-all file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200" required>
                                <p class="text-xs text-gray-500 mt-1">Required for payment verification</p>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"/>
                                    </svg>
                                    Upload GCash Receipt Screenshot
                                </label>
                                <input type="file" name="gcash_receipt" accept="image/*" class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 input-focus transition-all file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                                <p class="text-xs text-gray-500 mt-1">Upload proof of payment for faster processing</p>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="grand_total" id="grand_total_input">
                    
                    <!-- SUBMIT BUTTON -->
                    <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white py-4 rounded-lg font-bold text-lg btn-primary shadow-lg flex items-center justify-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Confirm Booking
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center py-12 animate-fadeIn">
                    <svg class="w-20 h-20 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-gray-600 text-lg font-semibold mb-2">No Available Slots</p>
                    <p class="text-gray-500">This diver doesn't have any available time slots right now. Please check back later.</p>
                    <a href="../user/user_dashboard.php" class="inline-block mt-6 bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-lg font-semibold btn-primary">
                        Browse Other Divers
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- FOOTER -->
<footer class="bg-gradient-to-r from-blue-700 to-blue-600 text-white text-center py-6 mt-auto shadow-lg">
    <div class="container mx-auto">
        <p class="font-medium">&copy; <?= date('Y') ?> DiveConnect. All rights reserved.</p>
        <p class="text-sm text-blue-200 mt-1">Connecting divers worldwide</p>
    </div>
</footer>

<script>
// Mobile menu toggle
document.getElementById('mobileMenuBtn').addEventListener('click', function() {
    const menu = document.getElementById('mobileMenu');
    menu.classList.toggle('hidden');
});

// PAX Control
const maxPax = <?= intval($diver['max_pax'] ?? 6) ?>;
const basePrice = <?= floatval($diver['price'] ?? 0) ?>;

function increasePax() {
    const input = document.getElementById('paxCount');
    let current = parseInt(input.value);
    if (current < maxPax) {
        input.value = current + 1;
        computeTotal();
        updateSlotInfo(); // Update slot info when PAX changes
    }
}

function decreasePax() {
    const input = document.getElementById('paxCount');
    let current = parseInt(input.value);
    if (current > 1) {
        input.value = current - 1;
        computeTotal();
        updateSlotInfo(); // Update slot info when PAX changes
    }
}

function updateSlotInfo() {
    const select = document.getElementById('availabilitySelect');
    const selectedOption = select.options[select.selectedIndex];
    const capacityInfo = document.getElementById('slotCapacityInfo');
    const availableSlotsCount = document.getElementById('availableSlotsCount');
    
    if (selectedOption.value && selectedOption.dataset.available) {
        const availableSlots = parseInt(selectedOption.dataset.available);
        const maxSlots = parseInt(selectedOption.dataset.max);
        const paxCount = parseInt(document.getElementById('paxCount').value);
        
        availableSlotsCount.textContent = `${availableSlots} / ${maxSlots}`;
        capacityInfo.classList.remove('hidden');
        
        // Show warning if not enough slots
        if (paxCount > availableSlots) {
            capacityInfo.innerHTML = `⚠️ Not enough slots! Available: <span class="font-semibold text-red-600">${availableSlots}</span>, You selected: ${paxCount}`;
            capacityInfo.classList.add('text-red-600');
            capacityInfo.classList.remove('text-gray-600');
        } else {
            // ✅ FIXED: Show correct available slots even if status is "fully_booked"
            capacityInfo.innerHTML = `Available slots: <span class="font-semibold text-green-600">${availableSlots}</span>`;
            capacityInfo.classList.remove('text-red-600');
            capacityInfo.classList.add('text-gray-600');
        }
    } else {
        capacityInfo.classList.add('hidden');
    }
}

function validateBooking() {
    const select = document.getElementById('availabilitySelect');
    const selectedOption = select.options[select.selectedIndex];
    const paxCount = parseInt(document.getElementById('paxCount').value);
    
    if (selectedOption.value && selectedOption.dataset.available) {
        const availableSlots = parseInt(selectedOption.dataset.available);
        
        if (paxCount > availableSlots) {
            Swal.fire({
                icon: 'error',
                title: 'Not Enough Slots',
                text: `You selected ${paxCount} divers but only ${availableSlots} slots are available. Please reduce the number of divers or choose another time slot.`,
                confirmButtonColor: '#1d4ed8'
            });
            return false;
        }
    }
    
    return true;
}

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

document.addEventListener("DOMContentLoaded", function() {
    const paymentSelect = document.getElementById('payment_method');
    const gcashSection = document.getElementById('gcashSection');
    const inPersonSection = document.getElementById('inPersonSection');

    function togglePaymentSections() {
        gcashSection.classList.add('hidden');
        inPersonSection.classList.add('hidden');

        gcashSection.querySelectorAll('input').forEach(i => i.required = false);

        if (paymentSelect.value === "GCash") {
            gcashSection.classList.remove('hidden');
            gcashSection.querySelectorAll('input[name="user_signature"]').forEach(i => i.required = true);
        } 
        else if (paymentSelect.value === "In Person Payment") {
            inPersonSection.classList.remove('hidden');
        }
    }

    if (paymentSelect) {
        paymentSelect.addEventListener('change', togglePaymentSections);
        togglePaymentSections();
    }

    const gearCheckboxes = document.querySelectorAll('.gear-checkbox');
    gearCheckboxes.forEach(cb => cb.addEventListener('change', computeTotal));

    // Add event listener for slot selection
    const availabilitySelect = document.getElementById('availabilitySelect');
    if (availabilitySelect) {
        availabilitySelect.addEventListener('change', updateSlotInfo);
    }

    function computeTotal() {
        const paxCount = parseInt(document.getElementById('paxCount').value) || 1;
        const diverFee = basePrice * pax_count;
        
        let gearTotal = 0;
        document.querySelectorAll('.gear-checkbox:checked').forEach(cb => {
            gearTotal += parseFloat(cb.getAttribute('data-price'));
        });

        const grandTotal = diverFee + gearTotal;

        document.getElementById('paxDisplay').textContent = paxCount;
        document.getElementById('diverFee').textContent = diverFee.toFixed(2);
        document.getElementById('gearTotal').textContent = gearTotal.toFixed(2);
        document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
        document.getElementById('grand_total_input').value = grandTotal.toFixed(2);
    }

    // Make computeTotal global for PAX buttons
    window.computeTotal = computeTotal;
    window.updateSlotInfo = updateSlotInfo;

    // Initialize total on page load
    computeTotal();
});
</script>

</body>
</html>