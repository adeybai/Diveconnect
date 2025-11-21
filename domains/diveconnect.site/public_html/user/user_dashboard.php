<?php
session_start();
require '../includes/db.php';
use DiveConnect\Mailer;

if (!isset($_SESSION['user_id'])) {
    header("Location: login_user.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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

// Get user info
$stmt = $conn->prepare("SELECT fullname, email, phone, whatsapp, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get divers with availability
$divers = $conn->query("
    SELECT d.id, d.fullname, d.specialty, d.whatsapp_number, d.level, d.profile_pic, d.price,
           COALESCE(AVG(r.rating), 0) AS avg_rating,
           (
               SELECT a.status
               FROM availability a
               WHERE a.diver_id = d.id
               ORDER BY a.created_at DESC
               LIMIT 1
           ) AS availability_status
    FROM divers d
    LEFT JOIN ratings r ON d.id = r.diver_id
    GROUP BY d.id
    ORDER BY d.fullname ASC
");

// Get user's bookings with calculated cancellable status
$stmt = $conn->prepare("
    SELECT b.id, b.booking_date, b.status, b.pax_count, d.fullname AS diver_name,
           TIMESTAMPDIFF(HOUR, NOW(), b.booking_date) AS hours_until
    FROM bookings b
    JOIN divers d ON b.diver_id = d.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
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
            <a href="?lang=fil" class="px-4 py-2 rounded-lg hover:bg-blue-800 transition-all">Language</a>
           
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
        <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">Tidechart</a>
        <a href="../book.php" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">Book</a>
        <a href="?lang=fil" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">Language</a>
        <a href="../about.php" class="block hover:bg-blue-800 px-4 py-3 rounded-lg transition-all">About</a>
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
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            WhatsApp: <?= htmlspecialchars($user['whatsapp']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
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
                    ?>
                    <div class="glass-effect p-5 rounded-xl shadow-md booking-card" onclick="openBookingDetails(<?= htmlspecialchars(json_encode($booking), ENT_QUOTES) ?>)">
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
                                             'bg-red-100 text-red-700')) ?>">
                                <?= htmlspecialchars($booking['status']) ?>
                            </span>
                        </div>
                        <div class="border-t pt-3">
                            <p class="text-xs text-gray-500 mb-2">Booking ID: #<?= htmlspecialchars($booking['id']) ?></p>
                            <p class="text-xs text-gray-500">Number of Divers: <span class="font-semibold text-blue-600"><?= intval($booking['pax_count'] ?? 1) ?></span></p>
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
                <p class="text-gray-500 text-sm mt-2">Start exploring our masterdivers below!</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- MASTERDIVERS -->
<section class="p-6 animate-fadeIn">
    <div class="container mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
                </svg>
                Available Masterdivers
            </h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 diver-grid">
            <?php while ($diver = $divers->fetch_assoc()): ?>
                <?php
                $availability = strtolower(trim((string)($diver['availability_status'] ?? 'none')));
                $isAvailable = ($availability === "available");
                ?>
                <div class="glass-effect rounded-xl shadow-lg card-hover overflow-hidden">
                    <div class="relative">
                        <img src="../admin/uploads/<?= htmlspecialchars($diver['profile_pic'] ?: 'default.png') ?>"
                             class="w-full h-48 object-cover">
                        <div class="absolute top-3 right-3">
                            <span class="<?= $isAvailable ? 'bg-green-500' : 'bg-red-500' ?> text-white text-xs px-3 py-1.5 rounded-full font-semibold shadow-lg flex items-center gap-1">
                                <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                                <?= $isAvailable ? "Available" : "Not Available" ?>
                            </span>
                        </div>
                    </div>

                    <div class="p-5">
                        <h3 class="font-bold text-xl text-gray-800 mb-1"><?= htmlspecialchars($diver['fullname']) ?></h3>
                        <p class="text-blue-600 text-sm font-medium mb-3"><?= htmlspecialchars($diver['specialty']) ?></p>
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
                            <button 
                                onclick="openProfileModal(
                                    <?= $diver['id'] ?>,
                                    '<?= htmlspecialchars($diver['fullname'], ENT_QUOTES) ?>',
                                    '<?= $isAvailable ? "Available" : "Not Available" ?>',
                                    '<?= number_format($diver['avg_rating'],1) ?>',
                                    '../admin/uploads/<?= htmlspecialchars($diver['profile_pic'] ?: 'default.png') ?>'
                                )"
                                class="w-full bg-gray-800 hover:bg-gray-900 text-white py-3 rounded-lg font-semibold btn-primary relative">
                                View Profile & Rate
                            </button>

                            <form method="POST" action="book_diver.php">
                                <input type="hidden" name="diver_id" value="<?= $diver['id'] ?>">
                                <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white py-3 rounded-lg font-semibold btn-primary relative">
                                    Book Now
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

        <div id="cancelButtonContainer" class="mt-6"></div>

        <button onclick="closeBookingDetails()" class="w-full bg-gray-600 hover:bg-gray-700 text-white py-3 rounded-lg font-semibold mt-4">Close</button>
    </div>
</div>

<!-- PROFILE MODAL -->
<div id="profileModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-lg relative animate-scaleIn">
        <button class="absolute top-4 right-4 text-gray-400 hover:text-red-600 text-2xl transition-colors" onclick="closeProfileModal()">✕</button>

        <div class="flex flex-col items-center text-center">
            <img id="modalProfileImage" src="default_profile.png" alt="Profile" class="w-32 h-32 rounded-full object-cover border-4 border-blue-500 shadow-lg mb-4">

            <h2 id="modalName" class="text-3xl font-bold text-gray-800 mb-2"></h2>
            <p id="modalAvailability" class="text-sm font-semibold mb-2"></p>
            <p id="modalRating" class="text-yellow-500 text-xl mb-4"></p>

            <div id="modalDetails" class="text-gray-700 text-sm mb-6 w-full">
                <div class="bg-gray-50 rounded-lg p-4 space-y-2 text-left">
                    <p class="flex items-center gap-2"><strong class="text-gray-900">Whatsapp no.:</strong> <span id="whatsapp_number">N/A</span></p>
                    <p class="flex items-center gap-2"><strong class="text-gray-900">Certifications:</strong> <span id="modalCertifications">N/A</span></p>
                    <p class="flex items-center gap-2"><strong class="text-gray-900">Experience:</strong> <span id="experience">N/A</span></p>
                    <p class="mt-3"><strong class="text-gray-900">Bio:</strong></p>
                    <p id="modalBio" class="text-gray-600 italic">No information available.</p>
                </div>
            </div>
        </div>

        <hr class="my-6 border-gray-200">

        <div class="text-center">
            <p class="text-gray-700 font-semibold mb-3">Rate this Diver</p>
            <form method="POST" action="rate_diver.php" class="flex justify-center gap-2">
                <input type="hidden" name="diver_id" id="modalDiverID">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="submit" name="rating" value="<?= $i ?>" class="text-4xl text-gray-300 hover:text-yellow-500 hover:scale-125 transition-all duration-200">★</button>
                <?php endfor; ?>
            </form>
        </div>
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

// Confirm logout
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
            window.location.href = '../index.php';
        }
    });
}

