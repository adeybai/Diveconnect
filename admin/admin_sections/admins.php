<?php
if(!isset($conn)){
    include("../includes/db.php");
}

// ✅ Kunin logged in admin info para ma-check role
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

// Fetch admins based on view
if($view == 'archive'){
    $result = $conn->query("SELECT * FROM admins_archive ORDER BY admin_id ASC");
} else {
    $result = $conn->query("SELECT * FROM admins ORDER BY admin_id ASC");
}

// Handle update submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin_id'])){
    $admin_id = intval($_POST['edit_admin_id']);
    $email = $_POST['email'];
    $password = $_POST['password'];
    $edited_at = date('Y-m-d H:i:s'); // timestamp for edit

    // Build dynamic SQL depending on what was changed
    if(!empty($password)){
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET email = ?, password = ?, edited_at = ? WHERE admin_id = ?");
        $stmt->bind_param("sssi", $email, $hashedPassword, $edited_at, $admin_id);
    } else {
        $stmt = $conn->prepare("UPDATE admins SET email = ?, edited_at = ? WHERE admin_id = ?");
        $stmt->bind_param("ssi", $email, $edited_at, $admin_id);
    }
    $stmt->execute();
    header("Location: admin_dashboard.php?section=admins&view=active");
    exit;
}
?>

<!-- Tabs
<div class="mb-4 flex space-x-2">
    <a href="?section=admins&view=active" class="px-4 py-2 <?= ($view=='active') ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> rounded">Active</a>
    <a href="?section=admins&view=archive" class="px-4 py-2 <?= ($view=='archive') ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> rounded">Archived</a>
</div>-->

<div class="bg-white p-6 rounded-lg shadow">
    <h3 class="text-xl font-semibold text-gray-700 mb-4">Manage Admins</h3>

    <table class="w-full border-collapse">
        <thead>
            <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600">
                <th class="p-3">ID</th>
                <th class="p-3">Email</th>
                <th class="p-3">Created At</th>
                <th class="p-3">Edited At</th>
                <th class="p-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="text-sm">
            <?php if($result->num_rows > 0): ?>
                <?php while($admin = $result->fetch_assoc()): ?>
                    <?php if($edit_id == $admin['admin_id'] && $view=='active'): ?>
                        <!-- Inline Edit Form -->
                        <tr class="border-b bg-yellow-50">
                            <form method="POST">
                                <td class="p-3"><?= $admin['admin_id'] ?><input type="hidden" name="edit_admin_id" value="<?= $admin['admin_id'] ?>"></td>
                                <td class="p-3"><input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" class="border p-1 rounded w-full" required></td>
                                <td class="p-3"><?= $admin['created_at'] ?></td>
                                <td class="p-3"><?= $admin['edited_at'] ?? '-' ?></td>
                                <td class="p-3 text-center space-y-1">
                                    <input type="password" name="password" placeholder="New password (optional)" class="border p-1 rounded w-full">
                                    <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 w-full">Save</button>
                                    <a href="?section=admins&view=active" class="bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500 w-full block text-center">Cancel</a>
                                </td>
                            </form>
                        </tr>
                    <?php else: ?>
                            <tr class="border-b">
                            <td class="p-3"><?= $admin['admin_id'] ?></td>
                            <td class="p-3"><?= htmlspecialchars($admin['email']) ?></td>
                            <td class="p-3"><?= $admin['created_at'] ?></td>
                            <td class="p-3"><?= $admin['edited_at'] ?? '-' ?></td>
                            <td class="p-3 text-center space-x-2">
                            <?php if($view == 'active'): ?>

                                <?php if($current_admin['role'] == 'super'): ?>
                                    <!-- Super admin can edit, delete anyone -->
                                    <a href="?section=admins&view=active&edit_id=<?= $admin['admin_id'] ?>" 
                                    class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Edit</a>

                                    <?php if($current_admin['admin_id'] != $admin['admin_id']): ?>
                                        <a href="delete_admin.php?id=<?= $admin['admin_id'] ?>" 
                                        class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600" 
                                        onclick="return confirm('Are you sure?')">Delete</a>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <!-- Normal admin can only edit their own account -->
                                    <?php if($current_admin['admin_id'] == $admin['admin_id']): ?>
                                        <a href="?section=admins&view=active&edit_id=<?= $admin['admin_id'] ?>" 
                                        class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Edit</a>
                                    <?php endif; ?>
                                <?php endif; ?>

                            <?php else: ?>
                                <?php if($current_admin['role'] == 'super'): ?>
                                    <a href="restore_admin.php?id=<?= $admin['admin_id'] ?>" 
                                    class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600">Restore</a>

                                    <?php if($current_admin['admin_id'] != $admin['admin_id']): ?>
                                        <a href="permanent_delete_admin.php?id=<?= $admin['admin_id'] ?>" 
                                        class="bg-red-700 text-white px-3 py-1 rounded hover:bg-red-800" 
                                        onclick="return confirm('Permanently delete this admin?')">Permanent Delete</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        </tr>

                    <?php endif; ?>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="p-3 text-center text-gray-500">No admins found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if($view == 'active' && $current_admin['role'] == 'super'): ?>
    <div class="mt-4">
        <a href="add_admin.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">➕ Add New Admin</a>
    </div>
<?php endif; ?>

</div>
