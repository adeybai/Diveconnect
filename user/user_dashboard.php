<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require '../includes/db.php';
include '../header.php';
use DiveConnect\Mailer;

/* ----------------- SESSION TIMEOUT + NO-CACHE ----------------- */
$timeoutSeconds = 30 * 60;
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Check timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeoutSeconds)) {
    session_unset();
    session_destroy();
    header("Location: user_login.php?session=expired");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

/* ----------------- AUTH GUARD ----------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info including email verification status
$stmt = $conn->prepare("SELECT fullname, email, phone, whatsapp, profile_pic, is_email_verified FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// SweetAlert trigger
$alert = "";
if (isset($_GET['rated']) && $_GET['rated'] == "success") {
    $alert = "success";
}

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Check if booking belongs to user and get booking details including diver info
    $check_stmt = $conn->prepare("SELECT b.booking_date, b.status, d.email AS diver_email, d.fullname AS diver_name, u.fullname AS user_name 
                                   FROM bookings b 
                                   JOIN divers d ON b.diver_id = d.id 
                                   JOIN users u ON b.user_id = u.id
                                   WHERE b.id = ? AND b.user_id = ?");
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $booking_data = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($booking_data) {
        $booking_date = new DateTime($booking_data['booking_date']);
        $now = new DateTime();
        $diff_hours = ($booking_date->getTimestamp() - $now->getTimestamp()) / 3600;
        
        // Only allow cancellation if more than 24 hours before booking
        if ($diff_hours >= 24) {
            $update_stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
            $update_stmt->bind_param("ii", $booking_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Send email notification to diver
            require '../library/mailer.php';
            
            $mailer = new Mailer();
            $mailer->sendCancellation(
                $booking_data['diver_email'],
                $booking_data['diver_name'],
                $booking_data['user_name'],
                $booking_data['booking_date']
            );
            
            $_SESSION['alert'] = ['type'=>'success','title'=>'Booking Cancelled','text'=>'Your booking has been cancelled successfully.'];
        } else {
            $_SESSION['alert'] = ['type'=>'error','title'=>'Cannot Cancel','text'=>'Bookings can only be cancelled 24 hours before the scheduled date.'];
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Handle mark as done and redirect to rating
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_done'])) {
    $booking_id = intval($_POST['booking_id']);
    
    // Check if booking belongs to user and is completed
    $check_stmt = $conn->prepare("SELECT b.*, d.fullname AS diver_name, d.id AS diver_id 
                                 FROM bookings b 
                                 JOIN divers d ON b.diver_id = d.id 
                                 WHERE b.id = ? AND b.user_id = ? AND b.status = 'completed'");
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $booking = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($booking) {
        // Check if already rated
        $rating_check = $conn->prepare("SELECT id FROM ratings WHERE booking_id = ?");
        $rating_check->bind_param("i", $booking_id);
        $rating_check->execute();
        $existing_rating = $rating_check->get_result()->fetch_assoc();
        $rating_check->close();
        
        if (!$existing_rating) {
            $_SESSION['booking_to_rate'] = $booking_id;
            $_SESSION['diver_to_rate'] = $booking['diver_id'];
            $_SESSION['diver_name_to_rate'] = $booking['diver_name'];
            header("Location: rate_diver.php");
            exit;
        } else {
            $_SESSION['alert'] = ['type'=>'info','title'=>'Already Rated','text'=>'You have already rated this dive master.'];
        }
    } else {
        $_SESSION['alert'] = ['type'=>'error','title'=>'Invalid Action','text'=>'Cannot mark this booking as done.'];
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Get divers with availability
$check_destination = $conn->query("SHOW COLUMNS FROM availability LIKE 'destination_id'");
if ($check_destination->num_rows > 0) {
    // New structure with destination
    $divers = $conn->query("
        SELECT d.id, d.fullname, d.specialty, d.whatsapp_number, d.level, d.profile_pic, d.price,
               COALESCE(AVG(r.rating), 0) AS avg_rating,
               (
                   SELECT COUNT(*) 
                   FROM availability a 
                   WHERE a.diver_id = d.id 
                   AND a.available_slots > 0
                   AND (
                       (a.available_date > CURDATE()) 
                       OR 
                       (
                           a.available_date = CURDATE() 
                           AND ADDTIME(a.start_time, '-01:00:00') > CURTIME()
                       )
                   )
               ) AS available_slots_count
        FROM divers d
        LEFT JOIN ratings r ON d.id = r.diver_id
        WHERE d.verification_status = 'approved'
        GROUP BY d.id
        ORDER BY d.fullname ASC
    ");
} else {
    // Old structure without destination
    $divers = $conn->query("
        SELECT d.id, d.fullname, d.specialty, d.whatsapp_number, d.level, d.profile_pic, d.price,
               COALESCE(AVG(r.rating), 0) AS avg_rating,
               (
                   SELECT COUNT(*) 
                   FROM availability a 
                   WHERE a.diver_id = d.id 
                   AND a.available_slots > 0
                   AND (
                       (a.available_date > CURDATE()) 
                       OR 
                       (
                           a.available_date = CURDATE() 
                           AND ADDTIME(a.start_time, '-01:00:00') > CURTIME()
                       )
                   )
               ) AS available_slots_count
        FROM divers d
        LEFT JOIN ratings r ON d.id = r.diver_id
        WHERE d.verification_status = 'approved'
        GROUP BY d.id
        ORDER BY d.fullname ASC
    ");
}

// Get user's bookings with calculated cancellable status and rating info
$stmt = $conn->prepare("
    SELECT b.*, d.fullname AS diver_name, d.id AS diver_id,
           TIMESTAMPDIFF(HOUR, NOW(), b.booking_date) AS hours_until,
           r.id AS rating_id,
           CASE 
               WHEN b.status = 'completed' AND r.id IS NULL THEN 1 
               ELSE 0 
           END AS can_rate
    FROM bookings b
    JOIN divers d ON b.diver_id = d.id
    LEFT JOIN ratings r ON b.id = r.booking_id
    WHERE b.user_id = ?
    ORDER BY 
        CASE 
            WHEN b.status = 'completed' AND r.id IS NULL THEN 0
            ELSE 1 
        END,
        b.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard | DiveConnect</title>
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

    .status-badge {
        animation: fadeIn 0.5s ease-out;
    }

    .profile-img {
        transition: transform 0.3s ease;
    }

    .profile-img:hover {
        transform: scale(1.05);
    }

    .star-rating {
        transition: transform 0.2s ease;
    }

    .star-rating:hover {
        transform: scale(1.1);
    }

    .backdrop-blur {
        backdrop-filter: blur(10px);
    }

    .glass-effect {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }

    .mobile-menu-transition {
        transition: max-height 0.3s ease-in-out, opacity 0.3s ease-in-out;
    }

    .diver-grid {
        display: grid;
        gap: 1.5rem;
        animation: fadeIn 0.6s ease-out;
    }

    .booking-card {
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
        cursor: pointer;
    }

    .booking-card:hover {
        border-left-color: #1d4ed8;
        background-color: #f9fafb;
    }
    
    /* Enhanced slot indicator */
    .slot-indicator {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .slot-indicator.pulse::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #10b981;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(0.95); opacity: 1; }
        50% { transform: scale(1.2); opacity: 0.7; }
        100% { transform: scale(0.95); opacity: 1; }
    }

    /* Rating badge */
    .rating-badge {
        animation: pulse 2s infinite;
    }
</style>
</head>
<body class="min-h-screen flex flex-col">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow-lg sticky top-0 z-40 animate-fadeIn">
    <div class="container mx-auto flex items-center justify-between p-4">
        <!-- logo -> user dashboard -->
        <a href="user_dashboard.php" class="flex items-center gap-2 hover:opacity-90 transition-opacity">
            <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-12">
        </a>

        <nav class="hidden md:flex items-center gap-2">
            <a href="user_dashboard.php" class="bg-white text-blue-700 px-4 py-2 rounded-lg font-semibold hover:bg-blue-50 transition-all">Dashboard</a>
            <a href="settings.php" class="bg-white text-blue-700 px-4 py-2 rounded-lg font-semibold hover:bg-blue-50 transition-all">Settings</a>
            <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Tidechart</a>
            <!-- Explore now points to user_explore.php -->
            <a href="user_explore.php" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Explore</a>
            <!-- <a href="?lang=fil" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Language</a> -->
           
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
        <!-- Explore now points to user_explore.php -->
        <a href="user_explore.php" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">Explore</a>
        <a href="settings.php" class="block bg-white text-blue-700 px-4 py-3 rounded-lg font-semibold">Settings</a>
        <a href="user_dashboard.php" class="block bg-white text-blue-700 px-4 py-3 rounded-lg font-semibold">Dashboard</a>
        <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" class="block hover:bg-blue-800 px:4 py-3 rounded-lg transition-all">Tidechart</a>
        <a href="../book.php" class="block hover:bg-blue-800 px:4 py-3 rounded-lg transition-all">Book</a>
        <a href="?lang=fil" class="block hover:bg-blue-800 px:4 py-3 rounded-lg transition-all">Language</a>
        <a href="../about.php" class="block hover:bg-blue-800 px:4 py-3 rounded-lg transition-all">About</a>
        <a href="#" onclick="confirmLogout(event)" class="block bg-red-500 hover:bg-red-600 px-4 py-3 rounded-lg transition-all">Logout</a>
    </div>
</div>

<!-- PROFILE SECTION -->
<section class="bg-gradient-to-r from-blue-700 to-blue-600 text-white p-6 shadow-xl animate-slideIn">
    <div class="container mx-auto">
        <div class="flex items-center gap-6">
            <img src="<?= htmlspecialchars(!empty($user['profile_pic']) ? $user['profile_pic'] : '../assets/images/default.png') ?>" 
                 alt="User" class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-white object-cover shadow-lg profile-img">
            <div class="flex-1">
                <h1 class="text-3xl md:text-4xl font-bold mb-2"><?= htmlspecialchars($user['fullname']) ?></h1>
                <div class="flex flex-wrap gap-4 text-sm md:text-base">
                    <p class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                        <?= htmlspecialchars($user['email']) ?>
                    </p>
                    <p class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                        </svg>
                        <?= htmlspecialchars($user['phone']) ?>
                    </p>
                    <?php if (!empty($user['whatsapp'])): ?>
                        <p class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0.16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            WhatsApp: <?= htmlspecialchars($user['whatsapp']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- EMAIL VERIFICATION SECTION -->
<section class="p-6 animate-fadeIn">
    <div class="container mx-auto">
        <div class="glass-effect rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.94 6.412A2 2 0 002 8.108V16a2 2 0 002 2h12a2 2 0 002-2V8.108a2 2 0 00-.94-1.696l-6-3.75a2 2 0 00-2.12 0l-6 3.75zm2.615 2.423a1 1 0 10-1.11 1.664l5 3.333a1 1 0 001.11 0l5-3.333a1 1 0 00-1.11-1.664L10 11.798 5.555 8.835z" clip-rule="evenodd"/>
                </svg>
                Email Verification
            </h2>
            
            <?php if ($user['is_email_verified']): ?>
                <div class="flex items-center text-green-600 mb-2">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium">Email Verified</span>
                </div>
                <p class="text-gray-600">Your email <?= htmlspecialchars($user['email']) ?> has been verified successfully.</p>
            <?php else: ?>
                <div class="flex items-center text-yellow-600 mb-2">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293-1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-medium">Email Not Verified</span>
                </div>
                <p class="text-gray-600 mb-4">Your email <?= htmlspecialchars($user['email']) ?> is not verified. Please verify to access all features.</p>
                <button onclick="resendVerification()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                    Verify Email
                </button>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- USER BOOKINGS -->
<section class="p-6 animate-fadeIn">
    <div class="container mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                </svg>
                Your Bookings
            </h2>
            <span class="bg-blue-100 text-blue-700 px-4 py-2 rounded-full text-sm font-semibold">
                <?= count($bookings) ?> Total
            </span>
        </div>
        
        <?php if (count($bookings) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($bookings as $booking): ?>
                    <?php
                    $can_cancel = ($booking['hours_until'] >= 24 && in_array($booking['status'], ['pending', 'confirmed']));
                    $can_rate = ($booking['status'] == 'completed' && $booking['can_rate'] == 1);
                    $is_rated = ($booking['status'] == 'completed' && $booking['rating_id'] !== null);
                    ?>
                    <div class="glass-effect p-5 rounded-xl shadow-md booking-card">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="font-bold text-lg text-gray-800"><?= htmlspecialchars($booking['diver_name']) ?></p>
                                <p class="text-gray-600 text-sm flex items-center gap-1 mt-1">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                                    </svg>
                                    <?= htmlspecialchars($booking['booking_date']) ?>
                                </p>
                            </div>
                            <span class="capitalize text-xs px-3 py-1.5 rounded-full font-semibold status-badge
                                         <?= $booking['status'] == 'confirmed' ? 'bg-green-100 text-green-700' : 
                                             ($booking['status'] == 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                                             ($booking['status'] == 'cancelled' ? 'bg-gray-100 text-gray-700' : 
                                             ($booking['status'] == 'completed' ? 'bg-blue-100 text-blue-700' : 
                                             'bg-red-100 text-red-700'))) ?>">
                                <?= htmlspecialchars($booking['status']) ?>
                                <?php if ($is_rated): ?>
                                    <span class="ml-1">⭐</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="border-t pt-3">
                            <p class="text-xs text-gray-500 mb-2">Booking ID: #<?= htmlspecialchars($booking['id']) ?></p>
                            <p class="text-xs text-gray-500">Number of Divers: <span class="font-semibold text-blue-600"><?= intval($booking['pax_count'] ?? 1) ?></span></p>
                            <?php if ($booking['remarks']): ?>
                                <p class="text-xs text-gray-500 mt-1">
                                    <strong>Remarks:</strong> <?= htmlspecialchars($booking['remarks']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php if ($can_cancel): ?>
                                    <form method="POST" class="inline" onsubmit="return confirmCancel(<?= $booking['id'] ?>)">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <button type="submit" name="cancel_booking" class="bg-red-500 hover:bg-red-600 text-white text-xs px-3 py-1.5 rounded-lg transition-all">
                                            Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($can_rate): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                        <button type="submit" name="mark_done" class="bg-green-500 hover:bg-green-600 text-white text-xs px-3 py-1.5 rounded-lg transition-all rating-badge">
                                            ⭐ Rate Now
                                        </button>
                                    </form>
                                <?php elseif ($is_rated): ?>
                                    <span class="bg-green-100 text-green-700 text-xs px-3 py-1.5 rounded-lg">
                                        ✅ Rated
                                    </span>
                                <?php endif; ?>
                                
                                <button onclick="openBookingDetails(<?= htmlspecialchars(json_encode($booking), ENT_QUOTES) ?>)" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-3 py-1.5 rounded-lg transition-all">
                                    View Details
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="glass-effect p-12 rounded-xl text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-gray-600 text-lg font-medium">No bookings yet</p>
                <p class="text-gray-500 text-sm mt-2">Start exploring our dive masters below!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- MASTERDIVERS -->
<section class="p-6 animate-fadeIn">
    <div class="container mx-auto">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
                </svg>
                Available Dive Masters
            </h2>

            <!-- FILTER BUTTONS -->
            <div class="inline-flex bg-white/80 rounded-full p-1 shadow-md text-sm font-semibold">
                <button
                    type="button"
                    class="px-4 py-2 rounded-full filter-btn text-blue-700 bg-white"
                    data-filter-btn
                    data-filter="all">
                    All
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-full filter-btn bg-blue-700 text-white shadow-md"
                    data-filter-btn
                    data-filter="available">
                    Available
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-full filter-btn text-blue-700 bg-white"
                    data-filter-btn
                    data-filter="not">
                    Not Available
                </button>
            </div>
        </div>

        <div id="diverGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 diver-grid">
            <?php while ($diver = $divers->fetch_assoc()): ?>
                <?php
                // ✅ FIXED: Check available slots instead of just count
                $hasAvailableSlots = ($diver['available_slots_count'] > 0);
                $availableText = $hasAvailableSlots ? "Available" : "Not Available";
                $availableClass = $hasAvailableSlots ? "available" : "not";
                $slotText = $diver['available_slots_count'] > 0 ? 
                    "{$diver['available_slots_count']} slot" . ($diver['available_slots_count'] > 1 ? 's' : '') : 
                    "No slots";
                ?>
                <div class="glass-effect rounded-xl shadow-lg card-hover overflow-hidden diver-card"
                     data-availability="<?= $availableClass ?>"
                     data-available-slots="<?= $diver['available_slots_count'] ?>">
                    <div class="relative">
                        <img src="../admin/uploads/<?= htmlspecialchars($diver['profile_pic'] ?: 'default.png') ?>"
                             class="w-full h-48 object-cover">
                        <div class="absolute top-3 right-3">
                            <span class="<?= $hasAvailableSlots ? 'bg-green-500' : 'bg-red-500' ?> text-white text-xs px-3 py-1.5 rounded-full font-semibold shadow-lg flex items-center gap-1 slot-indicator <?= $hasAvailableSlots ? 'pulse' : '' ?>">
                                <?php if ($hasAvailableSlots): ?>
                                    <span class="w-2 h-2 bg-white rounded-full"></span>
                                <?php endif; ?>
                                <?= $availableText ?> (<?= $slotText ?>)
                            </span>
                        </div>
                    </div>

                    <div class="p-5">
                        <h3 class="font-bold text-xl text-gray-800 mb-1"><?= htmlspecialchars($diver['fullname']) ?></h3>
                        <p class="text-blue-600 text-sm font-medium mb-1"><?= htmlspecialchars($diver['specialty']) ?></p>
                        <p class="text-blue-600 text-sm font-medium mb-3">Whatsapp No.: <?= htmlspecialchars($diver['whatsapp_number']) ?></p>

                        <div class="flex items-center gap-2 mb-4">
                            <div class="flex text-yellow-400 text-lg star-rating">
                                <?php
                                $avg = round($diver['avg_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo ($avg >= $i) ? "★" : "<span class='text-gray-300'>★</span>";
                                }
                                ?>
                            </div>
                            <span class="text-sm text-gray-600 font-medium">(<?= number_format($diver['avg_rating'], 1) ?>)</span>
                        </div>

                        <div class="space-y-2">
                            <form method="POST" action="book_diver.php">
                                <input type="hidden" name="diver_id" value="<?= $diver['id'] ?>">
                                <button type="submit" 
                                        class="w-full bg-blue-700 hover:bg-blue-800 text-white py-3 rounded-lg font-semibold btn-primary relative"
                                        <?= !$hasAvailableSlots ? 'disabled' : '' ?>>
                                    <?= $hasAvailableSlots ? 'Book Now' : 'No Slots Available' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- BOOKING DETAILS MODAL -->
<div id="bookingDetailsModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-lg relative animate-scaleIn">
        <button class="absolute top-4 right-4 text-gray-400 hover:text-red-600 text-2xl transition-colors" onclick="closeBookingDetails()">✕</button>

        <h2 class="text-2xl font-bold text-gray-800 mb-6">📋 Booking Details</h2>

        <div id="bookingDetailsContent" class="space-y-4 mb-6"></div>

        <div id="actionButtonsContainer" class="mt-6 space-y-3"></div>

        <button onclick="closeBookingDetails()" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-3 rounded-lg font-semibold mt-4">Close</button>
    </div>
</div>

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

// Email Verification Functions
function resendVerification() {
    fetch('register_user.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'resend_verification=1&email=<?= $user['email'] ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Email Sent!',
                text: data.message
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: data.message
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Please try again later.'
        });
    });
}

// Booking details modal - COMPLETELY FIXED VERSION
function openBookingDetails(booking) {
    console.log('Booking data:', booking); // Debug line
    
    const modal = document.getElementById('bookingDetailsModal');
    const content = document.getElementById('bookingDetailsContent');
    const actionContainer = document.getElementById('actionButtonsContainer');
    
    const statusColors = {
        'confirmed': 'bg-green-100 text-green-700',
        'pending': 'bg-yellow-100 text-yellow-700',
        'cancelled': 'bg-gray-100 text-gray-700',
        'completed': 'bg-blue-100 text-blue-700',
        'declined': 'bg-red-100 text-red-700'
    };
    
    content.innerHTML = `
        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-600">Booking ID</p>
                <p class="font-semibold text-gray-800">#${booking.id}</p>
            </div>
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-600">Masterdiver</p>
                <p class="font-semibold text-gray-800">${escapeHtml(booking.diver_name)}</p>
            </div>
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-600">Date</p>
                <p class="font-semibold text-gray-800">${escapeHtml(booking.booking_date)}</p>
            </div>
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-600">Number of Divers</p>
                <p class="font-semibold text-blue-600">${booking.pax_count || 1}</p>
            </div>
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-600">Status</p>
                <span class="capitalize text-xs px-3 py-1.5 rounded-full font-semibold ${statusColors[booking.status] || 'bg-gray-100 text-gray-700'}">
                    ${escapeHtml(booking.status)}
                    ${booking.rating_id ? ' ⭐' : ''}
                </span>
            </div>
            ${booking.remarks ? `
            <div class="border-t pt-3">
                <p class="text-sm text-gray-600 font-semibold">Diver's Remarks:</p>
                <p class="text-sm text-gray-700 mt-1">${escapeHtml(booking.remarks)}</p>
            </div>
            ` : ''}
        </div>
    `;
    
    // DEBUG: Check the actual values
    console.log('Booking status:', booking.status);
    console.log('Can rate:', booking.can_rate);
    console.log('Rating ID:', booking.rating_id);
    console.log('Hours until:', booking.hours_until);
    
    // Show action buttons if applicable - FIXED LOGIC
    const canCancel = booking.hours_until >= 24 && (booking.status === 'pending' || booking.status === 'confirmed');
    const canRate = booking.status === 'completed' && booking.can_rate == 1;
    const isRated = booking.status === 'completed' && booking.rating_id !== null;
    
    console.log('Can cancel:', canCancel);
    console.log('Can rate:', canRate);
    console.log('Is rated:', isRated);
    
    let actionButtons = '';
    
    if (canCancel) {
        actionButtons += `
            <button onclick="confirmCancelBooking(${booking.id})" 
                class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg font-semibold transition-all">
                Cancel Booking
            </button>
        `;
    }
    
    if (canRate) {
        actionButtons += `
            <form method="POST" class="w-full">
                <input type="hidden" name="booking_id" value="${booking.id}">
                <button type="submit" name="mark_done" 
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-semibold rating-badge transition-all flex items-center justify-center gap-2">
                    <span>⭐</span>
                    <span>Rate Dive Master</span>
                </button>
            </form>
        `;
    } else if (isRated) {
        actionButtons += `
            <div class="bg-green-100 text-green-700 py-3 rounded-lg text-center font-semibold flex items-center justify-center gap-2">
                <span>✅</span>
                <span>Already Rated</span>
            </div>
        `;
    }
    
    console.log('Action buttons HTML:', actionButtons);
    actionContainer.innerHTML = actionButtons;
    
    modal.classList.remove('hidden');
}

function closeBookingDetails() {
    document.getElementById('bookingDetailsModal').classList.add('hidden');
}

function confirmCancelBooking(bookingId) {
    Swal.fire({
        title: 'Cancel Booking?',
        text: 'Are you sure you want to cancel this booking?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Cancel',
        cancelButtonText: 'No, Keep It'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="cancel_booking" value="1">
                <input type="hidden" name="booking_id" value="${bookingId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function confirmCancel(bookingId) {
    Swal.fire({
        title: 'Cancel Booking?',
        text: 'Are you sure you want to cancel this booking?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Cancel',
        cancelButtonText: 'No, Keep It'
    }).then((result) => {
        if (!result.isConfirmed) {
            return false;
        }
    });
    return false;
}

function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return String(unsafe)
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

/* ================== MASTERDIVER FILTERING (Available / All / Not) ================== */
const diverGrid   = document.getElementById('diverGrid');
const diverCards  = diverGrid ? Array.from(diverGrid.querySelectorAll('.diver-card')) : [];
const filterBtns  = document.querySelectorAll('[data-filter-btn]');
let currentFilter = 'available'; // default: Available lang

function setActiveFilterButton(filter) {
    filterBtns.forEach(btn => {
        const f = btn.getAttribute('data-filter');
        if (f === filter) {
            btn.classList.add('bg-blue-700','text-white','shadow-md');
            btn.classList.remove('bg-white','text-blue-700');
        } else {
            btn.classList.remove('bg-blue-700','text-white','shadow-md');
            btn.classList.add('bg-white','text-blue-700');
        }
    });
}

function applyDiverFilter() {
    if (!diverGrid || !diverCards.length) return;

    diverCards.forEach(card => {
        const availability = (card.dataset.availability || 'not').toLowerCase();
        const availableSlots = parseInt(card.dataset.availableSlots || 0);
        
        if (currentFilter === 'available') {
            // ✅ FIXED: Show divers with ANY available slots (> 0)
            card.classList.toggle('hidden', availableSlots <= 0);
        } else if (currentFilter === 'not') {
            // ✅ FIXED: Show divers with NO available slots (0)
            card.classList.toggle('hidden', availableSlots > 0);
        } else { // all
            card.classList.remove('hidden');
        }
    });

    // Pag "all" ang filter, lagi unahin si Available
    if (currentFilter === 'all') {
        const availableCards = diverCards.filter(c => parseInt(c.dataset.availableSlots || 0) > 0);
        const otherCards     = diverCards.filter(c => parseInt(c.dataset.availableSlots || 0) <= 0);
        const ordered        = availableCards.concat(otherCards);
        ordered.forEach(c => diverGrid.appendChild(c));
    }
}

filterBtns.forEach(btn => {
    btn.addEventListener('click', function () {
        const filter = this.getAttribute('data-filter') || 'all';
        currentFilter = filter;
        setActiveFilterButton(filter);
        applyDiverFilter();
    });
});

// Initial state: Available lang
document.addEventListener('DOMContentLoaded', function () {
    setActiveFilterButton(currentFilter);
    applyDiverFilter();
});

// SweetAlert for rating success
<?php if ($alert == "success"): ?>
Swal.fire({
    icon: 'success',
    title: 'Rating Submitted!',
    text: 'Thank you for your feedback.',
    confirmButtonColor: '#1d4ed8',
    timer: 3000
});
<?php endif; ?>

// SweetAlert for session alerts
<?php if(isset($_SESSION['alert'])): ?>
Swal.fire({
    icon: '<?= $_SESSION['alert']['type'] ?>',
    title: '<?= $_SESSION['alert']['title'] ?>',
    text: '<?= $_SESSION['alert']['text'] ?>',
    confirmButtonColor: '#1d4ed8'
});
<?php unset($_SESSION['alert']); endif; ?>
</script>

</body>
</html>