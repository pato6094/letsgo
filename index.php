<?php
date_default_timezone_set('Europe/Rome');
include 'connessione.php';

// Check if time limits table exists, if not create it
$conn->query("CREATE TABLE IF NOT EXISTS limiti_orari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    giorno_settimana ENUM('lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica') NOT NULL,
    orario TIME NOT NULL,
    limite_persone INT NOT NULL DEFAULT 1,
    attivo TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_slot (giorno_settimana, orario)
)");

// Check if specific date limits table exists, if not create it
$conn->query("CREATE TABLE IF NOT EXISTS limiti_date_specifiche (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_specifica DATE NOT NULL,
    orario TIME NOT NULL,
    limite_persone INT NOT NULL DEFAULT 1,
    attivo TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_date_slot (data_specifica, orario)
)");
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Old School Barber - Prenota il tuo taglio</title>
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
            min-height: 100vh;
            color: #ffd700;
            overflow-x: hidden;
        }

        /* Background Animation */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.1;
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Header */
        .header {
            text-align: center;
            padding: 2rem 1rem;
            margin-bottom: 2rem;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .logo i {
            font-size: 3rem;
            color: #d4af37;
            filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.3));
        }

        .logo h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #d4af37 0%, #ffd700 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 30px rgba(212, 175, 55, 0.5);
        }

        .tagline {
            font-size: 1.1rem;
            color: #a0a0a0;
            font-weight: 300;
            margin-top: 0.5rem;
        }

        /* Main Container */
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 200px);
            padding: 1rem;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.5), transparent);
        }

        .form-container:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 35px 70px -12px rgba(0, 0, 0, 0.35),
                0 0 0 1px rgba(255, 255, 255, 0.1);
        }

        .form-title {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .form-title h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .form-title p {
            color: #a0a0a0;
            font-size: 0.95rem;
            font-weight: 400;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #e0e0e0;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0a0a0;
            font-size: 1rem;
            z-index: 2;
        }

        input, select {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            color: #ffd700;
            font-size: 1rem;
            font-weight: 400;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        input::placeholder {
            color: #a0a0a0;
            font-weight: 400;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #d4af37;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 
                0 0 0 3px rgba(212, 175, 55, 0.1),
                0 8px 25px -8px rgba(212, 175, 55, 0.2);
            transform: translateY(-1px);
        }

        select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2214%22%20height%3D%2210%22%20viewBox%3D%220%200%2014%2010%22%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%3E%3Cpath%20d%3D%22M1%200l6%206%206-6%22%20stroke%3D%22%23d4af37%22%20stroke-width%3D%222%22%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 14px 10px;
            cursor: pointer;
        }

        select option {
            background: #1a1a2e;
            color: #ffffff;
            padding: 0.5rem;
        }

        /* Availability indicator */
        .availability-indicator {
            position: absolute;
            right: 3rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            z-index: 3;
        }

        .availability-indicator.available {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .availability-indicator.unavailable {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .availability-indicator.checking {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1.2rem 2rem;
            background: linear-gradient(135deg, #d4af37 0%, #ffd700 100%);
            border: none;
            border-radius: 12px;
            color: #1a1a2e;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 1rem;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px -5px rgba(212, 175, 55, 0.4);
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: #a0a0a0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Admin Link */
        .admin-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-link a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-link a:hover {
            color: #ffd700;
            transform: translateX(5px);
        }

        /* Services Preview */
        .services-preview {
            margin-top: 3rem;
            text-align: center;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 1rem;
        }

        .service-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(212, 175, 55, 0.3);
        }

        .service-card i {
            font-size: 2rem;
            color: #d4af37;
            margin-bottom: 1rem;
        }

        .service-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #ffffff;
        }

        .service-card p {
            color: #a0a0a0;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 1.5rem 1rem;
            }

            .logo h1 {
                font-size: 2rem;
            }

            .logo i {
                font-size: 2.5rem;
            }

            .form-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
                border-radius: 20px;
            }

            .form-title h2 {
                font-size: 1.5rem;
            }

            input, select {
                padding: 0.9rem 0.9rem 0.9rem 2.8rem;
                font-size: 0.95rem;
            }

            .submit-btn {
                padding: 1rem 1.5rem;
                font-size: 1rem;
            }

            .services-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .logo {
                flex-direction: column;
                gap: 0.5rem;
            }

            .form-container {
                padding: 1.5rem 1rem;
            }

            .tagline {
                font-size: 1rem;
            }
        }

        /* Loading Animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(212, 175, 55, 0.3);
            border-top: 3px solid #d4af37;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="bg-animation"></div>
    
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <header class="header">
        <div class="logo">
            <i class="fas fa-cut"></i>
            <h1>Old School Barber</h1>
        </div>
        <p class="tagline">Tradizione, stile e passione dal 1985</p>
    </header>

    <div class="container">
        <div class="form-container">
            <div class="form-title">
                <h2>Prenota il tuo taglio</h2>
                <p>Scegli il servizio perfetto per te</p>
            </div>

            <form method="POST" action="prenota.php" id="bookingForm">
                <div class="form-group">
                    <label for="nome">Nome completo</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="nome" name="nome" placeholder="Inserisci il tuo nome" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="la-tua-email@esempio.com">
                    </div>
                </div>

                <div class="form-group">
                    <label for="telefono">Telefono</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="telefono" name="telefono" placeholder="+39 123 456 7890">
                    </div>
                </div>

                <div class="form-group">
                    <label for="servizio">Servizio</label>
                    <div class="input-wrapper">
                        <i class="fas fa-scissors"></i>
                        <select id="servizio" name="servizio" required>
                            <option value="" disabled selected>Seleziona il servizio</option>
                            <?php
                            $query = $conn->query("SELECT nome, prezzo FROM servizi");
                            while ($row = $query->fetch_assoc()) {
                                echo "<option value='{$row['nome']}'>{$row['nome']} - €{$row['prezzo']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="data_prenotazione">Data</label>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" id="data_prenotazione" name="data_prenotazione" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="orario">Orario</label>
                    <div class="input-wrapper">
                        <i class="fas fa-clock"></i>
                        <select id="orario" name="orario" required>
                            <option value="" disabled selected>Seleziona l'orario</option>
                        </select>
                        <div class="availability-indicator" id="availabilityIndicator" style="display: none;"></div>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-calendar-check"></i>
                    Prenota ora
                </button>
            </form>

            <div class="admin-link">
                <a href="login.php">
                    <i class="fas fa-user-shield"></i>
                    Area Amministratore
                </a>
            </div>
        </div>
    </div>

    <div class="services-preview">
        <div class="services-grid">
            <div class="service-card">
                <i class="fas fa-cut"></i>
                <h3>Taglio Classico</h3>
                <p>Il nostro taglio tradizionale con forbici e rasoio</p>
            </div>
            <div class="service-card">
                <i class="fas fa-user-tie"></i>
                <h3>Taglio & Barba</h3>
                <p>Servizio completo per un look impeccabile</p>
            </div>
            <div class="service-card">
                <i class="fas fa-spa"></i>
                <h3>Trattamenti</h3>
                <p>Cura e benessere per i tuoi capelli</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dateInput = document.getElementById('data_prenotazione');
            const timeSelect = document.getElementById('orario');
            const form = document.getElementById('bookingForm');
            const loading = document.getElementById('loading');
            const submitBtn = document.getElementById('submitBtn');
            const availabilityIndicator = document.getElementById('availabilityIndicator');

            // Set minimum date to today
            const today = new Date();
            const day = String(today.getDate()).padStart(2, '0');
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const year = today.getFullYear();
            const todayLocal = `${year}-${month}-${day}`;

            dateInput.setAttribute("min", todayLocal);
            dateInput.value = todayLocal;

            // Block Mondays and Sundays
            dateInput.addEventListener('input', () => {
                const selected = new Date(dateInput.value);
                const dayOfWeek = selected.getDay();
                
                if (dayOfWeek === 1) { // Monday
                    alert('Siamo chiusi il lunedì. Scegli un altro giorno.');
                    dateInput.value = "";
                    timeSelect.innerHTML = '<option value="" disabled selected>Seleziona l\'orario</option>';
                    hideAvailabilityIndicator();
                } else if (dayOfWeek === 0) { // Sunday
                    alert('Siamo chiusi la domenica. Scegli un altro giorno.');
                    dateInput.value = "";
                    timeSelect.innerHTML = '<option value="" disabled selected>Seleziona l\'orario</option>';
                    hideAvailabilityIndicator();
                } else {
                    updateTimeSlots();
                }
            });

            // Check availability when time is selected
            timeSelect.addEventListener('change', () => {
                if (dateInput.value && timeSelect.value) {
                    checkAvailability(dateInput.value, timeSelect.value);
                }
            });

            function hideAvailabilityIndicator() {
                availabilityIndicator.style.display = 'none';
                submitBtn.disabled = false;
            }

            function showAvailabilityIndicator(status, message) {
                availabilityIndicator.style.display = 'block';
                availabilityIndicator.className = `availability-indicator ${status}`;
                availabilityIndicator.textContent = message;
                
                if (status === 'unavailable') {
                    submitBtn.disabled = true;
                } else {
                    submitBtn.disabled = false;
                }
            }

            function checkAvailability(date, time) {
                showAvailabilityIndicator('checking', 'Controllo...');
                
                fetch(`check_availability.php?date=${date}&time=${time}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            showAvailabilityIndicator('available', data.message);
                        } else {
                            showAvailabilityIndicator('unavailable', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error checking availability:', error);
                        showAvailabilityIndicator('unavailable', 'Errore controllo');
                    });
            }

            // Function to update time slots based on selected date
            function updateTimeSlots() {
                const selectedDate = new Date(dateInput.value);
                const dayOfWeek = selectedDate.getDay();
                
                // Clear existing options
                timeSelect.innerHTML = '<option value="" disabled selected>Seleziona l\'orario</option>';
                hideAvailabilityIndicator();
                
                // Generate time slots from 9:00 to 18:30
                for (let hour = 9; hour < 19; hour++) {
                    for (let min of [0, 30]) {
                        let h = hour.toString().padStart(2, '0');
                        let m = min.toString().padStart(2, '0');
                        let timeValue = `${h}:${m}`;
                        
                        let option = document.createElement('option');
                        option.value = timeValue;
                        option.textContent = timeValue;
                        timeSelect.appendChild(option);
                    }
                }
            }

            // Initial time slots generation
            updateTimeSlots();

            // Form submission with loading
            form.addEventListener('submit', (e) => {
                if (submitBtn.disabled) {
                    e.preventDefault();
                    alert('Seleziona un orario disponibile prima di procedere.');
                    return;
                }
                loading.style.display = 'flex';
            });

            // Add smooth animations
            const inputs = document.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('focus', () => {
                    input.parentElement.parentElement.style.transform = 'translateY(-2px)';
                });
                
                input.addEventListener('blur', () => {
                    input.parentElement.parentElement.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>