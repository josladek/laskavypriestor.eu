<?php
// Pomocné funkcie pre PHP aplikáciu

/**
 * Bezpečné zobrazenie HTML obsahu
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Alias pre e() funkciu
 */
function h($string) {
    return e($string);
}

/**
 * Generovanie URL
 */
function url($path = '') {
    $baseUrl = rtrim(BASE_URL, '/');
    return $baseUrl . '/' . ltrim($path, '/');
}

/**
 * Presmerovanie na správnu stránku podľa role používateľa
 */
function redirect($url = null) {
    if ($url) {
        header('Location: ' . $url);
        exit;
    }
    
    // Ak nie je špecifikovaná URL, presmeruj podľa role
    if (!isLoggedIn()) {
        header('Location: ../pages/index.php');
        exit;
    }
    
    $user = getCurrentUser();
    if (!$user) {
        header('Location: ../pages/index.php');
        exit;
    }
    
    // Presmeruj podľa role
    switch ($user['role']) {
        case 'admin':
            header('Location: ../admin/index.php');
            break;
        case 'lektor':
            header('Location: ../lektor/index.php');
            break;
        case 'klient':
        default:
            header('Location: ../pages/index.php');
            break;
    }
    exit;
}

/**
 * Formátovanie ceny
 */
function formatPrice($price) {
    return number_format((float)$price, 2, ',', ' ') . '€';
}

/**
 * Formátovanie dátumu
 */
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

/**
 * Formátovanie dátumu a času
 */
function formatDateTime($datetime) {
    if (!$datetime) return '';
    return date('d.m.Y H:i', strtotime($datetime));
}

/**
 * Získanie zostatok kreditu používateľa
 */
