<?php
// Email funkcionalita pomocou SendGrid

class EmailService {
    private $apiKey;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->apiKey = SENDGRID_API_KEY;
        $this->fromEmail = FROM_EMAIL;
        $this->fromName = FROM_NAME;
    }
    
    public function sendEmail($to, $subject, $htmlContent, $textContent = null) {
        if (empty($this->apiKey) || $this->apiKey === 'SG.your_sendgrid_api_key_here') {
            error_log('SendGrid API key not configured');
            return false;
        }
        
        $data = [
            'personalizations' => [
                [
                    'to' => [
                        [
                            'email' => $to
                        ]
                    ]
                ]
            ],
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName
            ],
            'subject' => $subject,
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $htmlContent
                ]
            ]
        ];
        
        if ($textContent) {
            $data['content'][] = [
                'type' => 'text/plain',
                'value' => $textContent
            ];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    public function sendRegistrationConfirmation($userEmail, $userName, $className, $classDate, $classTime) {
        $subject = 'Potvrdenie registrácie - ' . $className;
        
        $htmlContent = $this->getEmailTemplate('registration_confirmation', [
            'user_name' => $userName,
            'class_name' => $className,
            'class_date' => formatDate($classDate),
            'class_time' => formatTime($classTime)
        ]);
        
        return $this->sendEmail($userEmail, $subject, $htmlContent);
    }
    
    public function sendCancellationNotification($userEmail, $userName, $className, $refundAmount = null) {
        $subject = 'Zrušenie registrácie - ' . $className;
        
        $htmlContent = $this->getEmailTemplate('cancellation_notification', [
            'user_name' => $userName,
            'class_name' => $className,
            'refund_amount' => $refundAmount ? formatPrice($refundAmount) : null
        ]);
        
        return $this->sendEmail($userEmail, $subject, $htmlContent);
    }
    
    public function sendClassReminder($userEmail, $userName, $className, $classDate, $classTime) {
        $subject = 'Pripomienka lekcie - ' . $className;
        
        $htmlContent = $this->getEmailTemplate('class_reminder', [
            'user_name' => $userName,
            'class_name' => $className,
            'class_date' => formatDate($classDate),
            'class_time' => formatTime($classTime)
        ]);
        
        return $this->sendEmail($userEmail, $subject, $htmlContent);
    }
    
    private function getEmailTemplate($template, $variables = []) {
        // Basic email template
        $baseTemplate = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{{subject}}</title>
            <style>
                body { font-family: 'Roboto', Arial, sans-serif; line-height: 1.6; color: #333; background-color: #faf8f5; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #a8b5a0 0%, #8db3a0 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
                .button { display: inline-block; background: #a8b5a0; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                .logo { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">Láskavý Priestor</div>
                    <p>S úctou k sebe</p>
                </div>
                <div class="content">
                    {{content}}
                </div>
                <div class="footer">
                    <p>Láskavý Priestor | info@laskavypriestor.eu</p>
                    <p>S úctou k sebe. Miesto, kde sa môžeš zastaviť. Nadýchnuť. Byť.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $content = '';
        
        switch ($template) {
            case 'registration_confirmation':
                $content = '
                    <h2>Registrácia potvrdená!</h2>
                    <p>Milý/á ' . e($variables['user_name']) . ',</p>
                    <p>Ďakujeme za registráciu na lekciu <strong>' . e($variables['class_name']) . '</strong>.</p>
                    <p><strong>Detaily lekcie:</strong></p>
                    <ul>
                        <li>Dátum: ' . e($variables['class_date']) . '</li>
                        <li>Čas: ' . e($variables['class_time']) . '</li>
                        <li>Miesto: Láskavý Priestor štúdio</li>
                    </ul>
                    <p>Tešíme sa na stretnutie s vami!</p>
                    <a href="' . BASE_URL . '" class="button">Prejsť do systému</a>
                ';
                break;
                
            case 'cancellation_notification':
                $content = '
                    <h2>Registrácia zrušená</h2>
                    <p>Milý/á ' . e($variables['user_name']) . ',</p>
                    <p>Vaša registrácia na lekciu <strong>' . e($variables['class_name']) . '</strong> bola úspešne zrušená.</p>
                ';
                if ($variables['refund_amount']) {
                    $content .= '<p>Kredit vo výške <strong>' . e($variables['refund_amount']) . '</strong> bol vrátený na váš účet.</p>';
                }
                $content .= '
                    <p>Budeme sa tešiť na vaše ďalšie návštevy!</p>
                    <a href="' . BASE_URL . 'pages/classes.php" class="button">Prehliadnuť lekcie</a>
                ';
                break;
                
            case 'class_reminder':
                $content = '
                    <h2>Pripomienka lekcie</h2>
                    <p>Milý/á ' . e($variables['user_name']) . ',</p>
                    <p>Pripomíname vám lekciu <strong>' . e($variables['class_name']) . '</strong>, ktorá sa koná zajtra.</p>
                    <p><strong>Detaily:</strong></p>
                    <ul>
                        <li>Dátum: ' . e($variables['class_date']) . '</li>
                        <li>Čas: ' . e($variables['class_time']) . '</li>
                        <li>Miesto: Láskavý Priestor štúdio</li>
                    </ul>
                    <p>Odporúčame prísť 10 minút pred začiatkom lekcie.</p>
                    <p>Tešíme sa na vás!</p>
                ';
                break;
        }
        
        return str_replace('{{content}}', $content, $baseTemplate);
    }
}

// Globálna instancia
function emailService() {
    static $instance = null;
    if ($instance === null) {
        $instance = new EmailService();
    }
    return $instance;
}
?>