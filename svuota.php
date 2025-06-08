<?php
session_start();
if (!isset($_SESSION['logged'])) {
    header("Location: login.php");
    exit();
}
include 'connessione.php';
$conn->query("DELETE FROM prenotazioni");
$conn->close();
header("Location: admin.php");
?>