<?php
// Prevent back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
?>

<?php
session_start();
require '../includes/db.php';
require '../library/mailer.php';
include '../header.php';
use DiveConnect\Mailer;

// Check if diver is logged in
if (!isset($_SESSION['diver_id'])) {
    header("Location: login_diver.php");
    exit;
}

$diver_id = $_SESSION['diver_id'];

// Get diver info
$stmt = $conn->prepare("SELECT fullname, email, specialty, profile_pic, price, max_pax FROM divers WHERE id = ?");
$stmt->bind_param("i", $diver_id);
$stmt->execute();
$diver = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ===== ENHANCED AVAILABILITY STATUS MANAGEMENT WITH 1-HOUR PREPARATION =====
$update_completed = $conn->prepare("
    UPDATE availability 
    SET status = 'completed' 
    WHERE diver_id = ? 
    AND (
        (available_date < CURDATE()) 
        OR 
        (
            available_date = CURDATE() 
            AND status = 'available' 
            AND booked_slots = 0 
            AND ADDTIME(start_time, '-01:00:00') < CURTIME()
        )
    )
    AND status != 'completed'
");
$update_completed->bind_param("i", $diver_id);
$update_completed->execute();
$update_completed->close();

// ===== FETCH DIVER'S EARNINGS DATA =====
$earnings_stmt = $conn->prepare("
    SELECT 
        COUNT(b.id) AS total_bookings,
        COALESCE(SUM(b.grand_total), 0) AS total_earned
    FROM bookings b 
    WHERE b.diver_id = ? 
    AND b.status IN('confirmed','completed')
");
$earnings_stmt->bind_param("i", $diver_id);
$earnings_stmt->execute();
$earnings_result = $earnings_stmt->get_result()->fetch_assoc();
$earnings_stmt->close();

$total_bookings = $earnings_result['total_bookings'] ?? 0;
$total_earned = $earnings_result['total_earned'] ?? 0;

// Recent bookings for earnings breakdown
$recent_bookings_stmt = $conn->prepare("
    SELECT 
        b.id,
        u.fullname AS user_name,
        b.booking_date,
        b.pax_count,
        b.grand_total,
        b.status,
        b.created_at
    FROM bookings b 
    JOIN users u ON b.user_id = u.id
    WHERE b.diver_id = ? 
    AND b.status IN('confirmed','completed')
    ORDER BY b.created_at DESC
    LIMIT 10
");
$recent_bookings_stmt->bind_param("i", $diver_id);
$recent_bookings_stmt->execute();
$recent_bookings = $recent_bookings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_bookings_stmt->close();

// ===== GET ALL DESTINATIONS FOR DROPDOWN =====
$destinations_stmt = $conn->query("SELECT destination_id, title, location FROM destinations WHERE is_active = 1 ORDER BY title");
$destinations = $destinations_stmt->fetch_all(MYSQLI_ASSOC);
$destinations_stmt->close();

// ===== UPDATE MAX PAX =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_max_pax'])) {
    $max_pax = intval($_POST['max_pax']);
    if ($max_pax >= 1 && $max_pax <= 6) {
        $stmt = $conn->prepare("UPDATE divers SET max_pax = ? WHERE id = ?");
        $stmt->bind_param("ii", $max_pax, $diver_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['alert'] = ['type'=>'success','title'=>'Capacity updated!','text'=>'Your maximum diver capacity has been saved.'];
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// ===== ADD GEAR =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gear'])) {
    $gear_name = trim($_POST['gear_name']);
    $price_field = $_POST['gear_price'] ?? $_POST['price'] ?? 0;
    $price = floatval($price_field);
    if ($gear_name !== '' && $price >= 0) {
        $stmt = $conn->prepare("INSERT INTO diver_gears (diver_id, gear_name, price) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $diver_id, $gear_name, $price);
        $stmt->execute();
        $stmt->close();
        $_SESSION['alert'] = ['type'=>'success','title'=>'Gear Added!','text'=>'New gear has been saved.'];
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// ===== DELETE GEAR =====
if (isset($_GET['delete_gear']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $gear_id = intval($_GET['delete_gear']);
    $stmt = $conn->prepare("DELETE FROM diver_gears WHERE id = ? AND diver_id = ?");
    $stmt->bind_param("ii", $gear_id, $diver_id);
    $stmt->execute();
    $stmt->close();
        $_SESSION['alert'] = ['type'=>'success','title'=>'Deleted!','text'=>'Gear successfully removed.'];
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// ===== BOOKINGS (confirm/decline with remarks) - COMPLETELY FIXED VERSION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle CONFIRM booking - FIXED STATUS CALCULATION
    if (isset($_POST['confirm_booking'])) {
        $booking_id = intval($_POST['booking_id']);
        $remarks = trim($_POST['confirm_remarks'] ?? '');
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First get the booking details including PAX count
            $stmt = $conn->prepare("
                SELECT b.pax_count, a.id as availability_id, a.max_slots, a.booked_slots, a.available_slots 
                FROM bookings b 
                JOIN availability a ON b.booking_date = a.available_date AND b.diver_id = a.diver_id 
                WHERE b.id = ? AND b.diver_id = ?
            ");
            $stmt->bind_param("ii", $booking_id, $diver_id);
            $stmt->execute();
            $booking_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$booking_data) {
                throw new Exception("Booking data not found.");
            }

            $pax_count = intval($booking_data['pax_count'] ?? 1);
            $new_booked_slots = $booking_data['booked_slots'] + $pax_count;
            $new_available_slots = $booking_data['max_slots'] - $new_booked_slots;
            
            // ✅ FIXED: Only mark as fully_booked if no slots left, otherwise keep available
            $new_status = ($new_available_slots <= 0) ? 'fully_booked' : 'available';

            // Update booking status
            $update = $conn->prepare("UPDATE bookings SET status = 'confirmed', remarks = ?, updated_at = NOW() WHERE id = ? AND diver_id = ?");
            $update->bind_param("sii", $remarks, $booking_id, $diver_id);
            
            if (!$update->execute()) {
                throw new Exception("Failed to update booking status.");
            }
            $update->close();

            // ✅ FIXED: Update availability slots with CORRECT calculation
            $update_slots = $conn->prepare("
                UPDATE availability 
                SET booked_slots = ?, 
                    available_slots = ?,
                    status = ?
                WHERE id = ? AND diver_id = ?
            ");
            $update_slots->bind_param("iisii", $new_booked_slots, $new_available_slots, $new_status, $booking_data['availability_id'], $diver_id);
            
            if (!$update_slots->execute()) {
                throw new Exception("Failed to update availability slots.");
            }
            $update_slots->close();

            // Send email
            $user_stmt = $conn->prepare("SELECT u.fullname, u.email, b.booking_date, b.pax_count 
                                         FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?");
            $user_stmt->bind_param("i", $booking_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();

            if ($user_data) {
                $mailer = new Mailer();
                $mailer->sendApproved(
                    $user_data['email'],
                    $user_data['fullname'],
                    $diver['specialty'] ?? 'Dive Site',
                    $user_data['booking_date'],
                    $diver['fullname'],
                    $remarks
                );
            }

            $conn->commit();
            $_SESSION['alert'] = [
                'type'=>'success',
                'title'=>'Booking Confirmed!',
                'text'=>'You successfully confirmed the booking.'
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['alert'] = [
                'type'=>'error',
                'title'=>'Error',
                'text'=>'Failed to confirm booking: ' . $e->getMessage()
            ];
        }
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    // Handle DECLINE booking - FIXED SLOT RELEASE
    if (isset($_POST['decline_booking'])) {
        $booking_id = intval($_POST['booking_id']);
        $remarks = trim($_POST['decline_remarks'] ?? '');
        
        // Validate required remarks for rejection
        if (empty($remarks)) {
            $_SESSION['alert'] = ['type'=>'error','title'=>'Remarks Required','text'=>'Please provide a reason for rejecting this booking.'];
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }

        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First get the booking details including PAX count
            $stmt = $conn->prepare("
                SELECT b.pax_count, a.id as availability_id, a.max_slots, a.booked_slots, a.available_slots 
                FROM bookings b 
                JOIN availability a ON b.booking_date = a.available_date AND b.diver_id = a.diver_id 
                WHERE b.id = ? AND b.diver_id = ?
            ");
            $stmt->bind_param("ii", $booking_id, $diver_id);
            $stmt->execute();
            $booking_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($booking_data) {
                $pax_count = intval($booking_data['pax_count'] ?? 1);
                $new_booked_slots = max(0, $booking_data['booked_slots'] - $pax_count);
                $new_available_slots = $booking_data['max_slots'] - $new_booked_slots;
                
                // ✅ FIXED: Only mark as fully_booked if no slots left, otherwise back to available
                $new_status = ($new_available_slots > 0) ? 'available' : 'fully_booked';

                // Update availability slots
                $update_slots = $conn->prepare("
                    UPDATE availability 
                    SET booked_slots = ?, 
                        available_slots = ?,
                        status = ?
                    WHERE id = ? AND diver_id = ?
                ");
                $update_slots->bind_param("iisii", $new_booked_slots, $new_available_slots, $new_status, $booking_data['availability_id'], $diver_id);
                $update_slots->execute();
                $update_slots->close();
            }

            // Update booking status
            $update = $conn->prepare("UPDATE bookings SET status = 'declined', remarks = ?, updated_at = NOW() WHERE id = ? AND diver_id = ?");
            $update->bind_param("sii", $remarks, $booking_id, $diver_id);
            $update->execute();
            $update->close();

            // Send email
            $user_stmt = $conn->prepare("SELECT u.fullname, u.email, b.booking_date, b.pax_count 
                                         FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?");
            $user_stmt->bind_param("i", $booking_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();

            if ($user_data) {
                $mailer = new Mailer();
                $mailer->sendDeclined(
                    $user_data['email'],
                    $user_data['fullname'],
                    $diver['specialty'] ?? 'Dive Site',
                    $user_data['booking_date'],
                    $diver['fullname'],
                    $remarks
                );
            }

            $conn->commit();
            $_SESSION['alert'] = [
                'type'=>'success',
                'title'=>'Booking Declined!',
                'text'=>'You have declined the booking.'
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['alert'] = [
                'type'=>'error',
                'title'=>'Error',
                'text'=>'Failed to decline booking: ' . $e->getMessage()
            ];
        }
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    // ===== MARK BOOKING AS COMPLETED =====
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
        $booking_id = intval($_POST['booking_id']);
        
        // Verify that booking belongs to this diver and is confirmed
        $check_stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND diver_id = ? AND status = 'confirmed'");
        $check_stmt->bind_param("ii", $booking_id, $diver_id);
        $check_stmt->execute();
        $booking_exists = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($booking_exists) {
            $update_stmt = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
            $update_stmt->bind_param("i", $booking_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['alert'] = ['type'=>'success','title'=>'Booking Completed!','text'=>'Booking has been marked as completed. User can now rate your service.'];
            } else {
                $_SESSION['alert'] = ['type'=>'error','title'=>'Error','text'=>'Failed to update booking status.'];
            }
            $update_stmt->close();
        } else {
            $_SESSION['alert'] = ['type'=>'error','title'=>'Error','text'=>'Booking not found or not confirmed.'];
        }
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// ===== DELETE AVAILABILITY WITH CONFIRMATION =====
if (isset($_POST['delete_availability']) && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    $delete_id = $_POST['delete_id'];

    $stmt = $conn->prepare("DELETE FROM availability WHERE id = ? AND diver_id = ?");
    $stmt->bind_param("ii", $delete_id, $diver_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['alert'] = ['type'=>'success','title'=>'Slot Removed','text'=>'Availability slot deleted successfully.'];
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// ===== ADD AVAILABILITY WITH REAL-TIME VALIDATION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_availability'])) {
    $available_date = $_POST['available_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $destination_id = intval($_POST['destination_id']);
    $max_slots = intval($_POST['max_slots'] ?? $diver['max_pax'] ?? 6);
    
    // Real-time validation: Check if date/time is in the past
    $current_datetime = date('Y-m-d H:i:s');
    $selected_datetime = $available_date . ' ' . $start_time;
    
    if (strtotime($selected_datetime) <= strtotime($current_datetime)) {
        $_SESSION['alert'] = ['type'=>'error','title'=>'Invalid Schedule','text'=>'Cannot set availability for past date/time.'];
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    
    $available_time = $start_time.' - '.$end_time;
    
    if (!empty($available_date) && !empty($available_time) && !empty($destination_id)) {
        // Check if destination_id column exists
        $check_columns = $conn->query("SHOW COLUMNS FROM availability LIKE 'destination_id'");
        if ($check_columns->num_rows > 0) {
            // New structure with destination
            $insert = $conn->prepare("INSERT INTO availability (diver_id, destination_id, available_date, available_time, start_time, end_time, max_slots, available_slots, booked_slots, booking_deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, '2 hours', 'available')");
            $available_slots = $max_slots;
            $insert->bind_param("iissssii", $diver_id, $destination_id, $available_date, $available_time, $start_time, $end_time, $max_slots, $available_slots);
        } else {
            // Old structure without destination
            $insert = $conn->prepare("INSERT INTO availability (diver_id, available_date, available_time, start_time, end_time, max_slots, available_slots, booked_slots, booking_deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, 0, '2 hours', 'available')");
            $available_slots = $max_slots;
            $insert->bind_param("issssii", $diver_id, $available_date, $available_time, $start_time, $end_time, $max_slots, $available_slots);
        }
        
        if ($insert->execute()) {
            $_SESSION['alert'] = ['type'=>'success','title'=>'Availability Added','text'=>'New availability slot has been added.'];
        } else {
            $_SESSION['alert'] = ['type'=>'error','title'=>'Error','text'=>'Failed to add availability slot.'];
        }
        $insert->close();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['alert'] = ['type'=>'error','title'=>'Missing Information','text'=>'Please fill all required fields including destination.'];
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// ===== UPDATED: FETCH AVAILABILITY WITH DESTINATION =====
// Check availability table structure and fetch accordingly
$check_columns = $conn->query("SHOW COLUMNS FROM availability LIKE 'destination_id'");
if ($check_columns->num_rows > 0) {
    // New structure with destination
    $avail_stmt = $conn->prepare("
        SELECT a.id, a.available_date, a.available_time, a.start_time, a.end_time, a.max_slots, a.available_slots, a.booked_slots, a.booking_deadline, a.status,
               d.title AS destination_title, d.location AS destination_location
        FROM availability a 
        LEFT JOIN destinations d ON a.destination_id = d.destination_id
        WHERE a.diver_id = ? 
        AND (
            status != 'completed' 
            OR booked_slots > 0
        )
        AND (
            available_date > CURDATE() 
            OR 
            (
                available_date = CURDATE() 
                AND ADDTIME(start_time, '-01:00:00') > CURTIME()
            )
        )
        ORDER BY available_date ASC, start_time ASC
    ");
} else {
    // Old structure without destination
    $avail_stmt = $conn->prepare("
        SELECT id, available_date, available_time, start_time, end_time, max_slots, available_slots, booked_slots, booking_deadline, status 
        FROM availability 
        WHERE diver_id = ? 
        AND (
            status != 'completed' 
            OR booked_slots > 0
        )
        AND (
            available_date > CURDATE() 
            OR 
            (
                available_date = CURDATE() 
                AND ADDTIME(start_time, '-01:00:00') > CURTIME()
            )
        )
        ORDER BY available_date ASC, start_time ASC
    ");
}
$avail_stmt->bind_param("i", $diver_id);
$avail_stmt->execute();
$availability_list = $avail_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$avail_stmt->close();

// ===== FETCH: bookings (all), gears =====
$bookings = $conn->prepare("
    SELECT b.*, u.fullname AS user_name, u.email AS user_email, u.phone AS user_phone, 
           u.certify_agency, u.certification_level, u.diver_id_number,
           (SELECT COUNT(*) FROM user_gears ug WHERE ug.user_id = u.id) as has_gear
    FROM bookings b 
    JOIN users u ON b.user_id = u.id
    WHERE b.diver_id = ? 
    ORDER BY 
        CASE 
            WHEN b.status = 'pending' THEN 1
            WHEN b.status = 'confirmed' THEN 2
            WHEN b.status = 'completed' THEN 3
            ELSE 4
        END,
        b.booking_date DESC
");
$bookings->bind_param("i", $diver_id);
$bookings->execute();
$booking_list = $bookings->get_result()->fetch_all(MYSQLI_ASSOC);
$bookings->close();

$pending_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE diver_id = ? AND status = 'pending'");
$pending_stmt->bind_param("i", $diver_id);
$pending_stmt->execute();
$pending_count = $pending_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$pending_stmt->close();

$history_stmt = $conn->prepare("SELECT b.*, u.fullname AS user_name
                               FROM bookings b JOIN users u ON b.user_id = u.id
                               WHERE b.diver_id = ? AND b.status = 'confirmed' ORDER BY b.booking_date DESC");
$history_stmt->bind_param("i", $diver_id);
$history_stmt->execute();
$history_list = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

$gears_stmt = $conn->prepare("SELECT * FROM diver_gears WHERE diver_id = ?");
$gears_stmt->bind_param("i", $diver_id);
$gears_stmt->execute();
$gears = $gears_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$gears_stmt->close();

$tab = $_GET['tab'] ?? 'dashboard';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MasterDiver Dashboard | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<style>
    header {
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    /* Smooth transitions for mobile menu */
    #mobileMenu {
      transition: all 0.3s ease-in-out;
    }

    /* Prevent layout shift on mobile */
    @media (max-width: 768px) {
      .container {
        padding-left: 1rem;
        padding-right: 1rem;
      }
    }
  
    body {
        background-image: url('../assets/images/dive background.jpg');
        background-size: cover;
        background-repeat: no-repeat;
        background-attachment: fixed;
    }

    .bg-white {
        background-color: rgba(255, 255, 255, 0.85) !important;
        backdrop-filter: blur(4px);
    }
    
    .slot-indicator {
        font-size: 0.75rem;
        padding: 2px 6px;
        border-radius: 8px;
    }
    
    .slot-available { background: #dcfce7; color: #166534; }
    .slot-limited { background: #fef3c7; color: #92400e; }
    .slot-full { background: #fee2e2; color: #991b1b; }
    .slot-expired { background: #e5e7eb; color: #6b7280; }
    
    .time-status {
        font-size: 0.7rem;
        padding: 1px 4px;
        border-radius: 4px;
        margin-left: 5px;
    }
    
    .status-ongoing { background: #fef3c7; color: #92400e; }
    .status-upcoming { background: #dcfce7; color: #166534; }
    .status-completed { background: #e5e7eb; color: #6b7280; }
</style>

<body class="min-h-screen flex flex-col">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow-md">
  <div class="container mx-auto flex justify-between items-center p-4">

    <!-- Logo -->
    <div class="flex items-center gap-4">
      <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" class="h-12">
    </div>

    <!-- NAV + PROFILE + NOTIF -->
    <div class="flex items-center gap-4">

      <!-- Desktop Navigation -->
      <div class="hidden md:flex items-center gap-3">

        <a href="manage_destinations.php"
           class="px-3 py-2 rounded bg-white text-blue-700 font-semibold hover:bg-blue-100 transition no-underline">
          <i class="ri-map-pin-line mr-1"></i>Destinations
        </a>

        <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/"
           target="_blank"
           class="px-3 py-2 rounded bg-white text-blue-700 font-semibold hover:bg-blue-100 transition no-underline">
          <i class="ri-timer-line mr-1"></i>Tide Chart
        </a>

        <a href="diver_dashboard.php?tab=history"
           class="px-3 py-2 rounded bg-white text-blue-700 font-semibold hover:bg-blue-100 transition no-underline <?= ($tab === 'history') ? 'bg-blue-200' : '' ?>">
          <i class="ri-history-line mr-1"></i>Booking History
        </a>

      </div>

      <!-- Notification Bell -->
      <button id="notifBtn" title="Notifications"
        class="relative p-2 rounded-full hover:bg-blue-600/20"
        data-bs-toggle="modal" data-bs-target="#notifModal">

        <svg xmlns="http://www.w3.org/2000/svg"
             class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 005 14h14a1 1 0 00.707-1.707L18 11.586V8a6 6 0 00-6-6zM8 20a4 4 0 008 0H8z"/>
        </svg>

        <?php if($pending_count > 0): ?>
          <span class="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full px-1.5">
            <?= intval($pending_count) ?>
          </span>
        <?php else: ?>
          <span class="hidden absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full px-1.5">0</span>
        <?php endif; ?>
      </button>

      <!-- Desktop Profile -->
      <div class="hidden md:flex items-center gap-3 ml-2">

        <div class="text-right mr-2">
          <div class="text-white font-bold text-lg"><?= htmlspecialchars($diver['fullname']) ?></div>
          <div class="text-blue-200 text-sm"><?= htmlspecialchars($diver['specialty'] ?? '') ?></div>
        </div>

        <img src="../admin/uploads/<?= htmlspecialchars($diver['profile_pic'] ?: 'default.png') ?>"
             class="w-12 h-12 rounded-full border-2 border-white object-cover">

        <a href="../diver/settings.php"
           class="bg-blue-600 hover:bg-blue-800 px-3 py-1.5 rounded no-underline text-white">⚙️</a>

        <!-- UPDATED LOGOUT BUTTON -->
        <a href="#" onclick="confirmLogout(event)"
           class="bg-red-500 hover:bg-red-600 px-3 py-1.5 rounded no-underline text-white">Logout</a>

      </div>

      <!-- Mobile: Avatar + Menu Button -->
      <div class="flex md:hidden items-center gap-2">
        <img src="../admin/uploads/<?= htmlspecialchars($diver['profile_pic'] ?: 'default.png') ?>"
             class="w-10 h-10 rounded-full border-2 border-white object-cover">

        <button id="mobileMenuBtn"
          class="p-2 rounded hover:bg-blue-600/20"
          aria-label="Menu">

          <svg xmlns="http://www.w3.org/2000/svg"
               class="h-6 w-6" fill="none" viewBox="0 0 24 24"
               stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"/>
          </svg>

        </button>
      </div>

    </div>
  </div>

  <!-- MOBILE MENU -->
  <div id="mobileMenu" class="hidden md:hidden bg-blue-800 border-t border-blue-600">
    <div class="px-4 py-4 space-y-2">

      <!-- Profile Info -->
      <div class="pb-3 border-b border-blue-600/30 mb-3">
        <div class="text-white font-bold"><?= htmlspecialchars($diver['fullname']) ?></div>
        <div class="text-blue-200 text-sm"><?= htmlspecialchars($diver['specialty'] ?? '') ?></div>
      </div>

      <!-- Mobile Navigation -->
      <a href="<?php echo $_SERVER['PHP_SELF']; ?>"
        class="block px-4 py-2.5 rounded hover:bg-blue-700 text-white no-underline <?= ($tab === 'dashboard') ? 'bg-blue-600' : '' ?>">
        <i class="ri-dashboard-line mr-2"></i>Dashboard
      </a>

      <a href="manage_destinations.php"
        class="block px-4 py-2.5 rounded hover:bg-blue-700 text-white no-underline">
        <i class="ri-map-pin-line mr-2"></i>Destinations
      </a>

      <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/"
        class="block px-4 py-2.5 rounded hover:bg-blue-700 text-white no-underline">
        <i class="ri-timer-line mr-2"></i>Tide Chart
      </a>

      <a href="?tab=history"
        class="block px-4 py-2.5 rounded hover:bg-blue-700 text-white no-underline <?= ($tab === 'history') ? 'bg-blue-600' : '' ?>">
        <i class="ri-history-line mr-2"></i>Booking History
      </a>

      <div class="border-t border-blue-600/30 my-3"></div>

      <!-- Actions -->
      <a href="../diver/settings.php"
        class="block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded text-center font-semibold no-underline">
        <i class="ri-settings-3-line mr-2"></i>Settings
      </a>

      <!-- UPDATED MOBILE LOGOUT -->
      <a href="#" onclick="confirmLogout(event)"
        class="block bg-red-500 hover:bg-red-600 text-white px-4 py-2.5 rounded text-center font-semibold no-underline">
        <i class="ri-logout-box-r-line mr-2"></i>Logout
      </a>

    </div>
  </div>

</header>



<main class="flex-grow container mx-auto p-6 space-y-8">

  <!-- GREETING -->
  <section class="bg-white rounded-xl shadow-md p-6">
    <div class="text-left">
      <h1 class="text-4xl font-extrabold text-blue-700"><?= htmlspecialchars($diver['fullname']) ?></h1>
      <p class="text-gray-600 mt-1">Manage your rates, capacity, gears, bookings, and availability below.</p>
    </div>
  </section>
  
  <?php if($tab === 'dashboard'): ?>

  <!-- DIVE SETTINGS & EARNINGS -->
  <section class="bg-white rounded-xl shadow-md p-6">
    <h2 class="text-xl font-semibold text-blue-700 mb-4">⚙️ Dive Settings & Earnings</h2> 
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Max PAX Capacity -->
      <div class="border-2 border-blue-300 rounded-lg p-4 bg-gradient-to-r from-blue-50 to-indigo-50">
        <h3 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
          <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
          </svg>
          Maximum Diver Capacity
        </h3>
        <form method="POST" class="space-y-3">
          <select name="max_pax" class="border-2 border-gray-300 rounded-lg px-3 py-2 w-full focus:ring-2 focus:ring-blue-500" required>
            <?php for($i=1; $i<=6; $i++): ?>
              <option value="<?= $i ?>" <?= (intval($diver['max_pax'] ?? 6) === $i) ? 'selected' : '' ?>>
                <?= $i ?> <?= $i === 1 ? 'Diver' : 'Divers' ?>
              </option>
            <?php endfor; ?>
          </select>
          <button type="submit" name="update_max_pax" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-lg font-semibold w-full">Update Capacity</button>
        </form>
        <p class="text-gray-600 mt-3 text-sm">Current Capacity: <span class="font-semibold text-blue-700 text-lg"><?= intval($diver['max_pax'] ?? 6) ?> divers</span></p>
        <p class="text-xs text-gray-500 mt-2">💡 This sets how many divers you can handle per session</p>
      </div>

      <!-- Total Earnings -->
      <div class="border-2 border-purple-300 rounded-lg p-4 bg-gradient-to-r from-purple-50 to-violet-50">
        <h3 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
          <i class="ri-wallet-3-line text-purple-600"></i>
          Total Earnings
        </h3>
        <div class="text-center space-y-2">
          <div class="text-3xl font-bold text-purple-700">₱<?= number_format($total_earned, 2) ?></div>
          <div class="text-sm text-gray-600">From <?= $total_bookings ?> confirmed booking<?= $total_bookings !== 1 ? 's' : '' ?></div>
        </div>
        <div class="mt-4 space-y-2">
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Total Bookings:</span>
            <span class="font-semibold"><?= $total_bookings ?></span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-600">Total Earned:</span>
            <span class="font-semibold text-green-600">₱<?= number_format($total_earned, 2) ?></span>
          </div>
        </div>
        <p class="text-xs text-gray-500 mt-3">💡 Earnings from confirmed and completed bookings only</p>
      </div>
    </div>
  </section>

  <!-- RECENT EARNINGS BREAKDOWN -->
  <?php if (count($recent_bookings) > 0): ?>
  <section class="bg-white rounded-xl shadow-md p-6">
    <h2 class="text-xl font-semibold text-blue-700 mb-4">💰 Recent Earnings</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm border border-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-2 text-left">Client</th>
            <th class="px-4 py-2 text-left">Booking Date</th>
            <th class="px-4 py-2 text-center">PAX</th>
            <th class="px-4 py-2 text-right">Amount</th>
            <th class="px-4 py-2 text-center">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent_bookings as $booking): ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="px-4 py-2"><?= htmlspecialchars($booking['user_name']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($booking['booking_date']) ?></td>
              <td class="px-4 py-2 text-center">
                <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded font-semibold"><?= intval($booking['pax_count'] ?? 1) ?></span>
              </td>
              <td class="px-4 py-2 text-right font-semibold text-green-700">
                ₱<?= number_format($booking['grand_total'], 2) ?>
              </td>
              <td class="px-4 py-2 text-center">
                <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-semibold">
                  <?= htmlspecialchars(ucfirst($booking['status'])) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="mt-4 bg-green-100 text-green-800 px-4 py-2 rounded shadow text-right">
      <strong>Total Shown: ₱<?= number_format(array_sum(array_column($recent_bookings, 'grand_total')), 2) ?></strong>
    </div>
  </section>
  <?php endif; ?>

  <!-- MANAGE GEARS -->
  <section class="bg-white rounded-xl shadow-md p-6">
    <div class="flex justify-between items-center mb-3">
      <h2 class="text-xl font-semibold text-blue-700">🧰 Manage Your Gears</h2>
      <button onclick="openGearModal()" class="bg-blue-700 hover:bg-blue-800 text-white px-3 py-1 rounded">Add Gear</button>
    </div>

    <?php if (count($gears) > 0): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2 text-left">Gear</th>
              <th class="px-4 py-2 text-left">Price (₱)</th>
              <th class="px-4 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($gears as $g): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-2"><?= htmlspecialchars($g['gear_name']) ?></td>
                <td class="px-4 py-2"><?= number_format($g['price'],2) ?></td>
                <td class="px-4 py-2 text-center">
                  <a href="#" onclick="confirmDeleteGear(event, <?= $g['id'] ?>)" class="text-red-600 hover:underline">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-500 text-center mt-3">No gears added yet.</p>
    <?php endif; ?>
  </section>

  <!-- BOOKINGS -->
  <section id="bookingsSection" class="bg-white rounded-xl shadow-md p-6">
    <h2 class="text-xl font-semibold text-blue-700 mb-4">📅 Booking Requests</h2>

    <?php if (count($booking_list) > 0): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200">
          <thead class="bg-blue-700 text-white">
            <tr>
              <th class="px-4 py-2">Name</th>
              <th class="px-4 py-2">Email</th>
              <th class="px-4 py-2">Phone</th>
              <th class="px-4 py-2">Date</th>
              <th class="px-4 py-2">PAX</th>
              <th class="px-4 py-2">Payment</th>
              <th class="px-4 py-2">Status</th>
              <th class="px-4 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($booking_list as $b): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-2"><?= htmlspecialchars($b['user_name']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($b['user_email']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($b['user_phone']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($b['booking_date']) ?></td>
                <td class="px-4 py-2 text-center">
                  <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded font-semibold"><?= intval($b['pax_count'] ?? 1) ?></span>
                </td>
                <td class="px-4 py-2">
                  <?= htmlspecialchars(ucfirst($b['payment_method'] ?? 'N/A')) ?>
                </td>
                <td class="px-4 py-2">
                  <?php if ($b['status'] === 'pending'): ?>
                    <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-semibold">Pending</span>
                  <?php elseif ($b['status'] === 'confirmed'): ?>
                    <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-semibold">Confirmed</span>
                  <?php elseif ($b['status'] === 'completed'): ?>
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-semibold">Completed</span>
                  <?php elseif ($b['status'] === 'cancelled'): ?>
                    <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-semibold">Cancelled</span>
                  <?php else: ?>
                    <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-semibold">Declined</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-center flex justify-center gap-2">
                  <button onclick='openModal(<?= json_encode($b, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)' class="text-blue-600 hover:text-blue-800">👁️</button>
                  <?php if ($b['status']==='pending'): ?>
                    <button onclick="showConfirmModal(<?= $b['id'] ?>)" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm font-semibold">Confirm</button>
                    <button onclick="showDeclineModal(<?= $b['id'] ?>)" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm font-semibold">Decline</button>
                  <?php elseif ($b['status']==='confirmed'): ?>
                    <button onclick="showCompleteModal(<?= $b['id'] ?>)" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-semibold">Mark as Done</button>
                  <?php elseif ($b['status']==='completed'): ?>
                    <span class="text-green-600 text-sm font-semibold">✅ Done</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-500 text-center mt-4">No bookings found.</p>
    <?php endif; ?>
  </section>

  <!-- UPDATED: AVAILABILITY SECTION WITH DESTINATION -->
  <section class="bg-white rounded-xl shadow-md p-6">
    <h2 class="text-xl font-semibold text-blue-700 mb-4">🕒 My Availability</h2>
    
    <!-- Add Availability Form -->
    <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6 items-end">
      <div>
        <label class="block text-sm font-medium text-gray-700">Date</label>
        <input type="date" name="available_date" id="available_date" required 
               min="<?= date('Y-m-d') ?>" 
               class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Start Time</label>
        <input type="time" name="start_time" id="start_time" required class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">End Time</label>
        <input type="time" name="end_time" required class="border rounded px-3 py-2 w-full">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Destination</label>
        <select name="destination_id" required class="border rounded px-3 py-2 w-full">
          <option value="">Select Destination</option>
          <?php foreach ($destinations as $destination): ?>
            <option value="<?= $destination['destination_id'] ?>">
              <?= htmlspecialchars($destination['title']) ?> - <?= htmlspecialchars($destination['location']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" name="add_availability" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded font-semibold w-full">Add Slot</button>
      </div>
    </form>
    
    <!-- Real-time validation message -->
    <div id="timeValidation" class="text-sm text-red-600 mb-4 hidden"></div>
    
    <?php if (count($availability_list)>0): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2">Date</th>
              <th class="px-4 py-2">Time</th>
              <th class="px-4 py-2">Destination</th>
              <th class="px-4 py-2">Slots</th>
              <th class="px-4 py-2">Status</th>
              <th class="px-4 py-2">Time Status</th>
              <th class="px-4 py-2">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($availability_list as $a): 
              // Check if new structure exists
              $has_destination = isset($a['destination_title']);
              
              if ($has_destination) {
                  $available_slots = $a['available_slots'] ?? $a['max_slots'] ?? 0;
                  $max_slots = $a['max_slots'] ?? 6;
                  $booked_slots = $a['booked_slots'] ?? 0;
                  
                  // DEBUG: Check if calculation is correct
                  $calculated_available = $max_slots - $booked_slots;
                  
                  // Use the calculated value if database value seems wrong
                  if ($available_slots != $calculated_available && $available_slots < 0) {
                      $available_slots = $calculated_available;
                  }
                  
                  // Ensure available_slots is not negative
                  $available_slots = max(0, $available_slots);
                  
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
                  
                  // Determine time status
                  $current_time = date('H:i:s');
                  $slot_date = $a['available_date'];
                  $start_time = $a['start_time'] ?? explode(' - ', $a['available_time'])[0];
                  $end_time = $a['end_time'] ?? explode(' - ', $a['available_time'])[1] ?? '';
                  
                  $time_status = '';
                  $time_class = '';
                  
                  if ($a['status'] === 'completed') {
                      $time_status = 'Completed';
                      $time_class = 'status-completed';
                  } elseif ($slot_date < date('Y-m-d')) {
                      $time_status = 'Past';
                      $time_class = 'status-completed';
                  } elseif ($slot_date == date('Y-m-d') && $start_time < $current_time) {
                      if ($booked_slots > 0) {
                          $time_status = 'Ongoing';
                          $time_class = 'status-ongoing';
                      } else {
                          $time_status = 'Expired';
                          $time_class = 'status-completed';
                      }
                  } else {
                      $time_status = 'Upcoming';
                      $time_class = 'status-upcoming';
                  }
                  
              } else {
                  // Old structure - show default values
                  $max_slots = $diver['max_pax'] ?? 6;
                  $available_slots = $max_slots;
                  $slot_class = 'slot-available';
                  $slot_text = 'Available';
                  $time_status = 'Upcoming';
                  $time_class = 'status-upcoming';
              }
            ?>
              <tr class="border-b">
                <td class="px-4 py-2"><?= htmlspecialchars($a['available_date']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($a['available_time']) ?></td>
                <td class="px-4 py-2">
                  <?php if ($has_destination && !empty($a['destination_title'])): ?>
                    <div>
                      <strong><?= htmlspecialchars($a['destination_title']) ?></strong>
                      <div class="text-xs text-gray-600"><?= htmlspecialchars($a['destination_location']) ?></div>
                    </div>
                  <?php else: ?>
                    <span class="text-gray-500 italic">No destination set</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2">
                  <span class="slot-indicator <?= $slot_class ?>">
                    <?= $available_slots ?>/<?= $max_slots ?> <?= $slot_text ?>
                  </span>
                </td>
                <td class="px-4 py-2">
                  <span class="capitalize px-2 py-1 rounded text-xs font-semibold <?= $a['status'] === 'completed' ? 'bg-gray-100 text-gray-700' : ($a['status'] === 'fully_booked' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700') ?>">
                    <?= htmlspecialchars($a['status']) ?>
                  </span>
                </td>
                <td class="px-4 py-2">
                  <span class="time-status <?= $time_class ?>"><?= $time_status ?></span>
                </td>
                <td class="px-4 py-2 text-center">
                  <?php if ($a['status'] !== 'completed' && $time_status !== 'Expired'): ?>
                    <button onclick="confirmDeleteAvailability(<?= $a['id'] ?>)" 
                      class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                      Delete
                    </button>
                  <?php else: ?>
                    <span class="text-gray-400 text-xs">Completed/Expired</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-500 text-center mt-4">No availability yet.</p>
    <?php endif; ?>
  </section>

  <?php endif; ?>

  <?php if($tab === 'history'): ?>
  <!-- BOOKING HISTORY -->
  <section class="bg-white rounded-xl shadow-md p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold text-blue-700">📚 Booking History (Confirmed)</h2>
      <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="text-sm text-blue-600 hover:underline">Back to Dashboard</a>
    </div>

    <?php if (count($history_list) > 0): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-4 py-2">Client</th>
              <th class="px-4 py-2">Date</th>
              <th class="px-4 py-2">PAX</th>
              <th class="px-4 py-2">Remarks</th>
              <th class="px-4 py-2">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history_list as $h): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="px-4 py-2"><?= htmlspecialchars($h['user_name']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($h['booking_date']) ?></td>
                <td class="px-4 py-2 text-center">
                  <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded font-semibold"><?= intval($h['pax_count'] ?? 1) ?></span>
                </td>
                <td class="px-4 py-2"><?= htmlspecialchars($h['remarks'] ?? '') ?></td>
                <td class="px-4 py-2"><span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-semibold">Confirmed</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-500 text-center mt-4">No booking history found.</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

</main>

<!-- MODALS -->
<!-- Notifications Modal -->
<div class="modal fade" id="notifModal" tabindex="-1" aria-labelledby="notifModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-blue-700 text-white">
        <h5 class="modal-title" id="notifModalLabel">📢 Notifications</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if($pending_count > 0): ?>
          <div class="alert alert-warning">
            You have <strong><?= $pending_count ?></strong> pending booking<?= $pending_count > 1 ? 's' : '' ?> waiting for your response.
          </div>
        <?php else: ?>
          <div class="alert alert-info">
            You're all caught up! No pending notifications.
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- CONFIRM BOOKING MODAL -->
<div id="confirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg w-96 relative">
    <button onclick="closeConfirmModal()" class="absolute top-2 right-3 text-gray-500 hover:text-gray-800 text-xl">&times;</button>
    <h3 class="text-lg font-bold text-blue-700 mb-3">Confirm Booking</h3>
    <form method="POST" id="confirmForm" class="space-y-3">
      <input type="hidden" name="booking_id" id="confirmBookingId">
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes (Optional)</label>
        <textarea name="confirm_remarks" class="border rounded w-full p-2 text-sm" rows="4" placeholder="Add any additional notes or instructions..."></textarea>
      </div>
      
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeConfirmModal()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded">Cancel</button>
        <button type="submit" name="confirm_booking" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">Confirm Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- DECLINE BOOKING MODAL -->
<div id="declineModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg w-96 relative">
    <button onclick="closeDeclineModal()" class="absolute top-2 right-3 text-gray-500 hover:text-gray-800 text-xl">&times;</button>
    <h3 class="text-lg font-bold text-blue-700 mb-3">Decline Booking</h3>
    <form method="POST" id="declineForm" class="space-y-3">
      <input type="hidden" name="booking_id" id="declineBookingId">
      
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection *</label>
        <textarea name="decline_remarks" class="border rounded w-full p-2 text-sm" rows="4" placeholder="Please provide a reason for rejecting this booking..." required></textarea>
        <p class="text-xs text-gray-500 mt-1">Remarks are required when declining a booking</p>
      </div>
      
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeDeclineModal()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded">Cancel</button>
        <button type="submit" name="decline_booking" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded">Decline Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- MARK AS COMPLETED MODAL -->
<div id="completeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg w-96 relative">
    <button onclick="closeCompleteModal()" class="absolute top-2 right-3 text-gray-500 hover:text-gray-800 text-xl">&times;</button>
    <h3 class="text-lg font-bold text-blue-700 mb-3">Mark Booking as Completed</h3>
    <form method="POST" id="completeForm" class="space-y-4">
      <input type="hidden" name="booking_id" id="completeBookingId">
      
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-center gap-2 text-yellow-800 mb-2">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
          <span class="font-semibold">Important Notice</span>
        </div>
        <p class="text-sm text-yellow-700">
          Once marked as completed, the user will be able to rate your service. This action cannot be undone.
        </p>
      </div>
      
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeCompleteModal()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded">Cancel</button>
        <button type="submit" name="mark_completed" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">Mark as Completed</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD GEAR MODAL -->
<div id="gearModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg w-96 relative">
    <button onclick="closeGearModal()" class="absolute top-2 right-3 text-gray-500 hover:text-gray-800 text-xl">&times;</button>
    <h3 class="text-lg font-bold text-blue-700 mb-4">➕ Add Diving Gear</h3>
    <form method="POST" id="addGearForm" class="space-y-4">
      <input type="text" name="gear_name" placeholder="Gear Name (e.g. Mask, Fins, Wetsuit)" required class="border rounded w-full p-2">
      <input type="number" name="gear_price" step="0.01" placeholder="Price (₱)" required class="border rounded w-full p-2">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeGearModal()" class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded">Cancel</button>
        <button type="submit" name="add_gear" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- BOOKING DETAILS MODAL WITH ENHANCED INFORMATION -->
<div id="bookingModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg w-96 max-h-[80vh] overflow-y-auto relative">
    <button onclick="closeModal()" class="absolute top-2 right-3 text-gray-500 hover:text-gray-800 text-xl">&times;</button>
    <h3 class="text-lg font-bold text-blue-700 mb-4">📋 Booking Details</h3>
    <div id="modalContent" class="space-y-3 text-sm"></div>
    <button onclick="closeModal()" class="mt-4 bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded w-full">Close</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Mobile menu toggle
document.getElementById('mobileMenuBtn')?.addEventListener('click', function() {
  const menu = document.getElementById('mobileMenu');
  const icon = this.querySelector('svg');
  
  menu.classList.toggle('hidden');
  
  if (!menu.classList.contains('hidden')) {
    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />';
  } else {
    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />';
  }
});

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
  const menu = document.getElementById('mobileMenu');
  const menuBtn = document.getElementById('mobileMenuBtn');
  
  if (!menu.contains(event.target) && !menuBtn.contains(event.target) && !menu.classList.contains('hidden')) {
    menu.classList.add('hidden');
    menuBtn.querySelector('svg').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />';
  }
});

// Real-time availability validation
document.addEventListener('DOMContentLoaded', function() {
  const dateInput = document.getElementById('available_date');
  const timeInput = document.getElementById('start_time');
  const validationDiv = document.getElementById('timeValidation');
  
  function validateDateTime() {
    if (dateInput.value && timeInput.value) {
      const selectedDateTime = new Date(dateInput.value + 'T' + timeInput.value);
      const currentDateTime = new Date();
      
      if (selectedDateTime <= currentDateTime) {
        validationDiv.classList.remove('hidden');
        validationDiv.textContent = '❌ Cannot set availability for past date/time.';
        return false;
      } else {
        validationDiv.classList.add('hidden');
        return true;
      }
    }
    return true;
  }
  
  dateInput?.addEventListener('change', validateDateTime);
  timeInput?.addEventListener('change', validateDateTime);
});

// Confirm logout
function confirmLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure you want to logout?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffffffff',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'logout.php';
        }
    });
}

// Confirm delete gear
function confirmDeleteGear(e, gearId) {
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure you want to delete this gear?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete_gear=' + gearId + '&confirm=yes';
        }
    });
}

// Confirm delete availability
function confirmDeleteAvailability(availId) {
    Swal.fire({
        title: 'Are you sure you want to delete this availability slot?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_availability" value="1">
                <input type="hidden" name="delete_id" value="${availId}">
                <input type="hidden" name="confirm_delete" value="yes">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Mark as Completed Modal Functions
function showCompleteModal(bookingId) {
  document.getElementById('completeBookingId').value = bookingId;
  document.getElementById('completeModal').classList.remove('hidden');
}

function closeCompleteModal() {
  document.getElementById('completeModal').classList.add('hidden');
}

// Enhanced booking details modal
function openModal(data){
  const m = document.getElementById('bookingModal');
  const c = document.getElementById('modalContent');
  
  const sig = data.user_signature ? ('../user/uploads/' + data.user_signature) : null;
  const rcpt = data.gcash_receipt ? ('../user/uploads/' + data.gcash_receipt) : null;
  const pax = data.pax_count ? parseInt(data.pax_count) : 1;
  
  // Enhanced content with diver profile information
  c.innerHTML = `
    <div class="space-y-3">
      <div class="border-b pb-3">
        <h4 class="font-semibold text-gray-800">👤 Diver Information</h4>
        <p><b>Name:</b> ${escapeHtml(data.user_name)}</p>
        <p><b>Email:</b> ${escapeHtml(data.user_email)}</p>
        <p><b>Phone:</b> ${escapeHtml(data.user_phone)}</p>
        ${data.certify_agency ? `<p><b>Certifying Agency:</b> ${escapeHtml(data.certify_agency)}</p>` : ''}
        ${data.certification_level ? `<p><b>Certification Level:</b> ${escapeHtml(data.certification_level)}</p>` : ''}
        ${data.diver_id_number ? `<p><b>Diver ID:</b> ${escapeHtml(data.diver_id_number)}</p>` : ''}
        <p><b>Gear Status:</b> 
          <span class="${data.has_gear > 0 ? 'text-green-600 font-semibold' : 'text-orange-600 font-semibold'}">
            ${data.has_gear > 0 ? '✅ Has own gear' : '🔄 Needs gear rental'}
          </span>
        </p>
      </div>
      
      <div class="border-b pb-3">
        <h4 class="font-semibold text-gray-800">📅 Booking Details</h4>
        <p><b>Date:</b> ${escapeHtml(data.booking_date)}</p>
        <p><b>Number of Divers:</b> <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded font-semibold">${pax}</span></p>
        <p><b>Status:</b> <span class="capitalize ${getStatusColor(data.status)}">${escapeHtml(data.status)}</span></p>
        ${data.remarks ? `<p><b>Remarks:</b> ${escapeHtml(data.remarks)}</p>` : ''}
      </div>
      
      ${sig ? `
      <div class="border-b pb-3">
        <h4 class="font-semibold text-gray-800">✍️ Signature</h4>
        <img src="${sig}" class="w-40 h-20 object-contain border rounded bg-gray-50">
      </div>
      ` : ''}
      
      ${rcpt ? `
      <div>
        <h4 class="font-semibold text-gray-800">🧾 GCash Receipt</h4>
        <img src="${rcpt}" class="w-40 h-40 object-cover border rounded">
      </div>
      ` : ''}
    </div>
  `;
  m.classList.remove('hidden');
}

function getStatusColor(status) {
  switch(status) {
    case 'confirmed': return 'text-green-600 bg-green-100 px-2 py-1 rounded';
    case 'pending': return 'text-yellow-600 bg-yellow-100 px-2 py-1 rounded';
    case 'completed': return 'text-blue-600 bg-blue-100 px-2 py-1 rounded';
    case 'declined': return 'text-red-600 bg-red-100 px-2 py-1 rounded';
    default: return 'text-gray-600 bg-gray-100 px-2 py-1 rounded';
  }
}

function closeModal(){
  document.getElementById('bookingModal').classList.add('hidden');
}

// Separate modals for confirm and decline
function showConfirmModal(bookingId) {
  document.getElementById('confirmBookingId').value = bookingId;
  document.getElementById('confirmModal').classList.remove('hidden');
}

function closeConfirmModal() {
  document.getElementById('confirmModal').classList.add('hidden');
}

function showDeclineModal(bookingId) {
  document.getElementById('declineBookingId').value = bookingId;
  document.getElementById('declineModal').classList.remove('hidden');
}

function closeDeclineModal() {
  document.getElementById('declineModal').classList.add('hidden');
}

function openGearModal(){
  document.getElementById('gearModal').classList.remove('hidden');
}

function closeGearModal(){
  document.getElementById('gearModal').classList.add('hidden');
}

function showAlert(type,title,text=''){
  Swal.fire({icon:type,title,text,confirmButtonColor:'#2563eb',timer:2000,showConfirmButton:false});
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

function goToBookings(){
  const el = document.getElementById('bookingsSection');
  if(el){
    el.scrollIntoView({behavior:'smooth', block:'start'});
    el.classList.add('ring-4','ring-yellow-200');
    setTimeout(()=>el.classList.remove('ring-4','ring-yellow-200'), 1600);
  } else {
    window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>';
  }
}

document.addEventListener('DOMContentLoaded', function(){
  const badge = document.getElementById('notifBadge');
  if(badge && badge.textContent.trim() === '0') badge.classList.add('hidden');
});
</script>

<?php if(isset($_SESSION['alert'])): ?>
<script>
document.addEventListener("DOMContentLoaded",()=>showAlert('<?= $_SESSION['alert']['type'] ?>','<?= $_SESSION['alert']['title'] ?>','<?= $_SESSION['alert']['text'] ?>'));
</script>
<?php unset($_SESSION['alert']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  history.pushState(null, null, location.href);
  window.onpopstate = function () {
      history.pushState(null, null, location.href);
  };
</script>
</body>
</html>