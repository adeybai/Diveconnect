<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require __DIR__ . '/../includes/db.php';

// Check if database connection is successful
if (!isset($conn) || $conn->connect_error) {
    $db_error = "Database connection failed. Please try again later.";
    error_log("Database connection error: " . $conn->connect_error);
} else {
    $db_error = null;
}

// Include mailer with proper error handling
$mailer_available = false;
try {
    if (file_exists('../library/mailer.php')) {
        require_once '../library/mailer.php';
        $mailer_available = true;
    } else {
        throw new Exception("Mailer file not found");
    }
} catch (Exception $e) {
    error_log("Mailer include error: " . $e->getMessage());
    $mailer_available = false;
}

function sendError($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function sendSuccess($message, $data = []) {
    echo json_encode(['success' => true, 'message' => $message] + $data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    
    // Check database connection first
    if (!isset($conn) || $conn->connect_error) {
        sendError("Database connection failed. Please try again later.");
    }
    
    // Check if it's resend verification request
    if (isset($_POST['resend_verification'])) {
        resendVerification($conn);
        exit;
    }

    // Original registration logic
    handleRegistration($conn);
}

function handleRegistration($conn) {
    // Gather inputs
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $rawPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // New fields for Certify Agency and Diver ID
    $certify_agency = trim($_POST['certify_agency'] ?? '');
    $certify_agency_other = trim($_POST['certify_agency_other'] ?? '');
    $certification_level = trim($_POST['certification_level'] ?? '');
    $certification_level_other = trim($_POST['certification_level_other'] ?? '');
    $diver_id_number = trim($_POST['diver_id_number'] ?? '');

    // Debug log
    error_log("Starting registration for: " . $email);

    // Basic required checks
    if (empty($fullname) || empty($email) || empty($whatsapp)) {
        sendError("Please fill all required fields.");
    }

    // Email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError("Invalid email format.");
    }

    // Email uniqueness
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt_check) {
        error_log("Prepare failed: " . $conn->error);
        sendError("Database error. Please try again.");
    }
    
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows > 0) {
        $stmt_check->close();
        sendError("This email is already registered.");
    }
    $stmt_check->close();

    // Enhanced password validation
    if (strlen($rawPassword) < 8) {
        sendError("Password must be at least 8 characters.");
    }
    if (!preg_match('/[A-Z]/', $rawPassword)) {
        sendError("Password must contain at least one uppercase letter.");
    }
    if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $rawPassword)) {
        sendError("Password must contain at least one special character.");
    }
    if ($rawPassword !== $confirmPassword) {
        sendError("Passwords do not match.");
    }
    $password = password_hash($rawPassword, PASSWORD_DEFAULT);

    // WhatsApp number validation
    $waDigits = preg_replace('/\D+/', '', $whatsapp);
    if (strlen($waDigits) < 10) {
        sendError("Please enter a valid WhatsApp number (e.g. +639123456789, 09123456789, +1234567890).");
    }
    $whatsapp = $waDigits;

    // Certify Agency validation
    if (empty($certify_agency)) {
        sendError("Please select a certifying agency.");
    }
    if ($certify_agency === 'Other' && empty($certify_agency_other)) {
        sendError("Please specify your certifying agency.");
    }
    
    // Certification Level validation
    if (empty($certification_level)) {
        sendError("Please select your certification level.");
    }
    if ($certification_level === 'Other' && empty($certification_level_other)) {
        sendError("Please specify your certification level.");
    }
    
    // Diver ID validation
    if (empty($diver_id_number)) {
        sendError("Please enter your Diver ID number.");
    }

    // Handle uploads - create uploads dir if not exists
    $targetDir = __DIR__ . "/../uploads/";
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            sendError("Failed to create upload directory.");
        }
    }

    // Profile picture (optional)
    $profilePicPath = null;
    if (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png'])) {
            sendError("Invalid profile picture format. JPG/PNG only.");
        }
        
        // Check file size (max 5MB)
        if ($_FILES['profile_pic']['size'] > 5 * 1024 * 1024) {
            sendError("Profile picture must be less than 5MB.");
        }
        
        $profilePic = time() . "_" . uniqid() . "_" . preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($_FILES['profile_pic']['name']));
        $profilePicPath = "uploads/" . $profilePic;
        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetDir . $profilePic)) {
            sendError("Failed to upload profile picture.");
        }
    }

    // Valid ID - REQUIRED
    if (empty($_FILES['valid_id']['name']) || $_FILES['valid_id']['error'] !== UPLOAD_ERR_OK) {
        sendError("Valid ID is required.");
    }
    $ext = strtolower(pathinfo($_FILES['valid_id']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'])) {
        sendError("Invalid ID format. JPG/PNG/PDF only.");
    }
    
    // Check file size (max 10MB)
    if ($_FILES['valid_id']['size'] > 10 * 1024 * 1024) {
        sendError("Valid ID file must be less than 10MB.");
    }
    
    $validId = time() . "_id_" . uniqid() . "_" . preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($_FILES['valid_id']['name']));
    $validIdPath = "uploads/" . $validId;
    if (!move_uploaded_file($_FILES['valid_id']['tmp_name'], $targetDir . $validId)) {
        sendError("Failed to upload valid ID.");
    }

    // Diver's ID upload
    if (empty($_FILES['diver_id_file']['name']) || $_FILES['diver_id_file']['error'] !== UPLOAD_ERR_OK) {
        sendError("Diver's ID upload is required.");
    }
    $ext = strtolower(pathinfo($_FILES['diver_id_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'])) {
        sendError("Invalid Diver's ID format. JPG/PNG/PDF only.");
    }
    
    // Check file size (max 10MB)
    if ($_FILES['diver_id_file']['size'] > 10 * 1024 * 1024) {
        sendError("Diver's ID file must be less than 10MB.");
    }
    
    $diverFile = time() . "_diver_" . uniqid() . "_" . preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($_FILES['diver_id_file']['name']));
    $diverPath = "uploads/" . $diverFile;
    if (!move_uploaded_file($_FILES['diver_id_file']['tmp_name'], $targetDir . $diverFile)) {
        sendError("Failed to upload Diver's ID file.");
    }

    // Use other agency name if "Other" is selected
    if ($certify_agency === 'Other' && !empty($certify_agency_other)) {
        $certify_agency = $certify_agency_other;
    }

    // Use other certification level if "Other" is selected
    if ($certification_level === 'Other' && !empty($certification_level_other)) {
        $certification_level = $certification_level_other;
    }

    // Generate verification token
    $verify_token = bin2hex(random_bytes(32));
    $verify_token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // DEBUG: Log the values before insert
    error_log("DEBUG - Inserting user:");
    error_log("Fullname: " . $fullname);
    error_log("Email: " . $email);
    error_log("WhatsApp: " . $whatsapp);
    error_log("Certify Agency: " . $certify_agency);
    error_log("Certification Level: " . $certification_level);
    error_log("Diver ID Number: " . $diver_id_number);

    // Insert into DB - SIMPLIFIED to avoid column name issues
    $sql = "INSERT INTO users 
        (fullname, email, password, whatsapp, profile_pic, valid_id, diver_id_file, certify_agency, certification_level, diver_id_number, verify_token, verify_token_expires, is_verified, admin_approved, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())";
    
    error_log("SQL: " . $sql);
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare error: " . $conn->error);
        sendError("Database preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssssssssssss", 
        $fullname, 
        $email, 
        $password, 
        $whatsapp, 
        $profilePicPath, 
        $validIdPath, 
        $diverPath, 
        $certify_agency, 
        $certification_level, 
        $diver_id_number, 
        $verify_token, 
        $verify_token_expires
    );

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        error_log("User registered successfully with ID: " . $user_id);
        
        // Send verification email
        try {
            global $mailer_available;
            if ($mailer_available) {
                $mailer = new DiveConnect\Mailer();
                $emailSent = $mailer->sendVerification($email, $fullname, $verify_token);
                
                if ($emailSent) {
                    sendSuccess('Registration successful! Please check your email to verify your account. After verification, your account will be pending admin approval.');
                } else {
                    sendSuccess('Registration successful! However, verification email failed to send. Please contact support.');
                }
            } else {
                sendSuccess('Registration successful! However, email service is currently unavailable. Please contact support to verify your account.');
            }
        } catch (Exception $e) {
            error_log("Mailer error: " . $e->getMessage());
            sendSuccess('Registration successful! However, verification email failed to send. Please contact support to verify your account.');
        }
    } else {
        error_log("Execute error: " . $stmt->error);
        sendError('Registration failed: ' . $stmt->error);
    }

    $stmt->close();
}

