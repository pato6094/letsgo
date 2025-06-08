<?php
session_start();
if (!isset($_SESSION['logged'])) {
    header("Location: login.php");
    exit();
}
include 'connessione.php';

// Create operators table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS operatori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cognome VARCHAR(255) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(255),
    specialita TEXT,
    attivo TINYINT(1) DEFAULT 1,
    data_inserimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_operator'])) {
        $nome = trim($_POST['nome']);
        $cognome = trim($_POST['cognome']);
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']);
        $specialita = trim($_POST['specialita']);
        
        if ($nome && $cognome) {
            $stmt = $conn->prepare("INSERT INTO operatori (nome, cognome, telefono, email, specialita) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nome, $cognome, $telefono, $email, $specialita);
            if ($stmt->execute()) {
                $success_message = "Operatore aggiunto con successo.";
            } else {
                $error_message = "Errore nell'aggiunta dell'operatore.";
            }
            $stmt->close();
        } else {
            $error_message = "Nome e cognome sono obbligatori.";
        }
    }
    
    if (isset($_POST['toggle_operator'])) {
        $operator_id = intval($_POST['operator_id']);
        $stmt = $conn->prepare("UPDATE operatori SET attivo = NOT attivo WHERE id = ?");
        $stmt->bind_param("i", $operator_id);
        if ($stmt->execute()) {
            $success_message = "Stato dell'operatore aggiornato.";
        } else {
            $error_message = "Errore nell'aggiornamento dello stato.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['remove_operator'])) {
        $operator_id = intval($_POST['operator_id']);
        
        // Check if operator has bookings
        $check_bookings = $conn->prepare("SELECT COUNT(*) as count FROM prenotazioni WHERE operatore_id = ?");
        $check_bookings->bind_param("i", $operator_id);
        $check_bookings->execute();
        $booking_result = $check_bookings->get_result();
        $booking_count = $booking_result->fetch_assoc()['count'];
        
        if ($booking_count > 0) {
            $error_message = "Impossibile eliminare l'operatore perché ha $booking_count prenotazione/i associate.";
        } else {
            $stmt = $conn->prepare("DELETE FROM operatori WHERE id = ?");
            $stmt->bind_param("i", $operator_id);
            if ($stmt->execute()) {
                $success_message = "Operatore eliminato con successo.";
            } else {
                $error_message = "Errore nell'eliminazione dell'operatore.";
            }
            $stmt->close();
        }
        $check_bookings->close();
    }
    
    if (isset($_POST['update_operator'])) {
        $operator_id = intval($_POST['operator_id']);
        $nome = trim($_POST['edit_nome']);
        $cognome = trim($_POST['edit_cognome']);
        $telefono = trim($_POST['edit_telefono']);
        $email = trim($_POST['edit_email']);
        $specialita = trim($_POST['edit_specialita']);
        
        if ($nome && $cognome) {
            $stmt = $conn->prepare("UPDATE operatori SET nome = ?, cognome = ?, telefono = ?, email = ?, specialita = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $nome, $cognome, $telefono, $email, $specialita, $operator_id);
            if ($stmt->execute()) {
                $success_message = "Operatore aggiornato con successo.";
            } else {
                $error_message = "Errore nell'aggiornamento dell'operatore.";
            }
            $stmt->close();
        } else {
            $error_message = "Nome e cognome sono obbligatori.";
        }
    }
}

// Get all operators
$operators_query = $conn->query("SELECT * FROM operatori ORDER BY cognome, nome");

// Get operator statistics
$stats_query = $conn->query("
    SELECT 
        o.id,
        o.nome,
        o.cognome,
        COUNT(p.id) as total_bookings,
        SUM(CASE WHEN p.stato = 'Confermata' THEN 1 ELSE 0 END) as confirmed_bookings
    FROM operatori o
    LEFT JOIN prenotazioni p ON o.id = p.operatore_id
    WHERE o.attivo = 1
    GROUP BY o.id, o.nome, o.cognome
    ORDER BY total_bookings DESC
");
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Gestione Operatori - Old School Barber</title>
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

        .form-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #4ade80;
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

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #d4af37;
            background: rgba(255, 255, 255, 0.12);
        }

        .form-group input::placeholder, .form-group textarea::placeholder {
            color: #a0a0a0;
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
            background: linear-gradient(135deg, #22c55e, #16a34a);
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
            min-width: 800px;
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

        .action-btn.edit {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(20px);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            color: #a0a0a0;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #ffffff;
        }

        /* Responsive Design */
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
        <li><a href="gestione_prenotazioni.php"><i class="fas fa-calendar-alt"></i><span>Prenotazioni</span></a></li>
        <li><a href="#" class="active"><i class="fas fa-scissors"></i><span>Operatori</span></a></li>
        <li><a href="#"><i class="fas fa-chart-line"></i><span>Report</span></a></li>
        <li><a href="#"><i class="fas fa-cog"></i><span>Impostazioni</span></a></li>
        <li><a href="index.php"><i class="fas fa-arrow-left"></i><span>Torna al sito</span></a></li>
    </ul>
</div>

<div class="main" id="main">
    <div class="header">
        <h1 class="header-title">Gestione Operatori</h1>
        <a href="admin.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Torna al Dashboard
        </a>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="success-message"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="error-message"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="form-section">
        <div class="form-card success">
            <h3><i class="fas fa-user-plus"></i>Aggiungi Nuovo Operatore</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="nome">Nome *</label>
                    <input type="text" name="nome" placeholder="Nome" required>
                </div>
                <div class="form-group">
                    <label for="cognome">Cognome *</label>
                    <input type="text" name="cognome" placeholder="Cognome" required>
                </div>
                <div class="form-group">
                    <label for="telefono">Telefono</label>
                    <input type="tel" name="telefono" placeholder="+39 123 456 7890">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" placeholder="email@esempio.com">
                </div>
                <div class="form-group">
                    <label for="specialita">Specialità</label>
                    <textarea name="specialita" placeholder="Es. Taglio classico, barba, trattamenti..."></textarea>
                </div>
                <button type="submit" name="add_operator" class="submit-btn">
                    <i class="fas fa-plus"></i> Aggiungi Operatore
                </button>
            </form>
        </div>
    </div>

    <div class="content-grid">
        <div class="content-card">
            <h3><i class="fas fa-users"></i>Lista Operatori</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Cognome</th>
                            <th>Telefono</th>
                            <th>Email</th>
                            <th>Specialità</th>
                            <th>Stato</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($operators_query && $operators_query->num_rows > 0): ?>
                            <?php while ($row = $operators_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td><?php echo htmlspecialchars($row['cognome']); ?></td>
                                <td><?php echo htmlspecialchars($row['telefono'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars(substr($row['specialita'] ?? '-', 0, 30)) . (strlen($row['specialita'] ?? '') > 30 ? '...' : ''); ?></td>
                                <td>
                                    <span class="status <?php echo $row['attivo'] ? 'attivo' : 'inattivo'; ?>">
                                        <?php echo $row['attivo'] ? 'Attivo' : 'Inattivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="action-btn edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                        <i class="fas fa-edit"></i>Modifica
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="operator_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="toggle_operator" class="action-btn toggle" 
                                                onclick="return confirm('Cambiare lo stato di questo operatore?')">
                                            <i class="fas fa-toggle-on"></i>
                                            <?php echo $row['attivo'] ? 'Disattiva' : 'Attiva'; ?>
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="operator_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="remove_operator" class="action-btn delete" 
                                                onclick="return confirm('Eliminare questo operatore? Questa azione non può essere annullata.')">
                                            <i class="fas fa-trash"></i>Elimina
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #a0a0a0;">
                                    Nessun operatore trovato
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
            <h3><i class="fas fa-chart-bar"></i>Statistiche Operatori</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Operatore</th>
                            <th>Prenotazioni Totali</th>
                            <th>Prenotazioni Confermate</th>
                            <th>Tasso Conferma</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($stats_query && $stats_query->num_rows > 0): ?>
                            <?php while ($row = $stats_query->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nome'] . ' ' . $row['cognome']); ?></td>
                                <td><?php echo $row['total_bookings']; ?></td>
                                <td><?php echo $row['confirmed_bookings']; ?></td>
                                <td>
                                    <?php 
                                    $rate = $row['total_bookings'] > 0 ? round(($row['confirmed_bookings'] / $row['total_bookings']) * 100, 1) : 0;
                                    echo $rate . '%';
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #a0a0a0;">
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

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h3><i class="fas fa-edit"></i> Modifica Operatore</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="operator_id" id="edit_operator_id">
            <div class="form-group">
                <label for="edit_nome">Nome *</label>
                <input type="text" name="edit_nome" id="edit_nome" required>
            </div>
            <div class="form-group">
                <label for="edit_cognome">Cognome *</label>
                <input type="text" name="edit_cognome" id="edit_cognome" required>
            </div>
            <div class="form-group">
                <label for="edit_telefono">Telefono</label>
                <input type="tel" name="edit_telefono" id="edit_telefono">
            </div>
            <div class="form-group">
                <label for="edit_email">Email</label>
                <input type="email" name="edit_email" id="edit_email">
            </div>
            <div class="form-group">
                <label for="edit_specialita">Specialità</label>
                <textarea name="edit_specialita" id="edit_specialita"></textarea>
            </div>
            <button type="submit" name="update_operator" class="submit-btn">
                <i class="fas fa-save"></i> Salva Modifiche
            </button>
        </form>
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

function openEditModal(operator) {
    document.getElementById('edit_operator_id').value = operator.id;
    document.getElementById('edit_nome').value = operator.nome;
    document.getElementById('edit_cognome').value = operator.cognome;
    document.getElementById('edit_telefono').value = operator.telefono || '';
    document.getElementById('edit_email').value = operator.email || '';
    document.getElementById('edit_specialita').value = operator.specialita || '';
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
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