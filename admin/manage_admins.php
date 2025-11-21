<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require '../includes/db.php';

// Require admin session
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Helper function
function e($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// Handle form submission (Create / Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = 'admin'; // Always set to 'admin'
    $gcash_amount = $_POST['gcash_amount'] ?? null;
    $gcash_owner = trim($_POST['gcash_owner'] ?? '');

    // Handle GCash QR upload
    $gcash_qr = null;
    if (isset($_FILES['gcash_qr']) && $_FILES['gcash_qr']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . "/uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $timestamp = time();
        $fileName = $timestamp . '_gcashqr_' . basename($_FILES['gcash_qr']['name']);
        $targetPath = $uploadDir . $fileName;
        
        // Check if image file
        $imageFileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $targetPath)) {
                $gcash_qr = "admin/uploads/" . $fileName;
            }
        }
    }

    if ($fullname && $email) {
        if (!empty($_POST['admin_id'])) {
            $admin_id = intval($_POST['admin_id']);
            
            // Get current GCash QR if not uploading new one
            if (!$gcash_qr) {
                $currentQR = $conn->query("SELECT gcash_qr FROM admins WHERE admin_id = $admin_id");
                if ($currentQR && $currentQR->num_rows > 0) {
                    $currentData = $currentQR->fetch_assoc();
                    $gcash_qr = $currentData['gcash_qr'];
                }
            }
            
            if ($password) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admins SET fullname=?, email=?, password=?, role=?, gcash_amount=?, gcash_qr=?, gcash_owner=? WHERE admin_id=?");
                $stmt->bind_param("ssssdssi", $fullname, $email, $password_hash, $role, $gcash_amount, $gcash_qr, $gcash_owner, $admin_id);
            } else {
                $stmt = $conn->prepare("UPDATE admins SET fullname=?, email=?, role=?, gcash_amount=?, gcash_qr=?, gcash_owner=? WHERE admin_id=?");
                $stmt->bind_param("sssdssi", $fullname, $email, $role, $gcash_amount, $gcash_qr, $gcash_owner, $admin_id);
            }
            $stmt->execute();
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admins (fullname, email, password, role, gcash_amount, gcash_qr, gcash_owner, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssdss", $fullname, $email, $password_hash, $role, $gcash_amount, $gcash_qr, $gcash_owner);
            $stmt->execute();
        }
    }
    header("Location: manage_admins.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM admins WHERE admin_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_admins.php");
    exit;
}

