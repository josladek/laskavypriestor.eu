    </main>

    <!-- Footer -->
    <footer class="bg-sand py-5 mt-5">
        <div class="container">
            <?php $studioInfo = getStudioInfo(); ?>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center mb-3">
                        <svg width="30" height="30" viewBox="0 0 100 100" class="me-2">
                            <defs>
                                <linearGradient id="footer-logo-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#a8b5a0;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#8db3a0;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                            <circle cx="50" cy="50" r="35" fill="none" stroke="url(#footer-logo-gradient)" stroke-width="3"/>
                            <path d="M30 40 Q50 20 70 40" fill="none" stroke="url(#footer-logo-gradient)" stroke-width="2"/>
                            <path d="M30 60 Q50 80 70 60" fill="none" stroke="url(#footer-logo-gradient)" stroke-width="2"/>
                            <circle cx="50" cy="50" r="5" fill="var(--sage)"/>
                        </svg>
                        <h5 class="mb-0 text-charcoal"><?= h($studioInfo['name']) ?></h5>
                    </div>
                    <p class="text-muted">
                        Vaše miesto pre jógu, meditáciu a vnútorný pokoj. 
                        Pripojte sa k našej komunite a objavte krásu pravidelnej praxe jógy.
                    </p>
                </div>
                
                <div class="col-lg-2 col-md-3 col-6">
                    <h6 class="text-charcoal mb-3">Navigácia</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?= url('index.php') ?>" class="text-muted text-decoration-none">Domov</a></li>
                        <li><a href="<?= url('pages/classes.php') ?>" class="text-muted text-decoration-none">Lekcie</a></li>
                        <li><a href="<?= url('pages/courses.php') ?>" class="text-muted text-decoration-none">Kurzy</a></li>
                        <li><a href="<?= url('pages/instructors.php') ?>" class="text-muted text-decoration-none">Lektori</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-3 col-6">
                    <h6 class="text-charcoal mb-3">Účet</h6>
                    <ul class="list-unstyled">
                        <?php if (isLoggedIn()): ?>
                            <li><a href="<?= url('pages/my-classes.php') ?>" class="text-muted text-decoration-none">Moje lekcie</a></li>
                            <li><a href="<?= url('pages/my-courses.php') ?>" class="text-muted text-decoration-none">Moje kurzy</a></li>
                            <li><a href="<?= url('api/logout.php') ?>" class="text-muted text-decoration-none">Odhlásiť sa</a></li>
                        <?php else: ?>
                            <li><a href="<?= url('pages/login.php') ?>" class="text-muted text-decoration-none">Prihlásenie</a></li>
                            <li><a href="<?= url('pages/register.php') ?>" class="text-muted text-decoration-none">Registrácia</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <h6 class="text-charcoal mb-3">Kontakt</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-envelope text-sage me-2"></i>
                            <a href="mailto:<?= h($studioInfo['email']) ?>" class="text-muted text-decoration-none">
                                <?= h($studioInfo['email']) ?>
                            </a>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone text-sage me-2"></i>
                            <span class="text-muted"><?= h($studioInfo['phone']) ?></span>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-map-marker-alt text-sage me-2"></i>
                            <span class="text-muted"><?= h($studioInfo['full_address']) ?></span>
                        </li>
                    </ul>
                    
                    <!-- Social Media -->
                    <div class="mt-3">
                        <a href="https://www.facebook.com/profile.php?id=61578132173098" class="text-sage me-3 fs-5"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-sage me-3 fs-5"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-sage me-3 fs-5"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            
            <hr class="my-4 border-sage">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted small mb-0">
                        &copy; <?= date('Y') ?> <?= h($studioInfo['name']) ?>. Všetky práva vyhradené.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="<?= url('pages/terms.php') ?>" class="text-muted text-decoration-none small me-3">
                        Podmienky používania
                    </a>
                    <a href="<?= url('pages/privacy.php') ?>" class="text-muted text-decoration-none small">
                        Ochrana osobných údajov
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS (Local) -->
    <script src="<?= url('assets/js/bootstrap.bundle.min.js') ?>"></script>
    <!-- Custom JS -->
    <script src="<?= url('assets/js/script.js') ?>"></script>
</body>
</html>