<?php
$host = "localhost";  // Change if your database is remote
$user = "root";       // Your MySQL username
$pass = "";           // Your MySQL password
$dbname = "er_diagrams";  // Your database name

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
