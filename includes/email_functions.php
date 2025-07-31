<?php
/**
 * Email functions for the yoga studio application
 * Contains functions for sending various types of emails
 */

/**
 * Send email using PHP mail() function
 */
function sendEmail($to, $subject, $htmlBody) {
    try {
        // Get email settings from payment config
        $fromEmail = defined('PAYMENT_EMAIL_FROM') ? PAYMENT_EMAIL_FROM : 'info@laskavypriestor.eu';
        $companyName = defined('COMPANY_NAME') ? COMPANY_NAME : 'Láskavý Priestor';
        
        // Ensure proper UTF-8 encoding for subject and body
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        
        // Set headers with proper encoding
        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'From: =?UTF-8?B?' . base64_encode($companyName) . '?= <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        // Log email attempt for debugging
        error_log("Sending email to: $to, Subject length: " . strlen($htmlBody) . " chars");
        
        // Send email
        $result = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        
        if (!$result) {
            error_log("Mail function failed for: $to - Check server mail configuration");
            return false;
        }
        
        error_log("Email sent successfully to: $to");
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate payment instructions HTML email
 */
/**
 * Send workshop payment email with instructions
 */
function sendWorkshopPaymentEmail($data) {
    $subject = 'Pokyny na platbu za workshop - ' . $data['workshop_title'];
    $htmlBody = generateWorkshopPaymentHtml($data);
    
    return sendEmail($data['user_email'], $subject, $htmlBody);
}

/**
 * Send course payment email with instructions
 */
function sendCoursePaymentEmail($data) {
    $subject = 'Pokyny na platbu za kurz - ' . $data['course_name'];
    $htmlBody = generateCoursePaymentHtml($data);
    
    return sendEmail($data['user_email'], $subject, $htmlBody);
}

/**
 * Generate workshop payment instructions HTML
 */
function generateWorkshopPaymentHtml($data) {
    $userName = $data['user_name'];
    $workshopTitle = $data['workshop_title'] ?? $data['workshop_name'];
    $amount = $data['amount'];
    $variableSymbol = $data['variable_symbol'];
    $studioInfo = $data['studio_info'];
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pokyny na platbu za workshop</title>
        <style>
            body { font-family: Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f6f0; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #8db3a0 0%, #6a9d7e 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
            .content { padding: 30px 20px; }
            .amount { font-size: 28px; font-weight: bold; color: #8db3a0; text-align: center; margin: 15px 0; }
            .vs-code { font-size: 20px; font-weight: bold; color: #333; background: #e8f0e5; padding: 10px; border-radius: 6px; margin: 10px 0; letter-spacing: 2px; text-align: center; }
            .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #4a4a4a; color: white; padding: 20px; text-align: center; font-size: 14px; }
            .payment-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .qr-container { text-align: center; margin: 20px 0; }
            .payment-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
            .payment-row:last-child { border-bottom: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔧 Potvrdenie registrácie na workshop</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Ďakujeme za registráciu!</p>
            </div>
            
            <div class="content">
                <p>Vážený/á <strong>' . htmlspecialchars($userName) . '</strong>,</p>
                
                <p>ďakujeme za registráciu na workshop <strong>' . htmlspecialchars($workshopTitle) . '</strong>.</p>
                
                <div class="amount">' . number_format($amount, 2) . ' EUR</div>
                
                <div class="payment-details">
                    <h3 style="color: #8db3a0; margin-top: 0;">Platobné údaje</h3>
                    <div class="payment-row">
                        <strong>IBAN:</strong>
                        <span>' . htmlspecialchars($studioInfo['banka'] ?? 'SK1234567890123456789012') . '</span>
                    </div>
                    <div class="payment-row">
                        <strong>Suma:</strong>
                        <span>' . number_format($amount, 2) . ' EUR</span>
                    </div>
                    <div class="payment-row">
                        <strong>Variabilný symbol:</strong>
                        <span style="color: #d63384; font-weight: bold;">' . htmlspecialchars($variableSymbol) . '</span>
                    </div>
                    <div class="payment-row">
                        <strong>Príjemca:</strong>
                        <span>' . htmlspecialchars($studioInfo['nazov'] ?? 'Láskavý Priestor') . '</span>
                    </div>
                    <div class="payment-row">
                        <strong>Správa pre príjemcu:</strong>
                        <span>Workshop: ' . htmlspecialchars($workshopTitle) . '</span>
                    </div>
                </div>
                
                ' . (isset($data['qr_data']) ? '
                <div class="qr-container">
                    <h4 style="color: #8db3a0;">QR kód pre rýchlu platbu</h4>
                    <img src="data:image/png;base64,' . $data['qr_data'] . '" alt="QR kód pre platbu" style="border: 1px solid #ddd; border-radius: 8px;">
                    <p style="font-size: 14px; color: #666;">Naskenujte QR kód v mobile bankingu pre automatické vyplnenie údajov</p>
                </div>
                ' : '') . '
                
                <div class="alert">
                    <h4 style="margin-top: 0; color: #856404;">⚠️ Dôležité upozornenie</h4>
                    <ul style="margin-bottom: 0;">
                        <li>Platbu prosím uhraďte <strong>do 24 hodín</strong> od registrácie</li>
                        <li>Neuhradené registrácie budú automaticky zrušené</li>
                        <li>Po uhradení platby vám príde email s potvrdením</li>
                        <li>Pri problémoch s platbou nás kontaktujte</li>
                    </ul>
                </div>
                
                <p>S láskou,<br>
                <strong>Tím ' . htmlspecialchars($studioInfo['nazov'] ?? 'Láskavý Priestor') . '</strong></p>
            </div>
            
            <div class="footer">
                <p>Tento email bol odoslaný automaticky. Prosím, neodpovedajte na túto správu.</p>
                <p>' . htmlspecialchars($studioInfo['nazov'] ?? 'Láskavý Priestor') . ' | ' . htmlspecialchars($studioInfo['email'] ?? 'info@laskavypriestor.eu') . '</p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Generate course payment instructions HTML
 */
function generateCoursePaymentHtml($data) {
    $userName = $data['user_name'];
    $courseName = $data['course_name'];
    $amount = $data['amount'];
    $variableSymbol = $data['variable_symbol'];
    $studioInfo = $data['studio_info'];
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pokyny na platbu za kurz</title>
        <style>
            body { font-family: Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f6f0; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #8db3a0 0%, #6a9d7e 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
            .content { padding: 30px 20px; }
            .amount { font-size: 28px; font-weight: bold; color: #8db3a0; text-align: center; margin: 15px 0; }
            .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #4a4a4a; color: white; padding: 20px; text-align: center; font-size: 14px; }
            .payment-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .qr-container { text-align: center; margin: 20px 0; }
            .payment-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
            .payment-row:last-child { border-bottom: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎓 Potvrdenie registrácie na kurz</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Ďakujeme za registráciu!</p>
            </div>
            
            <div class="content">
                <p>Vážený/á <strong>' . htmlspecialchars($userName) . '</strong>,</p>
                
                <p>ďakujeme za registráciu na kurz <strong>' . htmlspecialchars($courseName) . '</strong>.</p>
                
                <div class="amount">' . number_format($amount, 2) . ' EUR</div>
                
                <div class="payment-details">
                    <h3 style="color: #8db3a0; margin-top: 0;">Platobné údaje</h3>
                    <div class="payment-row">
                        <strong>IBAN:</strong>
                        <span>' . htmlspecialchars($studioInfo['banka'] ?? 'SK1234567890123456789012') . '</span>
                    </div>
                    <div class="payment-row">
                        <strong>Suma:</strong>
                        <span>' . number_format($amount, 2) . ' EUR</span>
                    </div>
                    <div class="payment-row">
                        <strong>Variabilný symbol:</strong>
                        <span style="color: #d63384; font-weight: bold;">' . htmlspecialchars($variableSymbol) . '</span>
                    </div>
                    <div class="payment-row">
                        <strong>Príjemca:</strong>
                        <span>' . htmlspecialchars($studioInfo['nazov'] ?? 'Láskavý Priestor') . '</span>
                    </div>
                    <div class="payment-row">
                        <strong>Správa pre príjemcu:</strong>
                        <span>Kurz: ' . htmlspecialchars($courseName) . '</span>
                    </div>
                </div>
                
                ' . (isset($data['qr_data']) ? '
                <div class="qr-container">
                    <h4 style="color: #8db3a0;">QR kód pre rýchlu platbu</h4>
                    <img src="data:image/png;base64,' . $data['qr_data'] . '" alt="QR kód pre platbu" style="border: 1px solid #ddd; border-radius: 8px;">
                    <p style="font-size: 14px; color: #666;">Naskenujte QR kód v mobile bankingu pre automatické vyplnenie údajov</p>
                </div>
                ' : '') . '
                
                <div class="alert">
                    <h4 style="margin-top: 0; color: #856404;">⚠️ Dôležité upozornenie</h4>
                    <ul style="margin-bottom: 0;">
                        <li>Platbu prosím uhraďte <strong>do 24 hodín</strong> od registrácie</li>
                        <li>Neuhradené registrácie budú automaticky zrušené</li>
                        <li>Po uhradení platby vám príde email s potvrdením</li>
                        <li>Pri problémoch s platbou nás kontaktujte</li>
                    </ul>
                </div>
                
                <p>S láskou,<br>
                <strong>Tím ' . htmlspecialchars($studioInfo['nazov'] ?? 'Láskavý Priestor') . '</strong></p>
            </div>
            
            <div class="footer">
                <p>Tento email bol odoslaný automaticky. Prosím, neodpovedajte na túto správu.</p>
                <p>' . htmlspecialchars($studioInfo['nazov'] ?? 'Láskavý Priestor') . ' | ' . htmlspecialchars($studioInfo['email'] ?? 'info@laskavypriestor.eu') . '</p>
            </div>
        </div>
    </body>
    </html>';
}

function generatePaymentInstructionsHtml($userName, $packageName, $amount, $variableSymbol) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pokyny na dobíjanie kreditu</title>
        <style>
            body { font-family: Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f6f0; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #8db3a0 0%, #6a9d7e 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
            .content { padding: 30px 20px; }
            .amount { font-size: 28px; font-weight: bold; color: #8db3a0; text-align: center; margin: 15px 0; }
            .vs-code { font-size: 20px; font-weight: bold; color: #333; background: #e8f0e5; padding: 10px; border-radius: 6px; margin: 10px 0; letter-spacing: 2px; text-align: center; }
            .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #4a4a4a; color: white; padding: 20px; text-align: center; font-size: 14px; }
            .btn { display: inline-block; background: linear-gradient(135deg, #8db3a0, #6a9d7e); color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🧘‍♀️ Ďakujeme za objednávku!</h1>
                <p>Pokyny na dobíjanie kreditu</p>
            </div>
            
            <div class="content">
                <h2>Dobrý deň ' . htmlspecialchars($userName) . ',</h2>
                
                <p>Ďakujeme za objednávku kreditného balíčka <strong>' . htmlspecialchars($packageName) . '</strong>!</p>
                
                <div class="amount">' . formatPrice($amount) . '</div>
                
                <h3>📋 Platobné údaje</h3>
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 12px; font-weight: bold; background: #f8f6f0;">IBAN:</td>
                        <td style="padding: 12px; font-family: monospace;">SK7311000000002612938533</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 12px; font-weight: bold; background: #f8f6f0;">Suma:</td>
                        <td style="padding: 12px; font-weight: bold; color: #8db3a0;">' . formatPrice($amount) . '</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 12px; font-weight: bold; background: #f8f6f0;">Variabilný symbol:</td>
                        <td style="padding: 12px;">
                            <div class="vs-code">' . htmlspecialchars($variableSymbol) . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: bold; background: #f8f6f0;">Správa pre príjemcu:</td>
                        <td style="padding: 12px;">Dobíjanie kreditu - ' . htmlspecialchars($packageName) . '</td>
                    </tr>
                </table>
                
                <div style="background: #e8f0e5; border-left: 4px solid #8db3a0; padding: 20px; margin: 20px 0;">
                    <h4 style="margin-top: 0; color: #8db3a0;">📱 QR kód pre rýchlu platbu</h4>
                    <p>Ak používate mobilnú bankovú aplikáciu, môžete použiť QR kód na našej stránke pre rýchle vyplnenie platobných údajov.</p>
                    <p><a href="https://www.laskavypriestor.eu/pages/payment-confirmation.php?request_id=' . $variableSymbol . '" class="btn">Zobraziť QR kód</a></p>
                </div>
                
                <div style="background: #e8f0e5; border: 2px solid #8db3a0; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: #8db3a0; margin-top: 0;">💰 Alternatíva platby</h4>
                    <p style="margin-bottom: 0;"><strong>Kredit môžete uhradiť aj v hotovosti v štúdiu pred lekciou.</strong></p>
                    <p style="margin-bottom: 0;">Jednoducho sa prihláste na lekciu a dobitie kreditu uveďte pred začiatkom.</p>
                </div>
                
                <h3>📋 Ďalšie kroky:</h3>
                <ol>
                    <li>Uskutočnite platbu pomocou vyššie uvedených údajov</li>
                    <li>Platba bude spracovaná do 24 hodín</li>
                    <li>Kredit bude automaticky pripísaný na váš účet</li>
                    <li>Dostanete potvrdzovacie email o pripísaní kreditu</li>
                </ol>
                
                <div class="alert">
                    <strong>Dôležité:</strong> Bez správneho variabilného symbolu nebude možné platbu spárovať s vaším účtom.
                </div>
                
                <p>V prípade otázok nás kontaktujte na <a href="mailto:info@laskavypriestor.eu">info@laskavypriestor.eu</a> alebo na čísle +421 XXX XXX XXX.</p>
                
                <p>S láskou,<br><strong>Tím Láskavého Priestoru</strong></p>
            </div>
            
            <div class="footer">
                <p>' . COMPANY_NAME . ' | ' . COMPANY_ADDRESS . '</p>
                <p>IČO: ' . COMPANY_ICO . ' | DIČ: ' . COMPANY_DIC . '</p>
                <p><a href="https://www.laskavypriestor.eu" style="color: #a8b5a0;">www.laskavypriestor.eu</a></p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Send course payment instructions
 */
function sendCoursePaymentInstructions($userEmail, $userName, $course, $variableSymbol, $amount) {
    $qrData = generateQRPaymentString($amount, $variableSymbol, "Kurz: " . $course['name']);
    $qrCodeHtml = displayPaymentQRCode($qrData);
    
    $subject = "Pokyny na úhradu kurzu: " . $course['name'];
    
    $html = generateCoursePaymentEmailHtml($userName, $course, $amount, $variableSymbol);
    
    return sendEmail($userEmail, $subject, $html);
}

/**
 * Send payment instructions for standalone class
 */
function sendClassPaymentInstructions($email, $name, $class, $variableSymbol, $amount) {
    require_once __DIR__ . '/qr_generator.php';
    $companySettings = getCompanySettings();
    $qrData = generateQRPaymentString($amount, $variableSymbol, "Lekcia: " . $class['name']);
    $qrCodeHtml = displayPaymentQRCode($qrData);
    
    $subject = "Platobné pokyny pre lekciu: " . $class['name'];
    
    $htmlBody = generateClassPaymentInstructionsHtml($name, $class, $variableSymbol, $amount, $companySettings, $qrCodeHtml);
    
    return sendEmail($email, $subject, $htmlBody);
}

/**
 * Generate HTML for class payment instructions
 */
function generateClassPaymentInstructionsHtml($name, $class, $variableSymbol, $amount, $settings, $qrCodeHtml) {
    $formattedAmount = formatPrice($amount);
    $formattedDate = formatDate($class['date']);
    $formattedTime = formatTime($class['time_start']) . ' - ' . formatTime($class['time_end']);
    
    return "
    <div style='font-family: Roboto, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f6f0;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <h1 style='color: #4a4a4a; margin-bottom: 10px;'>Láskavý Priestor</h1>
            <p style='color: #8db3a0; font-size: 16px; margin: 0;'>Jogové štúdio</p>
        </div>
        
        <div style='background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <h2 style='color: #4a4a4a; margin-bottom: 20px;'>Ďakujeme za registráciu!</h2>
            
            <p style='color: #4a4a4a; margin-bottom: 25px;'>Milý/á {$name},</p>
            
            <p style='color: #4a4a4a; margin-bottom: 25px;'>
                Úspešne ste sa zaregistrovali na lekciu <strong>{$class['name']}</strong>, 
                ktorá sa koná <strong>{$formattedDate} o {$formattedTime}</strong> 
                v priestoroch {$class['location']}.
            </p>
            
            <div style='background-color: #e8f0e5; padding: 20px; border-radius: 8px; margin-bottom: 25px;'>
                <h3 style='color: #4a4a4a; margin-bottom: 15px;'>Platobné údaje</h3>
                <p style='margin: 5px 0; color: #4a4a4a;'><strong>Suma:</strong> {$formattedAmount}</p>
                <p style='margin: 5px 0; color: #4a4a4a;'><strong>IBAN:</strong> {$settings['company_iban']}</p>
                <p style='margin: 5px 0; color: #4a4a4a;'><strong>Variabilný symbol:</strong> {$variableSymbol}</p>
                <p style='margin: 5px 0; color: #4a4a4a;'><strong>Správa pre príjemcu:</strong> Lekcia: {$class['name']}</p>
            </div>
            
            <div style='text-align: center; margin-bottom: 25px;'>
                <h4 style='color: #4a4a4a; margin-bottom: 15px;'>QR kód pre platbu</h4>
                {$qrCodeHtml}
                <p style='font-size: 12px; color: #666; margin-top: 10px;'>
                    Naskenujte QR kód mobilnou bankou pre rýchlu platbu
                </p>
            </div>
            
            <div style='background-color: #fdf9f0; padding: 15px; border-radius: 6px; border-left: 4px solid #8db3a0; margin-bottom: 20px;'>
                <p style='margin: 0; color: #4a4a4a; font-size: 14px;'>
                    <strong>Dôležité:</strong> Vaše miesto na lekcii bude potvrdené po uhradení platby. 
                    Platbu prosím uhraďte najneskôr 24 hodín pred začiatkom lekcie.
                </p>
            </div>
            
            <p style='color: #4a4a4a; margin-bottom: 20px;'>
                V prípade otázok nás neváhajte kontaktovať na <a href='mailto:{$settings['company_email']}' style='color: #8db3a0; text-decoration: none;'>{$settings['company_email']}</a> 
                alebo na telefónnom čísle {$settings['company_phone']}.
            </p>
            
            <p style='color: #4a4a4a; margin-bottom: 0;'>
                Tešíme sa na vás!<br>
                <strong>Tím Láskavého Priestoru</strong>
            </p>
        </div>
        
        <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
            <p>© 2025 Láskavý Priestor - Jogové štúdio</p>
        </div>
    </div>";
}

/**
 * Send email verification
 */
function sendEmailVerification($email, $name, $token) {
    $verificationLink = 'https://www.laskavypriestor.eu/pages/verify-email.php?token=' . urlencode($token);
    $subject = 'Overte váš email - Láskavý Priestor';
    
    $html = generateEmailVerificationHtml($name, $verificationLink);
    
    return sendEmail($email, $subject, $html);
}

/**
 * Send bulk email for communication system
 */
function sendBulkEmail($to, $recipientName, $subject, $message) {
    try {
        $fromEmail = defined('PAYMENT_EMAIL_FROM') ? PAYMENT_EMAIL_FROM : 'info@laskavypriestor.eu';
        $companyName = defined('COMPANY_NAME') ? COMPANY_NAME : 'Láskavý Priestor';
        
        // Create HTML email
        $htmlBody = generateBulkEmailHtml($recipientName, $message);
        
        return sendEmail($to, $subject, $htmlBody);
        
    } catch (Exception $e) {
        error_log("Bulk email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate HTML template for bulk emails
 */
function generateBulkEmailHtml($recipientName, $message) {
    $companyName = defined('COMPANY_NAME') ? COMPANY_NAME : 'Láskavý Priestor';
    $logoUrl = defined('LOGO_URL') ? LOGO_URL : '';
    
    $html = '<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Správa od ' . htmlspecialchars($companyName) . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { background: linear-gradient(135deg, #a8b5a0, #8db3a0); padding: 20px; text-align: center; }
        .header h1 { color: white; margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .message { background-color: #f9f9f9; padding: 20px; border-left: 4px solid #a8b5a0; margin: 20px 0; white-space: pre-line; }
        .footer { background-color: #8db3a0; color: white; padding: 20px; text-align: center; font-size: 14px; }
        .footer a { color: white; }
        @media (max-width: 600px) {
            .container { width: 100% !important; }
            .content { padding: 20px !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($companyName) . '</h1>
        </div>
        
        <div class="content">
            <h2>Milý/Milá ' . htmlspecialchars($recipientName) . ',</h2>
            
            <div class="message">
                ' . htmlspecialchars($message) . '
            </div>
            
            <p>S pozdravom,<br>
            <strong>Tím ' . htmlspecialchars($companyName) . '</strong></p>
        </div>
        
        <div class="footer">
            <p><strong>' . htmlspecialchars($companyName) . '</strong></p>
            <p>Ak si už neželáte dostávať naše správy, kontaktujte nás emailom.</p>
        </div>
    </div>
</body>
</html>';

    return $html;
}

/**
 * Generate email verification HTML template
 */
function generateEmailVerificationHtml($userName, $verificationLink) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Overte váš email</title>
        <style>
            body { font-family: Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f6f0; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #8db3a0 0%, #6a9d7e 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
            .content { padding: 30px 20px; }
            .button { display: inline-block; background: linear-gradient(135deg, #8db3a0, #6a9d7e); color: white; text-decoration: none; padding: 15px 30px; border-radius: 25px; font-weight: bold; margin: 20px 0; }
            .button:hover { background: linear-gradient(135deg, #6a9d7e, #8db3a0); }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #4a4a4a; color: white; padding: 20px; text-align: center; font-size: 14px; }
        </style>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f6f0;">
        <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
            <div style="background: #8db3a0; color: white; padding: 30px 20px; text-align: center;">
                <h1 style="margin: 0; font-size: 24px; font-weight: bold;">🧘‍♀️ Vítajte v Láskavom Priestore</h1>
                <p style="margin: 10px 0 0 0;">Overte váš email pre dokončenie registrácie</p>
            </div>
            
            <div style="padding: 30px 20px;">
                <h2 style="margin: 0 0 20px 0; color: #333; font-size: 18px;">Dobrý deň ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ',</h2>
                
                <p style="margin: 0 0 15px 0; color: #333; line-height: 1.6;">Ďakujeme za registráciu v našom jogovom štúdiu Láskavý Priestor!</p>
                
                <p style="margin: 0 0 25px 0; color: #333; line-height: 1.6;">Pre dokončenie registrácie a aktiváciu vášho účtu je potrebné overiť vašu emailovú adresu.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '" style="display: inline-block; background: #8db3a0; color: white; text-decoration: none; padding: 15px 30px; border-radius: 6px; font-weight: bold; font-family: Arial, sans-serif;">Overiť môj email</a>
                </div>
                
                <div style="background: #f0f8f0; border: 2px solid #8db3a0; padding: 20px; margin: 20px 0;">
                    <p style="margin: 0; font-size: 14px; color: #333; line-height: 1.4;">
                        <strong>Alebo kliknite na tento link:</strong><br><br>
                        <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '" style="color: #8db3a0; text-decoration: underline; word-break: break-all; font-size: 12px;">' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '</a>
                    </p>
                </div>
                
                <p style="margin: 20px 0 10px 0; color: #333; line-height: 1.6;">Po overení emailu budete môcť:</p>
                <ul style="margin: 0 0 20px 20px; padding: 0; color: #333; line-height: 1.6;">
                    <li style="margin: 0 0 5px 0;">Registrovať sa na jogové lekcie a kurzy</li>
                    <li style="margin: 0 0 5px 0;">Spravovať svoj účet a kreditový systém</li>
                    <li style="margin: 0 0 5px 0;">Sledovanie vašej jogovej cesty</li>
                </ul>
                
                <p style="margin: 20px 0 15px 0; color: #333; line-height: 1.6;">V prípade problémov s aktiváciou nás kontaktujte na <a href="mailto:info@laskavypriestor.eu" style="color: #8db3a0; text-decoration: none;">info@laskavypriestor.eu</a>.</p>
                
                <p style="margin: 20px 0 0 0; color: #333; line-height: 1.6;">S láskou,<br><strong>Tím Láskavého Priestoru</strong></p>
            </div>
            
            <div style="background: #4a4a4a; color: white; padding: 20px; text-align: center; font-size: 14px;">
                <p style="margin: 0 0 5px 0; color: white;">Láskavý Priestor - Jogové štúdio</p>
                <p style="margin: 0; color: white;"><a href="https://www.laskavypriestor.eu" style="color: #a8b5a0; text-decoration: none;">www.laskavypriestor.eu</a></p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Send payment instruction email for credit purchase
 */
function sendPaymentInstructionEmail($user, $package, $variableSymbol, $qrCodeData) {
    $subject = "Pokyny na úhradu kreditu - Láskavý Priestor";
    
    $html = generatePaymentInstructionEmailHtml($user, $package, $variableSymbol, $qrCodeData);
    
    return sendEmail($user['email'], $subject, $html);
}

/**
 * Generate payment instruction email HTML for credit purchase
 */
function generatePaymentInstructionEmailHtml($user, $package, $variableSymbol, $qrCodeData) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pokyny na úhradu kreditu</title>
        <style>
            body { font-family: Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f6f0; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #8db3a0 0%, #6a9d7e 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
            .content { padding: 30px 20px; }
            .amount { font-size: 28px; font-weight: bold; color: #8db3a0; text-align: center; margin: 15px 0; }
            .vs-code { font-size: 20px; font-weight: bold; color: #333; background: #e8f0e5; padding: 10px; border-radius: 6px; margin: 10px 0; letter-spacing: 2px; text-align: center; }
            .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #4a4a4a; color: white; padding: 20px; text-align: center; font-size: 14px; }
            .btn { display: inline-block; background: linear-gradient(135deg, #8db3a0, #6a9d7e); color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🧘‍♀️ Ďakujeme za objednávku!</h1>
                <p>Pokyny na dobíjanie kreditu</p>
            </div>
            
            <div class="content">
                <h2>Dobrý deň ' . htmlspecialchars($user['name']) . ',</h2>
                
                <p>Ďakujeme za objednávku kreditného balíčka <strong>' . htmlspecialchars($package['name']) . '</strong>!</p>
                
                <div class="amount">' . number_format($package['price'], 2) . '€</div>
                
                <h3>📋 Platobné údaje</h3>
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 12px; font-weight: bold; background: #f8f6f0;">IBAN:</td>
                        <td style="padding: 12px; font-family: monospace;">SK7311000000002612938533</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 12px; font-weight: bold; background: #f8f6f0;">Suma:</td>
                        <td style="padding: 12px; font-weight: bold; color: #8db3a0;">' . number_format($package['price'], 2) . '€</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 12px; font-weight: bold; background: #f8f6f0;">Variabilný symbol:</td>
                        <td style="padding: 12px;">
                            <div class="vs-code">' . htmlspecialchars($variableSymbol) . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 12px; font-weight: bold; background: #f8f6f0;">Správa pre príjemcu:</td>
                        <td style="padding: 12px;">Dobíjanie kreditu - ' . htmlspecialchars($package['name']) . '</td>
                    </tr>
                </table>
                
                <div style="background: #e8f0e5; border-left: 4px solid #8db3a0; padding: 20px; margin: 20px 0;">
                    <h4 style="margin-top: 0; color: #8db3a0;">📱 QR kód pre rýchlu platbu</h4>
                    <p>Ak používate mobilnú bankovú aplikáciu, môžete použiť QR kód na našej stránke pre rýchle vyplnenie platobných údajov.</p>
                    <p><a href="https://www.laskavypriestor.eu/pages/payment-confirmation.php?request_id=' . $variableSymbol . '" class="btn">Zobraziť QR kód</a></p>
                </div>
                
                <div style="background: #e8f0e5; border: 2px solid #8db3a0; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: #8db3a0; margin-top: 0;">💰 Alternatíva platby</h4>
                    <p style="margin-bottom: 0;"><strong>Kredit môžete uhradiť aj v hotovosti v štúdiu pred lekciou.</strong></p>
                    <p style="margin-bottom: 0;">Jednoducho sa prihláste na lekciu a dobitie kreditu uveďte pred začiatkom.</p>
                </div>
                
                <h3>📋 Ďalšie kroky:</h3>
                <ol>
                    <li>Uskutočnite platbu pomocou vyššie uvedených údajov</li>
                    <li>Platba bude spracovaná do 24 hodín</li>
                    <li>Kredit bude automaticky pripísaný na váš účet</li>
                    <li>Dostanete potvrdzovacie email o pripísaní kreditu</li>
                </ol>
                
                <div class="alert">
                    <strong>Dôležité:</strong> Bez správneho variabilného symbolu nebude možné platbu spárovať s vaším účtom.
                </div>
                
                <p>V prípade otázok nás kontaktujte na <a href="mailto:info@laskavypriestor.eu">info@laskavypriestor.eu</a> alebo na čísle +421 XXX XXX XXX.</p>
                
                <p>S láskou,<br><strong>Tím Láskavého Priestoru</strong></p>
            </div>
            
            <div class="footer">
                <p>Láskavý Priestor - Jogové štúdio</p>
                <p><a href="https://www.laskavypriestor.eu" style="color: #a8b5a0;">www.laskavypriestor.eu</a></p>
            </div>
        </div>
    </body>
    </html>';
}



/**
 * Send class cancellation email to a client
 */
function sendClassCancellationEmail($user, $classes, $cancellationReason = '') {
    $subject = "Zrušenie lekcie - Láskavý Priestor";
    
    if (count($classes) > 1) {
        $subject = "Zrušenie lekcií - Láskavý Priestor";
    }
    
    $html = generateClassCancellationEmailHtml($user, $classes, $cancellationReason);
    
    return sendEmail($user['email'], $subject, $html);
}

/**
 * Generate class cancellation email HTML
 */
function generateClassCancellationEmailHtml($user, $classes, $cancellationReason = '') {
    $isMultiple = count($classes) > 1;
    
    $lessonsHtml = '';
    foreach ($classes as $class) {
        $dateFormatted = !empty($class['date']) ? date('d.m.Y', strtotime($class['date'])) : 'Neznámy dátum';
        $timeFormatted = !empty($class['time']) ? date('H:i', strtotime($class['time'])) : 'Neznámy čas';
        
        $lessonsHtml .= '
        <div style="background: #f8f9fa; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; border-radius: 4px;">
            <h4 style="margin: 0 0 5px 0; color: #dc3545;">' . htmlspecialchars($class['name'] ?? 'Neznáma lekcia') . '</h4>
            <p style="margin: 0; color: #666;">📅 ' . $dateFormatted . ' o ' . $timeFormatted . '</p>
        </div>';
    }
    
    $reasonHtml = '';
    if (!empty($cancellationReason)) {
        $reasonHtml = '
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="margin: 0 0 10px 0; color: #856404;">📝 Dôvod zrušenia:</h4>
            <p style="margin: 0; color: #856404;">' . htmlspecialchars($cancellationReason) . '</p>
        </div>';
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Zrušenie ' . ($isMultiple ? 'lekcií' : 'lekcie') . '</title>
        <style>
            body { font-family: Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f8f6f0; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
            .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
            .content { padding: 30px 20px; }
            .footer { background: #4a4a4a; color: white; padding: 20px; text-align: center; font-size: 14px; }
            .btn { display: inline-block; background: linear-gradient(135deg, #8db3a0, #6a9d7e); color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>⚠️ Zrušenie ' . ($isMultiple ? 'lekcií' : 'lekcie') . '</h1>
                <p>Informácia o zmenách v rozvrhu</p>
            </div>
            
            <div class="content">
                <h2>Dobrý deň ' . htmlspecialchars($user['name']) . ',</h2>
                
                <p>Ľutujeme, ale musíme vás informovať o zrušení ' . ($isMultiple ? 'nasledujúcich lekcií' : 'lekcie') . ', na ' . ($isMultiple ? 'ktoré ste boli prihlásený/á' : 'ktorú ste boli prihlásený/á') . ':</p>
                
                ' . $lessonsHtml . '
                
                ' . $reasonHtml . '
                
                <div style="background: #e8f0e5; border: 2px solid #8db3a0; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: #8db3a0; margin-top: 0;">💰 Vrátenie platby</h4>
                    <p style="margin-bottom: 10px;">Vaša registrácia bola automaticky zrušená a:</p>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>Použitý kredit bol vrátený na váš účet</li>
                        <li>Platba kartou bude automaticky vrátená</li>
                        <li>Hotovostná platba bude vrátená pri najbližšej návšteve štúdia</li>
                    </ul>
                </div>
                
                <div style="background: #f0f8f0; border-left: 4px solid #8db3a0; padding: 20px; margin: 20px 0;">
                    <h4 style="margin-top: 0; color: #8db3a0;">🔄 Alternatívne lekcie</h4>
                    <p style="margin-bottom: 10px;">Pozrite si náš aktuálny rozvrh a prihláste sa na inú lekciu:</p>
                    <p><a href="https://www.laskavypriestor.eu/pages/classes.php" class="btn">Zobraziť rozvrh</a></p>
                </div>
                
                <p>Za spôsobené nepríjemnosti sa ospravedlňujeme a tešíme sa na vás na iných lekciách!</p>
                
                <p>V prípade otázok nás kontaktujte na <a href="mailto:info@laskavypriestor.eu" style="color: #8db3a0;">info@laskavypriestor.eu</a>.</p>
                
                <p>S láskou,<br><strong>Tím Láskavého Priestoru</strong></p>
            </div>
            
            <div class="footer">
                <p>Láskavý Priestor - Jogové štúdio</p>
                <p><a href="https://www.laskavypriestor.eu" style="color: #a8b5a0;">www.laskavypriestor.eu</a></p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Process batch cancellation notifications for recurring series
 */
function sendRecurringSeriesCancellationNotifications($recurringSeriesId, $fromDate, $cancellationReason, $sendNotifications = true) {
    if (!$sendNotifications) {
        error_log("Notifications disabled for recurring series cancellation: $recurringSeriesId");
        return ['sent' => 0, 'errors' => 0];
    }
    
    try {
        $registrations = getRecurringSeriesWithClients($recurringSeriesId, $fromDate);
        
        if (empty($registrations)) {
            return ['sent' => 0, 'errors' => 0];
        }
        
        // Group registrations by user
        $userLessons = [];
        foreach ($registrations as $reg) {
            $userId = $reg['user_id'];
            if (!isset($userLessons[$userId])) {
                $userLessons[$userId] = [
                    'user' => [
                        'id' => $reg['user_id'],
                        'name' => $reg['user_name'],
                        'email' => $reg['user_email']
                    ],
                    'lessons' => []
                ];
            }
            
            $userLessons[$userId]['lessons'][] = [
                'id' => $reg['class_id'],
                'name' => $reg['class_name'],
                'date' => $reg['date'],
                'time' => $reg['time']
            ];
        }
        
        $sent = 0;
        $errors = 0;
        
        // Send one email per user with all their cancelled lessons
        foreach ($userLessons as $userData) {
            try {
                if (sendClassCancellationEmail($userData['user'], $userData['lessons'], $cancellationReason)) {
                    $sent++;
                    error_log("Cancellation notification sent to: " . $userData['user']['email'] . " for " . count($userData['lessons']) . " lessons");
                } else {
                    $errors++;
                    error_log("Failed to send cancellation notification to: " . $userData['user']['email']);
                }
            } catch (Exception $e) {
                $errors++;
                error_log("Error sending cancellation notification to " . $userData['user']['email'] . ": " . $e->getMessage());
            }
        }
        
        return ['sent' => $sent, 'errors' => $errors];
        
    } catch (Exception $e) {
        error_log("Error in batch cancellation notifications: " . $e->getMessage());
        return ['sent' => 0, 'errors' => 1];
    }
}

// verifyEmailToken function moved to functions.php to avoid conflicts

?>