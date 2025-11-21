<?php
session_start();
require '../includes/db.php';

// Require admin session
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// helper for escaping
function e($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// Fetch counts
$counts = [];
$tables = [
    'admins' => "SELECT COUNT(*) FROM admins",
    'users' => "SELECT COUNT(*) FROM users",
    'divers' => "SELECT COUNT(*) FROM divers",
    'bookings' => "SELECT COUNT(*) FROM bookings",
    'payments' => "SELECT COUNT(*) FROM payments",
    'availability' => "SELECT COUNT(*) FROM availability",
    'terms' => "SELECT COUNT(*) FROM terms_conditions"
];
foreach ($tables as $k => $sql) {
    $res = $conn->query($sql);
    $counts[$k] = $res ? $res->fetch_row()[0] : 0;
}

// Recent admins
$recent_admins = [];
$stmt = $conn->prepare("SELECT admin_id, fullname, email, role, created_at FROM admins ORDER BY created_at DESC LIMIT 6");
$stmt->execute();
$recent_admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent users
$recent_users = [];
$stmt = $conn->prepare("SELECT id, fullname, email, phone, is_verified, created_at, profile_pic FROM users ORDER BY created_at DESC LIMIT 6");
$stmt->execute();
$recent_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent divers
$recent_divers = [];
$stmt = $conn->prepare("SELECT id, fullname, email, specialty, level, profile_pic, created_at FROM divers ORDER BY created_at DESC LIMIT 6");
$stmt->execute();
$recent_divers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent bookings (join users & divers)
$recent_bookings = [];
$stmt = $conn->prepare("
    SELECT b.id, b.booking_date, b.status, u.fullname AS user_name, d.fullname AS diver_name, b.created_at
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN divers d ON b.diver_id = d.id
    ORDER BY b.created_at DESC
    LIMIT 8
");
$stmt->execute();
$recent_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent payments (join bookings)
$recent_payments = [];
$stmt = $conn->prepare("
    SELECT p.id, p.amount, p.status, p.payment_date, p.booking_id
    FROM payments p
    ORDER BY p.payment_date DESC
    LIMIT 8
");
$stmt->execute();
$recent_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Upcoming availability
$upcoming_avail = [];
$stmt = $conn->prepare("
    SELECT a.id, a.available_date, a.available_time, a.status, d.fullname AS diver_name
    FROM availability a
    LEFT JOIN divers d ON a.diver_id = d.id
    WHERE a.status = 'available'
    ORDER BY a.available_date ASC, a.available_time ASC
    LIMIT 10
");
$stmt->execute();
$upcoming_avail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Terms & Conditions (recent)
$terms = [];
$stmt = $conn->prepare("SELECT id, content, is_active, created_at FROM terms_conditions ORDER BY created_at DESC LIMIT 6");
$stmt->execute();
$terms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 font-sans">

<header class="bg-blue-700 text-white shadow">
    <div class="container mx-auto flex items-center justify-between p-4">
        <div class="flex items-center gap-3">
            <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-10">
            <span class="font-bold text-xl">DiveConnect — Admin</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="verify_divers.php" class="hidden md:inline-block text-sm bg-white text-blue-700 px-3 py-2 rounded">Verify Divers</a>
            <form action="logout.php" method="post" class="inline">
                <button type="submit" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded text-sm">Logout</button>
            </form>
        </div>
    </div>
</header>

<div class="container mx-auto grid grid-cols-1 md:grid-cols-12 gap-6 mt-6 px-4">

   <!-- Mobile Menu Overlay -->
<div id="menuOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden backdrop-blur-sm" onclick="closeMenu()"></div>

<div class="container mx-auto grid grid-cols-1 md:grid-cols-12 gap-4 sm:gap-6 mt-4 sm:mt-6 px-3 sm:px-4 pb-6">

  <!-- SIDEBAR - Mobile Drawer / Desktop Sticky -->
  <aside id="sidebar" class="md:col-span-3 bg-white rounded-lg shadow-xl fixed md:sticky top-0 left-0 h-full md:h-screen w-72 md:w-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50 md:z-auto overflow-y-auto hide-scrollbar">
    
    <!-- Mobile Menu Header -->
    <div class="md:hidden flex items-center justify-between p-4 border-b border-gray-200 bg-blue-50">
      <h3 class="text-lg font-bold text-blue-700">Admin Menu</h3>
      <button onclick="closeMenu()" class="p-2 hover:bg-blue-100 rounded-lg transition-colors">
        <i class="ri-close-line text-2xl text-gray-700"></i>
      </button>
    </div>

    <div class="p-4 sm:p-6">
      <h3 class="hidden md:block text-lg font-semibold text-blue-700 mb-4">Admin Menu</h3>
      <nav class="space-y-1.5 sm:space-y-2">
        <a href="index.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm hover:shadow-md transition-all">
          <i class="ri-home-4-line text-xl"></i>
          <span>Home</span>
        </a>
        <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-dashboard-line text-xl"></i>
          <span>Dashboard</span>
        </a>
        <a href="manage_admins.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-user-settings-line text-xl"></i>
          <span>Manage Admins</span>
        </a>
        <a href="manage_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-user-star-line text-xl"></i>
          <span>Manage Dive Master</span>
        </a>
        <a href="verify_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-shield-check-line text-xl"></i>
          <span>Verify Master Divers</span>
        </a>
        <a href="manage_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-user-line text-xl"></i>
          <span>Manage User Divers</span>
        </a>
        <a href="verify_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-user-shared-line text-xl"></i>
          <span>Verify User Divers</span>
        </a>
        <a href="manage_bookings.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-calendar-check-line text-xl"></i>
          <span>Bookings</span>
        </a>
        <a href="payments.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-bank-card-line text-xl"></i>
          <span>Payments</span>
        </a>
        <a href="manage_destinations.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-map-pin-line text-xl"></i>
          <span>Destinations</span>
        </a>
      </nav>
    </div>
  </aside>

    <main class="md:col-span-9 space-y-6">

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
                <div class="bg-blue-100 text-blue-700 p-3 rounded"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a4 4 0 00-4 4v1H5a3 3 0 00-3 3v3h16v-3a3 3 0 00-3-3h-1V6a4 4 0 00-4-4z"/></svg></div>
                <div>
                    <p class="text-sm text-gray-500">Admins</p>
                    <p class="text-2xl font-bold"><?= e($counts['admins']) ?></p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
                <div class="bg-green-100 text-green-700 p-3 rounded"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M2 5a2 2 0 012-2h3l1 2h6a2 2 0 012 2v3H2V5z"/><path d="M2 12h16v1a3 3 0 01-3 3H5a3 3 0 01-3-3v-1z"/></svg></div>
                <div>
                    <p class="text-sm text-gray-500">Users</p>
                    <p class="text-2xl font-bold"><?= e($counts['users']) ?></p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
                <div class="bg-purple-100 text-purple-700 p-3 rounded"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M6 2a2 2 0 00-2 2v5a2 2 0 002 2h1v4l3-2 3 2v-4h1a2 2 0 002-2V4a2 2 0 00-2-2H6z"/></svg></div>
                <div>
                    <p class="text-sm text-gray-500">Divers</p>
                    <p class="text-2xl font-bold"><?= e($counts['divers']) ?></p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
                <div class="bg-yellow-100 text-yellow-700 p-3 rounded"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M4 3h12v2H4V3zM4 7h12v8H4V7z"/></svg></div>
                <div>
                    <p class="text-sm text-gray-500">Bookings</p>
                    <p class="text-2xl font-bold"><?= e($counts['bookings']) ?></p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
                <div class="bg-pink-100 text-pink-700 p-3 rounded"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M2 5h16v2H2zM4 9h12v6H4z"/></svg></div>
                <div>
                    <p class="text-sm text-gray-500">Payments</p>
                    <p class="text-2xl font-bold"><?= e($counts['payments']) ?></p>
                </div>
            </div>

            <div class="bg-white p-4 rounded-lg shadow flex items-center gap-4">
                <div class="bg-indigo-100 text-indigo-700 p-3 rounded"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M6 2h2v2H6V2zm6 0h2v2h-2V2zM3 6h14v12H3V6z"/></svg></div>
                <div>
                    <p class="text-sm text-gray-500">Availability</p>
                    <p class="text-2xl font-bold"><?= e($counts['availability']) ?></p>
                </div>
            </div>
        </section>

        <section id="manage-admins" class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-blue-700">Manage Admins</h3>
                <a href="manage_admins.php" class="text-sm bg-blue-700 text-white px-3 py-1 rounded">Open</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2">Fullname</th>
                            <th class="px-3 py-2">Email</th>
                            <th class="px-3 py-2">Role</th>
                            <th class="px-3 py-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_admins as $a): ?>
                            <tr class="border-b">
                                <td class="px-3 py-2"><?= e($a['fullname']) ?></td>
                                <td class="px-3 py-2"><?= e($a['email']) ?></td>
                                <td class="px-3 py-2"><?= e($a['role']) ?></td>
                                <td class="px-3 py-2"><?= e($a['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="manage-users" class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-blue-700">Manage Users</h3>
                <a href="manage_users.php" class="text-sm bg-blue-700 text-white px-3 py-1 rounded">Open</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Email</th>
                            <th class="px-3 py-2">Phone</th>
                            <th class="px-3 py-2">Verified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $u): ?>
                            <tr class="border-b">
                                <td class="px-3 py-2 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-200">
                                        <?php
                                            $pic = $u['profile_pic'] ? 'uploads/' . basename($u['profile_pic']) : '../assets/images/default.png';
                                        ?>
                                        <img src="<?= e($pic) ?>" alt="<?= e($u['fullname']) ?>" class="object-cover w-full h-full">
                                    </div>
                                    <span><?= e($u['fullname']) ?></span>
                                </td>
                                <td class="px-3 py-2"><?= e($u['email']) ?></td>
                                <td class="px-3 py-2"><?= e($u['phone'] ?: '—') ?></td>
                                <td class="px-3 py-2"><?= $u['is_verified'] ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="manage-divers" class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-blue-700">Manage Master Divers</h3>
                <a href="monitor_divers.php" class="text-sm bg-blue-700 text-white px-3 py-1 rounded">Open</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Specialty</th>
                            <th class="px-3 py-2">Level</th>
                            <th class="px-3 py-2">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_divers as $d): ?>
                            <tr class="border-b">
                                <td class="px-3 py-2 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-200">
                                        <?php $dp = $d['profile_pic'] ? 'uploads/' . basename($d['profile_pic']) : '../assets/images/diver_default.png'; ?>
                                        <img src="<?= e($dp) ?>" alt="<?= e($d['fullname']) ?>" class="object-cover w-full h-full">
                                    </div>
                                    <span><?= e($d['fullname']) ?></span>
                                </td>
                                <td class="px-3 py-2"><?= e($d['specialty'] ?: '—') ?></td>
                                <td class="px-3 py-2"><?= e($d['level'] ?: '—') ?></td>
                                <td class="px-3 py-2"><?= e($d['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="bookings" class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-blue-700">Recent Bookings</h3>
                <a href="manage_bookings.php" class="text-sm bg-blue-700 text-white px-3 py-1 rounded">Open</a>
            </div>

            <?php if (count($recent_bookings) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2">User</th>
                                <th class="px-3 py-2">Diver</th>
                                <th class="px-3 py-2">Booking Date</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $b): ?>
                                <tr class="border-b">
                                    <td class="px-3 py-2"><?= e($b['user_name'] ?: '—') ?></td>
                                    <td class="px-3 py-2"><?= e($b['diver_name'] ?: '—') ?></td>
                                    <td class="px-3 py-2"><?= e($b['booking_date']) ?></td>
                                    <td class="px-3 py-2"><?= e($b['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No recent bookings.</p>
            <?php endif; ?>
        </section>

        <section id="payments" class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-blue-700">Recent Payments</h3>
                <a href="manage_payments.php" class="text-sm bg-blue-700 text-white px-3 py-1 rounded">Open</a>
            </div>
            <?php if (count($recent_payments) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2">ID</th>
                                <th class="px-3 py-2">Amount</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $p): ?>
                                <tr class="border-b">
                                    <td class="px-3 py-2"><?= e($p['id']) ?></td>
                                    <td class="px-3 py-2"><?= e(number_format($p['amount'], 2)) ?></td>
                                    <td class="px-3 py-2"><?= e($p['status']) ?></td>
                                    <td class="px-3 py-2"><?= e($p['payment_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No payments yet.</p>
            <?php endif; ?>
        </section>

        <section id="availability" class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-blue-700">Upcoming Availability</h3>
                <a href="manage_availability.php" class="text-sm bg-blue-700 text-white px-3 py-1 rounded">Open</a>
            </div>

            <?php if (count($upcoming_avail) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Time</th>
                                <th class="px-3 py-2">Diver</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_avail as $a): ?>
                                <tr class="border-b">
                                    <td class="px-3 py-2"><?= e($a['available_date']) ?></td>
                                    <td class="px-3 py-2"><?= e($a['available_time']) ?></td>
                                    <td class="px-3 py-2"><?= e($a['diver_name'] ?: '—') ?></td>
                                    <td class="px-3 py-2"><?= e($a['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No availability listed.</p>
            <?php endif; ?>
        </section>

        <section id="terms" class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-blue-700">Terms & Conditions</h3>
                <a href="manage_terms.php" class="text-sm bg-blue-700 text-white px-3 py-1 rounded">Open</a>
            </div>

            <?php if (count($terms) > 0): ?>
                <ul class="space-y-3 text-sm">
                    <?php foreach ($terms as $t): ?>
                        <li class="border p-3 rounded">
                            <div class="flex justify-between items-start">
                                <div class="prose max-w-none text-sm"><?= nl2br(e(substr($t['content'],0,300))) ?><?= strlen($t['content'])>300 ? '...' : '' ?></div>
                                <div class="text-xs text-gray-500 ml-3"><?= $t['is_active'] ? 'Active' : 'Inactive' ?></div>
                            </div>
                            <div class="text-xs text-gray-400 mt-2"><?= e($t['created_at']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500">No terms found.</p>
            <?php endif; ?>
        </section>

    </main>
</div>

<footer class="bg-blue-700 text-white text-center py-4 mt-8">
    <div class="container mx-auto">
        <small>&copy; <?= date('Y') ?> DiveConnect. All rights reserved.</small>
    </div>
</footer>

</body>
</html>