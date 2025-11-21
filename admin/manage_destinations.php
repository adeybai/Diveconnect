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

// Handle form submissions
$message = '';
$error = '';

// ADD NEW DESTINATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = trim($_POST['title']);
    $location = trim($_POST['location']);
    $rating = (int)$_POST['rating'];
    $description = trim($_POST['description']);
    $display_order = (int)$_POST['display_order'];
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newname = 'destination_' . time() . '_' . uniqid() . '.' . $ext;
            $uploadPath = '../assets/images/' . $newname;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $imagePath = 'assets/images/' . $newname;
                
                $stmt = $conn->prepare("INSERT INTO destinations (title, image_path, location, rating, description, display_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssisi", $title, $imagePath, $location, $rating, $description, $display_order);
                
                if ($stmt->execute()) {
                    $message = "Destination added successfully!";
                } else {
                    $error = "Database error: " . $conn->error;
                }
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, GIF, and WEBP allowed.";
        }
    } else {
        $error = "Please upload an image.";
    }
}

// UPDATE DESTINATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)$_POST['destination_id'];
    $title = trim($_POST['title']);
    $location = trim($_POST['location']);
    $rating = (int)$_POST['rating'];
    $description = trim($_POST['description']);
    $display_order = (int)$_POST['display_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Check if new image uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // Get old image to delete
            $oldStmt = $conn->prepare("SELECT image_path FROM destinations WHERE destination_id = ?");
            $oldStmt->bind_param("i", $id);
            $oldStmt->execute();
            $oldResult = $oldStmt->get_result();
            if ($oldRow = $oldResult->fetch_assoc()) {
                $oldImagePath = '../' . $oldRow['image_path'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            $newname = 'destination_' . time() . '_' . uniqid() . '.' . $ext;
            $uploadPath = '../assets/images/' . $newname;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $imagePath = 'assets/images/' . $newname;
                
                $stmt = $conn->prepare("UPDATE destinations SET title=?, image_path=?, location=?, rating=?, description=?, display_order=?, is_active=? WHERE destination_id=?");
                $stmt->bind_param("sssisiii", $title, $imagePath, $location, $rating, $description, $display_order, $is_active, $id);
            }
        }
    } else {
        // Update without changing image
        $stmt = $conn->prepare("UPDATE destinations SET title=?, location=?, rating=?, description=?, display_order=?, is_active=? WHERE destination_id=?");
        $stmt->bind_param("ssissii", $title, $location, $rating, $description, $display_order, $is_active, $id);
    }
    
    if (isset($stmt) && $stmt->execute()) {
        $message = "Destination updated successfully!";
    } else {
        $error = "Failed to update destination.";
    }
}

// DELETE DESTINATION
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get image path before deleting
    $stmt = $conn->prepare("SELECT image_path FROM destinations WHERE destination_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $imagePath = '../' . $row['image_path'];
        
        // Delete from database
        $deleteStmt = $conn->prepare("DELETE FROM destinations WHERE destination_id = ?");
        $deleteStmt->bind_param("i", $id);
        
        if ($deleteStmt->execute()) {
            // Delete image file
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
            $message = "Destination deleted successfully!";
        } else {
            $error = "Failed to delete destination.";
        }
    }
}