// Fetch all admins
$admins = $conn->query("SELECT * FROM admins ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Admins | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
<style>
  html { scroll-behavior: smooth; }
  .hide-scrollbar::-webkit-scrollbar { display: none; }
  .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

  @keyframes slideIn {
    from { transform: translateX(-100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  .menu-enter { animation: slideIn 0.3s ease-out; }
  .backdrop-blur-sm { backdrop-filter: blur(4px); }
  
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
  }
  
  .qr-preview {
    max-width: 200px;
    max-height: 200px;
    border: 2px dashed #d1d5db;
    border-radius: 0.5rem;
    padding: 0.5rem;
  }
</style>
</head>
<body class="min-h-screen bg-gray-100 font-sans">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow sticky top-0 z-50">
  <div class="container mx-auto flex items-center justify-between p-3 sm:p-4">
    <div class="flex items-center gap-2 sm:gap-3">
      <button id="menuToggle" class="md:hidden p-2 hover:bg-blue-600 rounded-lg transition-colors">
        <i class="ri-menu-line text-2xl"></i>
      </button>
      <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-8 sm:h-10">
      <span class="hidden sm:inline text-lg font-semibold">DiveConnect</span>
    </div>
    <div class="flex items-center gap-2 sm:gap-3">
      <button id="logoutBtn" class="bg-red-500 hover:bg-red-600 px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-md hover:shadow-lg">
        <i class="ri-logout-box-line mr-1"></i>
        <span class="hidden sm:inline">Logout</span>
        <span class="sm:hidden">Exit</span>
      </button>
      <form id="logoutForm" action="../index.php" method="post" class="hidden"></form>
    </div>
  </div>
</header>

<!-- Mobile Menu Overlay -->
<div id="menuOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden backdrop-blur-sm" onclick="closeMenu()"></div>

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
        <a href="manage_admins.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm"><i class="ri-user-settings-line text-xl"></i><span>Manage Admins</span></a>
        <a href="manage_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-star-line text-xl"></i><span>Manage Dive Master</span></a>
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
    <div class="bg-white rounded-lg shadow p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-700">Manage Admin Accounts</h1>
        <button onclick="document.getElementById('adminModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          + Add Admin
        </button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto max-w-full hide-scrollbar">
        <table class="w-full border-collapse text-sm min-w-[1100px]">
          <thead>
            <tr class="bg-blue-100 text-left text-gray-700">
              <th class="p-3 border">#</th>
              <th class="p-3 border">Full Name</th>
              <th class="p-3 border">Email</th>
              <th class="p-3 border">Role</th>
              <th class="p-3 border">Gcash Amount</th>
              <th class="p-3 border">Gcash Owner</th>
              <th class="p-3 border">Gcash QR</th>
              <th class="p-3 border">Created</th>
              <th class="p-3 border text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($admins->num_rows > 0): ?>
              <?php while ($a = $admins->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50">
                <td class="p-3 border"><?= e($a['admin_id']) ?></td>
                <td class="p-3 border"><?= e($a['fullname']) ?></td>
                <td class="p-3 border"><?= e($a['email']) ?></td>
                <td class="p-3 border">
                  <span class="status-badge bg-blue-100 text-blue-700"><?= e($a['role']) ?></span>
                </td>
                <td class="p-3 border">₱<?= e($a['gcash_amount'] ?? '0.00') ?></td>
                <td class="p-3 border"><?= e($a['gcash_owner'] ?? '-') ?></td>
                <td class="p-3 border">
                  <?php if (!empty($a['gcash_qr'])): ?>
                    <img src="../<?= e($a['gcash_qr']) ?>" alt="GCash QR" class="w-16 h-16 object-cover rounded border">
                  <?php else: ?>
                    <span class="text-gray-400">No QR</span>
                  <?php endif; ?>
                </td>
                <td class="p-3 border text-gray-500"><?= e($a['created_at']) ?></td>
                <td class="p-3 border text-center">
                  <button onclick='editAdmin(<?= json_encode($a) ?>)' class="text-blue-600 hover:underline mr-3">Edit</button>
                  <a href="?delete=<?= e($a['admin_id']) ?>" onclick="return confirm('Delete this admin?')" class="text-red-600 hover:underline">Delete</a>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="9" class="text-center p-4 text-gray-500">No admins found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- Modal -->
<div id="adminModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-6 relative max-h-[90vh] overflow-y-auto">
    <h2 id="modalTitle" class="text-xl font-bold mb-4 text-blue-700">Add Admin</h2>
    <form method="post" enctype="multipart/form-data" class="space-y-3">
      <input type="hidden" name="admin_id" id="admin_id">
      <div>
        <label class="block font-semibold mb-1">Full Name</label>
        <input type="text" name="fullname" id="fullname" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block font-semibold mb-1">Email</label>
        <input type="email" name="email" id="email" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block font-semibold mb-1">Password</label>
        <input type="password" name="password" id="password" class="w-full border rounded px-3 py-2" required>
        <small class="text-gray-500 text-sm">Required for new admin</small>
      </div>
      <div>
        <label class="block font-semibold mb-1">Role</label>
        <select name="role" id="role" class="w-full border rounded px-3 py-2" disabled>
          <option value="admin" selected>Admin</option>
        </select>
        <small class="text-gray-500 text-sm">All admins have the same role</small>
      </div>
      <div>
        <label class="block font-semibold mb-1">Gcash Amount</label>
        <input type="number" name="gcash_amount" id="gcash_amount" step="0.01" class="w-full border rounded px-3 py-2" placeholder="0.00">
        <small class="text-gray-500 text-sm">Registration fee amount for divers</small>
      </div>
      <div>
        <label class="block font-semibold mb-1">Gcash Owner Name</label>
        <input type="text" name="gcash_owner" id="gcash_owner" class="w-full border rounded px-3 py-2" placeholder="GCash account owner name">
        <small class="text-gray-500 text-sm">Name displayed for GCash payments</small>
      </div>
      <div>
        <label class="block font-semibold mb-1">Gcash QR Code</label>
        <input type="file" name="gcash_qr" id="gcash_qr" accept="image/*" class="w-full border rounded px-3 py-2">
        <small class="text-gray-500 text-sm">QR code image for GCash payments</small>
        
        <!-- QR Preview -->
        <div id="qrPreviewContainer" class="mt-2 hidden">
          <label class="block text-sm font-medium text-gray-700 mb-1">Preview:</label>
          <img id="qrPreview" class="qr-preview">
        </div>
        
        <!-- Current QR Display (for edit) -->
        <div id="currentQRContainer" class="mt-2 hidden">
          <label class="block text-sm font-medium text-gray-700 mb-1">Current QR:</label>
          <img id="currentQR" class="qr-preview">
          <p class="text-xs text-gray-500 mt-1">Upload new QR to replace current one</p>
        </div>
      </div>
      <div class="flex justify-end gap-3 mt-4">
        <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
      </div>
    </form>
    <button onclick="closeModal()" class="absolute top-3 right-4 text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
  </div>
</div>

<!-- Scripts -->
<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const menuOverlay = document.getElementById('menuOverlay');

function openMenu() {
  sidebar.classList.remove('-translate-x-full');
  sidebar.classList.add('menu-enter');
  menuOverlay.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}
function closeMenu() {
  sidebar.classList.add('-translate-x-full');
  menuOverlay.classList.add('hidden');
  document.body.style.overflow = '';
}
menuToggle.addEventListener('click', openMenu);

const navLinks = sidebar.querySelectorAll('a');
navLinks.forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth < 768) closeMenu();
  });
});
window.addEventListener('resize', () => {
  if (window.innerWidth >= 768) closeMenu();
});

