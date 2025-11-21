<?php
require '../includes/db.php';

$email = "diveconnect23@gmail.com";
$password = password_hash("admin123", PASSWORD_DEFAULT);

$sql = "INSERT INTO admins (fullname, email, gcash_qr, gcash_amount, password, role, created_at, vat_percent)
        VALUES ('DiveConnect Admin', '$email', '', '0', '$password', 'admin', NOW(), '12')";

$conn->query($sql);

echo "Admin account added!";
