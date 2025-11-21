<?php
include '../includes/db.php';

// ✅ IMPROVED: Create uploads directory with proper permissions
$targetDir = __DIR__ . "/uploads/";
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        die("Failed to create upload directory. Please check permissions.");
    }
}

// ✅ Check if uploads directory is writable
if (!is_writable($targetDir)) {
    die("Upload directory is not writable. Please check permissions for: " . $targetDir);
}

// ✅ Fetch Admin GCash QR Code, Amount and Owner Name
$qrResult = $conn->query("SELECT gcash_qr, gcash_amount, gcash_owner FROM admins LIMIT 1");
if ($qrResult && $qrResult->num_rows > 0) {
    $adminData = $qrResult->fetch_assoc();
    $adminQR = $adminData['gcash_qr'];
    $adminAmount = $adminData['gcash_amount'];
    $adminOwner = $adminData['gcash_owner'];
} else {
    $adminQR = null;
    $adminAmount = null;
    $adminOwner = null;
}

$showSuccessModal = false;
$showDuplicateModal = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // sanitize minimal - expand as needed
    $fullname       = trim($_POST['fullname']);
    $email          = trim($_POST['email']);
    $raw_password   = $_POST['password'];
    $confirm_password = $_POST['confirm_password']; 
    $whatsapp_number = trim($_POST['whatsapp_number']);
    
    // ✅ UPDATED: Universal WhatsApp number validation (removed PH-specific restriction)
    if (!preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $whatsapp_number)) {
        die("<script>Swal.fire({
            title:'Invalid WhatsApp Number',
            text:'Please enter a valid WhatsApp number (e.g. +639123456789, 09123456789, +1234567890).',
            icon:'error'
        });</script>");
    }

    // ✅ UPDATED: Enhanced password validation with capital letter and special character
    if (strlen($raw_password) < 8) {
        die("<script>Swal.fire({title:'Password too short', text:'Password must be at least 8 characters.', icon:'error'});</script>");
    }
    
    // Check for at least one capital letter and one special character
    if (!preg_match('/[A-Z]/', $raw_password) || !preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $raw_password)) {
        die("<script>Swal.fire({
            title:'Weak Password',
            text:'Password must contain at least one capital letter and one special character.',
            icon:'error'
        });</script>");
    }
    
    if ($raw_password !== $confirm_password) {
        die("<script>Swal.fire({title:'Passwords do not match', text:'Please ensure both password fields match.', icon:'error'});</script>");
    }

    $password       = password_hash($raw_password, PASSWORD_DEFAULT);

    // Professional organization + ID (new)
    $pro_org        = isset($_POST['pro_org']) ? trim($_POST['pro_org']) : '';
    $pro_org_other  = isset($_POST['pro_org_other']) ? trim($_POST['pro_org_other']) : '';
    if ($pro_org === 'Other' && !empty($pro_org_other)) {
        $pro_org = $pro_org_other;
    }

    // ✅ FIXED: Certification Level - ensure it captures the correct value
    $levels = isset($_POST['level']) ? trim($_POST['level']) : '';
    $level_other = isset($_POST['level_other']) ? trim($_POST['level_other']) : '';
    
    // DEBUG: Check what level value we're getting
    error_log("DEBUG Level value: " . $levels);
    error_log("DEBUG Level other: " . $level_other);
    
    if ($levels === 'Other' && !empty($level_other)) {
        $levels = $level_other;
    }

    $pro_diver_id   = trim($_POST['pro_diver_id']);

    // ✅ UPDATED: Changed from "Specialty / Interest" to just "Specialty"
    $specialty = '';
    if (isset($_POST['specialty']) && is_array($_POST['specialty'])) {
        $specialty = implode(", ", $_POST['specialty']);
    }

    $nationality    = $_POST['nationality'];
    if ($nationality === 'Other' && !empty($_POST['nationality_other'])) {
        $nationality = trim($_POST['nationality_other']);
    }

    // Language as comma-separated string from multiple select
    $language = '';
    if (isset($_POST['language']) && is_array($_POST['language'])) {
        $language = implode(", ", $_POST['language']);
    }

    // ✅ Check if email already exists
    $checkEmail = $conn->prepare("SELECT email FROM divers WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        $showDuplicateModal = true;
    } else {
        $timestamp = time();
        $uploadErrors = [];
        $fileNames = [];

        // ✅ IMPROVED: File upload handling with better error checking
        $uploadFiles = [
            'profile_pic' => ['required' => false, 'field' => 'profile_pic'],
            'valid_id' => ['required' => true, 'field' => 'valid_id'], 
            'qr_code' => ['required' => true, 'field' => 'qr_code'],
            'gcash_receipt' => ['required' => true, 'field' => 'gcash_receipt']
        ];

        $allUploadsOK = true;

        foreach ($uploadFiles as $fileKey => $fileConfig) {
            $required = $fileConfig['required'];
            $fieldName = $fileConfig['field'];
            
            if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
                if ($required) {
                    $uploadErrors[] = "$fieldName is required.";
                    $allUploadsOK = false;
                }
                $fileNames[$fieldName] = null;
                continue;
            }

            $file = $_FILES[$fieldName];
            
            // ✅ Check file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $uploadErrors[] = "$fieldName is too large. Maximum size is 5MB.";
                $allUploadsOK = false;
                continue;
            }

            // ✅ Check file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                $uploadErrors[] = "$fieldName must be an image (JPEG, PNG, GIF) or PDF.";
                $allUploadsOK = false;
                continue;
            }

            // ✅ Generate safe filename
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeFilename = $timestamp . '_' . $fieldName . '_' . uniqid() . '.' . $fileExtension;
            $targetPath = $targetDir . $safeFilename;

            // ✅ Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $fileNames[$fieldName] = "admin/uploads/" . $safeFilename; // ✅ Store relative path
                error_log("DEBUG: Successfully uploaded $fieldName to: " . $targetPath);
            } else {
                $uploadErrors[] = "Failed to upload $fieldName.";
                $allUploadsOK = false;
                error_log("DEBUG: Failed to move uploaded file for $fieldName from " . $file['tmp_name'] . " to " . $targetPath);
            }
        }

        if ($allUploadsOK) {
            // DEBUG: Check final level value before insert
            error_log("DEBUG Final level value to insert: " . $levels);
            
            // ✅ IMPROVED: INSERT with proper file paths
            $sql = "INSERT INTO divers 
            (fullname, email, whatsapp_number, password, pro_org, pro_diver_id, specialty, profile_pic, valid_id, qr_code, gcash_receipt, level, nationality, language, verification_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param(
                "ssssssssssssss",
                $fullname, 
                $email, 
                $whatsapp_number, 
                $password, 
                $pro_org, 
                $pro_diver_id,
                $specialty, 
                $fileNames['profile_pic'],
                $fileNames['valid_id'],
                $fileNames['qr_code'], 
                $fileNames['gcash_receipt'], 
                $levels,
                $nationality, 
                $language
            );

            if ($stmt->execute()) {
                $showSuccessModal = true;
                error_log("DEBUG: Successfully registered diver: " . $email);
            } else {
                error_log("DEBUG: Database error: " . $stmt->error);
                echo "<div style='color:red;padding:10px;'>Database error: " . htmlspecialchars($stmt->error) . "</div>";
            }
        } else {
            // Show upload errors
            $errText = implode(" ", $uploadErrors);
            error_log("DEBUG: Upload errors: " . $errText);
            echo "<script>Swal.fire({
                title:'Upload Error',
                text:'" . addslashes($errText) . "',
                icon:'error'
            });</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Diver</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    #termsCheckbox:checked + label span { color: green; }
    .custom-multiselect {
        max-height: 150px;
        overflow-y: auto;
    }
    .selected-languages {
        margin-top: 8px;
        font-size: 0.875rem;
        color: #4b5563;
    }
    .selected-languages span {
        background: #e5e7eb;
        padding: 2px 8px;
        border-radius: 12px;
        margin-right: 4px;
        display: inline-block;
        margin-bottom: 4px;
    }
    .hidden {
        display: none;
    }
    .file-upload-preview {
        max-width: 200px;
        max-height: 150px;
        margin-top: 5px;
        display: none;
    }
