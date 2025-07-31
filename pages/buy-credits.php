<?php
// Session is handled by config.php

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /pages/login.php');
    exit;
}

$user = getCurrentUser();
if (!$user || $user['role'] !== 'klient') {
    header('Location: /pages/dashboard.php');
    exit;
}

// EUR predplatené balíčky
$packages = [
    [
        'id' => 'basic',
        'name' => 'Základný balíček',
        'amount' => 50,
        'price' => 50.00,
        'popular' => false,
        'features' => [
            '50 € predplatené',
            'Platnosť 6 mesiacov',
            'Online rezervácie',
            'Email pripomienky'
        ]
    ],
    [
        'id' => 'standard', 
        'name' => 'Štandardný balíček',
        'amount' => 75,
        'price' => 75.00,
        'popular' => true,
        'features' => [
            '75 € predplatené',
            'Platnosť 12 mesiacov',
            'Online rezervácie', 
            'Email pripomienky',
            'Prioritné rezervácie'
        ]
    ],
    [
        'id' => 'premium',
        'name' => 'Prémiový balíček', 
        'amount' => 100,
        'price' => 100.00,
        'popular' => false,
        'features' => [
            '100 € predplatené',
            'Platnosť 24 mesiacov',
            'Online rezervácie',
            'Email pripomienky',
            'Prioritné rezervácie',
            'Zľava na workshopy'
        ]
    ]
];

$currentPage = 'buy-credits';
$pageTitle = 'Dobiť kredit';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold mb-3">Dobiť kredit</h1>
        <p class="lead text-muted">Vyberte si balíček predplateného kreditu</p>
        
        <div class="alert alert-info d-inline-block">
            <i class="fas fa-wallet me-2"></i>
            Aktuálny zostatok: <strong><?= number_format($user['eur_balance'], 2) ?> €</strong>
        </div>
    </div>

    <!-- Pricing Comparison -->
    <div class="row justify-content-center mb-5">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-center mb-4">Porovnanie cien</h5>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="pricing-comparison">
                                <div class="h4 text-muted">12 €</div>
                                <div class="text-muted">Platba na mieste</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="pricing-comparison">
                                <div class="h4 text-success">10 €</div>
                                <div class="text-success">S predplateným kreditom</div>
                                <div class="small text-success fw-bold">Ušetríte 2 €</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Credit Packages -->
    <div class="row justify-content-center g-4">
        <?php foreach ($packages as $package): ?>
            <div class="col-lg-4">
                <div class="card pricing-card h-100 <?= $package['popular'] ? 'pricing-featured' : '' ?>">
                    <?php if ($package['popular']): ?>
                        <div class="featured-badge">Najpopulárnejší</div>
                    <?php endif; ?>
                    
                    <div class="card-body text-center">
                        <h3 class="card-title fw-bold mb-3"><?= e($package['name']) ?></h3>
                        
                        <div class="price mb-3">
                            <span class="display-4 fw-bold"><?= (int)$package['amount'] ?> €</span>
                            <span class="fs-5 text-muted">predplatené</span>
                        </div>
                        
                        <div class="mb-4">
                            <div class="h4 text-primary"><?= number_format($package['price'], 2) ?> €</div>
                            <div class="text-muted">Jednorázová platba</div>
                        </div>
                        
                        <ul class="list-unstyled mb-4">
                            <?php foreach ($package['features'] as $feature): ?>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <?= e($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <form action="/pages/create-stripe-session.php" method="POST">
                            <input type="hidden" name="package_id" value="<?= e($package['id']) ?>">
                            <input type="hidden" name="amount" value="<?= e($package['price']) ?>">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-credit-card me-2"></i>
                                Kúpiť teraz
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Payment Info -->
    <div class="row justify-content-center mt-5">
        <div class="col-md-8">
            <div class="alert alert-light">
                <h6><i class="fas fa-shield-alt me-2"></i>Bezpečná platba</h6>
                <p class="mb-0">Platby sú spracovávané cez zabezpečenú Stripe platformu. Podporujeme platobné karty Visa, Mastercard a PayPal.</p>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>