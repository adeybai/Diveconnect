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

// File existence checker
function fileExists($path) {
    if (empty($path)) return false;
    $fullPath = '../' . $path;
    return file_exists($fullPath) && is_file($fullPath);
}

// ✅ Fetch Users - SIMPLIFIED QUERY
$res = $conn->query("
    SELECT u.*
    FROM users u 
    ORDER BY u.fullname ASC
");
$users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// ✅ Get Statistics
$total_users = count($users);
$verified_users = count(array_filter($users, function($user) { 
    return $user['is_verified']; 
}));
$pending_users = count(array_filter($users, function($user) { 
    return !$user['is_verified']; 
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage User Divers | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
<style>
  .hide-scrollbar::-webkit-scrollbar { display: none; }
  .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

  @keyframes slideIn { from {transform: translateX(-100%); opacity:0} to{transform:translateX(0);opacity:1} }
  .menu-enter { animation: slideIn 0.3s ease-out; }
  
  /* Better table styling */
  .table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  /* Image modal styling */
  .image-modal img {
    max-width: 90vw;
    max-height: 80vh;
    object-fit: contain;
  }

  /* Read-only field styling */
  .readonly-field {
    background-color: #f9fafb;
    border: 1px solid #d1d5db;
    color: #6b7280;
    cursor: not-allowed;
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
<div id="imageModal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex justify-center items-center z-[60] p-4">
  <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl image-modal">
    <div class="flex justify-between items-center p-4 border-b">
      <h3 class="text-lg font-semibold" id="imageModalTitle">Document View</h3>
      <button onclick="closeImageModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
    </div>
    <div class="p-4 flex justify-center items-center">
      <img id="modalImage" src="" alt="Document" class="rounded">
    </div>
    <div class="p-4 border-t text-right">
      <button onclick="closeImageModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 transition-colors">
        Close
      </button>
    </div>
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
        <a href="manage_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-star-line text-xl"></i><span>Manage Dive Master</span></a>
        <a href="verify_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-shield-check-line text-xl"></i><span>Verify Dive Master</span></a>
        <a href="manage_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm"><i class="ri-user-line text-xl"></i><span>Manage User Divers</span></a>
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
    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
      <h2 class="text-xl font-semibold text-blue-700 mb-4">👥 User Divers Summary</h2>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <div class="flex items-center">
            <div class="bg-blue-100 p-3 rounded-lg mr-4">
              <i class="ri-user-line text-blue-600 text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-blue-600 font-medium">Total User Divers</p>
              <p class="text-2xl font-bold text-blue-700"><?= $total_users ?></p>
            </div>
          </div>
        </div>
        
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
          <div class="flex items-center">
            <div class="bg-green-100 p-3 rounded-lg mr-4">
              <i class="ri-checkbox-circle-line text-green-600 text-xl"></i>
            </div>
            <div>
              <p class="text-sm text-green-600 font-medium">Verified Users</p>
              <p class="text-2xl font-bold text-green-700"><?= $verified_users ?></p>
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
              <p class="text-2xl font-bold text-yellow-700"><?= $pending_users ?></p>
            </div>
          </div>
        </div>
      </div>

      <?php if ($users): ?>
      <div class="table-container">
        <table class="min-w-[1000px] w-full text-sm border border-gray-200">
          <thead class="bg-blue-50 text-blue-700">
            <tr>
              <th class="border px-4 py-3 text-center w-16">ID</th>
              <th class="border px-4 py-3 text-left w-48">Name</th>
              <th class="border px-4 py-3 text-left w-64">Email</th>
              <th class="border px-4 py-3 text-center w-32">WhatsApp</th>
              <th class="border px-4 py-3 text-center w-28">Status</th>
              <th class="border px-4 py-3 text-center w-32">Registered Date</th>
              <th class="border px-4 py-3 text-center w-24">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): 
              $hasProfilePic = fileExists($user['profile_pic']);
              $hasValidId = fileExists($user['valid_id']);
              $hasDiverId = fileExists($user['diver_id_file']);
            ?>
            <tr class="hover:bg-gray-50">
              <td class="border px-4 py-3 text-center"><?= e($user['id']) ?></td>
              <td class="border px-4 py-3">
                <div class="flex items-center gap-3">
                  <?php if ($hasProfilePic): ?>
                    <img src="../<?= e($user['profile_pic']) ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover">
                  <?php else: ?>
                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                      <i class="ri-user-line text-gray-500"></i>
                    </div>
                  <?php endif; ?>
                  <span class="font-medium"><?= e($user['fullname']) ?></span>
                </div>
              </td>
              <td class="border px-4 py-3">
                <div class="truncate" title="<?= e($user['email']) ?>">
                  <?= e($user['email']) ?>
                </div>
              </td>
              <td class="border px-4 py-3 text-center font-mono text-sm">
                <?= e($user['whatsapp']) ?>
              </td>
              <td class="border px-4 py-3 text-center">
                <span class="px-3 py-1 rounded-full text-xs font-medium
                <?= ($user['is_verified'])?'bg-green-100 text-green-700 border border-green-200':'bg-yellow-100 text-yellow-700 border border-yellow-200' ?>">
                  <?= ($user['is_verified'])?'Verified':'Pending' ?>
                </span>
              </td>
              <td class="border px-4 py-3 text-center text-sm">
                <?= date('M j, Y', strtotime($user['created_at'])) ?>
              </td>
              <td class="border px-4 py-3 text-center">
                <button type="button"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm transition flex items-center gap-2 justify-center w-full"
                        onclick="openModal(<?= e($user['id']) ?>)">
                  <i class="ri-eye-line"></i>
                  View
                </button>
              </td>
            </tr>

            <!-- MODAL - VIEW ONLY -->
            <div id="modal-<?= e($user['id']) ?>" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50">
              <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative overflow-y-auto max-h-[90vh]">
                <h3 class="text-lg font-semibold mb-4 text-blue-700">User Diver Details - View Only</h3>
                
                <!-- Basic Information - DISPLAY ONLY -->
                <div class="space-y-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                      <div class="readonly-field rounded w-full p-2">
                        <?= e($user['fullname']) ?>
                      </div>
                    </div>
                    
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                      <div class="readonly-field rounded w-full p-2">
                        <?= e($user['email']) ?>
                      </div>
                    </div>
                  </div>
                  
                  <!-- WHATSAPP NUMBER ONLY - REMOVED PHONE NUMBER -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                    <div class="readonly-field rounded w-full p-2">
                      <?= e($user['whatsapp']) ?>
                    </div>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Verification Status</label>
                    <div class="readonly-field rounded w-full p-2">
                      <?= $user['is_verified'] ? 'Verified' : 'Pending Verification' ?>
                    </div>
                  </div>

                  <!-- Document Links -->
                  <div class="border-t pt-4 mt-4">
                    <h4 class="font-semibold text-gray-700 mb-3">Uploaded Documents</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                          <i class="ri-image-line text-blue-600"></i>
                          <span class="text-sm font-medium">Profile Picture</span>
                        </div>
                        <?php if ($hasProfilePic): ?>
                          <button type="button" onclick="viewImage('../<?= e($user['profile_pic']) ?>', 'Profile Picture')" 
                                  class="text-blue-600 hover:text-blue-800 text-sm underline">
                            View
                          </button>
                        <?php else: ?>
                          <span class="text-gray-500 text-sm">No picture added</span>
                        <?php endif; ?>
                      </div>
                      
                      <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                          <i class="ri-id-card-line text-green-600"></i>
                          <span class="text-sm font-medium">Valid ID</span>
                        </div>
                        <?php if ($hasValidId): ?>
                          <button type="button" onclick="viewImage('../<?= e($user['valid_id']) ?>', 'Valid ID')" 
                                  class="text-blue-600 hover:text-blue-800 text-sm underline">
                            View
                          </button>
                        <?php else: ?>
                          <span class="text-gray-500 text-sm">No ID added</span>
                        <?php endif; ?>
                      </div>
                      
                      <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-3">
                          <i class="ri-file-text-line text-purple-600"></i>
                          <span class="text-sm font-medium">Diver's ID</span>
                        </div>
                        <?php if ($hasDiverId): ?>
                          <button type="button" onclick="viewImage('../<?= e($user['diver_id_file']) ?>', 'Diver ID')" 
                                  class="text-blue-600 hover:text-blue-800 text-sm underline">
                            View
                          </button>
                        <?php else: ?>
                          <span class="text-gray-500 text-sm">No ID added</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  
                  <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="closeModal(<?= e($user['id']) ?>)" 
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm transition-colors flex items-center gap-2">
                      <i class="ri-close-line"></i>
                      Close
                    </button>
                  </div>
                </div>
                <button class="absolute top-2 right-3 text-gray-500 hover:text-gray-700 text-xl" 
                        onclick="closeModal(<?= e($user['id']) ?>)">&times;</button>
              </div>
            </div>
            <!-- END MODAL -->

            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="text-center py-8">
          <i class="ri-user-search-line text-4xl text-gray-400 mb-3"></i>
          <p class="text-gray-600 text-lg">No user divers found in the database.</p>
          <p class="text-gray-500 text-sm">Users will appear here after they register through the registration form.</p>
        </div>
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

function openModal(id){ 
  document.getElementById('modal-'+id).classList.remove('hidden'); 
}

function closeModal(id){ 
  document.getElementById('modal-'+id).classList.add('hidden'); 
}

// Image viewing functions
function viewImage(imagePath, title) {
  document.getElementById('modalImage').src = imagePath;
  document.getElementById('imageModalTitle').textContent = title;
  document.getElementById('imageModal').classList.remove('hidden');
}

function closeImageModal() {
  document.getElementById('imageModal').classList.add('hidden');
  document.getElementById('modalImage').src = '';
}

// Close image modal when clicking outside the image
document.getElementById('imageModal').addEventListener('click', function(e) {
  if (e.target.id === 'imageModal') {
    closeImageModal();
  }
});

window.addEventListener('resize', ()=>{ 
  if(window.innerWidth >= 768) closeMenu(); 
});
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