function getUserCreditBalance($userId) {
    try {
        $result = db()->fetch("SELECT eur_balance FROM users WHERE id = ?", [$userId]);
        return $result ? (float)$result['eur_balance'] : 0.0;
    } catch (Exception $e) {
        error_log("Error getting credit balance: " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Kontrola, či je lekcia/workshop už ukončený
 */
function isEventFinished($date, $time_end) {
    $event_end_datetime = $date . ' ' . $time_end;
    $current_datetime = date('Y-m-d H:i:s');
    return strtotime($current_datetime) > strtotime($event_end_datetime);
}

/**
 * Získanie statusu lekcie/workshopu
 */
function getEventStatus($date, $time_end) {
    if (isEventFinished($date, $time_end)) {
        return 'finished';
    }
    
    $event_date = date('Y-m-d', strtotime($date));
    $today = date('Y-m-d');
    
    if ($event_date < $today) {
        return 'finished'; // Changed from 'past' to 'finished' for consistency
    } elseif ($event_date == $today) {
        return 'today';
    } else {
        return 'upcoming';
    }
}

/**
 * Formátovanie času
 */
function formatTime($time) {
    if ($time === null || $time === '') {
        return '';
    }
    return substr($time, 0, 5);
}



/**
 * Získanie názvu dňa v týždni
 */
function getDayName($dayNumber) {
    $days = [
        0 => 'Pondelok',
        1 => 'Utorok', 
        2 => 'Streda',
        3 => 'Štvrtok',
        4 => 'Piatok',
        5 => 'Sobota',
        6 => 'Nedeľa'
    ];
    return $days[$dayNumber] ?? 'Neznámy';
}

/**
 * Získanie všetkých kurzov
 */
function getCourses($activeOnly = false) {
    $sql = "
        SELECT c.id, c.name, c.description, c.instructor_id, c.total_lessons, c.lesson_duration_minutes,
               c.price, c.price_with_credit, c.start_date, c.end_date, c.day_of_week,
               c.time_start, c.time_end, c.capacity, c.location, c.image_url, c.status,
               c.created_at, c.updated_at, u.name as lektor_name,
               (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.id AND cr.status = 'confirmed') as registered_count
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.id 
    ";
    
    if ($activeOnly) {
        $sql .= " WHERE c.status = 'active' AND c.end_date >= CURDATE()";
    }
    
    $sql .= " ORDER BY c.start_date ASC";
    
    $courses = db()->fetchAll($sql);
    
    // Pridaj pomocné metódy
    foreach ($courses as &$course) {
        $course['day_name'] = function() use ($course) {
            return getDayName($course['day_of_week']);
        };
    }
    
    return $courses;
}

/**
 * Kontrola či je kurz plný
 */
function isCourseFull($courseId) {
    $course = db()->fetch("SELECT capacity, (SELECT COUNT(*) FROM course_registrations WHERE course_id = ? AND status = 'confirmed') as registered FROM courses WHERE id = ?", [$courseId, $courseId]);
    return $course && $course['registered'] >= $course['capacity'];
}

/**
 * Získanie všetkých lekcií
 */
function getClasses($filters = []) {
    $currentUser = getCurrentUser();
    $userId = $currentUser ? $currentUser['id'] : 0;
    
    $sql = "
        SELECT yc.*, u.name as lektor_name,
        (SELECT COUNT(*) FROM registrations r WHERE r.class_id = yc.id AND r.status IN ('confirmed', 'pending')) as registered_count,
        (SELECT COUNT(*) FROM registrations r WHERE r.class_id = yc.id AND r.status = 'waitlisted') as waitlist_count,
        (SELECT COUNT(*) FROM registrations r WHERE r.class_id = yc.id AND r.user_id = ? AND r.status IN ('confirmed', 'pending')) as user_registered,
        CASE 
            WHEN yc.date < CURDATE() OR (yc.date = CURDATE() AND yc.time_end < CURTIME()) THEN 'closed'
            ELSE yc.status
        END as effective_status
        FROM yoga_classes yc 
        LEFT JOIN users u ON yc.instructor_id = u.id 
        WHERE (yc.date > CURDATE() OR (yc.date = CURDATE() AND yc.time_end >= CURTIME()))
        AND yc.status = 'active'
        AND yc.course_id IS NULL
    ";
    
    $params = [$userId];
    
    if (!empty($filters['type']) && $filters['type'] !== 'all') {
        $sql .= " AND yc.type = ?";
        $params[] = $filters['type'];
    }
    
    if (!empty($filters['level']) && $filters['level'] !== 'all') {
        $sql .= " AND yc.level = ?";
        $params[] = $filters['level'];
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND yc.date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['lektor'])) {
        $sql .= " AND yc.instructor_id = ?";
        $params[] = $filters['lektor'];
    }
    
    $sql .= " ORDER BY yc.date ASC, yc.time_start ASC";
    
    return db()->fetchAll($sql, $params);
}

/**
 * Kontrola či je lekcia plná
 */
function isClassFull($classId) {
    $class = db()->fetch("SELECT capacity, (SELECT COUNT(*) FROM registrations WHERE class_id = ? AND status IN ('confirmed', 'pending')) as registered FROM yoga_classes WHERE id = ?", [$classId, $classId]);
    return $class && $class['registered'] >= $class['capacity'];
}

/**
 * Registrácia používateľa na lekciu
 */
function registerUserForClass($userId, $classId, $paidWithCredit = false) {
    try {
        db()->beginTransaction();
        
        // Získaj detaily lekcie
        $class = db()->fetch("SELECT * FROM yoga_classes WHERE id = ?", [$classId]);
        if (!$class) {
            throw new Exception('Lekcia neexistuje');
        }
        
        // Skontroluj kapacitu
        $registeredCount = db()->fetch("SELECT COUNT(*) as count FROM registrations WHERE class_id = ? AND status IN ('confirmed', 'pending')", [$classId])['count'];
        
        $status = 'confirmed';
        $waitlistPosition = null;
        
        if ($registeredCount >= $class['capacity']) {
            $status = 'waitlisted';
            $waitlistPosition = db()->fetch("SELECT COUNT(*) as count FROM registrations WHERE class_id = ? AND status = 'waitlisted'", [$classId])['count'] + 1;
        }
        
        // Vytvor registráciu
        db()->query("INSERT INTO registrations (user_id, class_id, status, paid_with_credit, waitlist_position) VALUES (?, ?, ?, ?, ?)", 
            [$userId, $classId, $status, $paidWithCredit, $waitlistPosition]);
        
        // Ak platené kreditom, odpočítaj kredit
        if ($paidWithCredit && $status === 'confirmed') {
            $price = $class['price_with_credit'];
            db()->query("UPDATE users SET eur_balance = eur_balance - ? WHERE id = ?", [$price, $userId]);
            
            // Vytvor transakciu
            db()->query("INSERT INTO credit_transactions (user_id, amount, transaction_type, reference_id) VALUES (?, ?, 'class_payment', ?)", 
                [$userId, -$price, $classId]);
        }
        
        db()->commit();
        return true;
        
    } catch (Exception $e) {
        db()->rollback();
        throw $e;
    }
}

/**
 * Zrušenie registrácie
 */
function cancelRegistration($registrationId, $userId) {
    try {
        db()->beginTransaction();
        
        // Získaj registráciu
        $registration = db()->fetch("SELECT * FROM registrations WHERE id = ? AND user_id = ?", [$registrationId, $userId]);
        if (!$registration) {
            throw new Exception('Registrácia neexistuje');
        }
        
        // Získaj lekciu
        $class = db()->fetch("SELECT * FROM yoga_classes WHERE id = ?", [$registration['class_id']]);
        
        // Zmaž registráciu
        db()->query("DELETE FROM registrations WHERE id = ?", [$registrationId]);
        
        // Ak bolo platené kreditom, vráť kredit
        if ($registration['paid_with_credit'] && $registration['status'] === 'confirmed') {
            $refundAmount = $class['price_with_credit'];
            db()->query("UPDATE users SET eur_balance = eur_balance + ? WHERE id = ?", [$refundAmount, $userId]);
            
            // Vytvor transakciu pre vrátenie kreditu
            db()->query("INSERT INTO credit_transactions (user_id, amount, transaction_type, reference_id, description) VALUES (?, ?, 'class_refund', ?, ?)", 
                [$userId, $refundAmount, $registrationId, "Vrátený kredit za zrušenie lekcie: " . $class['name']]);
        }
        
        // Posuň waitlist
        $waitlisted = db()->fetchAll("SELECT * FROM registrations WHERE class_id = ? AND status = 'waitlisted' ORDER BY waitlist_position ASC", [$registration['class_id']]);
        
        if (!empty($waitlisted) && $registration['status'] === 'confirmed') {
            // Posuň prvého z waitlistu na confirmed
            $firstWaitlisted = $waitlisted[0];
            db()->query("UPDATE registrations SET status = 'confirmed', waitlist_position = NULL WHERE id = ?", [$firstWaitlisted['id']]);
            
            // Aktualizuj pozície ostatných
            foreach ($waitlisted as $index => $waiting) {
                if ($index > 0) {
                    db()->query("UPDATE registrations SET waitlist_position = ? WHERE id = ?", [$index, $waiting['id']]);
                }
            }
        }
        
        db()->commit();
        return true;
        
    } catch (Exception $e) {
        db()->rollback();
        throw $e;
    }
}

/**
 * Odpočítanie kreditu používateľovi
 */
function deductCredit($userId, $amount, $transactionType = 'class_payment', $referenceId = null) {
    try {
        $needsTransaction = !db()->inTransaction();
        
        if ($needsTransaction) {
            db()->beginTransaction();
        }
        
        // Check if user has enough EUR balance
        $currentUser = db()->fetch("SELECT eur_balance FROM users WHERE id = ?", [$userId]);
        if (!$currentUser || $currentUser['eur_balance'] < $amount) {
            if ($needsTransaction) {
                db()->rollback();
            }
            return false;
        }
        
        // Aktualizuj EUR balance
        $stmt = db()->query("UPDATE users SET eur_balance = eur_balance - ? WHERE id = ?", [$amount, $userId]);
        if (!$stmt) {
            throw new Exception('Failed to update credit balance');
        }
        
        // Vytvor transakčný záznam
        $stmt = db()->query("INSERT INTO credit_transactions (user_id, amount, transaction_type, reference_id) VALUES (?, ?, ?, ?)", 
            [$userId, -$amount, $transactionType, $referenceId]);
        if (!$stmt) {
            throw new Exception('Failed to create transaction record');
        }
        
        // Refresh user cache if this is current user
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            refreshCurrentUser();
        }
        
        if ($needsTransaction) {
            db()->commit();
        }
        return true;
        
    } catch (Exception $e) {
        if ($needsTransaction) {
            db()->rollback();
        }
        throw $e;
    }
}

/**
 * Pridanie kreditu používateľovi  
 */
function addCredit($userId, $amount, $transactionType = 'purchase', $referenceId = null) {
    try {
        // Kontrola či už existuje transakcia
        $needsTransaction = !db()->inTransaction();
        
        if ($needsTransaction) {
            db()->beginTransaction();
            error_log("DEBUG addCredit: Started new transaction for user $userId, amount $amount");
        } else {
            error_log("DEBUG addCredit: Using existing transaction for user $userId, amount $amount");
        }
        
        // Aktualizuj EUR balance
        $updateResult = db()->query("UPDATE users SET eur_balance = eur_balance + ? WHERE id = ?", [$amount, $userId]);
        if (!$updateResult) {
            throw new Exception('Failed to update credit balance');
        }
        
        error_log("DEBUG addCredit: Updated user balance successfully");
        
        // Vytvor transakčný záznam
        $transactionResult = db()->query("INSERT INTO credit_transactions (user_id, amount, transaction_type, reference_id, created_at) VALUES (?, ?, ?, ?, NOW())", 
            [$userId, $amount, $transactionType, $referenceId]);
        if (!$transactionResult) {
            throw new Exception('Failed to create transaction record');
        }
        
        error_log("DEBUG addCredit: Created transaction record successfully");
        
        // Refresh user cache if this is current user
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            refreshCurrentUser();
            error_log("DEBUG addCredit: Refreshed current user session");
        }
        
        if ($needsTransaction) {
            db()->commit();
            error_log("DEBUG addCredit: Transaction committed successfully");
        }
        return true;
        
    } catch (Exception $e) {
        if ($needsTransaction && db()->inTransaction()) {
            db()->rollBack();
        }
        error_log("DEBUG addCredit ERROR: " . $e->getMessage());
        error_log("DEBUG addCredit ERROR Stack: " . $e->getTraceAsString());
        throw $e; // Propagate exception instead of returning false
    }
}

