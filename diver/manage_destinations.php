<?php 
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require '../includes/db.php';

// Require diver session
if (!isset($_SESSION['diver_id'])) {
    header("Location: login_diver.php");
    exit;
}

$diver_id = $_SESSION['diver_id'];

// Helper function
function e($v) {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// Get diver info including price
$diver_stmt = $conn->prepare("SELECT fullname, profile_pic, specialty, price FROM divers WHERE id = ?");
$diver_stmt->bind_param("i", $diver_id);
$diver_stmt->execute();
$diver = $diver_stmt->get_result()->fetch_assoc();

// Handle form submissions
$message = '';
$error = '';

// ADD NEW DESTINATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = trim($_POST['title']);
    $location = trim($_POST['location']);
    $rating = (int)$_POST['rating'];
    $description = trim($_POST['description']);
    
    // Use the preset price from diver's profile (set by admin)
    $price_per_diver = floatval($diver['price']);

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newname = 'diver_destination_' . time() . '_' . uniqid() . '.' . $ext;
            $uploadPath = '../assets/images/' . $newname;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $imagePath = 'assets/images/' . $newname;
                $stmt = $conn->prepare("INSERT INTO diver_destinations (diver_id, title, image_path, location, rating, description, price_per_diver) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssisd", $diver_id, $title, $imagePath, $location, $rating, $description, $price_per_diver);
                
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
    
    // Use the preset price from diver's profile (set by admin)
    $price_per_diver = floatval($diver['price']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Check if new image uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            // Get old image to delete
            $oldStmt = $conn->prepare("SELECT image_path FROM diver_destinations WHERE id = ? AND diver_id = ?");
            $oldStmt->bind_param("ii", $id, $diver_id);
            $oldStmt->execute();
            $oldResult = $oldStmt->get_result();
            
            if ($oldRow = $oldResult->fetch_assoc()) {
                $oldImagePath = '../' . $oldRow['image_path'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            $newname = 'diver_destination_' . time() . '_' . uniqid() . '.' . $ext;
            $uploadPath = '../assets/images/' . $newname;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $imagePath = 'assets/images/' . $newname;
                $stmt = $conn->prepare("UPDATE diver_destinations SET title=?, image_path=?, location=?, rating=?, description=?, price_per_diver=?, is_active=? WHERE id=? AND diver_id=?");
                $stmt->bind_param("sssisdiii", $title, $imagePath, $location, $rating, $description, $price_per_diver, $is_active, $id, $diver_id);
            }
        }
    } else {
        // Update without changing image
        $stmt = $conn->prepare("UPDATE diver_destinations SET title=?, location=?, rating=?, description=?, price_per_diver=?, is_active=? WHERE id=? AND diver_id=?");
        $stmt->bind_param("ssisdiii", $title, $location, $rating, $description, $price_per_diver, $is_active, $id, $diver_id);
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
    $stmt = $conn->prepare("SELECT image_path FROM diver_destinations WHERE id = ? AND diver_id = ?");
    $stmt->bind_param("ii", $id, $diver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $imagePath = '../' . $row['image_path'];
        
        // Delete from database
        $deleteStmt = $conn->prepare("DELETE FROM diver_destinations WHERE id = ? AND diver_id = ?");
        $deleteStmt->bind_param("ii", $id, $diver_id);
        
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

// Fetch diver's destinations
$destinations = $conn->prepare("SELECT * FROM diver_destinations WHERE diver_id = ? ORDER BY created_at DESC");
$destinations->bind_param("i", $diver_id);
$destinations->execute();
$destinations_result = $destinations->get_result();

// Get pending bookings count for notification
$pending_stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE diver_id = ? AND status = 'pending'");
$pending_stmt->bind_param("i", $diver_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result()->fetch_assoc();
$pending_count = $pending_result['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage My Destinations | DiveConnect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html {
            scroll-behavior: smooth;
        }
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .menu-enter {
            animation: slideIn 0.3s ease-out;
        }
        .backdrop-blur-sm {
            backdrop-filter: blur(4px);
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow-md">
    <div class="container mx-auto flex justify-between items-center p-4">
        <!-- Logo -->
        <div class="flex items-center gap-4">
            <a href="diver_dashboard.php" class="flex items-center gap-2 no-underline">
                <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" class="h-12">
            </a>
        </div>

        <!-- NAV + PROFILE + NOTIF -->
        <div class="flex items-center gap-4">
            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center gap-3">
                <a href="manage_destinations.php" class="px-3 py-2 rounded bg-white text-blue-700 font-semibold hover:bg-blue-100 transition no-underline">
                    <i class="ri-map-pin-line mr-1"></i>Destinations
                </a>
                <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" target="_blank" class="px-3 py-2 rounded bg-white text-blue-700 font-semibold hover:bg-blue-100 transition no-underline">
                    <i class="ri-timer-line mr-1"></i>Tide Chart
                </a>
                <a href="diver_dashboard.php?tab=history" class="px-3 py-2 rounded bg-white text-blue-700 font-semibold hover:bg-blue-100 transition no-underline">
                    <i class="ri-history-line mr-1"></i>Booking History
                </a>
            </div>

            <!-- Notification Bell -->
            <button id="notifBtn" title="Notifications" class="relative p-2 rounded-full hover:bg-blue-600/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor">
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
                <img src="../admin/uploads/<?= htmlspecialchars($diver['profile_pic'] ?: 'default.png') ?>" class="w-12 h-12 rounded-full border-2 border-white object-cover">
                <a href="../diver/settings.php" class="bg-blue-600 hover:bg-blue-800 px-3 py-1.5 rounded no-underline">⚙️</a>
                <a href="#" onclick="confirmLogout(event)" class="bg-red-500 hover:bg-red-600 px-3 py-1.5 rounded no-underline">Logout</a>
            </div>

            <!-- Mobile: Avatar + Menu Button -->
            <div class="flex md:hidden items-center gap-2">
                <img src="../admin/uploads/<?= htmlspecialchars($diver['profile_pic'] ?: 'default.png') ?>" class="w-10 h-10 rounded-full border-2 border-white object-cover">
                <button id="mobileMenuBtn" class="p-2 rounded hover:bg-blue-600/20" aria-label="Menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
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
            <a href="diver_dashboard.php" class="block px-4 py-2.5 rounded hover:bg-blue-700 text-white no-underline">
                <i class="ri-dashboard-line mr-2"></i>Dashboard
            </a>
            <a href="manage_destinations.php" class="block px-4 py-2.5 rounded hover:bg-blue-700 text-white no-underline">
                <i class="ri-map-pin-line mr-2"></i>Destinations
            </a>
            <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" class="block px-4 py-2.5 rounded hover:bg-blue-700 text-white no-underline">
                <i class="ri-timer-line mr-2"></i>Tide Chart
            </a>
            <a href="diver_dashboard.php?tab=history" class="block px-4 py-2.5 rounded hover:bg-blue-700 text-white no-underline">
                <i class="ri-history-line mr-2"></i>Booking History
            </a>

            <!-- Divider -->
            <div class="border-t border-blue-600/30 my-3"></div>

            <!-- Actions -->
            <a href="../diver/settings.php" class="block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded text-center font-semibold no-underline">
                <i class="ri-settings-3-line mr-2"></i>Settings
            </a>
            <a href="#" onclick="confirmLogout(event)" class="block bg-red-500 hover:bg-red-600 text-white px-4 py-2.5 rounded text-center font-semibold no-underline">
                <i class="ri-logout-box-r-line mr-2"></i>Logout
            </a>
        </div>
    </div>
</header>

<div class="container mx-auto p-6">
    <!-- MAIN CONTENT -->
    <div class="bg-white rounded-lg shadow-xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-blue-700">
                <i class="ri-map-pin-line mr-2"></i>Manage My Destinations
            </h1>
            <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium shadow-md transition-all hover:shadow-lg">
                <i class="ri-add-line mr-1"></i>Add Destination
            </button>
        </div>

        <!-- Price Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-blue-800">Standard Price Set by Admin</h3>
                    <p class="text-blue-600">All your destinations will use this fixed price per diver</p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-blue-700">₱<?= number_format($diver['price'], 2) ?></div>
                    <div class="text-sm text-blue-600">per diver</div>
                </div>
            </div>
        </div>

        <!-- Destinations Grid -->
        <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            <?php while ($dest = $destinations_result->fetch_assoc()): ?>
                <div class="bg-gray-50 rounded-xl overflow-hidden shadow-lg hover:shadow-xl transition-shadow">
                    <div class="relative">
                        <img src="../<?= e($dest['image_path']) ?>" alt="<?= e($dest['title']) ?>" class="w-full h-48 object-cover">
                        <?php if (!$dest['is_active']): ?>
                            <div class="absolute top-2 left-2 bg-red-500 text-white px-3 py-1 rounded-full text-xs font-semibold">
                                Inactive
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <h3 class="text-lg font-bold text-gray-800 mb-1"><?= e($dest['title']) ?></h3>
                        <p class="text-sm text-gray-600 mb-2"><i class="ri-map-pin-line"></i> <?= e($dest['location']) ?></p>
                        <p class="text-sm text-gray-700 mb-3 line-clamp-2"><?= e($dest['description']) ?></p>
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex text-yellow-400">
                                <?php for ($i=1; $i<=5; $i++): ?>
                                    <i class="ri-star-fill <?= $i <= $dest['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span class="text-lg font-bold text-blue-600">₱<?= number_format($dest['price_per_diver'], 2) ?></span>
                        </div>
                        <div class="flex gap-2">
                            <button onclick='openEditModal(<?= json_encode($dest) ?>)' class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition">
                                <i class="ri-edit-line"></i> Edit
                            </button>
                            <button onclick="confirmDelete(<?= $dest['id'] ?>)" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition">
                                <i class="ri-delete-bin-line"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>

            <?php if ($destinations_result->num_rows === 0): ?>
                <div class="col-span-full text-center py-12">
                    <i class="ri-map-pin-line text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Destinations Yet</h3>
                    <p class="text-gray-500 mb-4">Add your first dive destination to attract more customers</p>
                    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                        <i class="ri-add-line mr-2"></i>Add Your First Destination
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
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
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-semibold text-blue-800">Standard Price</h4>
                        <p class="text-sm text-blue-600">Set by admin and applied to all destinations</p>
                    </div>
                    <div class="text-right">
                        <div class="text-xl font-bold text-blue-700">₱<?= number_format($diver['price'], 2) ?></div>
                        <div class="text-sm text-blue-600">per diver</div>
                    </div>
                </div>
            </div>
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
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Price per Diver</label>
                    <input type="text" value="₱<?= number_format($diver['price'], 2) ?>" disabled class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                    <p class="text-xs text-gray-500 mt-1">Fixed price set by admin</p>
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
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-semibold text-blue-800">Standard Price</h4>
                        <p class="text-sm text-blue-600">Set by admin and applied to all destinations</p>
                    </div>
                    <div class="text-right">
                        <div class="text-xl font-bold text-blue-700">₱<?= number_format($diver['price'], 2) ?></div>
                        <div class="text-sm text-blue-600">per diver</div>
                    </div>
                </div>
            </div>
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
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Price per Diver</label>
                    <input type="text" value="₱<?= number_format($diver['price'], 2) ?>" disabled class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                    <p class="text-xs text-gray-500 mt-1">Fixed price set by admin</p>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description *</label>
                <textarea name="description" id="edit_description" required rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
            </div>
            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="edit_is_active" class="w-4 h-4 text-blue-600 rounded">
                    <span class="text-sm font-semibold text-gray-700">Active (visible to users)</span>
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
    // Mobile menu functionality
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    
    mobileMenuBtn.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!mobileMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
            mobileMenu.classList.add('hidden');
        }
    });

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
        document.getElementById('edit_destination_id').value = dest.id;
        document.getElementById('edit_title').value = dest.title;
        document.getElementById('edit_location').value = dest.location;
        document.getElementById('edit_rating').value = dest.rating;
        document.getElementById('edit_description').value = dest.description;
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

    function confirmLogout(event) {
        event.preventDefault();
        Swal.fire({
            title: 'Logout?',
            text: "Are you sure you want to logout?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, logout!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }

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