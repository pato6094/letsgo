<?php
include 'connessione.php';

if (!isset($_GET['data'])) {
    echo json_encode(['data_formattata' => '', 'ricavo' => 0]);
    exit();
}

$data = $conn->real_escape_string($_GET['data']);

// Cerca il ricavo in storico_ricavi
$query = $conn->query("SELECT ricavo FROM storico_ricavi WHERE data = '$data'");

if ($query->num_rows > 0) {
    $row = $query->fetch_assoc();
    $ricavo = floatval($row['ricavo']);
} else {
    // Se non c'è nello storico, cerca nelle prenotazioni confermate attuali
    $query = $conn->query("
        SELECT SUM(s.prezzo) as totale
        FROM prenotazioni p 
        JOIN servizi s ON p.servizio = s.nome
        WHERE p.data_prenotazione = '$data' AND p.stato = 'Confermata'
    ");
    $row = $query->fetch_assoc();
    $ricavo = floatval($row['totale'] ?? 0);
}

echo json_encode([
    'data_formattata' => date('d/m/Y', strtotime($data)),
    'ricavo' => $ricavo
]);
?>
