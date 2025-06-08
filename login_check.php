<?php
session_start();
include 'connessione.php';

$username = $_POST['username'];
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT password FROM admin WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($hashed_password);
if ($stmt->fetch() && password_verify($password, $hashed_password)) {
    $_SESSION['logged'] = true;
    header("Location: admin.php");
} else {
    echo "Credenziali errate.";
}
$stmt->close();
$conn->close();
?>
