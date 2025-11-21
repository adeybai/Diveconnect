<?php
if(!isset($conn)){
    include("../includes/db.php");
}

// dito ang logged in admin info
if(isset($_SESSION['admin_id'])){
    $stmt = $conn->prepare("SELECT * FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $current_admin = $res->fetch_assoc();
    $stmt->close();
} else {
    header("Location: ../login_admin.php");
    exit;
}

// Tabs
$view = $_GET['view'] ?? 'active';
$edit_id = $_GET['edit_id'] ?? null;

// Fetch bookings based on view
if($view == 'archive'){
    $result = $conn->query("
        SELECT b.*, u.fullname AS user_name, d.fullname AS diver_name
        FROM bookings_archive b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN divers d ON b.diver_id = d.id
        ORDER BY b.id ASC
    ");
} else {
    $result = $conn->query("
        SELECT b.*, u.fullname AS user_name, d.fullname AS diver_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN divers d ON b.diver_id = d.id
        ORDER BY b.id ASC
    ");
}

// Handle yung update submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_booking_id'])){
    $booking_id = intval($_POST['edit_booking_id']);
    $booking_date = $_POST['booking_date'];
    $status = $_POST['status'];
    $edited_at = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE bookings SET booking_date = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssi", $booking_date, $status, $booking_id);
    $stmt->execute();
    header("Location: bookings.php?view=active");
    exit;
}
?>

<!-- Tabs -->
<div class="mb-4 flex space-x-2">
    <a href="?view=active" class="px-4 py-2 <?= ($view=='active') ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> rounded">Active</a>
    <a href="?view=archive" class="px-4 py-2 <?= ($view=='archive') ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> rounded">Archived</a>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h3 class="text-xl font-semibold text-gray-700 mb-4">Manage Bookings</h3>

    <table class="w-full border-collapse">
        <thead>
            <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600">
                <th class="p-3">ID</th>
                <th class="p-3">User</th>
                <th class="p-3">Diver</th>
                <th class="p-3">Booking Date</th>
                <th class="p-3">Status</th>
                <th class="p-3">Created At</th>
                <th class="p-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="text-sm">
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($booking = $result->fetch_assoc()): ?>
                    <?php if($edit_id == $booking['id'] && $view=='active'): ?>
                        <!-- Inline Edit Form -->
                        <tr class="border-b bg-yellow-50">
                            <form method="POST">
                                <td class="p-3"><?= $booking['id'] ?><input type="hidden" name="edit_booking_id" value="<?= $booking['id'] ?>"></td>
                                <td class="p-3"><?= htmlspecialchars($booking['user_name']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($booking['diver_name']) ?></td>
                                <td class="p-3"><input type="date" name="booking_date" value="<?= htmlspecialchars($booking['booking_date']) ?>" class="border p-1 rounded w-full" required></td>
                                <td class="p-3">
                                    <select name="status" class="border p-1 rounded w-full">
                                        <option value="pending" <?= ($booking['status']=='pending')?'selected':'' ?>>Pending</option>
                                        <option value="confirmed" <?= ($booking['status']=='confirmed')?'selected':'' ?>>Confirmed</option>
                                        <option value="cancelled" <?= ($booking['status']=='cancelled')?'selected':'' ?>>Cancelled</option>
                                    </select>
                                </td>
                                <td class="p-3"><?= $booking['created_at'] ?></td>
                                <td class="p-3 text-center space-y-1">
                                    <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 w-full">Save</button>
                                    <a href="?view=active" class="bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500 w-full block text-center">Cancel</a>
                                </td>
                            </form>
                        </tr>
                    <?php else: ?>
                        <tr class="border-b">
                            <td class="p-3"><?= $booking['id'] ?></td>
                            <td class="p-3"><?= htmlspecialchars($booking['user_name']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($booking['diver_name']) ?></td>
                            <td class="p-3"><?= $booking['booking_date'] ?></td>
                            <td class="p-3"><?= ucfirst($booking['status']) ?></td>
                            <td class="p-3"><?= $booking['created_at'] ?></td>
                            <td class="p-3 text-center space-x-2">
                                <?php if($view == 'active'): ?>
                                    <?php if($current_admin['role'] == 'superadmin'): ?>
                                        <a href="?view=active&edit_id=<?= $booking['id'] ?>" 
                                        class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Edit</a>

                                        <a href="delete_booking.php?id=<?= $booking['id'] ?>" 
                                        class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600" 
                                        onclick="return confirm('Are you sure you want to archive this booking?')">Delete</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if($current_admin['role'] == 'superadmin'): ?>
                                        <a href="restore_booking.php?id=<?= $booking['id'] ?>" 
                                        class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600">Restore</a>
                                        <a href="permanent_delete_booking.php?id=<?= $booking['id'] ?>" 
                                        class="bg-red-700 text-white px-3 py-1 rounded hover:bg-red-800" 
                                        onclick="return confirm('Permanently delete this booking?')">Permanent Delete</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="p-3 text-center text-gray-500">No bookings found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
