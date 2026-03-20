<?php
$conn = new mysqli("localhost", "root", "", "tgj");
if ($conn->connect_error) die("Verbinding mislukt: " . $conn->connect_error);
$conn->set_charset("utf8mb4");
?>
