<?php
// Copy this file to connection.php and fill in your own credentials
$conn = new mysqli("localhost", "your_username", "your_password", "tgj");
if ($conn->connect_error) die("Verbinding mislukt: " . $conn->connect_error);
$conn->set_charset("utf8mb4");
?>