// Fetch all destinations
$destinations = $conn->query("SELECT * FROM destinations ORDER BY display_order ASC, destination_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Destinations | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
</style>
</head>
<body class="min-h-screen bg-gray-100 font-sans">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow-lg sticky top-0 z-50">
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
        <a href="manage_admins.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-settings-line text-xl"></i><span>Manage Admins</span></a>
        <a href="manage_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-star-line text-xl"></i><span>Manage Dive Master</span></a>
        <a href="verify_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-shield-check-line text-xl"></i><span>Verify Dive Master</span></a>
        <a href="manage_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-line text-xl"></i><span>Manage User Divers</span></a>
        <a href="verify_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-shared-line text-xl"></i><span>Verify User Divers</span></a>
        <a href="manage_destinations.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm">
          <i class="ri-map-pin-line text-xl"></i><span>Destinations</span>
        </a>
      </nav>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="md:col-span-9">
    <div class="bg-white rounded-lg shadow-xl p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-blue-700">
          <i class="ri-map-pin-line mr-2"></i>Manage Destinations
        </h1>
        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium shadow-md transition-all hover:shadow-lg">
          <i class="ri-add-line mr-1"></i>Add Destination
        </button>
      </div>

      <!-- Destinations Grid -->
      <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
        <?php while ($dest = $destinations->fetch_assoc()): ?>
        <div class="bg-gray-50 rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow">
          <div class="relative">
            <img src="../<?= e($dest['image_path']) ?>" alt="<?= e($dest['title']) ?>" class="w-full h-48 object-cover">
            <?php if (!$dest['is_active']): ?>
            <div class="absolute top-2 left-2 bg-red-500 text-white px-3 py-1 rounded-full text-xs font-semibold">
              Inactive
            </div>
            <?php endif; ?>
            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full text-sm font-semibold">
              <?php for ($i=0; $i<5; $i++): ?>
                <?= $i < $dest['rating'] ? '' : '' ?>
              <?php endfor; ?>
            </div>
          </div>
          <div class="p-4">
            <h3 class="text-lg font-bold text-gray-800 mb-1"><?= e($dest['title']) ?></h3>
            <p class="text-sm text-gray-600 mb-2"><i class="ri-map-pin-line"></i> <?= e($dest['location']) ?></p>
            <p class="text-sm text-gray-700 mb-3"><?= e($dest['description']) ?></p>
            <div class="flex gap-2">
              <button onclick='openEditModal(<?= json_encode($dest) ?>)' class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition">
                <i class="ri-edit-line"></i> Edit
              </button>
              <button onclick="confirmDelete(<?= $dest['destination_id'] ?>)" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition">
                <i class="ri-delete-bin-line"></i> Delete
              </button>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
  </main>
</div>

<!-- ADD MODAL -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="bg-blue-700 text-white p-4 flex justify-between items-center rounded-t-xl">
      <h2 class="text-xl font-bold"><i class="ri-add-circle-line mr-2"></i>Add New Destination</h2>
      <button onclick="closeAddModal()" class="hover:bg-blue-600 p-2 rounded-lg transition">
        <i class="ri-close-line text-2xl"></i>
      </button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
      <input type="hidden" name="action" value="add">
      
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Title *</label>
        <input type="text" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>
      
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Location *</label>
        <input type="text" name="location" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>
      
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Rating (1-5) *</label>
          <input type="number" name="rating" min="1" max="5" value="5" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Display Order *</label>
          <input type="number" name="display_order" value="0" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
      </div>
      
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Description *</label>
        <textarea name="description" required rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
      </div>
      
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Image * (JPG, PNG, GIF, WEBP)</label>
        <input type="file" name="image" accept="image/*" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
      </div>
      
      <div class="flex gap-3 pt-4">
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition shadow-md hover:shadow-lg">
          <i class="ri-save-line mr-2"></i>Add Destination
        </button>
        <button type="button" onclick="closeAddModal()" class="px-6 py-3 border border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
    <div class="bg-blue-700 text-white p-4 flex justify-between items-center rounded-t-xl">
      <h2 class="text-xl font-bold"><i class="ri-edit-line mr-2"></i>Edit Destination</h2>
      <button onclick="closeEditModal()" class="hover:bg-blue-600 p-2 rounded-lg transition">
        <i class="ri-close-line text-2xl"></i>
      </button>
    </div>
    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="destination_id" id="edit_destination_id">
      
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Title *</label>
        <input type="text" name="title" id="edit_title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>
      
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Location *</label>
        <input type="text" name="location" id="edit_location" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>
      
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Rating (1-5) *</label>
          <input type="number" name="rating" id="edit_rating" min="1" max="5" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Display Order *</label>
          <input type="number" name="display_order" id="edit_display_order" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
      </div>
      
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Description *</label>
        <textarea name="description" id="edit_description" required rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
      </div>
      
      <div>
        <label class="flex items-center gap-2">
          <input type="checkbox" name="is_active" id="edit_is_active" class="w-4 h-4 text-blue-600 rounded">
          <span class="text-sm font-semibold text-gray-700">Active (visible on explore page)</span>
        </label>
      </div>
      
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Change Image (optional)</label>
        <input type="file" name="image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current image</p>
      </div>
      
      <div class="flex gap-3 pt-4">
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition shadow-md hover:shadow-lg">
          <i class="ri-save-line mr-2"></i>Update Destination
        </button>
        <button type="button" onclick="closeEditModal()" class="px-6 py-3 border border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Mobile menu functions
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

// Modal functions
function openAddModal() {
  document.getElementById('addModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeAddModal() {
  document.getElementById('addModal').classList.add('hidden');
  document.body.style.overflow = '';
}

function openEditModal(dest) {
  document.getElementById('edit_destination_id').value = dest.destination_id;
  document.getElementById('edit_title').value = dest.title;
  document.getElementById('edit_location').value = dest.location;
  document.getElementById('edit_rating').value = dest.rating;
  document.getElementById('edit_description').value = dest.description;
  document.getElementById('edit_display_order').value = dest.display_order;
  document.getElementById('edit_is_active').checked = dest.is_active == 1;
  
  document.getElementById('editModal').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}

function closeEditModal() {
  document.getElementById('editModal').classList.add('hidden');
  document.body.style.overflow = '';
}

function confirmDelete(id) {
  Swal.fire({
    title: 'Delete Destination?',
    text: "This action cannot be undone!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = 'manage_destinations.php?delete=' + id;
    }
  });
}

// Logout handler
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

// Show success/error messages
<?php if ($message): ?>
Swal.fire({
  icon: 'success',
  title: 'Success!',
  text: '<?= $message ?>',
  timer: 2000,
  showConfirmButton: false
});
<?php endif; ?>

<?php if ($error): ?>
Swal.fire({
  icon: 'error',
  title: 'Error!',
  text: '<?= $error ?>',
  confirmButtonColor: '#3085d6'
});
<?php endif; ?>
</script>

</body>
</html>