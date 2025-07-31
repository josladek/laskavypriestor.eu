<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../includes/auth.php';
    require_once '../includes/functions.php';

    requireLogin();
} catch (Exception $e) {
    die("Chyba načítania: " . htmlspecialchars($e->getMessage()) . "<br><a href='../debug-error.php'>Debug</a>");
}

$currentUser = getCurrentUser(true); // Force refresh to get latest balance

// Get credit transaction history
$transactions = db()->fetchAll("
    SELECT ct.*, 
           CASE 
               WHEN ct.transaction_type = 'class_payment' THEN 
                   (SELECT yc.name FROM yoga_classes yc WHERE yc.id = ct.reference_id)
               WHEN ct.transaction_type = 'course_payment' THEN 
                   (SELECT c.name FROM courses c WHERE c.id = ct.reference_id)
               ELSE NULL
           END as reference_name
    FROM credit_transactions ct 
    WHERE ct.user_id = ? 
    ORDER BY ct.created_at DESC
", [$currentUser['id']]);

$pageTitle = 'História kreditov';
$currentPage = 'credit-history';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">História kreditov</h1>
                    <p class="text-muted">Prehľad všetkých kreditových transakcií</p>
                </div>
                <div class="d-flex gap-2">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <div class="h4 mb-0 text-success"><?= formatPrice($currentUser['eur_balance']) ?></div>
                            <small class="text-muted">Aktuálny zostatok</small>
                        </div>
                    </div>
                    <a href="buy-credits-manual.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Dobiť kredit
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($transactions)): ?>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Dátum</th>
                                <th>Typ transakcie</th>
                                <th>Popis</th>
                                <th class="text-end">Suma</th>
                                <th class="text-end">Zostatok</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $runningBalance = $currentUser['eur_balance'];
                            foreach ($transactions as $index => $transaction): 
                                // Calculate balance at time of transaction
                                if ($index > 0) {
                                    $runningBalance -= $transactions[$index - 1]['amount'];
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= formatDate($transaction['created_at']) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($transaction['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($transaction['transaction_type'] === 'purchase'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-plus me-1"></i> Nákup
                                            </span>
                                        <?php elseif ($transaction['transaction_type'] === 'manual_payment_approved'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-credit-card me-1"></i> Platba za kredity
                                            </span>
                                        <?php elseif ($transaction['transaction_type'] === 'class_payment'): ?>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-dumbbell me-1"></i> Platba za lekciu
                                            </span>
                                        <?php elseif ($transaction['transaction_type'] === 'course_payment'): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-graduation-cap me-1"></i> Platba za kurz
                                            </span>
                                        <?php elseif ($transaction['transaction_type'] === 'refund'): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-undo me-1"></i> Refund
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= e($transaction['transaction_type']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($transaction['transaction_type'] === 'purchase'): ?>
                                            <div class="fw-bold">Nákup kreditov</div>
                                            <?php if ($transaction['reference_id']): ?>
                                                <small class="text-muted">ID transakcie: <?= e($transaction['reference_id']) ?></small>
                                            <?php endif; ?>
                                        <?php elseif ($transaction['transaction_type'] === 'manual_payment_approved'): ?>
                                            <div class="fw-bold">Platba za kredity</div>
                                            <small class="text-muted">Bankovým prevodom</small>
                                        <?php elseif ($transaction['transaction_type'] === 'class_payment'): ?>
                                            <div class="fw-bold">Platba za lekciu</div>
                                            <?php if ($transaction['reference_name']): ?>
                                                <small class="text-muted"><?= e($transaction['reference_name']) ?></small>
                                            <?php endif; ?>
                                        <?php elseif ($transaction['transaction_type'] === 'course_payment'): ?>
                                            <div class="fw-bold">Platba za kurz</div>
                                            <?php if ($transaction['reference_name']): ?>
                                                <small class="text-muted"><?= e($transaction['reference_name']) ?></small>
                                            <?php endif; ?>
                                        <?php elseif ($transaction['transaction_type'] === 'refund'): ?>
                                            <div class="fw-bold">Vrátenie kreditu</div>
                                            <small class="text-muted">Zrušenie registrácie</small>
                                        <?php else: ?>
                                            <div class="fw-bold"><?= e($transaction['transaction_type']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($transaction['amount'] > 0): ?>
                                            <span class="text-success fw-bold">
                                                +<?= formatPrice($transaction['amount']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-danger fw-bold">
                                                <?= formatPrice($transaction['amount']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold"><?= formatPrice($runningBalance) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <?php $totalPurchases = array_sum(array_map(function($t) { return in_array($t['transaction_type'], ['purchase', 'manual_payment_approved']) ? $t['amount'] : 0; }, $transactions)); ?>
                        <div class="h4 text-success"><?= formatPrice($totalPurchases) ?></div>
                        <small class="text-muted">Celkom nakúpené</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <?php $totalSpent = abs(array_sum(array_map(function($t) { return in_array($t['transaction_type'], ['class_payment', 'course_payment']) ? $t['amount'] : 0; }, $transactions))); ?>
                        <div class="h4 text-primary"><?= formatPrice($totalSpent) ?></div>
                        <small class="text-muted">Celkom minúté</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <?php $totalRefunds = array_sum(array_map(function($t) { return $t['transaction_type'] === 'refund' ? $t['amount'] : 0; }, $transactions)); ?>
                        <div class="h4 text-warning"><?= formatPrice($totalRefunds) ?></div>
                        <small class="text-muted">Celkom refunds</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="h4 text-info"><?= count($transactions) ?></div>
                        <small class="text-muted">Počet transakcií</small>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <svg width="120" height="120" viewBox="0 0 100 100">
                    <defs>
                        <linearGradient id="empty-credits-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.2" />
                            <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.4" />
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="40" fill="none" stroke="url(#empty-credits-gradient)" stroke-width="2"/>
                    <path d="M35 40 L50 25 L65 40" fill="none" stroke="url(#empty-credits-gradient)" stroke-width="2"/>
                    <path d="M35 60 L50 75 L65 60" fill="none" stroke="url(#empty-credits-gradient)" stroke-width="2"/>
                    <circle cx="50" cy="50" r="8" fill="var(--sage)" opacity="0.3"/>
                    <text x="50" y="55" font-family="Roboto, Arial" font-size="12" fill="var(--sage)" text-anchor="middle">€</text>
                </svg>
            </div>
            <h3 class="text-muted">Žiadne transakcie</h3>
            <p class="text-muted">Zatiaľ nemáte žiadne kreditové transakcie.</p>
            <a href="buy-credits.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Nakúpiť kredity
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- CSS moved to laskavypriestor.css -->

<?php include '../includes/footer.php'; ?>