function resendVerification($conn) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        sendError('Email is required.');
    }

    // Generate new verification token
    $verify_token = bin2hex(random_bytes(32));
    $verify_token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Update user with new token
    $update_stmt = $conn->prepare("UPDATE users SET verify_token = ?, verify_token_expires = ? WHERE email = ? AND is_verified = 0");
    if (!$update_stmt) {
        error_log("Update prepare error: " . $conn->error);
        sendError("Database error.");
    }
    
    $update_stmt->bind_param("sss", $verify_token, $verify_token_expires, $email);
    
    if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
        // Get user details
        $stmt = $conn->prepare("SELECT fullname FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Send verification email
        try {
            global $mailer_available;
            if ($mailer_available) {
                $mailer = new DiveConnect\Mailer();
                $emailSent = $mailer->sendVerification($email, $user['fullname'], $verify_token);
                
                if ($emailSent) {
                    sendSuccess('New verification email sent! Please check your inbox.');
                } else {
                    sendError('Failed to send verification email. Please try again.');
                }
            } else {
                sendError('Email service currently unavailable. Please try again later.');
            }
        } catch (Exception $e) {
            error_log("Mailer error: " . $e->getMessage());
            sendError('Email service temporarily unavailable. Please try again later.');
        }
    } else {
        sendError('User not found or already verified.');
    }
}