/**
 * Získanie kreditných balíčkov
 */
function getCreditPackages() {
    return [
        [
            'id' => 1,
            'name' => 'Štartovací balíček',
            'credits' => 50,
            'price' => 45,
            'savings' => 5,
            'description' => 'Ideálny pre začiatočníkov'
        ],
        [
            'id' => 2,
            'name' => 'Populárny balíček',
            'credits' => 100,
            'price' => 85,
            'savings' => 15,
            'description' => 'Najobľúbenejší výber'
        ],
        [
            'id' => 3,
            'name' => 'Veľký balíček',
            'credits' => 200,
            'price' => 160,
            'savings' => 40,
            'description' => 'Najlepšia hodnota'
        ]
    ];
}

/**
 * Vytvorenie placeholder SVG obrázka
 */
function createPlaceholderSvg($text = 'Yoga', $width = 400, $height = 300) {
    return "
    <svg width='$width' height='$height' viewBox='0 0 $width $height' xmlns='http://www.w3.org/2000/svg'>
        <defs>
            <linearGradient id='placeholder-gradient' x1='0%' y1='0%' x2='100%' y2='100%'>
                <stop offset='0%' style='stop-color:#a8b5a0;stop-opacity:0.3' />
                <stop offset='100%' style='stop-color:#8db3a0;stop-opacity:0.6' />
            </linearGradient>
        </defs>
        <rect width='100%' height='100%' fill='url(#placeholder-gradient)'/>
        <text x='50%' y='50%' font-family='Roboto, Arial, sans-serif' font-size='24' fill='#4a4a4a' text-anchor='middle' dy='.3em'>$text</text>
    </svg>";
}

