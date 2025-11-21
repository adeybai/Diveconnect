<?php
require __DIR__ . '/../includes/db.php';
session_start();

$verified = false;
$error = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT id, email, fullname, verify_token_expires FROM users WHERE verify_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if token is expired
        if (strtotime($user['verify_token_expires']) > time()) {
            // Mark user as verified
            $stmt_update = $conn->prepare("UPDATE users SET is_verified = 1, verify_token = NULL, verify_token_expires = NULL WHERE verify_token = ?");
            $stmt_update->bind_param("s", $token);
            
            if ($stmt_update->execute()) {
                $verified = true;
                
                // Send notification to admin
                try {
                    require_once '../library/mailer.php';
                    $mailer = new DiveConnect\Mailer();
                    
                    // Get admin emails
                    $admin_stmt = $conn->prepare("SELECT email FROM admins WHERE role IN ('admin', 'superadmin')");
                    $admin_stmt->execute();
                    $admin_result = $admin_stmt->get_result();
                    
                    while ($admin = $admin_result->fetch_assoc()) {
                        // Send notification to admin about new user needing approval
                        $mailer->sendAdminNewUserNotification($admin['email'], $user['fullname'], $user['email']);
                    }
                    
                } catch (Exception $e) {
                    error_log("Admin notification error: " . $e->getMessage());
                }
                
                // Store email in session for potential login
                $_SESSION['verified_email'] = $user['email'];
            } else {
                $error = 'Failed to verify email. Please try again.';
            }
        } else {
            $error = 'Verification link has expired. Please request a new verification email.';
        }
    } else {
        $error = 'Invalid verification link.';
    }
} else {
    $error = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | DiveConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-cover bg-center min-h-screen flex items-center justify-center" 
      style="background-image: url('../assets/images/dive background.jpg');">

    <div class="bg-white/90 p-8 rounded-2xl shadow-xl w-full max-w-md text-center">
        <img src="../assets/images/diveconnect_logo.png" alt="DiveConnect Logo" class="mx-auto mb-6 w-24">
        
        <?php if ($verified): ?>
            <h2 class="text-2xl font-bold text-blue-700 mb-4">✅ Email Verified Successfully!</h2>
            <p class="text-gray-700 mb-6">
                Your email has been verified successfully! Your account is now pending admin approval. 
                You will be notified via email once your account is approved.
            </p>
            <a href="../user/user_login.php" 
               class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition">
               Go to Login
            </a>
        <?php else: ?>
            <h2 class="text-2xl font-bold text-red-600 mb-4">❌ Verification Failed</h2>
            <p class="text-gray-700 mb-6">
                <?= htmlspecialchars($error) ?>
            </p>
            <div class="space-y-3">
                <a href="../index.php" 
                   class="inline-block bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-6 rounded-lg transition">
                   Back to Home
                </a>
                <?php if (strpos($error, 'expired') !== false): ?>
                <a href="../user/resend_verification.php" 
                   class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-6 rounded-lg transition">
                   Resend Verification
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($verified): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Email Verified!',
            html: 'Your email has been verified!<br>Your account is now pending admin approval.<br>You will be notified via email once approved.',
            confirmButtonText: 'Proceed to Login',
            confirmButtonColor: '#0077b6'
        }).then(() => {
            window.location.href = '../user/user_login.php';
        });
    </script>
    <?php elseif ($error): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Verification Failed',
            text: '<?= htmlspecialchars($error) ?>',
            confirmButtonText: 'Back to Home',
            confirmButtonColor: '#d33'
        }).then(() => {
            window.location.href = '../index.php';
        });
    </script>
    <?php endif; ?>

</body>
</html>