<?php
$host = "localhost";
$user = "u913730501_diveconnect";
$pass = "Diveconnect2025";
$dbname = "u913730501_diveconnect";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
} else {
    echo "✅ Database connected successfully!";
}
?>
