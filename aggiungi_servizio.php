<?php
session_start();
if (!isset($_SESSION['logged'])) {
    header("Location: login.php");
    exit();
}
include 'connessione.php';

$nome = $_POST['nome_servizio'];
$prezzo = $_POST['prezzo'];
$stmt = $conn->prepare("INSERT INTO servizi (nome, prezzo) VALUES (?, ?)");
$stmt->bind_param("ss", $nome, $prezzo);
$stmt->execute();
$stmt->close();
$conn->close();
header("Location: admin.php");
?>