// HTML part remains the same but with better error handling
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register as Diver</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    .hidden { display: none; }
    .password-strength { font-size: 0.75rem; margin-top: 4px; }
    .password-strength.weak { color: #ef4444; }
    .password-strength.fair { color: #f59e0b; }
    .password-strength.good { color: #3b82f6; }
    .password-strength.strong { color: #10b981; }
    .whatsapp-input { font-size: 16px; padding-right: 120px; }
    .loading { opacity: 0.6; pointer-events: none; }
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 16px; }
    .alert-error { background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; }
  </style>
</head>
<body class="bg-cover bg-center min-h-screen flex items-center justify-center" 
      style="background-image: url('../assets/images/dive background.jpg'); background-size: cover; background-position: center;">

  <div class="bg-white/90 p-8 rounded-2xl shadow-xl w-full max-w-md">
    <a href="../index.php" class="inline-block mb-4 text-blue-600 hover:text-blue-800 font-semibold">
      ← Back to Home
    </a>

    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Create an Account as Diver</h2>

    <?php if (isset($db_error) && $db_error): ?>
    <div class="alert alert-error">
        <strong>⚠️ Database Error:</strong> <?php echo $db_error; ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-4" id="diverForm">
      <div>
        <label class="block text-sm font-medium text-gray-700">Full Name *</label>
        <input type="text" name="fullname" required 
               class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="Enter your full name">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Email *</label>
        <input type="email" name="email" id="email" required 
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="your@email.com">
      </div>

      <!-- WhatsApp input -->
      <div class="relative">
        <label class="block text-sm font-medium text-gray-700">WhatsApp Number *</label>
        <input type="tel" id="whatsapp" name="whatsapp" required
               placeholder="e.g. +639123456789 or 09123456789"
               class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 whatsapp-input"
               maxlength="20">
        <p class="text-xs text-gray-500 mt-1">✅ Format: +639123456789 or 09123456789 (any country)</p>
      </div>

      <!-- Certify Agency Field -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Certifying Agency *</label>
        <select name="certify_agency" id="certify_agency" required 
                class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="">Select Organization</option>
          <option value="PADI">PADI</option>
          <option value="NAUI">NAUI</option>
          <option value="SSI">SSI</option>
          <option value="CMAS">CMAS</option>
          <option value="Other">Other</option>
        </select>
        <input type="text" name="certify_agency_other" id="certify_agency_other" 
               placeholder="Type other organization" 
               class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hidden">
      </div>

      <!-- Certification Level Field -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Certification Level *</label>
        <select name="certification_level" id="certification_level" required 
                class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="">Select Certification Level</option>
        </select>
        <input type="text" name="certification_level_other" id="certification_level_other" 
               placeholder="Specify your certification level" 
               class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hidden">
      </div>

      <!-- Diver ID Number Field -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Diver ID Number *</label>
        <input type="text" name="diver_id_number" required 
               class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="Enter your official Diver ID">
      </div>

      <div class="relative">
        <label class="block text-sm font-medium text-gray-700">Password *</label>
        <input type="password" id="password" name="password" required 
               class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="Minimum 8 characters">
        <span onclick="togglePassword()" 
              class="absolute right-3 top-9 cursor-pointer text-gray-500">👁️</span>
        <div id="passwordStrength" class="password-strength"></div>
        <p class="text-xs text-gray-500 mt-1">Must contain at least one capital letter and one special character (!@#$%^&* etc.)</p>
      </div>

      <div class="relative">
        <label class="block text-sm font-medium text-gray-700">Confirm Password *</label>
        <input type="password" id="confirm_password" name="confirm_password" required
               class="w-full mt-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="Re-enter your password">
        <span onclick="toggleConfirmPassword()" 
              class="absolute right-3 top-9 cursor-pointer text-gray-500">👁️</span>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Profile Picture (optional)</label>
        <div class="mt-2">
          <input type="file" name="profile_pic" accept="image/*" class="w-full text-sm">
          <p class="text-xs text-gray-500 mt-1">JPG, PNG (Max 5MB)</p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Valid ID (Required) *</label>
        <input type="file" name="valid_id" accept="image/*,application/pdf" required class="w-full mt-1 text-sm">
        <p class="text-xs text-gray-500 mt-1">JPG, PNG, PDF (Max 10MB)</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Upload DIVER'S ID (Required) *</label>
        <input type="file" name="diver_id_file" accept="image/*,application/pdf" required class="w-full mt-1 text-sm">
        <p class="text-xs text-gray-500 mt-1">JPG, PNG, PDF (Max 10MB)</p>
      </div>

      <button type="submit" id="submitBtn"
              class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-lg transition disabled:bg-blue-400 disabled:cursor-not-allowed"
              <?php echo (isset($db_error) && $db_error) ? 'disabled' : ''; ?>>
        <?php echo (isset($db_error) && $db_error) ? 'Database Connection Failed' : 'Register'; ?>
      </button>
    </form>

    <div class="mt-4 text-center">
      <p class="text-sm text-gray-600">
        Already have an account? 
        <a href="user_login.php" class="text-blue-600 hover:text-blue-800 font-medium">Login here</a>
      </p>
    </div>
  </div>

<script>
  // ✅ UPDATED: Certification levels for Diver (Recreational levels only)
  const certificationLevels = {
      'PADI': ['Open Water Diver', 'Advanced Open Water Diver', 'Rescue Diver', 'Divemaster', 'Instructor'],
      'SSI': ['Open Water Diver', 'Advanced Adventurer', 'Stress & Rescue', 'Divemaster / Dive Guide', 'Instructor'],
      'NAUI': ['Scuba Diver (Open Water)', 'Advanced Scuba Diver', 'Rescue Scuba Diver', 'Divemaster', 'Instructor'],
      'CMAS': ['1-Star Diver', '2-Star Diver', '3-Star Diver', 'Instructor'],
      'Other': []
  };

  // Toggle password visibility
  function togglePassword() {
    const pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
  }
  
  function toggleConfirmPassword() {
    const pass = document.getElementById("confirm_password");
    pass.type = pass.type === "password" ? "text" : "password";
  }

  // Password strength indicator
  function updatePasswordStrength(password) {
      const strengthIndicator = document.getElementById('passwordStrength');
      let strength = 0;
      let feedback = '';
      
      if (password.length >= 8) strength++;
      if (/[A-Z]/.test(password)) strength++;
      if (/[!@#$%^&*()\-_=+{};:,<.>]/.test(password)) strength++;
      if (/[0-9]/.test(password)) strength++;
      
      switch(strength) {
          case 0: case 1: feedback = 'Weak'; strengthIndicator.className = 'password-strength weak'; break;
          case 2: feedback = 'Fair'; strengthIndicator.className = 'password-strength fair'; break;
          case 3: feedback = 'Good'; strengthIndicator.className = 'password-strength good'; break;
          case 4: feedback = 'Strong'; strengthIndicator.className = 'password-strength strong'; break;
      }
      
      strengthIndicator.textContent = `Password strength: ${feedback}`;
  }

  // Function to update certification levels
  function updateCertificationLevels(agency) {
      const levelSelect = document.getElementById('certification_level');
      
      while (levelSelect.options.length > 1) {
          levelSelect.remove(1);
      }
      
      const levelOther = document.getElementById('certification_level_other');
      
      if (agency === 'Other') {
          const otherOption = document.createElement('option');
          otherOption.value = 'Other';
          otherOption.textContent = 'Other (Specify below)';
          levelSelect.appendChild(otherOption);
          levelOther.classList.remove('hidden');
          levelOther.required = true;
      } else if (agency && certificationLevels[agency]) {
          certificationLevels[agency].forEach(level => {
              const option = document.createElement('option');
              option.value = level;
              option.textContent = level;
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

  // Email validation
  function validateEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
  }

  // Event Listeners
  document.addEventListener('DOMContentLoaded', function() {
      const passwordInput = document.getElementById('password');
      const certifyAgency = document.getElementById('certify_agency');
      const certificationLevel = document.getElementById('certification_level');

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

      // Initialize with empty certification levels
      updateCertificationLevels('');

      // Password strength real-time feedback
      passwordInput.addEventListener('input', function() {
          updatePasswordStrength(this.value);
      });

      // WhatsApp input formatting
      const whatsappInput = document.getElementById('whatsapp');
      whatsappInput.addEventListener('input', function(e) {
          this.value = this.value.replace(/[^\d+\-\s\(\)]/g, '');
      });
  });

  // Form submission
  document.getElementById('diverForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const submitBtn = document.getElementById('submitBtn');
      
      // Check if button is disabled (database connection failed)
      if (submitBtn.disabled) {
          Swal.fire({icon: 'error', title: 'Database Error', text: 'Database connection failed. Please contact administrator.'});
          return;
      }

      // Basic validation
      const email = document.getElementById('email').value.trim();
      const certifyAgency = document.getElementById('certify_agency').value;
      const certificationLevel = document.getElementById('certification_level').value;
      const diverIdNumber = document.querySelector('input[name="diver_id_number"]').value;
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;

      if (!email || !validateEmail(email)) {
          Swal.fire({icon: 'error', title: 'Error', text: 'Please enter a valid email address'});
          return;
      }

      if (!certifyAgency) {
          Swal.fire({icon: 'error', title: 'Certifying Agency Required', text: 'Please select a certifying agency'});
          return;
      }

      if (!certificationLevel) {
          Swal.fire({icon: 'error', title: 'Certification Level Required', text: 'Please select your certification level'});
          return;
      }

      if (!diverIdNumber.trim()) {
          Swal.fire({icon: 'error', title: 'Diver ID Required', text: 'Please enter your Diver ID number'});
          return;
      }

      if (password.length < 8 || !(/[A-Z]/.test(password)) || !(/[!@#$%^&*()\-_=+{};:,<.>]/.test(password))) {
          Swal.fire({icon:'error', title:'Password requirements', text:'Password must be at least 8 characters and include at least one uppercase letter and one special character.'});
          return;
      }

      if (password !== confirmPassword) {
          Swal.fire({icon: 'error', title: 'Password Mismatch', text: 'Passwords do not match'});
          return;
      }

      // Submit via AJAX
      const originalBtnText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<span>Registering...</span>';
      submitBtn.disabled = true;
      document.getElementById('diverForm').classList.add('loading');

      try {
          const formData = new FormData(this);
          
          const response = await fetch('register_user.php', {
              method: 'POST',
              body: formData
          });
          
          const data = await response.json();
          
          if (data.success) {
              Swal.fire({
                  icon: 'success',
                  title: 'Registration Successful!',
                  html: data.message + '<br><br>You will be redirected to login page.',
                  confirmButtonColor: '#3085d6'
              }).then(() => {
                  window.location.href = 'user_login.php';
              });
          } else {
              Swal.fire({icon: 'error', title: 'Registration Failed', text: data.message});
          }
          
      } catch (error) {
          console.error('Registration error:', error);
          Swal.fire({
              icon: 'error', 
              title: 'Registration Failed', 
              text: 'Registration failed. Please try again.'
          });
      } finally {
          // Re-enable button
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
          document.getElementById('diverForm').classList.remove('loading');
      }
  });
</script>

</body>
</html>