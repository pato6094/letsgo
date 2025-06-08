<?php
include 'connessione.php';

if (!isset($_GET['date']) || !isset($_GET['time'])) {
    echo json_encode(['available' => false, 'message' => 'Parametri mancanti']);
    exit();
}

$date = $_GET['date'];
$time = $_GET['time'];

// Get day of week in Italian
$dayOfWeek = date('N', strtotime($date)); // 1 = Monday, 7 = Sunday
$dayNames = ['', 'lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'];

// Block Sundays (7) and Mondays (1)
if ($dayOfWeek == 7 || $dayOfWeek == 1) {
    echo json_encode(['available' => false, 'message' => 'Siamo chiusi la domenica e il lunedì']);
    exit();
}

$selectedDay = $dayNames[$dayOfWeek];

// First check if there's a specific date limit (has priority)
$specific_limit_query = $conn->prepare("SELECT limite_persone FROM limiti_date_specifiche WHERE data_specifica = ? AND orario = ? AND attivo = 1");
$specific_limit_query->bind_param("ss", $date, $time);
$specific_limit_query->execute();
$specific_limit_result = $specific_limit_query->get_result();

$limit = 1; // Default limit
$limit_type = "default";

if ($specific_limit_result->num_rows > 0) {
    // Use specific date limit
    $specific_limit_row = $specific_limit_result->fetch_assoc();
    $limit = $specific_limit_row['limite_persone'];
    $limit_type = "specific";
} else {
    // Check for general day limit
    $general_limit_query = $conn->prepare("SELECT limite_persone FROM limiti_orari WHERE giorno_settimana = ? AND orario = ? AND attivo = 1");
    $general_limit_query->bind_param("ss", $selectedDay, $time);
    $general_limit_query->execute();
    $general_limit_result = $general_limit_query->get_result();
    
    if ($general_limit_result->num_rows > 0) {
        $general_limit_row = $general_limit_result->fetch_assoc();
        $limit = $general_limit_row['limite_persone'];
        $limit_type = "general";
    }
    $general_limit_query->close();
}

// Count existing bookings for this date and time (exclude cancelled bookings)
$booking_query = $conn->prepare("SELECT COUNT(*) as count FROM prenotazioni WHERE data_prenotazione = ? AND orario = ? AND (stato IS NULL OR stato != 'Cancellata')");
$booking_query->bind_param("ss", $date, $time);
$booking_query->execute();
$booking_result = $booking_query->get_result();
$booking_row = $booking_result->fetch_assoc();
$current_bookings = $booking_row['count'];

$available = $current_bookings < $limit;
$remaining_spots = $limit - $current_bookings;

echo json_encode([
    'available' => $available,
    'limit' => $limit,
    'current_bookings' => $current_bookings,
    'remaining_spots' => $remaining_spots,
    'limit_type' => $limit_type,
    'message' => $available ? "Disponibile ($remaining_spots posti rimasti)" : "Slot completo ($current_bookings/$limit)"
]);

$specific_limit_query->close();
$booking_query->close();
$conn->close();
?>