/**
 * Upload súboru
 */
function uploadFile($file, $directory = 'classes') {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    $uploadDir = "uploads/$directory/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}

/**
 * Debug funkcia
 */
function dd($var) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    die();
}

/**
 * Validácia emailovej adresy
 */
function validateEmail($email) {
    // Základná PHP filter validácia
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Dodatočné kontroly
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return false;
    }
    
    $localPart = $parts[0];
    $domain = $parts[1];
    
    // Kontrola dĺžky
    if (strlen($localPart) > 64 || strlen($domain) > 255) {
        return false;
    }
    
    // Kontrola formátu domény
    if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain)) {
        return false;
    }
    
    return true;
}

/**
 * Validácia telefónneho čísla (slovenský formát)
 */
function validatePhone($phone) {
    // Odstráň všetky medzery a špeciálne znaky
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Slovenské formáty:
    // +421 xxx xxx xxx (medzinárodný)
    // 0xxx xxx xxx (národný)
    // xxx xxx xxx (bez predvolby)
    
    $patterns = [
        '/^\+421[0-9]{9}$/',           // +421123456789
        '/^00421[0-9]{9}$/',           // 00421123456789  
        '/^0[0-9]{9}$/',               // 0123456789
        '/^[0-9]{9}$/'                 // 123456789
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $cleanPhone)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Formátovanie telefónneho čísla
 */
function formatPhone($phone) {
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Ak začína +421
    if (substr($cleanPhone, 0, 4) === '+421') {
        $number = substr($cleanPhone, 4);
        return '+421 ' . substr($number, 0, 3) . ' ' . substr($number, 3, 3) . ' ' . substr($number, 6, 3);
    }
    
    // Ak začína 0
    if (substr($cleanPhone, 0, 1) === '0') {
        return '0' . substr($cleanPhone, 1, 3) . ' ' . substr($cleanPhone, 4, 3) . ' ' . substr($cleanPhone, 7, 3);
    }
    
    // Inak pridaj 0
    return '0' . substr($cleanPhone, 0, 3) . ' ' . substr($cleanPhone, 3, 3) . ' ' . substr($cleanPhone, 6, 3);
}

/**
 * Získanie nastavenia z databázy
 */
function getSetting($key, $default = '') {
    try {
        $result = db()->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        error_log("Error getting setting $key: " . $e->getMessage());
        return $default;
    }
}

/**
 * Získanie informácií o štúdiu z nastavení
 */
function getStudioInfo() {
    static $studioInfo = null;
    
    if ($studioInfo === null) {
        $studioInfo = [
            'name' => getSetting('studio_name', 'Láskavý Priestor'),
            'street' => getSetting('street', ''),
            'house_number' => getSetting('house_number', ''),
            'postal_code' => getSetting('postal_code', ''),
            'city' => getSetting('city', 'obec'),
            'phone' => getSetting('phone', '+421 000 000 000'),
            'email' => getSetting('email', 'info@laskavypriestor.eu'),
            'website' => getSetting('website', 'www.laskavypriestor.eu'),
            'iban' => getSetting('iban', 'SK0000000000000000000000'),
            'bank_name' => getSetting('bank_name', 'Banka'),
            'ico' => getSetting('ico', '00000000')
        ];
        
        // Formatovaná adresa
        $address_parts = array_filter([
            trim($studioInfo['street'] . ' ' . $studioInfo['house_number']),
            $studioInfo['postal_code'] ? ($studioInfo['postal_code'] . ' ' . $studioInfo['city']) : $studioInfo['city']
        ]);
        $studioInfo['full_address'] = implode(', ', $address_parts);
        
        // Krátka adresa pre footer
        $studioInfo['short_address'] = $studioInfo['city'] ?: 'Bratislava, Slovensko';
        if (!empty($studioInfo['street'])) {
            $studioInfo['short_address'] = $studioInfo['city'] . ', Slovensko';
        }
    }
    
    return $studioInfo;
}

/**
 * Generovanie email verification tokenu
 */
function generateEmailVerificationToken($userId = null) {
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Uloženie tokenu do databázy
    db()->query("
        INSERT INTO email_verifications (user_id, token, expires_at, created_at) 
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        token = VALUES(token), 
        expires_at = VALUES(expires_at), 
        created_at = NOW()
    ", [$userId, $token, $expiresAt]);
    
    return $token;
}

/**
 * Overenie email verification tokenu
 */
function verifyEmailToken($token) {
    $verification = db()->fetch("
        SELECT ev.*, u.email, u.name 
        FROM email_verifications ev 
        JOIN users u ON ev.user_id = u.id 
        WHERE ev.token = ? AND ev.expires_at > NOW() AND ev.verified_at IS NULL
    ", [$token]);
    
    if (!$verification) {
        return false;
    }
    
    // Označenie ako overené
    db()->query("
        UPDATE email_verifications 
        SET verified_at = NOW() 
        WHERE token = ?
    ", [$token]);
    
    // Aktivácia používateľa
    db()->query("
        UPDATE users 
        SET email_verified = 1, status = 'active' 
        WHERE id = ?
    ", [$verification['user_id']]);
    
    return $verification;
}

// sendEmailVerification function moved to email_functions.php to avoid conflicts

// generateEmailVerificationHtml function moved to email_functions.php to avoid conflicts

/**
 * Získanie používateľských registrácií
 */
function getUserRegistrations($userId) {
    return db()->fetchAll("
        SELECT r.*, yc.name as class_name, yc.date, yc.time_start, yc.time_end, yc.location, yc.price_with_credit
        FROM registrations r 
        JOIN yoga_classes yc ON r.class_id = yc.id 
        WHERE r.user_id = ? 
        ORDER BY yc.date DESC, yc.time_start DESC
    ", [$userId]);
}

/**
 * Získanie používateľských kurzových registrácií
 */
function getUserCourseRegistrations($userId) {
    return db()->fetchAll("
        SELECT cr.*, c.name as course_name, c.start_date, c.end_date, c.total_lessons, 
               u.name as lektor_name
        FROM course_registrations cr 
        JOIN courses c ON cr.course_id = c.id 
        LEFT JOIN users u ON c.instructor_id = u.id
        WHERE cr.user_id = ? AND cr.status = 'confirmed'
        ORDER BY c.start_date DESC
    ", [$userId]);
}

/**
 * Kontrola či môže používateľ evidovať dochádzku pre lekciu
 */
function canMarkAttendance($classId, $userRole = 'lektor') {
    $class = db()->fetch("SELECT date FROM yoga_classes WHERE id = ?", [$classId]);
    if (!$class) {
        return false;
    }
    
    $classDate = strtotime($class['date']);
    $today = strtotime(date('Y-m-d'));
    $dayAfter = strtotime(date('Y-m-d', strtotime('+1 day', $classDate)));
    
    if ($userRole === 'admin') {
        // Admin môže evidovať najskôr v deň lekcie
        return $today >= $classDate;
    } else {
        // Lektor môže evidovať v deň lekcie a jeden deň po skončení
        return $today >= $classDate && $today <= $dayAfter;
    }
}

/**
 * Kontrola či je lekcia uzavretá pre registrácie
 */
function isClassClosed($classId) {
    $class = db()->fetch("SELECT date, time_end FROM yoga_classes WHERE id = ?", [$classId]);
    if (!$class) {
        return true;
    }
    
    $classDateTime = strtotime($class['date'] . ' ' . $class['time_end']);
    $now = time();
    
    return $now > $classDateTime;
}

/**
 * Kontrola či je možné zrušiť registráciu na lekciu (1 hodina pred začiatkom)
 */
function canCancelClassRegistration($classId) {
    $class = db()->fetch("SELECT date, time_start FROM yoga_classes WHERE id = ?", [$classId]);
    if (!$class) {
        return false;
    }
    
    $classStartTime = strtotime($class['date'] . ' ' . $class['time_start']);
    $cancellationDeadline = $classStartTime - (1 * 60 * 60); // 1 hodina pred začiatkom
    $now = time();
    
    return $now < $cancellationDeadline;
}

/**
 * Kontrola či je možné registrovať sa na lekciu (do konca dňa lekcie)
 */
function canRegisterForClass($classId) {
    $class = db()->fetch("SELECT date FROM yoga_classes WHERE id = ?", [$classId]);
    if (!$class) {
        return false;
    }
    
    $classDate = strtotime($class['date']);
    $endOfClassDay = strtotime(date('Y-m-d 23:59:59', $classDate));
    $now = time();
    
    return $now <= $endOfClassDay;
}

/**
 * Automatické uzavretie lekcií
 */
function autoCloseClasses() {
    try {
        $sql = "
            UPDATE yoga_classes 
            SET status = 'completed' 
            WHERE status = 'active' 
            AND (date < CURDATE() OR (date = CURDATE() AND time_end < CURTIME()))
        ";
        return db()->query($sql);
    } catch (Exception $e) {
        error_log("Error in autoCloseClasses: " . $e->getMessage());
        return false;
    }
}

/**
 * Označenie dochádzky pre študenta
 */
function markAttendance($classId, $userId, $attended, $notes = '', $markedBy = null) {
    try {
        // Skontroluj či už existuje záznam o dochádzke
        $existing = db()->fetch("
            SELECT id FROM attendance 
            WHERE class_id = ? AND user_id = ?
        ", [$classId, $userId]);
        
        if ($existing) {
            // Aktualizuj existujúci záznam
            return db()->query("
                UPDATE attendance 
                SET attended = ?, notes = ?, marked_by = ?, marked_at = NOW()
                WHERE class_id = ? AND user_id = ?
            ", [$attended, $notes, $markedBy, $classId, $userId]);
        } else {
            // Vytvor nový záznam
            return db()->query("
                INSERT INTO attendance (class_id, user_id, attended, notes, marked_by, marked_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$classId, $userId, $attended, $notes, $markedBy]);
        }
    } catch (Exception $e) {
        error_log("markAttendance error: " . $e->getMessage());
        return false;
    }
}

/**
 * Získanie dochádzky pre lekciu
 */
function getClassAttendance($classId) {
    return db()->fetchAll("
        SELECT a.*, u.name as client_name, m.name as marked_by_name
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN users m ON a.marked_by = m.id
        WHERE a.class_id = ?
        ORDER BY u.name ASC
    ", [$classId]);
}

/**
 * Získanie zrozumiteľného názvu pre typ transakcie
 */
function getTransactionTypeLabel($transactionType) {
    $typeLabels = [
        'manual_payment_approved' => 'Platba za kredity',
        'purchase' => 'Nákup kreditov', 
        'class_payment' => 'Platba za lekciu',
        'course_payment' => 'Platba za kurz',
        'refund' => 'Vrátenie kreditu',
        'admin_add' => 'Pridanie adminom',
        'admin_deduct' => 'Odpočítanie adminom',
        'demo_payment' => 'Demo platba',
        'test_debug' => 'Test transakcia',
        'test_rollback' => 'Test rollback'
    ];
    
    return $typeLabels[$transactionType] ?? ucfirst(str_replace('_', ' ', $transactionType));
}

/**
 * Získanie nastavení firmy zo settings tabuľky
 */
function getCompanySettings() {
    try {
        $settings = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $setting['setting_value'];
        }
        return $result;
    } catch (Exception $e) {
        // Return default values if settings table doesn't exist or has issues
        return [
            'company_name' => 'Láskavý Priestor',
            'company_iban' => 'SK7311000000002612938533',
            'company_email' => 'info@laskavypriestor.eu',
            'company_phone' => '+421 XXX XXX XXX',
            'company_address' => '',
            'company_ico' => ''
        ];
    }
}

/**
 * Získanie všetkých druhov lekcií
 */
function getLessonTypes() {
    try {
        return db()->fetchAll("SELECT * FROM lesson_types ORDER BY name");
    } catch (Exception $e) {
        error_log("Error fetching lesson types: " . $e->getMessage());
        return [];
    }
}

/**
 * Získanie všetkých úrovní
 */
function getLevels() {
    try {
        return db()->fetchAll("SELECT * FROM levels ORDER BY name");
    } catch (Exception $e) {
        error_log("Error fetching levels: " . $e->getMessage());
        return [];
    }
}

/**
 * Získanie názvu druhu lekcie podľa ID
 */
function getLessonTypeName($typeId) {
    if (!$typeId) return '';
    $type = db()->fetch("SELECT name FROM lesson_types WHERE id = ?", [$typeId]);
    return $type ? $type['name'] : '';
}

/**
 * Získanie názvu úrovne podľa ID
 */
function getLevelName($levelId) {
    if (!$levelId) return '';
    $level = db()->fetch("SELECT name FROM levels WHERE id = ?", [$levelId]);
    return $level ? $level['name'] : '';
}

/**
 * Získanie prihlásených klientov na lekciu
 */
function getRegisteredClients($classId) {
    try {
        return db()->fetchAll("
            SELECT u.id, u.name, u.email, r.status
            FROM registrations r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.class_id = ? AND r.status IN ('confirmed', 'pending')
            ORDER BY u.name
        ", [$classId]);
    } catch (Exception $e) {
        error_log("Error getting registered clients: " . $e->getMessage());
        return [];
    }
}

/**
 * Získanie informácií o opakovaných lekciách s klientmi
 */
function getRecurringSeriesWithClients($seriesId, $fromDate = null) {
    try {
        $currentDate = $fromDate ?: date('Y-m-d');
        
        return db()->fetchAll("
            SELECT 
                yc.id as class_id,
                yc.name as class_name,
                yc.date,
                yc.time_start as time,
                u.id as user_id,
                u.name as user_name,
                u.email as user_email,
                r.status as registration_status
            FROM yoga_classes yc
            JOIN registrations r ON yc.id = r.class_id
            JOIN users u ON r.user_id = u.id
            WHERE yc.recurring_series_id = ? 
            AND yc.date >= ?
            AND r.status IN ('confirmed', 'pending')
            ORDER BY yc.date, yc.time_start, u.name
        ", [$seriesId, $currentDate]);
    } catch (Exception $e) {
        error_log("Error getting recurring series with clients: " . $e->getMessage());
        return [];
    }
}





?>