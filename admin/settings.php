<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_POST['tab'] ?? '';
    
    try {
        db()->beginTransaction();
        
        if ($tab === 'contact') {
            // Update contact settings
            $contactSettings = [
                'studio_name' => $_POST['studio_name'] ?? '',
                'ico' => $_POST['ico'] ?? '',
                'street' => $_POST['street'] ?? '',
                'house_number' => $_POST['house_number'] ?? '',
                'postal_code' => $_POST['postal_code'] ?? '',
                'city' => $_POST['city'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? '',
                'website' => $_POST['website'] ?? '',
                'iban' => $_POST['iban'] ?? '',
                'bank_name' => $_POST['bank_name'] ?? ''
            ];
            
            foreach ($contactSettings as $key => $value) {
                db()->query("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ", [$key, $value]);
            }
            
            $_SESSION['flash_message'] = 'Kontaktné údaje boli úspešne uložené.';
            $_SESSION['flash_type'] = 'success';
            
        } elseif ($tab === 'notifications') {
            // Update notification settings
            $notificationSettings = [
                'smtp_email' => $_POST['smtp_email'] ?? '',
                'smtp_server' => $_POST['smtp_server'] ?? '',
                'smtp_username' => $_POST['smtp_username'] ?? '',
                'smtp_password' => $_POST['smtp_password'] ?? '',
                'smtp_port' => $_POST['smtp_port'] ?? '587'
            ];
            
            foreach ($notificationSettings as $key => $value) {
                db()->query("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ", [$key, $value]);
            }
            
            $_SESSION['flash_message'] = 'Nastavenia notifikácií boli úspešne uložené.';
            $_SESSION['flash_type'] = 'success';
        }
        
        db()->commit();
        
    } catch (Exception $e) {
        db()->rollBack();
        $_SESSION['flash_message'] = 'Chyba pri ukladaní nastavení: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    
    header('Location: settings.php#' . $tab);
    exit;
}

// Load current settings
$settings = [];
$settingsData = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
foreach ($settingsData as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Default values
$defaults = [
    'studio_name' => 'Láskavý Priestor',
    'ico' => '',
    'street' => '',
    'house_number' => '',
    'postal_code' => '',
    'city' => '',
    'phone' => '',
    'email' => 'info@laskavypriestor.eu',
    'website' => 'https://www.laskavypriestor.eu',
    'iban' => 'SK7311000000002612938533',
    'bank_name' => 'Tatrabanka a.s.',
    'smtp_email' => 'info@laskavypriestor.eu',
    'smtp_server' => 'smtp.forpsi.com',
    'smtp_username' => 'info@laskavypriestor.eu',
    'smtp_password' => '',
    'smtp_port' => '465'
];

// Merge with defaults
foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-charcoal mb-0">
                    <i class="fas fa-cog me-2"></i>Nastavenia systému
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Nastavenia</li>
                    </ol>
                </nav>
            </div>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['flash_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <div class="card border-sage shadow">
                <div class="card-body">
                    <!-- Navigation Tabs -->
                    <ul class="nav nav-tabs nav-fill mb-4" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                                <i class="fas fa-address-card me-2"></i>Kontaktné údaje
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                                <i class="fas fa-envelope me-2"></i>Odosielanie notifikácií
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="settingsTabContent">
                        
                        <!-- Contact Settings Tab -->
                        <div class="tab-pane fade show active" id="contact" role="tabpanel">
                            <form method="POST" action="settings.php">
                                <input type="hidden" name="tab" value="contact">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="text-sage mb-3">Základné údaje</h5>
                                        
                                        <div class="mb-3">
                                            <label for="studio_name" class="form-label">Názov štúdia</label>
                                            <input type="text" class="form-control" id="studio_name" name="studio_name" 
                                                   value="<?= htmlspecialchars($settings['studio_name']) ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="ico" class="form-label">IČO</label>
                                            <input type="text" class="form-control" id="ico" name="ico" 
                                                   value="<?= htmlspecialchars($settings['ico']) ?>">
                                        </div>
                                        
                                        <h5 class="text-sage mb-3 mt-4">Adresa</h5>
                                        
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label for="street" class="form-label">Ulica</label>
                                                    <input type="text" class="form-control" id="street" name="street" 
                                                           value="<?= htmlspecialchars($settings['street']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="house_number" class="form-label">Číslo</label>
                                                    <input type="text" class="form-control" id="house_number" name="house_number" 
                                                           value="<?= htmlspecialchars($settings['house_number']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="postal_code" class="form-label">PSČ</label>
                                                    <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                                           value="<?= htmlspecialchars($settings['postal_code']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label for="city" class="form-label">Obec</label>
                                                    <input type="text" class="form-control" id="city" name="city" 
                                                           value="<?= htmlspecialchars($settings['city']) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5 class="text-sage mb-3">Kontaktné údaje</h5>
                                        
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Telefón</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?= htmlspecialchars($settings['phone']) ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="email" class="form-label">E-mail</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?= htmlspecialchars($settings['email']) ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="website" class="form-label">Webová stránka</label>
                                            <input type="url" class="form-control" id="website" name="website" 
                                                   value="<?= htmlspecialchars($settings['website']) ?>">
                                        </div>
                                        
                                        <h5 class="text-sage mb-3 mt-4">Platobné údaje</h5>
                                        
                                        <div class="mb-3">
                                            <label for="iban" class="form-label">IBAN</label>
                                            <input type="text" class="form-control" id="iban" name="iban" 
                                                   value="<?= htmlspecialchars($settings['iban']) ?>" required>
                                            <div class="form-text">Bankový účet pre prijímanie platieb</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="bank_name" class="form-label">Banka</label>
                                            <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                                   value="<?= htmlspecialchars($settings['bank_name']) ?>" required>
                                            <div class="form-text">Názov banky pre zobrazenie na faktúrach a platobných pokynoch</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-sage">
                                        <i class="fas fa-save me-2"></i>Uložiť kontaktné údaje
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Notifications Settings Tab -->
                        <div class="tab-pane fade" id="notifications" role="tabpanel">
                            <form method="POST" action="settings.php">
                                <input type="hidden" name="tab" value="notifications">
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5 class="text-sage mb-3">SMTP konfigurácia</h5>
                                        <p class="text-muted mb-4">Nastavenia pre odosielanie emailových notifikácií</p>
                                        
                                        <div class="mb-3">
                                            <label for="smtp_email" class="form-label">E-mailová adresa odosielateľa</label>
                                            <input type="email" class="form-control" id="smtp_email" name="smtp_email" 
                                                   value="<?= htmlspecialchars($settings['smtp_email']) ?>" required>
                                            <div class="form-text">Adresa, ktorá sa zobrazí ako odosielateľ</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="smtp_server" class="form-label">SMTP server</label>
                                            <input type="text" class="form-control" id="smtp_server" name="smtp_server" 
                                                   value="<?= htmlspecialchars($settings['smtp_server']) ?>" required>
                                            <div class="form-text">Napr. smtp.gmail.com, smtp.forpsi.com</div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="mb-3">
                                                    <label for="smtp_username" class="form-label">Prihlasovacie meno</label>
                                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                                           value="<?= htmlspecialchars($settings['smtp_username']) ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="smtp_port" class="form-label">Port</label>
                                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                           value="<?= htmlspecialchars($settings['smtp_port']) ?>" required>
                                                    <div class="form-text">587 (TLS) alebo 465 (SSL)</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="smtp_password" class="form-label">Heslo</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                   value="<?= htmlspecialchars($settings['smtp_password']) ?>">
                                            <div class="form-text">Nechajte prázdne ak nechcete zmeniť existujúce heslo</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title text-sage">
                                                    <i class="fas fa-info-circle me-2"></i>Informácie
                                                </h6>
                                                <p class="card-text small">
                                                    SMTP nastavenia sa používajú pre:
                                                </p>
                                                <ul class="small">
                                                    <li>Potvrdenia registrácií</li>
                                                    <li>Platobné pokyny</li>
                                                    <li>Pripomienky lekcií</li>
                                                    <li>Systémové notifikácie</li>
                                                </ul>
                                                
                                                <hr>
                                                
                                                <h6 class="text-sage">Odporúčané nastavenia:</h6>
                                                <div class="small">
                                                    <strong>Gmail:</strong><br>
                                                    Server: smtp.gmail.com<br>
                                                    Port: 587 (TLS)<br><br>
                                                    
                                                    <strong>Forpsi:</strong><br>
                                                    Server: smtp.forpsi.com<br>
                                                    Port: 465 (SSL)
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-sage">
                                        <i class="fas fa-save me-2"></i>Uložiť nastavenia notifikácií
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Handle tab switching with URL hash
document.addEventListener('DOMContentLoaded', function() {
    // Get hash from URL
    const hash = window.location.hash.substring(1);
    
    if (hash && (hash === 'contact' || hash === 'notifications')) {
        // Activate the corresponding tab
        const tabButton = document.getElementById(hash + '-tab');
        const tabContent = document.getElementById(hash);
        
        if (tabButton && tabContent) {
            // Remove active class from all tabs
            document.querySelectorAll('.nav-link').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            
            // Activate selected tab
            tabButton.classList.add('active');
            tabContent.classList.add('show', 'active');
        }
    }
    
    // Update URL hash when tab is clicked
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tabButton => {
        tabButton.addEventListener('click', function() {
            const target = this.getAttribute('data-bs-target').substring(1);
            history.replaceState(null, null, '#' + target);
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>