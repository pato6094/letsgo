<?php
$host = "localhost";
$db = "verifica_barber";
$user = "verifica_admin";
$pass = "pianeta123!";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}
?>