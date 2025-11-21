<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if we have booking and diver info from session
if (!isset($_SESSION['booking_to_rate']) || !isset($_SESSION['diver_to_rate'])) {
    $_SESSION['alert'] = ['type'=>'error','title'=>'Invalid Request','text'=>'No booking found to rate.'];
    header("Location: user_dashboard.php");
    exit;
}

$booking_id = $_SESSION['booking_to_rate'];
$diver_id = $_SESSION['diver_to_rate'];
$diver_name = $_SESSION['diver_name_to_rate'];

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $rating = intval($_POST['rating']);
    $review = trim($_POST['review'] ?? '');
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $error = "Please select a valid rating between 1 and 5 stars.";
    } else {
        // Check if already rated
        $check_stmt = $conn->prepare("SELECT id FROM ratings WHERE booking_id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $booking_id, $user_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            $error = "You have already rated this booking.";
        } else {
            // Insert rating
            $insert_stmt = $conn->prepare("INSERT INTO ratings (user_id, diver_id, booking_id, rating, review) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("iiiis", $user_id, $diver_id, $booking_id, $rating, $review);
            
            if ($insert_stmt->execute()) {
                // Update diver's average rating
                $update_avg = $conn->prepare("
                    UPDATE divers 
                    SET rating = (
                        SELECT AVG(rating) 
                        FROM ratings 
                        WHERE diver_id = ?
                    ) 
                    WHERE id = ?
                ");
                $update_avg->bind_param("ii", $diver_id, $diver_id);
                $update_avg->execute();
                $update_avg->close();
                
                // Clear session data
                unset($_SESSION['booking_to_rate']);
                unset($_SESSION['diver_to_rate']);
                unset($_SESSION['diver_name_to_rate']);
                
                $_SESSION['alert'] = ['type'=>'success','title'=>'Thank You!','text'=>'Your rating has been submitted successfully.'];
                header("Location: user_dashboard.php?rated=success");
                exit;
            } else {
                $error = "Failed to submit rating. Please try again.";
            }
            
            $insert_stmt->close();
        }
    }
}

// Get booking details for display
$booking_stmt = $conn->prepare("
    SELECT b.*, d.fullname AS diver_name, d.profile_pic 
    FROM bookings b 
    JOIN divers d ON b.diver_id = d.id 
    WHERE b.id = ? AND b.user_id = ?
");
$booking_stmt->bind_param("ii", $booking_id, $user_id);
$booking_stmt->execute();
$booking = $booking_stmt->get_result()->fetch_assoc();
$booking_stmt->close();

if (!$booking) {
    $_SESSION['alert'] = ['type'=>'error','title'=>'Invalid Booking','text'=>'Booking not found.'];
    header("Location: user_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rate Your Experience | DiveConnect</title>
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
    
    .glass-effect {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
    }
    
    .star-rating {
        transition: all 0.2s ease;
    }
    
    .star-rating:hover {
        transform: scale(1.2);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fadeIn {
        animation: fadeIn 0.4s ease-out;
    }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl glass-effect rounded-2xl shadow-2xl p-8 animate-fadeIn">
        <a href="user_dashboard.php" class="text-blue-700 font-semibold hover:text-blue-800 flex items-center gap-2 transition-colors mb-6">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Dashboard
        </a>
        
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-700 mb-2">Rate Your Dive Experience</h1>
            <p class="text-gray-600">Share your feedback about your dive with <?= htmlspecialchars($diver_name) ?></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 text-red-700 border-2 border-red-400 rounded-lg p-4 mb-6 flex items-center gap-3">
                <svg class="w-6 h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293-1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                </svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Booking Summary -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 mb-8 border-2 border-blue-200">
            <div class="flex items-center gap-4">
                <img src="../admin/uploads/<?= htmlspecialchars($booking['profile_pic'] ?: 'default.png') ?>" 
                     alt="Diver" class="w-16 h-16 rounded-full object-cover border-2 border-blue-300">
                <div class="flex-1">
                    <h3 class="font-bold text-xl text-gray-800"><?= htmlspecialchars($booking['diver_name']) ?></h3>
                    <p class="text-gray-600 text-sm">Booking Date: <?= htmlspecialchars($booking['booking_date']) ?></p>
                    <p class="text-gray-600 text-sm">Divers: <?= intval($booking['pax_count']) ?> person(s)</p>
                </div>
            </div>
        </div>
        
        <!-- Rating Form -->
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-lg font-bold text-gray-800 mb-4 text-center">
                    How would you rate your experience?
                </label>
                <div class="flex justify-center gap-2 mb-4" id="starContainer">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" 
                                class="text-5xl star-rating text-gray-300 hover:text-yellow-400 transition-colors"
                                data-rating="<?= $i ?>"
                                onclick="setRating(<?= $i ?>)">
                            ★
                        </button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" required>
                <p class="text-center text-sm text-gray-500" id="ratingText">Tap to select rating</p>
            </div>
            
            <div>
                <label for="review" class="block text-sm font-bold text-gray-800 mb-2">
                    Optional Review
                </label>
                <textarea name="review" id="review" rows="4" 
                          class="w-full border-2 border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                          placeholder="Share details about your experience... What did you like? Any suggestions for improvement?"></textarea>
            </div>
            
            <button type="submit" name="submit_rating" 
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-4 rounded-lg font-bold text-lg transition-all shadow-lg flex items-center justify-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Submit Rating
            </button>
        </form>
    </div>

    <script>
        let currentRating = 0;
        const ratingTexts = {
            1: "Poor - Very disappointed",
            2: "Fair - Could be better", 
            3: "Good - Met expectations",
            4: "Very Good - Great experience",
            5: "Excellent - Outstanding!"
        };
        
        function setRating(rating) {
            currentRating = rating;
            document.getElementById('ratingInput').value = rating;
            
            // Update stars display
            const stars = document.querySelectorAll('.star-rating');
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.remove('text-gray-300');
                    star.classList.add('text-yellow-400');
                } else {
                    star.classList.remove('text-yellow-400');
                    star.classList.add('text-gray-300');
                }
            });
            
            // Update rating text
            document.getElementById('ratingText').textContent = ratingTexts[rating] || "Tap to select rating";
            document.getElementById('ratingText').className = "text-center text-sm font-medium " + 
                (rating >= 4 ? "text-green-600" : rating >= 3 ? "text-blue-600" : "text-orange-600");
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (currentRating === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Rating Required',
                    text: 'Please select a rating before submitting.',
                    confirmButtonColor: '#1d4ed8'
                });
            }
        });
    </script>
</body>
</html>