// Booking details modal
function openBookingDetails(booking) {
    const modal = document.getElementById('bookingDetailsModal');
    const content = document.getElementById('bookingDetailsContent');
    const cancelContainer = document.getElementById('cancelButtonContainer');
    
    const statusColors = {
        'confirmed': 'bg-green-100 text-green-700',
        'pending': 'bg-yellow-100 text-yellow-700',
        'cancelled': 'bg-gray-100 text-gray-700',
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
                </span>
            </div>
        </div>
    `;
    
    // Show cancel button if applicable
    const canCancel = booking.hours_until >= 24 && (booking.status === 'pending' || booking.status === 'confirmed');
    
    if (canCancel) {
        cancelContainer.innerHTML = `
            <button onclick="confirmCancelBooking(${booking.id})" 
                class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg font-semibold">
                Cancel Booking
            </button>
            <p class="text-xs text-gray-500 text-center mt-2">
                ⏰ You can cancel up to 24 hours before the booking date
            </p>
        `;
    } else if (booking.status === 'cancelled') {
        cancelContainer.innerHTML = `
            <div class="bg-gray-100 text-gray-600 py-3 rounded-lg text-center font-semibold">
                This booking has been cancelled
            </div>
        `;
    } else if (booking.hours_until < 24 && booking.hours_until > 0) {
        cancelContainer.innerHTML = `
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 py-3 px-4 rounded-lg text-center text-sm">
                ⚠️ Cancellation is only allowed 24 hours before the booking date
            </div>
        `;
    } else {
        cancelContainer.innerHTML = '';
    }
    
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

// Profile modal functions
function openProfileModal(id, name, availability, rating, image, whatsapp_number) {
    document.getElementById('modalName').innerText = name;
    document.getElementById('modalAvailability').innerText = availability;
    document.getElementById('modalAvailability').className = availability === 'Available' 
        ? 'text-sm text-green-600 font-semibold mb-2' 
        : 'text-sm text-red-600 font-semibold mb-2';
    document.getElementById('modalRating').innerText = "⭐ " + rating + "/5";
    document.getElementById('whatsapp_number').innerText = whatsapp_number || 'N/A';
    document.getElementById('modalDiverID').value = id;
    document.getElementById('modalProfileImage').src = image;
    document.getElementById('profileModal').classList.remove('hidden');
}

function closeProfileModal() {
    document.getElementById('profileModal').classList.add('hidden');
}

// Close modal on backdrop click
document.getElementById('profileModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeProfileModal();
    }
});

document.getElementById('bookingDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeBookingDetails();
    }
});

function escapeHtml(unsafe) {
    if (unsafe === null || unsafe === undefined) return '';
    return String(unsafe)
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

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
