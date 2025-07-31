<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email_functions.php';

requireRole(['admin', 'lektor']);

$currentUser = getCurrentUser();
$isAdmin = $currentUser['role'] === 'admin';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $recipients = $_POST['recipients'] ?? [];
    $sendToAll = isset($_POST['send_to_all']);
    
    if (empty($subject)) {
        $error = 'Prosím zadajte predmet správy.';
    } elseif (empty($message)) {
        $error = 'Prosím zadajte obsah správy.';
    } elseif (!$sendToAll && empty($recipients)) {
        $error = 'Prosím vyberte príjemcov alebo označte odoslanie všetkým.';
    } else {
        try {
            // Get recipients
            if ($sendToAll) {
                if ($isAdmin) {
                    // Admin can send to all users
                    $emailList = db()->fetchAll("
                        SELECT email, name FROM users 
                        WHERE email IS NOT NULL AND email != '' 
                        AND email_verified = 1
                        ORDER BY name
                    ");
                } else {
                    // Lektor can only send to their class participants
                    $emailList = db()->fetchAll("
                        SELECT DISTINCT u.email, u.name 
                        FROM users u
                        JOIN class_registrations cr ON u.id = cr.user_id
                        JOIN yoga_classes yc ON cr.class_id = yc.id
                        WHERE yc.instructor_id = ? AND u.email IS NOT NULL 
                        AND u.email != '' AND u.email_verified = 1
                        AND cr.status = 'confirmed'
                        ORDER BY u.name
                    ", [$currentUser['id']]);
                }
            } else {
                // Send to selected recipients
                if ($isAdmin) {
                    $placeholders = str_repeat('?,', count($recipients) - 1) . '?';
                    $emailList = db()->fetchAll("
                        SELECT email, name FROM users 
                        WHERE id IN ($placeholders) AND email IS NOT NULL 
                        AND email != '' AND email_verified = 1
                        ORDER BY name
                    ", $recipients);
                } else {
                    // Lektor can only send to their participants
                    $placeholders = str_repeat('?,', count($recipients) - 1) . '?';
                    $params = array_merge([$currentUser['id']], $recipients);
                    $emailList = db()->fetchAll("
                        SELECT DISTINCT u.email, u.name 
                        FROM users u
                        JOIN class_registrations cr ON u.id = cr.user_id
                        JOIN yoga_classes yc ON cr.class_id = yc.id
                        WHERE yc.instructor_id = ? AND u.id IN ($placeholders)
                        AND u.email IS NOT NULL AND u.email != '' 
                        AND u.email_verified = 1 AND cr.status = 'confirmed'
                        ORDER BY u.name
                    ", $params);
                }
            }
            
            if (empty($emailList)) {
                $error = 'Žiadni príjemcovia sa nenašli alebo nemajú overené emailové adresy.';
            } else {
                $sentCount = 0;
                $failedCount = 0;
                
                foreach ($emailList as $recipient) {
                    $personalizedMessage = str_replace('[MENO]', $recipient['name'], $message);
                    
                    if (sendBulkEmail($recipient['email'], $recipient['name'], $subject, $personalizedMessage)) {
                        $sentCount++;
                    } else {
                        $failedCount++;
                    }
                    
                    // Add small delay to prevent spam detection
                    usleep(100000); // 0.1 second
                }
                
                if ($sentCount > 0) {
                    $success = "Úspešne odoslané: $sentCount emailov.";
                    if ($failedCount > 0) {
                        $success .= " Nepodarilo sa odoslať: $failedCount emailov.";
                    }
                } else {
                    $error = 'Nepodarilo sa odoslať žiadny email.';
                }
            }
            
        } catch (Exception $e) {
            $error = 'Chyba pri odosielaní emailov: ' . $e->getMessage();
        }
    }
}

// Get available recipients based on user role
if ($isAdmin) {
    $availableRecipients = db()->fetchAll("
        SELECT id, name, email, role 
        FROM users 
        WHERE email IS NOT NULL AND email != '' AND email_verified = 1
        ORDER BY role, name
    ");
} else {
    $availableRecipients = db()->fetchAll("
        SELECT DISTINCT u.id, u.name, u.email, u.role 
        FROM users u
        JOIN class_registrations cr ON u.id = cr.user_id
        JOIN yoga_classes yc ON cr.class_id = yc.id
        WHERE yc.instructor_id = ? AND u.email IS NOT NULL 
        AND u.email != '' AND u.email_verified = 1
        AND cr.status = 'confirmed'
        ORDER BY u.name
    ", [$currentUser['id']]);
}

$pageTitle = 'Hromadná komunikácia';
$currentPage = 'communication';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title">📧 Hromadná komunikácia</h1>
        <div class="d-flex gap-2">
            <a href="<?= url('pages/reports.php') ?>" class="btn btn-secondary">
                <i class="fas fa-chart-bar me-2"></i>Reporty
            </a>
            <?php if ($isAdmin): ?>
            <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-outline-primary">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Email Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-envelope me-2"></i>Nová správa</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <!-- Subject -->
                        <div class="mb-3">
                            <label for="subject" class="form-label">Predmet *</label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" 
                                   placeholder="Zadajte predmet správy..." required>
                        </div>

                        <!-- Recipients -->
                        <div class="mb-3">
                            <label class="form-label">Príjemcovia *</label>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="send_to_all" name="send_to_all" 
                                       onchange="toggleRecipientSelection()" <?= isset($_POST['send_to_all']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="send_to_all">
                                    <strong>Odoslať všetkým dostupným kontaktom</strong>
                                    <?php if ($isAdmin): ?>
                                        (<?= count($availableRecipients) ?> používateľov)
                                    <?php else: ?>
                                        (<?= count($availableRecipients) ?> účastníkov vašich lekcií)
                                    <?php endif; ?>
                                </label>
                            </div>

                            <div id="recipient-selection" style="display: <?= isset($_POST['send_to_all']) ? 'none' : 'block' ?>;">
                                <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                    <?php if (empty($availableRecipients)): ?>
                                        <p class="text-muted mb-0">Žiadni dostupní príjemcovia.</p>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($availableRecipients as $recipient): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="recipients[]" value="<?= $recipient['id'] ?>"
                                                               id="recipient_<?= $recipient['id'] ?>"
                                                               <?= (isset($_POST['recipients']) && in_array($recipient['id'], $_POST['recipients'])) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="recipient_<?= $recipient['id'] ?>">
                                                            <strong><?= htmlspecialchars($recipient['name']) ?></strong>
                                                            <?php if ($isAdmin): ?>
                                                                <small class="text-muted">(<?= ucfirst($recipient['role']) ?>)</small>
                                                            <?php endif; ?>
                                                            <br>
                                                            <small class="text-muted"><?= htmlspecialchars($recipient['email']) ?></small>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                                Vybrať všetkých
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectNone()">
                                                Zrušiť výber
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Message -->
                        <div class="mb-3">
                            <label for="message" class="form-label">Správa *</label>
                            <textarea class="form-control" id="message" name="message" rows="8" 
                                      placeholder="Napíšte vašu správu...&#10;&#10;Môžete použiť [MENO] pre personalizáciu správy." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Tip: Použite <code>[MENO]</code> pre automatické vloženie mena príjemcu.
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="button" class="btn btn-secondary me-md-2" onclick="previewEmail()">
                                <i class="fas fa-eye me-2"></i>Náhľad
                            </button>
                            <button type="submit" class="btn btn-primary" onclick="return confirmSend()">
                                <i class="fas fa-paper-plane me-2"></i>Odoslať
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tips and Templates -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6><i class="fas fa-lightbulb me-2"></i>Tipy pre správy</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Používajte jasný a výstižný predmet
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Personalizujte správy pomocou [MENO]
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Buďte stručný ale informatívny
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Pridajte kontaktné informácie
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            Testujte na sebe pred odoslaním
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-file-alt me-2"></i>Rýchle šablóny</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="useTemplate('welcome')">
                            Uvítacia správa
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="useTemplate('reminder')">
                            Pripomienka lekcie
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="useTemplate('announcement')">
                            Oznámenie
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="useTemplate('feedback')">
                            Žiadosť o spätnú väzbu
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Náhľad správy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="border rounded p-3 bg-light">
                    <div class="mb-2">
                        <strong>Predmet:</strong> <span id="preview-subject"></span>
                    </div>
                    <div class="mb-2">
                        <strong>Príjemca:</strong> <span id="preview-recipient">Vzorový príjemca</span>
                    </div>
                    <hr>
                    <div id="preview-message" style="white-space: pre-line;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zavrieť</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRecipientSelection() {
    const sendToAll = document.getElementById('send_to_all').checked;
    const selection = document.getElementById('recipient-selection');
    selection.style.display = sendToAll ? 'none' : 'block';
}

function selectAll() {
    const checkboxes = document.querySelectorAll('input[name="recipients[]"]');
    checkboxes.forEach(cb => cb.checked = true);
}

function selectNone() {
    const checkboxes = document.querySelectorAll('input[name="recipients[]"]');
    checkboxes.forEach(cb => cb.checked = false);
}

function confirmSend() {
    const sendToAll = document.getElementById('send_to_all').checked;
    let recipientCount = 0;
    
    if (sendToAll) {
        recipientCount = <?= count($availableRecipients) ?>;
    } else {
        recipientCount = document.querySelectorAll('input[name="recipients[]"]:checked').length;
    }
    
    if (recipientCount === 0) {
        alert('Prosím vyberte aspoň jedného príjemcu.');
        return false;
    }
    
    return confirm(`Skutočne chcete odoslať správu ${recipientCount} príjemcom?`);
}

function previewEmail() {
    const subject = document.getElementById('subject').value;
    const message = document.getElementById('message').value;
    
    if (!subject || !message) {
        alert('Prosím vyplňte predmet a správu pre náhľad.');
        return;
    }
    
    document.getElementById('preview-subject').textContent = subject;
    document.getElementById('preview-message').textContent = message.replace('[MENO]', 'Vzorový príjemca');
    
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

function useTemplate(type) {
    const templates = {
        welcome: {
            subject: 'Vitajte v Láskavom Priestore!',
            message: `Milý/Milá [MENO],

vitajte v našom joga štúdiu Láskavý Priestor! Tešíme sa, že ste sa rozhodli pre cestu wellness a sebastarostlivosti s nami.

Naše lekcie sa konajú v priateľskej atmosfére, kde sa môžete úplne uvoľniť a sústrediť sa na svoj rozvoj.

Ak máte akékoľvek otázky, neváhajte nás kontaktovať.

S pozdravom,
Tím Láskavého Priestoru`
        },
        reminder: {
            subject: 'Pripomienka - vaša lekcia už zajtra',
            message: `Milý/Milá [MENO],

pripomíname vám, že zajtra máte naplánovanú lekciu v našom štúdiu.

Prosíme, príďte 10 minút pred začiatkom lekcie. Ak sa nemôžete zúčastniť, dajte nám prosím vedieť aspoň 2 hodiny vopred.

Tešíme sa na vás!

S pozdravom,
Tím Láskavého Priestoru`
        },
        announcement: {
            subject: 'Dôležité oznámenie',
            message: `Milý/Milá [MENO],

radi by sme vás informovali o dôležitých zmenách v našom štúdiu.

[DOPLŇTE DETAILY OZNÁMENIA]

Ďakujeme za pochopenie.

S pozdravom,
Tím Láskavého Priestoru`
        },
        feedback: {
            subject: 'Vaša spätná väzba je pre nás dôležitá',
            message: `Milý/Milá [MENO],

radi by sme sa dozvedeli váš názor na naše služby. Vaša spätná väzba nám pomáha zlepšovať sa a poskytovať vám lepší zážitok.

Môžete nám napísať odpoveď na tento email alebo navštíviť náš web, kde môžete hodnotiť absolvované lekcie.

Ďakujeme za váš čas!

S pozdravom,
Tím Láskavého Priestoru`
        }
    };
    
    if (templates[type]) {
        document.getElementById('subject').value = templates[type].subject;
        document.getElementById('message').value = templates[type].message;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>