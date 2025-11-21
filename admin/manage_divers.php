<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require '../includes/db.php';

// ✅ Ensure only admin can access
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

// Escape helper
function e($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// ✅ FIXED: Improved fileExists function for direct uploads directory
function fileExists($path) {
    if (empty($path)) return false;
    
    // Remove any leading ../ or ./ from path
    $cleanPath = ltrim($path, './');
    $cleanPath = str_replace('../', '', $cleanPath);
    
    // Build full path - all files are directly in admin/uploads/
    $fullPath = __DIR__ . '/../admin/uploads/' . $cleanPath;
    return file_exists($fullPath) && is_file($fullPath);
}

// ✅ FIXED: Get correct image path for display
function getImagePath($dbPath) {
    if (empty($dbPath)) return null;
    
    // If path already includes uploads/, use as is
    if (strpos($dbPath, 'uploads/') !== false) {
        return '../' . $dbPath;
    }
    
    // Otherwise, assume it's directly in admin/uploads/
    return '../admin/uploads/' . $dbPath;
}

$standard_price_updated = false;

// ✅ SET STANDARD PRICE FOR ALL DIVERS
if (isset($_POST['set_standard_price_confirm'])) {
    $standard_price = floatval($_POST['standard_price']);
    
    // Update all divers' prices
    $update_divers = $conn->query("UPDATE divers SET price = $standard_price");
    
    // Update all diver destinations' prices
    $update_destinations = $conn->query("UPDATE diver_destinations SET price_per_diver = $standard_price");
    
    if ($update_divers && $update_destinations) {
        $standard_price_updated = true;
        
        $conn->query("UPDATE admins SET gcash_amount = $standard_price WHERE gcash_amount IS NOT NULL");
        
        // Redirect to avoid form resubmission
        header("Location: manage_divers.php?price_updated=true&new_price=" . $standard_price);
        exit;
    }
}

// Check if price was just updated from redirect
if (isset($_GET['price_updated']) && $_GET['price_updated'] == 'true') {
    $standard_price_updated = true;
    $new_price = isset($_GET['new_price']) ? floatval($_GET['new_price']) : 0;
}

// ✅ Fetch Divers
$res = $conn->query("
    SELECT d.*
    FROM divers d 
    ORDER BY d.specialty ASC
");
$divers = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// ✅ Get Statistics
$total_divers = count($divers);
$verified_divers = count(array_filter($divers, function($diver) { 
    return $diver['verification_status'] == 'verified' || $diver['verification_status'] == 'approved'; 
}));
$pending_divers = count(array_filter($divers, function($diver) { 
    return $diver['verification_status'] == 'pending'; 
}));

// ✅ Get current standard price from first diver
$current_standard_price = 1000.00;
if (!empty($divers)) {
    $current_standard_price = floatval($divers[0]['price']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Divers | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
<style>
  .hide-scrollbar::-webkit-scrollbar { display: none; }
  .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

  @keyframes slideIn { from {transform: translateX(-100%); opacity:0} to{transform:translateX(0);opacity:1} }
  .menu-enter { animation: slideIn 0.3s ease-out; }
  
  .image-modal img {
    max-width: 90vw;
    max-height: 80vh;
    object-fit: contain;
  }
  
  .readonly-field {
    background-color: #f9fafb;
    border-color: #d1d5db;
    color: #6b7280;
    cursor: not-allowed;
  }
  
  .modal-overlay {
    z-index: 1000;
  }
  .modal-content {
    z-index: 1001;
  }
  
  .profile-img {
    width: 8rem;
    height: 8rem;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid #e5e7eb;
  }
  
  .document-img {
    max-width: 100%;
    max-height: 300px;
    object-fit: contain;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
  }
</style>
</head>
<body class="flex flex-col min-h-screen bg-gray-100 font-sans">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow sticky top-0 z-50">
  <div class="container mx-auto flex justify-between items-center p-3 sm:p-4">
    <div class="flex items-center gap-2 sm:gap-3">
      <button id="menuToggle" class="md:hidden p-2 hover:bg-blue-600 rounded-lg transition-colors">
        <i class="ri-menu-line text-2xl"></i>
      </button>
      <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="DiveConnect" class="h-8 sm:h-10">
      <span class="hidden sm:inline text-lg font-semibold">DiveConnect</span>
    </div>
    <button id="logoutBtn" class="bg-red-500 hover:bg-red-600 px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-md hover:shadow-lg">
      <i class="ri-logout-box-line mr-1"></i>
      <span class="hidden sm:inline">Logout</span>
      <span class="sm:hidden">Exit</span>
    </button>
    <form id="logoutForm" action="../index.php" method="post" class="hidden"></form>
  </div>
</header>

<!-- Mobile Menu Overlay -->
<div id="menuOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden backdrop-blur-sm" onclick="closeMenu()"></div>

<!-- Image View Modal -->
<div id="imageModal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex justify-center items-center z-[60] p-4 modal-overlay">
  <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl image-modal modal-content">
    <div class="flex justify-between items-center p-4 border-b">
      <h3 class="text-lg font-semibold" id="imageModalTitle">Document View</h3>
      <button onclick="closeImageModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
    </div>
    <div class="p-4 flex justify-center items-center min-h-[400px]">
      <div id="imageLoading" class="hidden">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
        <p class="text-center mt-2 text-gray-600">Loading image...</p>
      </div>
      <img id="modalImage" src="" alt="Document" class="rounded max-w-full max-h-full hidden" 
           onload="document.getElementById('imageLoading').classList.add('hidden'); this.classList.remove('hidden');"
           onerror="document.getElementById('imageLoading').classList.add('hidden'); this.classList.add('hidden'); document.getElementById('imageError').classList.remove('hidden');">
      <div id="imageError" class="hidden text-center">
        <i class="ri-error-warning-line text-4xl text-red-500 mb-2"></i>
        <p class="text-red-500 font-medium">Failed to load image</p>
        <p class="text-gray-600 text-sm mt-1">The image may not exist or is corrupted</p>
      </div>
    </div>
    <div class="p-4 border-t text-right">
      <button onclick="closeImageModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 transition-colors">
        Close
      </button>
    </div>
  </div>
</div>

<!-- STANDARD PRICE MODAL -->
<div id="standardPriceModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4 modal-overlay">
  <div class="bg-white rounded-xl shadow-2xl max-w-md w-full animate-scaleIn modal-content">
    <div class="bg-blue-700 text-white p-6 rounded-t-xl">
      <div class="flex items-center gap-3">
        <div class="bg-white bg-opacity-20 p-3 rounded-full">
          <i class="ri-price-tag-3-line text-2xl"></i>
        </div>
        <div>
          <h2 class="text-xl font-bold">Set Standard Price</h2>
          <p class="text-blue-100 text-sm">Update pricing for all dive masters</p>
        </div>
      </div>
    </div>
    
    <form method="POST" id="standardPriceForm" class="p-6 space-y-4">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">
          Standard Price (₱)
        </label>
        <div class="relative">
          <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 font-bold">₱</span>
          <input type="number" 
                 name="standard_price" 
                 id="standardPriceInput"
                 value="<?= number_format($current_standard_price, 2, '.', '') ?>" 
                 step="0.01" 
                 min="0" 
                 required 
                 class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg font-semibold">
        </div>
        <p class="text-xs text-gray-500 mt-2">
          Current standard price: <strong>₱<?= number_format($current_standard_price, 2) ?></strong>
        </p>
      </div>
      
      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex items-start gap-3">
          <i class="ri-alert-line text-yellow-600 text-lg mt-0.5"></i>
          <div>
            <p class="text-sm font-semibold text-yellow-800">Important Notice</p>
            <p class="text-xs text-yellow-700 mt-1">
              This will update ALL dive masters and their destinations to this new price. Existing bookings will not be affected.
            </p>
          </div>
        </div>
      </div>
      
      <div class="flex gap-3 pt-2">
        <button type="button" 
                onclick="closeStandardPriceModal()" 
                class="flex-1 px-4 py-3 border-2 border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
          Cancel
        </button>
        <button type="submit" 
                name="set_standard_price_confirm"
                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-semibold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
          <i class="ri-check-line"></i>
          Apply New Price
        </button>
      </div>
    </form>
  </div>
</div>

<div class="container mx-auto grid grid-cols-1 md:grid-cols-12 gap-4 sm:gap-6 mt-4 sm:mt-6 px-3 sm:px-4 pb-6">

  <!-- SIDEBAR -->
  <aside id="sidebar" class="md:col-span-3 bg-white rounded-lg shadow-xl fixed md:sticky top-0 left-0 h-full md:h-screen w-72 md:w-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50 md:z-auto overflow-y-auto hide-scrollbar">
    <div class="md:hidden flex items-center justify-between p-4 border-b border-gray-200 bg-blue-50">
      <h3 class="text-lg font-bold text-blue-700">Admin Menu</h3>
      <button onclick="closeMenu()" class="p-2 hover:bg-blue-100 rounded-lg transition-colors">
        <i class="ri-close-line text-2xl text-gray-700"></i>
      </button>
    </div>
    <div class="p-4 sm:p-6">
      <h3 class="hidden md:block text-lg font-semibold text-blue-700 mb-4">Admin Menu</h3>
      <nav class="space-y-1.5 sm:space-y-2">
        <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-dashboard-line text-xl"></i><span>Dashboard</span></a>
        <a href="manage_admins.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-settings-line text-xl"></i><span>Manage Admins</span></a>
        <a href="manage_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm"><i class="ri-user-star-line text-xl"></i><span>Manage Dive Master</span></a>
        <a href="verify_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-shield-check-line text-xl"></i><span>Verify Dive Master</span></a>
        <a href="manage_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-line text-xl"></i><span>Manage User Divers</span></a>
        <a href="verify_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-shared-line text-xl"></i><span>Verify User Divers</span></a>
        <a href="manage_destinations.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-map-pin-line text-xl"></i>
          <span>Destinations</span>
        </a>
      </nav>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="md:col-span-9">
    <div class="bg-white rounded-lg shadow p-4 sm:p-6 overflow-x-auto">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-xl font-semibold text-blue-700">🧍‍♂️ Dive Master Summary</h2>
        
        <!-- SET STANDARD PRICE BUTTON -->
        <button type="button" onclick="openStandardPriceModal()"
                class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold shadow-md transition-all hover:shadow-lg flex items-center gap-3 justify-center">
          <i class="ri-price-tag-3-line text-lg"></i>
          <span>Edit Standard Price</span>
        </button>
      </div>

      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-center gap-3">
          <i class="ri-information-line text-blue-600 text-xl"></i>
          <div>
            <p class="text-sm text-blue-700 font-medium">
              Current Standard Price: <strong class="text-lg">₱<?= number_format($current_standard_price, 2) ?></strong>
            </p>
            <p class="text-xs text-blue-600 mt-1">
              This price applies to all dive masters and their destinations.
            </p>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div class="flex items-center">
            <div class="bg-blue-100 p-3 rounded-lg mr-4">
              <i class="ri-user-star-line text-blue-600 text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-blue-600 font-medium">Total Dive Masters</p>
              <p class="text-2xl font-bold text-blue-700"><?= $total_divers ?></p>
            </div>
          </div>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
          <div class="flex items-center">
            <div class="bg-green-100 p-3 rounded-lg mr-4">
              <i class="ri-checkbox-circle-line text-green-600 text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-green-600 font-medium">Verified Masters</p>
              <p class="text-2xl font-bold text-green-700"><?= $verified_divers ?></p>
            </div>
          </div>
        </div>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div class="flex items-center">
            <div class="bg-yellow-100 p-3 rounded-lg mr-4">
              <i class="ri-time-line text-yellow-600 text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-yellow-600 font-medium">Pending Verification</p>
              <p class="text-2xl font-bold text-yellow-700"><?= $pending_divers ?></p>
            </div>
          </div>
        </div>
      </div>

      <?php if ($divers): ?>
      <div class="overflow-x-auto hide-scrollbar">
        <table class="min-w-[900px] text-sm border border-gray-200 table-auto">
          <thead class="bg-blue-50 text-blue-700">
            <tr>
              <th class="border px-3 py-2 text-center">ID</th>
              <th class="border px-3 py-2 text-left">Name</th>
              <th class="border px-3 py-2 text-left">Email</th>
              <th class="border px-3 py-2 text-center">Specialty</th>
              <th class="border px-3 py-2 text-center">Status</th>
              <th class="border px-3 py-2 text-center">Price</th>
              <th class="border px-3 py-2 text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($divers as $d): 
              // ✅ FIXED: Use the new functions for file checking and path generation
              $profilePicPath = getImagePath($d['profile_pic']);
              $validIdPath = getImagePath($d['valid_id']);
              $qrCodePath = getImagePath($d['qr_code']);
              $receiptPath = getImagePath($d['gcash_receipt']);
              
              $hasProfilePic = fileExists($d['profile_pic']);
              $hasValidId = fileExists($d['valid_id']);
              $hasQrCode = fileExists($d['qr_code']);
              $hasReceipt = fileExists($d['gcash_receipt']);
            ?>
            <tr class="hover:bg-gray-50">
              <td class="border px-2 py-1 text-center"><?= e($d['id']) ?></td>
              <td class="border px-2 py-1">
                <div class="flex items-center gap-3">
                  <?php if ($hasProfilePic && $profilePicPath): ?>
                    <img src="<?= e($profilePicPath) ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center" style="display: none;">
                      <i class="ri-user-line text-gray-500"></i>
                    </div>
                  <?php else: ?>
                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                      <i class="ri-user-line text-gray-500"></i>
                    </div>
                  <?php endif; ?>
                  <span class="font-medium"><?= e($d['fullname']) ?></span>
                </div>
              </td>
              <td class="border px-2 py-1"><?= e($d['email']) ?></td>
              <td class="border px-2 py-1 text-center"><?= e($d['specialty']) ?></td>
              <td class="border px-2 py-1 text-center">
                <span class="px-2 py-1 rounded text-xs font-medium
                <?= ($d['verification_status']=='verified'||$d['verification_status']=='approved')?'bg-green-100 text-green-700':
                   (($d['verification_status']=='rejected')?'bg-red-100 text-red-700':'bg-yellow-100 text-yellow-700') ?>">
                  <?= ucfirst($d['verification_status']) ?>
                </span>
              </td>
              <td class="border px-2 py-1 text-center">
                ₱<?= number_format($d['price'], 2) ?>
              </td>
              <td class="border px-2 py-1 text-center">
                <button type="button"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs transition"
                        onclick="openViewModal(<?= e($d['id']) ?>)">
                  View Details
                </button>
              </td>
            </tr>

            <!-- VIEW MODAL - READ ONLY -->
            <div id="view-modal-<?= e($d['id']) ?>" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 p-4 modal-overlay">
              <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative overflow-y-auto max-h-[90vh] modal-content">
                <h3 class="text-lg font-semibold mb-4 text-blue-700">View Diver Details</h3>
                
                <!-- Profile Image -->
                <div class="flex justify-center mb-6">
                  <?php if ($hasProfilePic && $profilePicPath): ?>
                    <img src="<?= e($profilePicPath) ?>" alt="Profile" class="profile-img"
                         onerror="this.style.display='none'; document.getElementById('defaultProfile-<?= e($d['id']) ?>').style.display='flex';">
                    <div id="defaultProfile-<?= e($d['id']) ?>" class="profile-img bg-gray-200 flex items-center justify-center hidden">
                      <i class="ri-user-line text-gray-500 text-3xl"></i>
                    </div>
                  <?php else: ?>
                    <div class="profile-img bg-gray-200 flex items-center justify-center">
                      <i class="ri-user-line text-gray-500 text-3xl"></i>
                    </div>
                  <?php endif; ?>
                </div>
                
                <!-- Basic Information - READ ONLY -->
                <div class="space-y-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                      <div class="border rounded w-full p-2 readonly-field bg-gray-50">
                        <?= e($d['fullname']) ?>
                      </div>
                    </div>
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                      <div class="border rounded w-full p-2 readonly-field bg-gray-50">
                        <?= e($d['email']) ?>
                      </div>
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Specialty</label>
                      <div class="border rounded w-full p-2 readonly-field bg-gray-50">
                        <?= e($d['specialty']) ?>
                      </div>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                      <div class="border rounded w-full p-2 readonly-field bg-gray-50">
                        <?= e($d['language']) ?>
                      </div>
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Certification Level</label>
                      <div class="border rounded w-full p-2 readonly-field bg-gray-50">
                        <?= e($d['level']) ?>
                      </div>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
                      <div class="border rounded w-full p-2 readonly-field bg-gray-50">
                        <?= e($d['nationality']) ?>
                      </div>
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                      <div class="border rounded w-full p-2 readonly-field bg-gray-50">
                        ₱<?= number_format($d['price'], 2) ?>
                      </div>
                      <p class="text-xs text-gray-500 mt-1">Note: Price can only be updated via "Edit Standard Price" button</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Verification Status</label>
                      <div class="border rounded w-full p-2 readonly-field bg-gray-50">
                        <?= ucfirst($d['verification_status']) ?>
                      </div>
                    </div>
                  </div>

                  <!-- Document Links -->
                  <div class="border-t pt-4 mt-4">
                    <h4 class="font-semibold text-gray-700 mb-3">Uploaded Documents</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <!-- Profile Picture -->
                      <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                          <i class="ri-image-line text-blue-600"></i>
                          <span class="text-sm font-medium">Profile Picture</span>
                        </div>
                        <?php if ($hasProfilePic && $profilePicPath): ?>
                          <button type="button" onclick="viewImage('<?= e($profilePicPath) ?>', 'Profile Picture - <?= e($d['fullname']) ?>')" 
                                  class="text-blue-600 hover:text-blue-800 text-sm underline">
                            View
                          </button>
                        <?php else: ?>
                          <span class="text-gray-500 text-sm">No picture added</span>
                        <?php endif; ?>
                      </div>
                      
                      <!-- MasterDiver's ID -->
                      <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                          <i class="ri-id-card-line text-green-600"></i>
                          <span class="text-sm font-medium">MasterDiver's ID</span>
                        </div>
                        <?php if ($hasValidId && $validIdPath): ?>
                          <button type="button" onclick="viewImage('<?= e($validIdPath) ?>', 'MasterDiver ID - <?= e($d['fullname']) ?>')" 
                                  class="text-blue-600 hover:text-blue-800 text-sm underline">
                            View
                          </button>
                        <?php else: ?>
                          <span class="text-gray-500 text-sm">No ID added</span>
                        <?php endif; ?>
                      </div>
                      
                      <!-- GCash QR Code -->
                      <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                          <i class="ri-qr-code-line text-purple-600"></i>
                          <span class="text-sm font-medium">GCash QR Code</span>
                        </div>
                        <?php if ($hasQrCode && $qrCodePath): ?>
                          <button type="button" onclick="viewImage('<?= e($qrCodePath) ?>', 'GCash QR Code - <?= e($d['fullname']) ?>')" 
                                  class="text-blue-600 hover:text-blue-800 text-sm underline">
                            View
                          </button>
                        <?php else: ?>
                          <span class="text-gray-500 text-sm">No QR code added</span>
                        <?php endif; ?>
                      </div>
                      
                      <!-- GCash Receipt -->
                      <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                          <i class="ri-receipt-line text-orange-600"></i>
                          <span class="text-sm font-medium">GCash Receipt</span>
                        </div>
                        <?php if ($hasReceipt && $receiptPath): ?>
                          <button type="button" onclick="viewImage('<?= e($receiptPath) ?>', 'GCash Receipt - <?= e($d['fullname']) ?>')" 
                                  class="text-blue-600 hover:text-blue-800 text-sm underline">
                            View
                          </button>
                        <?php else: ?>
                          <span class="text-gray-500 text-sm">No receipt added</span>
                        <?php endif; ?>
                      </div>
                    </div>
                    
                    <!-- Document Previews -->
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                      <?php if ($hasValidId && $validIdPath): ?>
                        <div>
                          <p class="text-sm font-medium text-gray-700 mb-2">MasterDiver's ID Preview:</p>
                          <img src="<?= e($validIdPath) ?>" alt="MasterDiver ID" class="document-img"
                               onerror="this.style.display='none';">
                        </div>
                      <?php endif; ?>
                      
                      <?php if ($hasQrCode && $qrCodePath): ?>
                        <div>
                          <p class="text-sm font-medium text-gray-700 mb-2">GCash QR Code Preview:</p>
                          <img src="<?= e($qrCodePath) ?>" alt="GCash QR Code" class="document-img"
                               onerror="this.style.display='none';">
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                  
                  <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeViewModal(<?= e($d['id']) ?>)" 
                            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 text-sm transition-colors">
                      Close
                    </button>
                  </div>
                </div>
                <button class="absolute top-2 right-3 text-gray-500 hover:text-gray-700 text-xl" 
                        onclick="closeViewModal(<?= e($d['id']) ?>)">&times;</button>
              </div>
            </div>
            <!-- END VIEW MODAL -->

            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="text-gray-600">No divers found in the database.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const menuOverlay = document.getElementById('menuOverlay');

menuToggle.addEventListener('click', ()=>{ 
  sidebar.classList.remove('-translate-x-full'); 
  sidebar.classList.add('menu-enter'); 
  menuOverlay.classList.remove('hidden'); 
  document.body.style.overflow='hidden'; 
});

function closeMenu(){ 
  sidebar.classList.add('-translate-x-full'); 
  menuOverlay.classList.add('hidden'); 
  document.body.style.overflow=''; 
}

// View Modal Functions
function openViewModal(id){ 
  document.getElementById('view-modal-'+id).classList.remove('hidden'); 
  document.body.style.overflow = 'hidden';
}

function closeViewModal(id){ 
  document.getElementById('view-modal-'+id).classList.add('hidden'); 
  document.body.style.overflow = '';
}

// Standard Price Modal Functions
function openStandardPriceModal() {
  document.getElementById('standardPriceModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
  
  setTimeout(() => {
    document.getElementById('standardPriceInput').focus();
    document.getElementById('standardPriceInput').select();
  }, 300);
}

function closeStandardPriceModal() {
  document.getElementById('standardPriceModal').classList.add('hidden');
  document.body.style.overflow = '';
}

// ✅ FIXED: Image viewing functions - improved path handling
function viewImage(imagePath, title) {
  console.log('Attempting to load image:', imagePath);
  
  // Reset modal state
  document.getElementById('imageLoading').classList.remove('hidden');
  document.getElementById('modalImage').classList.add('hidden');
  document.getElementById('imageError').classList.add('hidden');
  
  // Set image source and title
  document.getElementById('modalImage').src = imagePath;
  document.getElementById('imageModalTitle').textContent = title;
  document.getElementById('imageModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeImageModal() {
  document.getElementById('imageModal').classList.add('hidden');
  document.getElementById('modalImage').src = '';
  document.body.style.overflow = '';
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
  if (e.target.id === 'standardPriceModal') {
    closeStandardPriceModal();
  }
  
  if (e.target.id === 'imageModal') {
    closeImageModal();
  }
  
  <?php foreach ($divers as $d): ?>
  if (e.target.id === 'view-modal-<?= e($d['id']) ?>') {
    closeViewModal(<?= e($d['id']) ?>);
  }
  <?php endforeach; ?>
});

document.getElementById('standardPriceForm')?.addEventListener('submit', function(e) {
  // Let the form submit normally
});

window.addEventListener('resize', ()=>{ 
  if(window.innerWidth >= 768) closeMenu(); 
});

// Show success message if price was updated
<?php if($standard_price_updated): ?>
Swal.fire({
  title:'💰 Standard Price Updated!', 
  text:'All dive masters and their destinations have been updated to ₱<?= number_format($new_price, 2) ?>.', 
  icon:'success', 
  confirmButtonColor:'#16a34a',
  didClose: () => {
    const url = new URL(window.location);
    url.searchParams.delete('price_updated');
    url.searchParams.delete('new_price');
    window.history.replaceState({}, '', url);
  }
});
<?php endif; ?>
</script>

<script>
document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('logoutForm').submit();
        }
    });
});
</script>

</body>
</html>