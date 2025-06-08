<?php
session_start();
if (!isset($_SESSION['logged'])) {
    header("Location: login.php");
    exit();
}
include 'connessione.php';

// Create table for time slot limits if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS limiti_orari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    giorno_settimana ENUM('lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica') NOT NULL,
    orario TIME NOT NULL,
    limite_persone INT NOT NULL DEFAULT 1,
    attivo TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_slot (giorno_settimana, orario)
)");

// Create table for specific date limits
$conn->query("CREATE TABLE IF NOT EXISTS limiti_date_specifiche (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_specifica DATE NOT NULL,
    orario TIME NOT NULL,
    limite_persone INT NOT NULL DEFAULT 1,
    attivo TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_date_slot (data_specifica, orario)
)");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_limit'])) {
        $giorno = $_POST['giorno_settimana'];
        $orario = $_POST['orario'];
        $limite = intval($_POST['limite_persone']);
        
        if ($limite > 0) {
            $stmt = $conn->prepare("INSERT INTO limiti_orari (giorno_settimana, orario, limite_persone) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE limite_persone = ?, attivo = 1");
            $stmt->bind_param("ssii", $giorno, $orario, $limite, $limite);
            if ($stmt->execute()) {
                $success_message = "Limite orario aggiunto/aggiornato con successo.";
            } else {
                $error_message = "Errore nell'aggiunta del limite orario.";
            }
            $stmt->close();
        } else {
            $error_message = "Il limite deve essere maggiore di 0.";
        }
    }
    
    if (isset($_POST['add_specific_date_limit'])) {
        $data_specifica = $_POST['data_specifica'];
        $orario = $_POST['orario_specifico'];
        $limite = intval($_POST['limite_persone_specifico']);
        
        if ($limite > 0 && $data_specifica && $orario) {
            $stmt = $conn->prepare("INSERT INTO limiti_date_specifiche (data_specifica, orario, limite_persone) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE limite_persone = ?, attivo = 1");
            $stmt->bind_param("ssii", $data_specifica, $orario, $limite, $limite);
            if ($stmt->execute()) {
                $success_message = "Limite per data specifica aggiunto/aggiornato con successo.";
            } else {
                $error_message = "Errore nell'aggiunta del limite per data specifica.";
            }
            $stmt->close();
        } else {
            $error_message = "Tutti i campi sono obbligatori e il limite deve essere maggiore di 0.";
        }
    }
    
    if (isset($_POST['remove_limit'])) {
        $limit_id = intval($_POST['limit_id']);
        $stmt = $conn->prepare("DELETE FROM limiti_orari WHERE id = ?");
        $stmt->bind_param("i", $limit_id);
        if ($stmt->execute()) {
            $success_message = "Limite orario rimosso con successo.";
        } else {
            $error_message = "Errore nella rimozione del limite orario.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['remove_specific_limit'])) {
        $limit_id = intval($_POST['limit_id']);
        $stmt = $conn->prepare("DELETE FROM limiti_date_specifiche WHERE id = ?");
        $stmt->bind_param("i", $limit_id);
        if ($stmt->execute()) {
            $success_message = "Limite per data specifica rimosso con successo.";
        } else {
            $error_message = "Errore nella rimozione del limite per data specifica.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['toggle_limit'])) {
        $limit_id = intval($_POST['limit_id']);
        $stmt = $conn->prepare("UPDATE limiti_orari SET attivo = NOT attivo WHERE id = ?");
        $stmt->bind_param("i", $limit_id);
        if ($stmt->execute()) {
            $success_message = "Stato del limite orario aggiornato.";
        } else {
            $error_message = "Errore nell'aggiornamento dello stato.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['toggle_specific_limit'])) {
        $limit_id = intval($_POST['limit_id']);
        $stmt = $conn->prepare("UPDATE limiti_date_specifiche SET attivo = NOT attivo WHERE id = ?");
        $stmt->bind_param("i", $limit_id);
        if ($stmt->execute()) {
            $success_message = "Stato del limite per data specifica aggiornato.";
        } else {
            $error_message = "Errore nell'aggiornamento dello stato.";
        }
        $stmt->close();
    }
}

// Get all time limits
$limiti_query = $conn->query("SELECT * FROM limiti_orari ORDER BY 
    FIELD(giorno_settimana, 'lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'), 
    orario");

// Get all specific date limits
$limiti_specifici_query = $conn->query("SELECT * FROM limiti_date_specifiche ORDER BY data_specifica DESC, orario");

// Get booking statistics by time slot
$stats_query = $conn->query("
    SELECT 
        DAYNAME(data_prenotazione) as giorno,
        data_prenotazione,
        orario,
        COUNT(*) as prenotazioni_totali,
        SUM(CASE WHEN stato = 'Confermata' THEN 1 ELSE 0 END) as prenotazioni_confermate
    FROM prenotazioni 
    WHERE data_prenotazione >= CURDATE() - INTERVAL 30 DAY
    GROUP BY data_prenotazione, orario
    ORDER BY data_prenotazione DESC, orario
");

$giorni_italiani = [
    'Monday' => 'lunedi',
    'Tuesday' => 'martedi', 
    'Wednesday' => 'mercoledi',
    'Thursday' => 'giovedi',
    'Friday' => 'venerdi',
    'Saturday' => 'sabato',
    'Sunday' => 'domenica'
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Gestione Prenotazioni - Old School Barber</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: #ffffff;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 80px;
            height: 100vh;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar.expanded {
            width: 280px;
        }

        .sidebar-header {
            padding: 0 1.5rem;
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            justify-content: center;
        }

        .sidebar.expanded .sidebar-header {
            padding: 0 2rem;
            justify-content: flex-start;
        }

        .sidebar-logo {
            font-size: 2rem;
            color: #d4af37;
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            transition: opacity 0.3s ease;
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar.expanded .sidebar-title {
            opacity: 1;
            width: auto;
        }

        .sidebar-toggle {
            position: absolute;
            top: 1rem;
            right: -15px;
            width: 30px;
            height: 30px;
            background: #d4af37;
            border: none;
            border-radius: 50%;
            color: #1a1a2e;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: #ffd700;
            transform: scale(1.1);
        }

        .sidebar-nav {
            list-style: none;
            padding: 0 1rem;
        }

        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0.5rem;
            color: #a0a0a0;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
            justify-content: center;
        }

        .sidebar.expanded .sidebar-nav a {
            padding: 1rem;
            justify-content: flex-start;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37;
            transform: translateX(5px);
        }

        .sidebar-nav i {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-nav span {
            display: none;
        }

        .sidebar.expanded .sidebar-nav span {
            display: inline;
        }

        /* Main Content */
        .main {
            margin-left: 80px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .main.expanded {
            margin-left: 280px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem 2rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ffffff;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37;
            text-decoration: none;
            border-radius: 12px;
            border: 1px solid rgba(212, 175, 55, 0.2);
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(212, 175, 55, 0.2);
            transform: translateY(-2px);
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .content-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-card h3 i {
            color: #d4af37;
        }

        /* Form Styles */
        .form-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-card.success {
            border-color: rgba(34, 197, 94, 0.3);
            background: rgba(34, 197, 94, 0.05);
        }

        .form-card.danger {
            border-color: rgba(239, 68, 68, 0.3);
            background: rgba(239, 68, 68, 0.05);
        }

        .form-card.warning {
            border-color: rgba(251, 191, 36, 0.3);
            background: rgba(251, 191, 36, 0.05);
        }

        .form-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-card.success h3 {
            color: #4ade80;
        }

        .form-card.danger h3 {
            color: #f87171;
        }

        .form-card.warning h3 {
            color: #fbbf24;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #e0e0e0;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #d4af37;
            background: rgba(255, 255, 255, 0.12);
        }

        .form-group input::placeholder {
            color: #a0a0a0;
        }

        .form-group select option {
            background: #1a1a2e;
            color: #ffffff;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .submit-btn.success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }

        .submit-btn.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .submit-btn.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.02);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 1rem 0.8rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            white-space: nowrap;
        }

        th {
            background: rgba(255, 255, 255, 0.05);
            font-weight: 600;
            color: #d4af37;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            color: #e0e0e0;
            font-weight: 400;
            font-size: 0.9rem;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Status Badges */
        .status {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .status.attivo {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .status.inattivo {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin: 0 0.2rem;
        }

        .action-btn.toggle {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .action-btn.delete {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Messages */
        .error-message, .success-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        /* Info Box */
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-box h4 {
            color: #60a5fa;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box p {
            color: #a0a0a0;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
                padding: 1rem;
            }

            .main.expanded {
                margin-left: 0;
            }

            .header {
                padding: 1rem;
            }

            .header-title {
                font-size: 1.4rem;
            }

            .form-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-chevron-right"></i>
    </button>
    
    <div class="sidebar-header">
        <i class="fas fa-cut sidebar-logo"></i>
        <span class="sidebar-title">Admin Panel</span>
    </div>
    
    <ul class="sidebar-nav">
        <li><a href="admin.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
        <li><a href="#" class="active"><i class="fas fa-calendar-alt"></i><span>Prenotazioni</span></a></li>
        <li><a href="#"><i class="fas fa-scissors"></i><span>Servizi</span></a></li>
        <li><a href="#"><i class="fas fa-chart-line"></i><span>Report</span></a></li>
        <li><a href="#"><i class="fas fa-cog"></i><span>Impostazioni</span></a></li>
        <li><a href="index.php"><i class="fas fa-arrow-left"></i><span>Torna al sito</span></a></li>
    </ul>
</div>

<div class="main" id="main">
    <div class="header">
        <h1 class="header-title">Gestione Prenotazioni</h1>
        <a href="admin.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Torna al Dashboard
        </a>
    </div>

    <div class="info-box">
        <h4><i class="fas fa-info-circle"></i>Informazioni Importanti</h4>
        <p>
            <strong>Domenica e Lunedì:</strong> Le prenotazioni per domenica e lunedì sono automaticamente disabilitate.<br>
            <strong>Limiti Generali:</strong> Puoi impostare il numero massimo di persone per ogni fascia oraria e giorno della settimana.<br>
            <strong>Limiti Specifici:</strong> Puoi impostare limiti per date specifiche che hanno priorità sui limiti generali.
        </p>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="error-message"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="form-section">
        <div class="form-card success">
            <h3><i class="fas fa-plus-circle"></i>Aggiungi Limite Orario Generale</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="giorno_settimana">Giorno della Settimana</label>
                    <select name="giorno_settimana" required>
                        <option value="">Seleziona giorno</option>
                        <option value="martedi">Martedì</option>
                        <option value="mercoledi">Mercoledì</option>
                        <option value="giovedi">Giovedì</option>
                        <option value="venerdi">Venerdì</option>
                        <option value="sabato">Sabato</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="orario">Orario</label>
                    <select name="orario" required>
                        <option value="">Seleziona orario</option>
                        <?php
                        for ($hour = 9; $hour < 19; $hour++) {
                            for ($min = 0; $min < 60; $min += 30) {
                                $time = sprintf("%02d:%02d", $hour, $min);
                                echo "<option value='$time'>$time</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="limite_persone">Limite Persone</label>
                    <input type="number" name="limite_persone" min="1" max="10" placeholder="Es. 3" required>
                </div>
                <button type="submit" name="add_limit" class="submit-btn success">
                    <i class="fas fa-plus"></i> Aggiungi Limite Generale
                </button>
            </form>
        </div>

        <div class="form-card warning">
            <h3><i class="fas fa-calendar-day"></i>Aggiungi Limite per Data Specifica</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="data_specifica">Data Specifica</label>
                    <input type="date" name="data_specifica" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="orario_specifico">Orario</label>
                    <select name="orario_specifico" required>
                        <option value="">Seleziona orario</option>
                        <?php
                        for ($hour = 9; $hour < 19; $hour++) {
                            for ($min = 0; $min < 60; $min += 30) {
                                $time = sprintf("%02d:%02d", $hour, $min);
                                echo "<option value='$time'>$time</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="limite_persone_specifico">Limite Persone</label>
                    <input type="number" name="limite_persone_specifico" min="1" max="10" placeholder="Es. 2" required>
                </div>
                <button type="submit" name="add_specific_date_limit" class="submit-btn warning">
                    <i class="fas fa-plus"></i> Aggiungi Limite Specifico
                </button>
            </form>
        </div>
    </div>

    <div class="content-grid">
        <div class="content-card">
            <h3><i class="fas fa-clock"></i>Limiti Orari Generali</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Giorno</th>
                            <th>Orario</th>
                            <th>Limite</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($limiti_query && $limiti_query->num_rows > 0): ?>
                            <?php while ($row = $limiti_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo ucfirst($row['giorno_settimana']); ?></td>
                                <td><?php echo date('H:i', strtotime($row['orario'])); ?></td>
                                <td><?php echo $row['limite_persone']; ?> persone</td>
                                <td>
                                    <span class="status <?php echo $row['attivo'] ? 'attivo' : 'inattivo'; ?>">
                                        <?php echo $row['attivo'] ? 'Attivo' : 'Inattivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="limit_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="toggle_limit" class="action-btn toggle" 
                                                onclick="return confirm('Cambiare lo stato di questo limite?')">
                                            <i class="fas fa-toggle-on"></i>
                                            <?php echo $row['attivo'] ? 'Disattiva' : 'Attiva'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="limit_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="remove_limit" class="action-btn delete" 
                                                onclick="return confirm('Eliminare questo limite orario?')">
                                            <i class="fas fa-trash"></i>Elimina
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #a0a0a0;">
                                    Nessun limite orario generale configurato
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <div class="content-card">
            <h3><i class="fas fa-calendar-day"></i>Limiti per Date Specifiche</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Orario</th>
                            <th>Limite</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($limiti_specifici_query && $limiti_specifici_query->num_rows > 0): ?>
                            <?php while ($row = $limiti_specifici_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($row['data_specifica'])); ?></td>
                                <td><?php echo date('H:i', strtotime($row['orario'])); ?></td>
                                <td><?php echo $row['limite_persone']; ?> persone</td>
                                <td>
                                    <span class="status <?php echo $row['attivo'] ? 'attivo' : 'inattivo'; ?>">
                                        <?php echo $row['attivo'] ? 'Attivo' : 'Inattivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="limit_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="toggle_specific_limit" class="action-btn toggle" 
                                                onclick="return confirm('Cambiare lo stato di questo limite?')">
                                            <i class="fas fa-toggle-on"></i>
                                            <?php echo $row['attivo'] ? 'Disattiva' : 'Attiva'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="limit_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="remove_specific_limit" class="action-btn delete" 
                                                onclick="return confirm('Eliminare questo limite per data specifica?')">
                                            <i class="fas fa-trash"></i>Elimina
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #a0a0a0;">
                                    Nessun limite per date specifiche configurato
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <div class="content-card">
            <h3><i class="fas fa-chart-bar"></i>Statistiche Prenotazioni (Ultimi 30 giorni)</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Giorno</th>
                            <th>Orario</th>
                            <th>Totali</th>
                            <th>Confermate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($stats_query && $stats_query->num_rows > 0): ?>
                            <?php while ($row = $stats_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($row['data_prenotazione'])); ?></td>
                                <td><?php echo $giorni_italiani[$row['giorno']] ?? $row['giorno']; ?></td>
                                <td><?php echo date('H:i', strtotime($row['orario'])); ?></td>
                                <td><?php echo $row['prenotazioni_totali']; ?></td>
                                <td><?php echo $row['prenotazioni_confermate']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #a0a0a0;">
                                    Nessuna statistica disponibile
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let sidebarCollapsed = true;
let mobileOpen = false;

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('main');
    const toggleIcon = document.querySelector('.sidebar-toggle i');
    const isMobile = window.innerWidth <= 768;

    if (isMobile) {
        mobileOpen = !mobileOpen;
        sidebar.classList.toggle('mobile-open');
    } else {
        sidebarCollapsed = !sidebarCollapsed;
        sidebar.classList.toggle('expanded');
        main.classList.toggle('expanded');
        toggleIcon.className = sidebarCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
    }
}

// Close mobile sidebar when clicking outside
document.addEventListener('click', (e) => {
    const sidebar = document.getElementById('sidebar');
    
    if (window.innerWidth <= 768 && mobileOpen && 
        !sidebar.contains(e.target)) {
        toggleSidebar();
    }
});

// Handle window resize
window.addEventListener('resize', () => {
    const sidebar = document.getElementById('sidebar');
    
    if (window.innerWidth > 768) {
        sidebar.classList.remove('mobile-open');
        mobileOpen = false;
    }
});
</script>
</body>
</html>