</style>
</head>
<body class="bg-cover bg-center min-h-screen flex items-center justify-center" style="background-image: url('../assets/images/dive background.jpg');">
<div class="bg-white/90 rounded-2xl shadow-xl w-full max-w-md h-[95vh] flex flex-col overflow-hidden">
    <!-- Header -->
    <div class="p-4 border-b flex items-center justify-between">
        <a href="../index.php" class="text-blue-600 hover:text-blue-800 font-semibold"> ← Back to Dashboard </a>
    </div>
    <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Create an Account as Dive Master</h2>
    
    <!-- Scrollable Form -->
    <div class="flex-1 overflow-y-auto p-6 space-y-4">
        <form method="POST" enctype="multipart/form-data" class="space-y-4" id="regForm">

            <!-- ✅ UPDATED: SHOW ADMIN GCash QR WITH OWNER NAME -->
            <?php if (!empty($adminQR)): ?>
                <div class="text-center mb-4 p-4 bg-green-50 rounded-lg border border-green-200">
                    <p class="text-gray-700 font-medium mb-3">📱 Scan this GCash QR to pay your registration fee:</p>
                    <img src="../<?= htmlspecialchars($adminQR) ?>" 
                        alt="Admin GCash QR" 
                        class="mx-auto mt-2 w-56 h-auto rounded-lg shadow-md border">
                    
                    <?php if (!empty($adminAmount)): ?>
                        <p class="mt-3 text-lg font-semibold text-green-700">
                            💰 Registration Fee: ₱<?= number_format($adminAmount, 2) ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($adminOwner)): ?>
                        <p class="mt-2 text-sm text-gray-600">
                            💳 Send to: <span class="font-semibold"><?= htmlspecialchars($adminOwner) ?></span>
                        </p>
                    <?php endif; ?>
                    
                    <p class="mt-2 text-xs text-gray-500">
                        After payment, upload the receipt screenshot below.
                    </p>
                </div>
            <?php else: ?>
                <div class="text-center mb-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <p class="text-red-500 font-medium">⚠️ No GCash QR uploaded by Admin yet.</p>
                    <p class="text-sm text-gray-600 mt-1">Please contact administrator to complete registration.</p>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-gray-700">Upload Payment Receipt Screenshot</label>
                <input type="file" name="gcash_receipt" accept="image/*" required class="w-full mt-1 file-upload-input">
                <img id="receiptPreview" class="file-upload-preview rounded border">
                <p class="text-xs text-gray-500 mt-1">Upload screenshot of your GCash payment confirmation</p>
            </div>

            <!-- 1. Full Name -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                <input type="text" name="fullname" required class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- 2. Email -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" required class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- 3. WhatsApp Number -->
            <div>
                <label class="block text-sm font-medium text-gray-700">WhatsApp Number</label>
                <input type="tel" name="whatsapp_number" id="whatsapp_number"
                        pattern="^\+?[\d\s\-\(\)]{10,}$"
                        placeholder="e.g. +639123456789 or 09123456789"
                        required
                        class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">✅ Format: +639123456789 or 09123456789 (any country)</p>
            </div>

            <!-- 4. Password -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Password (min 8 chars with 1 capital & 1 special character)</label>
                <input type="password" name="password" id="passwordInput" minlength="8" required class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Must contain at least one capital letter and one special character (!@#$%^&* etc.)</p>
            </div>

            <!-- 5. Confirm Password -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirmPasswordInput" minlength="8" required class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- 6. Certifying Agency -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Certifying Agency</label>
                <select name="pro_org" id="proOrg" required class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Organization</option>
                    <option value="PADI">PADI</option>
                    <option value="SSI">SSI</option>
                    <option value="NAUI">NAUI</option>
                    <option value="CMAS">CMAS</option>
                    <option value="Other">Other</option>
                </select>
                <input type="text" name="pro_org_other" id="proOrgOther" placeholder="Type other organization" class="w-full mt-2 px-4 py-2 border rounded-lg hidden">
            </div>

            <!-- 7. Certification Level -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Certification Level</label>
                <select name="level" id="level" required class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Certification Level</option>
                    <!-- Options will be populated dynamically by JavaScript -->
                </select>
                <input type="text" name="level_other" id="levelOther" placeholder="Specify your certification level" class="w-full mt-2 px-4 py-2 border rounded-lg hidden">
            </div>

            <!-- 8. Diver ID -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Diver ID</label>
                <input type="text" name="pro_diver_id" required class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- 9. Specialty -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Specialty</label>
                <div class="grid grid-cols-1 gap-1 ml-2">
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Deep Diving" class="mr-2"> Deep Diving</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Wreck Diving" class="mr-2"> Wreck Diving</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Night Diving" class="mr-2"> Night Diving</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Underwater Navigation" class="mr-2"> Underwater Navigation</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Peak Performance Buoyancy" class="mr-2"> Peak Performance Buoyancy</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Nitrox (Enriched Air)" class="mr-2"> Nitrox (Enriched Air)</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Search and Recovery" class="mr-2"> Search and Recovery</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Underwater Photography/Video" class="mr-2"> Underwater Photography/Video</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Boat Diving" class="mr-2"> Boat Diving</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="specialty[]" value="Drift Diving" class="mr-2"> Drift Diving</label>
                </div>
            </div>

            <!-- 10. Nationality -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Nationality</label>
                <select name="nationality" id="nationality" required class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Nationality</option>
                    <option value="Filipino">Filipino</option>
                    <option value="American">American</option>
                    <option value="British">British</option>
                    <option value="Canadian">Canadian</option>
                    <option value="Australian">Australian</option>
                    <option value="Other">Other</option>
                </select>
                <input type="text" name="nationality_other" id="nationalityOther" placeholder="Specify nationality" class="w-full mt-2 px-4 py-2 border rounded-lg hidden">
            </div>

            <!-- 11. Language -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                <div class="relative">
                    <select name="language[]" id="languageSelect" multiple class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 custom-multiselect hidden">
                        <option value="English">English</option>
                        <option value="Tagalog">Tagalog</option>
                        <option value="Spanish">Spanish</option>
                        <option value="French">French</option>
                        <option value="German">German</option>
                        <option value="Chinese">Chinese</option>
                        <option value="Japanese">Japanese</option>
                        <option value="Korean">Korean</option>
                        <option value="Arabic">Arabic</option>
                        <option value="Other">Other</option>
                    </select>
                    
                    <!-- Custom dropdown trigger -->
                    <div id="languageDropdownTrigger" class="w-full mt-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 bg-white cursor-pointer">
                        <span class="text-gray-500">Select languages...</span>
                    </div>
                    
                    <!-- Custom dropdown -->
                    <div id="languageDropdown" class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg hidden custom-multiselect">
                        <div class="p-2 space-y-1">
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="English" class="language-checkbox mr-3">
                                <span>English</span>
                            </label>
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="Tagalog" class="language-checkbox mr-3">
                                <span>Tagalog</span>
                            </label>
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="Spanish" class="language-checkbox mr-3">
                                <span>Spanish</span>
                            </label>
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="French" class="language-checkbox mr-3">
                                <span>French</span>
                            </label>
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="German" class="language-checkbox mr-3">
                                <span>German</span>
                            </label>
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="Chinese" class="language-checkbox mr-3">
                                <span>Chinese</span>
                            </label>
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="Japanese" class="language-checkbox mr-3">
                                <span>Japanese</span>
                            </label>
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="Korean" class="language-checkbox mr-3">
                                <span>Korean</span>
                            </label>
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="Arabic" class="language-checkbox mr-3">
                                <span>Arabic</span>
                            </label>
                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer">
                                <input type="checkbox" value="Other" class="language-checkbox mr-3" id="languageOtherCheckbox">
                                <span>Other</span>
                            </label>
                            <div id="languageOtherInputContainer" class="p-2 hidden">
                                <input type="text" id="languageOtherInput" placeholder="Specify other language" class="w-full px-3 py-2 border rounded">
                            </div>
                        </div>
                        <div class="border-t p-2">
                            <button type="button" id="languageDoneBtn" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">Done</button>
                        </div>
                    </div>
                    
                    <!-- Selected languages display -->
                    <div id="selectedLanguages" class="selected-languages"></div>
                </div>
            </div>

            <!-- 12. Profile Picture -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Profile Picture (optional)</label>
                <input type="file" name="profile_pic" accept="image/*" capture="user" class="w-full mt-1 file-upload-input">
                <img id="profilePreview" class="file-upload-preview rounded-full border">
            </div>

            <!-- 13. MasterDiver's ID -->
            <div>
                <label class="block text-sm font-medium text-gray-700">MasterDiver's ID</label>
                <input type="file" name="valid_id" accept="image/*,application/pdf" capture="environment" required class="w-full mt-1 file-upload-input">
                <img id="idPreview" class="file-upload-preview rounded border">
            </div>

            <!-- 14. GCash QR Code -->
            <div>
                <label class="block text-sm font-medium text-gray-700">GCash QR Code (Please crop to ACTUAL SIZE)</label>
                <input type="file" name="qr_code" accept="image/*" required class="w-full mt-1 file-upload-input">
                <img id="qrPreview" class="file-upload-preview rounded border">
            </div>

            <button type="submit" id="submitBtn" disabled
              class="w-full py-3 bg-blue-400 text-white font-semibold rounded-lg shadow-lg transition cursor-not-allowed">
              Register Diver
            </button>

            <div class="mt-2">
                <label class="inline-flex items-start">
                    <input type="checkbox" id="termsCheckbox" name="termsCheckbox" disabled class="mt-1 mr-2">
                    <span class="text-sm text-gray-700">
                        I have read and agree to the 
                        <a href="#!" id="openModal" class="text-blue-600 underline">Terms and Conditions</a>.
                    </span>
                </label>
            </div>

            <div id="termsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
                <div class="bg-white w-[90%] md:w-[700px] max-h-[80vh] rounded-2xl shadow-2xl p-6 overflow-y-auto">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">DIVE CONNECT — Terms and Conditions</h2>
                    
                    <div class="space-y-4 text-sm text-gray-700">
                        <!-- 1. Eligibility Requirements -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">1. Eligibility Requirements</h3>
                            <p class="mb-2">1.1. Applicants must be certified Divers or Dive Masters from recognized agencies (PADI, SSI, CMAS, NAUI, etc.).</p>
                            <p class="mb-2">1.2. Applicants must upload valid and clear certification IDs and supporting documents.</p>
                            <p>1.3. Any falsified, edited, or invalid documents will result in immediate rejection or permanent account ban.</p>
                        </div>

                        <!-- 2. Verification Process -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">2. Verification Process</h3>
                            <p class="mb-2">2.1. All Dive Masters accounts undergo manual verification by the admin.</p>
                            <p class="mb-2">2.2. The admin reserves the right to:</p>
                            <ul class="list-disc ml-6 mb-2">
                                <li>Check license validity through official agency portals;</li>
                                <li>Approve, reject, or request additional documents if needed.</li>
                            </ul>
                            <p>2.3. Verification is not automatic, and processing time may vary depending on document completeness.</p>
                        </div>

                        <!-- 3. Subscription Fee -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">3. Subscription Fee</h3>
                            <p class="mb-2">3.1. Dive Masters are required to pay a ₱400 subscription fee to activate their account and appear on the platform.</p>
                            <p class="mb-2">3.2. The subscription fee is non-refundable, including cases where applications are rejected due to invalid credentials.</p>
                            <p>3.3. No bookings will be allowed if the subscription is unpaid or expired.</p>
                        </div>

                        <!-- 4. Schedule & Availability Management -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">4. Schedule & Availability Management</h3>
                            <p class="mb-2">4.1. Dive Masters are responsible for accurately setting their:</p>
                            <ul class="list-disc ml-6 mb-2">
                                <li>Available dates</li>
                                <li>Available time slots</li>
                                <li>Selecting Dive Spots</li>
                                <li>Maximum diver capacity</li>
                            </ul>
                            <p class="mb-2">4.2. Errors in schedules or double-booking caused by incorrect settings are the responsibility of the Dive Masters.</p>
                            <p>4.3. Booking requests must be accepted or declined promptly to avoid cancellations or conflicts.</p>
                        </div>

                        <!-- 5. Payment & Transaction Rules -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">5. Payment & Transaction Rules</h3>
                            <p class="mb-2">5.1. DiveConnect currently supports only:</p>
                            <ul class="list-disc ml-6 mb-2">
                                <li>GCash payment using your uploaded QR code, and</li>
                                <li>In-person (cash) payment</li>
                            </ul>
                            <p class="mb-2">5.2. Dive Masters must upload a verified and active GCash QR code for bookings.</p>
                            <p>5.3. DiveConnect is not responsible for payment disputes between Divers and Dive Masters (including delays, errors, or failed transfers).</p>
                        </div>

                        <!-- 6. Liability & Safety Responsibilities -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">6. Liability & Safety Responsibilities</h3>
                            <p class="mb-2">6.1. The Dive Masters are fully responsible for diver safety during the entire session.</p>
                            <p class="mb-2">6.2. Any accidents, injuries, misconduct, or equipment damage are the sole liability of the Master Diver.</p>
                            <p>6.3. Dive Masters must follow all local diving safety laws, marine protection rules, and industry standards in Mabini, Batangas.</p>
                        </div>

                        <!-- 7. Professional Conduct -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">7. Professional Conduct</h3>
                            <p class="mb-2">7.1. The following are strictly prohibited:</p>
                            <ul class="list-disc ml-6 mb-2">
                                <li>Overcharging beyond the rate displayed in the system</li>
                                <li>Providing training or certification without proper licensing</li>
                                <li>Posting misleading information</li>
                                <li>Unprofessional or unsafe behavior</li>
                            </ul>
                            <p class="mb-2">7.2. Repeated complaints from divers may lead to:</p>
                            <ul class="list-disc ml-6">
                                <li>Account suspension</li>
                                <li>Removal from the platform</li>
                                <li>Permanent banning</li>
                            </ul>
                        </div>

                        <!-- 8. Booking Policies -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">8. Booking Policies</h3>
                            <p class="mb-2">8.1. When a booking is confirmed, the Dive Masters must:</p>
                            <ul class="list-disc ml-6 mb-2">
                                <li>Arrive on time</li>
                                <li>Provide rental gear if offered</li>
                                <li>Notify divers immediately if changes are required</li>
                            </ul>
                            <p class="mb-2">8.2. Sessions may be cancelled due to:</p>
                            <ul class="list-disc ml-6 mb-2">
                                <li>Bad weather</li>
                                <li>Unsafe sea or tide conditions</li>
                                <li>Health or safety concerns</li>
                            </ul>
                            <p>8.3. Divers may cancel bookings under the 24-hour cancellation policy enforced by the platform.</p>
                        </div>

                        <!-- 9. Gear Rental Policy -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">9. Gear Rental Policy</h3>
                            <p class="mb-2">9.1. If offering rental gear, Dive Masters must ensure:</p>
                            <ul class="list-disc ml-6 mb-2">
                                <li>Accurate item descriptions</li>
                                <li>Clear pricing</li>
                                <li>Clean, safe, and functioning equipment</li>
                            </ul>
                            <p>9.2. The Dive Masters is liable for any gear-related issues that affect diver safety.</p>
                        </div>

                        <!-- 10. Account Security & Data Privacy -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">10. Account Security & Data Privacy</h3>
                            <p class="mb-2">10.1. Master Divers are responsible for securing their login credentials.</p>
                            <p class="mb-2">10.2. Sharing accounts or passwords is strictly prohibited.</p>
                            <p>10.3. Any unauthorized activity using your account will be considered your responsibility.</p>
                        </div>

                        <!-- 11. Account Suspension & Termination -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">11. Account Suspension & Termination</h3>
                            <p>DiveConnect may suspend or terminate your account if:</p>
                            <ul class="list-disc ml-6">
                                <li>Documents are fake or altered</li>
                                <li>You violate safety standards</li>
                                <li>You demonstrate unprofessional behavior</li>
                                <li>You repeatedly cancel bookings without a valid reason</li>
                                <li>You engage in unethical or unsafe diving practices</li>
                            </ul>
                        </div>

                        <!-- 12. Acceptance of Terms -->
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">12. Acceptance of Terms</h3>
                            <p>By registering as a Dive Master, you confirm that:</p>
                            <ul class="list-disc ml-6">
                                <li>You have read and understood these Terms and Conditions</li>
                                <li>You agree to comply with all platform rules</li>
                                <li>You accept the verification process and subscription fee</li>
                                <li>You take full responsibility for the services you provide</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" id="acceptTermsBtn" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">I Agree</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ✅ SweetAlert for Success -->
<?php if ($showSuccessModal): ?>
<script>
Swal.fire({
  title: "Registration Successful!",
  text: "Your application has been submitted and is pending admin verification.",
  icon: "success",
  confirmButtonColor: "#3085d6",
  confirmButtonText: "OK"
}).then(() => {
  window.location.href = "../index.php";
});
</script>
<?php endif; ?>

<!-- ✅ SweetAlert for Duplicate Email -->
<?php if ($showDuplicateModal): ?>
<script>
Swal.fire({
  title: "Duplicate Email Detected!",
  text: "This email is already registered. Please use a different one.",
  icon: "error",
  confirmButtonColor: "#d33",
  confirmButtonText: "OK"
});
</script>
<?php endif; ?>

</body>
<script>
// ✅ ADDED: File upload preview functionality
document.addEventListener('DOMContentLoaded', function() {
    // File preview functionality
    const fileInputs = document.querySelectorAll('.file-upload-input');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fieldName = this.name;
            const previewId = fieldName + 'Preview';
            const preview = document.getElementById(previewId);
            
            if (file && preview) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    });
});