function editAdmin(admin) {
  document.getElementById('modalTitle').innerText = 'Edit Admin';
  document.getElementById('admin_id').value = admin.admin_id;
  document.getElementById('fullname').value = admin.fullname;
  document.getElementById('email').value = admin.email;
  document.getElementById('gcash_amount').value = admin.gcash_amount ?? '';
  document.getElementById('gcash_owner').value = admin.gcash_owner ?? '';
  document.getElementById('password').value = '';
  document.getElementById('password').required = false;
  
  // Show current QR if exists
  if (admin.gcash_qr) {
    document.getElementById('currentQR').src = '../' + admin.gcash_qr;
    document.getElementById('currentQRContainer').classList.remove('hidden');
  } else {
    document.getElementById('currentQRContainer').classList.add('hidden');
  }
  
  document.getElementById('adminModal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('adminModal').classList.add('hidden');
  document.getElementById('admin_id').value = '';
  document.getElementById('modalTitle').innerText = 'Add Admin';
  document.getElementById('password').required = true;
  document.getElementById('currentQRContainer').classList.add('hidden');
  document.getElementById('qrPreviewContainer').classList.add('hidden');
  document.querySelector('form').reset();
}

// QR Preview functionality
document.getElementById('gcash_qr').addEventListener('change', function(e) {
  const file = e.target.files[0];
  const preview = document.getElementById('qrPreview');
  const previewContainer = document.getElementById('qrPreviewContainer');
  
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      previewContainer.classList.remove('hidden');
    }
    reader.readAsDataURL(file);
  } else {
    previewContainer.classList.add('hidden');
  }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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