// Rest of your existing JavaScript code remains the same...
document.addEventListener('DOMContentLoaded', function () {
    const openModalBtn = document.getElementById('openModal');
    const termsModal = document.getElementById('termsModal');
    const acceptTermsBtn = document.getElementById('acceptTermsBtn');
    const termsCheckbox = document.getElementById('termsCheckbox');
    const submitBtn = document.getElementById('submitBtn');
    const pw = document.getElementById('passwordInput');
    const confirmPw = document.getElementById('confirmPasswordInput');

    // Agency and level elements
    const proOrg = document.getElementById('proOrg');
    const proOrgOther = document.getElementById('proOrgOther');
    const levelSelect = document.getElementById('level');
    const levelOther = document.getElementById('levelOther');

    openModalBtn.addEventListener('click', function(e){ 
        e.preventDefault(); 
        termsModal.classList.remove('hidden'); 
        termsModal.classList.add('flex'); 
    });
    
    acceptTermsBtn.addEventListener('click', function(){
        termsModal.classList.add('hidden'); 
        termsModal.classList.remove('flex');
        termsCheckbox.disabled = false;
    });

    termsCheckbox.addEventListener('change', updateSubmitState);

    // Agency change handler
    proOrg.addEventListener('change', function(){
        const selectedAgency = this.value;
        
        if (selectedAgency === 'Other') {
            proOrgOther.classList.remove('hidden');
            proOrgOther.required = true;
            updateCertificationLevels('Other');
            levelOther.classList.remove('hidden');
            levelOther.required = true;
        } else {
            proOrgOther.classList.add('hidden');
            proOrgOther.required = false;
            proOrgOther.value = '';
            levelOther.classList.add('hidden');
            levelOther.required = false;
            levelOther.value = '';
            updateCertificationLevels(selectedAgency);
        }
    });

    // Certification levels configuration
    const certificationLevels = {
        'PADI': ['Divemaster', 'Assistant Instructor', 'Open Water Scuba Instructor (OWSI)'],
        'SSI': ['Dive Guide', 'Divemaster', 'Dive Control Specialist (DCS)', 'Open Water Instructor'],
        'NAUI': ['Divemaster', 'Instructor'],
        'CMAS': ['One Star Instructor', 'Two Star Instructor', 'Three Star Instructor'],
        'Other': []
    };

    function updateCertificationLevels(agency) {
        while (levelSelect.options.length > 1) {
            levelSelect.remove(1);
        }
        
        if (agency === 'Other') {
            const otherOption = document.createElement('option');
            otherOption.value = 'Other';
            otherOption.textContent = 'Other (Specify below)';
            levelSelect.appendChild(otherOption);
        } else if (agency && certificationLevels[agency]) {
            const levels = certificationLevels[agency];
            levels.forEach(level => {
                const option = document.createElement('option');
                option.value = level;
                option.textContent = level;
                levelSelect.appendChild(option);
            });
        }
    }

    // Initialize with empty levels
    updateCertificationLevels('');

    // nationality other
    const nationality = document.getElementById('nationality');
    const nationalityOther = document.getElementById('nationalityOther');
    nationality.addEventListener('change', function(){
        if (this.value === 'Other') nationalityOther.classList.remove('hidden'); 
        else nationalityOther.classList.add('hidden');
    });

    // Language Multi-Select Functionality (unchanged)
    const languageDropdownTrigger = document.getElementById('languageDropdownTrigger');
    const languageDropdown = document.getElementById('languageDropdown');
    const languageCheckboxes = document.querySelectorAll('.language-checkbox');
    const languageOtherCheckbox = document.getElementById('languageOtherCheckbox');
    const languageOtherInputContainer = document.getElementById('languageOtherInputContainer');
    const languageOtherInput = document.getElementById('languageOtherInput');
    const languageDoneBtn = document.getElementById('languageDoneBtn');
    const selectedLanguagesDiv = document.getElementById('selectedLanguages');
    const languageSelect = document.getElementById('languageSelect');

    let selectedLanguages = [];

    languageDropdownTrigger.addEventListener('click', function() {
        languageDropdown.classList.toggle('hidden');
    });

    languageCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.value === 'Other') {
                languageOtherInputContainer.classList.toggle('hidden', !this.checked);
                if (!this.checked) {
                    languageOtherInput.value = '';
                }
            }
            updateSelectedLanguages();
        });
    });

    languageDoneBtn.addEventListener('click', function() {
        languageDropdown.classList.add('hidden');
        updateSelectedLanguages();
    });

    function updateSelectedLanguages() {
        selectedLanguages = [];
        const selectedOptions = [];
        
        languageCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                if (checkbox.value === 'Other' && languageOtherInput.value.trim()) {
                    const otherLang = languageOtherInput.value.trim();
                    selectedLanguages.push(otherLang);
                    selectedOptions.push(otherLang);
                } else if (checkbox.value !== 'Other') {
                    selectedLanguages.push(checkbox.value);
                    selectedOptions.push(checkbox.value);
                }
            }
        });

        if (selectedLanguages.length === 0) {
            selectedLanguagesDiv.innerHTML = '';
            languageDropdownTrigger.innerHTML = '<span class="text-gray-500">Select languages...</span>';
        } else {
            selectedLanguagesDiv.innerHTML = selectedLanguages.map(lang => 
                `<span>${lang}</span>`
            ).join('');
            languageDropdownTrigger.innerHTML = `<span class="text-gray-700">${selectedLanguages.length} language(s) selected</span>`;
        }

        languageSelect.innerHTML = '';
        selectedOptions.forEach(option => {
            const newOption = document.createElement('option');
            newOption.value = option;
            newOption.textContent = option;
            newOption.selected = true;
            languageSelect.appendChild(newOption);
        });
    }

    document.addEventListener('click', function(event) {
        if (!languageDropdownTrigger.contains(event.target) && !languageDropdown.contains(event.target)) {
            languageDropdown.classList.add('hidden');
        }
    });

    // Password validation
    pw.addEventListener('input', function(){
        const password = pw.value;
        let error = '';
        
        if (password.length < 8) {
            error = 'Password must be at least 8 characters.';
        } else if (!/[A-Z]/.test(password)) {
            error = 'Password must contain at least one capital letter.';
        } else if (!/[!@#$%^&*()\-_=+{};:,<.>]/.test(password)) {
            error = 'Password must contain at least one special character.';
        }
        
        if (error) {
            pw.setCustomValidity(error);
        } else {
            pw.setCustomValidity('');
            if (confirmPw.value && pw.value !== confirmPw.value) {
                confirmPw.setCustomValidity('Passwords do not match.');
            } else if (confirmPw.value) {
                confirmPw.setCustomValidity('');
            }
        }
        
        updatePasswordStrength(password);
    });

    function updatePasswordStrength(password) {
        const strengthIndicator = document.getElementById('passwordStrength') || createPasswordStrengthIndicator();
        let strength = 0;
        let feedback = '';
        
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[!@#$%^&*()\-_=+{};:,<.>]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        
        switch(strength) {
            case 0:
            case 1:
                feedback = 'Weak';
                strengthIndicator.className = 'text-xs text-red-500 mt-1';
                break;
            case 2:
                feedback = 'Fair';
                strengthIndicator.className = 'text-xs text-orange-500 mt-1';
                break;
            case 3:
                feedback = 'Good';
                strengthIndicator.className = 'text-xs text-blue-500 mt-1';
                break;
            case 4:
                feedback = 'Strong';
                strengthIndicator.className = 'text-xs text-green-500 mt-1';
                break;
        }
        
        strengthIndicator.textContent = `Password strength: ${feedback}`;
    }
    
    function createPasswordStrengthIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'passwordStrength';
        indicator.className = 'text-xs text-gray-500 mt-1';
        pw.parentNode.appendChild(indicator);
        return indicator;
    }

    confirmPw.addEventListener('input', function(){
        if (pw.value !== confirmPw.value) {
            confirmPw.setCustomValidity('Passwords do not match.');
        } else {
            confirmPw.setCustomValidity('');
        }
    });

    function updateSubmitState(){
        const termsChecked = termsCheckbox.checked;
        if (termsChecked) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('cursor-not-allowed', 'bg-blue-400');
            submitBtn.classList.add('bg-blue-600');
        } else {
            submitBtn.disabled = true;
            submitBtn.classList.add('cursor-not-allowed', 'bg-blue-400');
            submitBtn.classList.remove('bg-blue-600');
        }
    }

    updateSubmitState();

    document.getElementById('regForm').addEventListener('submit', function(e){
        if (submitBtn.disabled) {
            e.preventDefault();
            Swal.fire({
                icon:'warning', 
                title:'Complete requirements', 
                text:'Please accept terms and ensure password meets all requirements.'
            });
        }
    });
});
</script>
</html>