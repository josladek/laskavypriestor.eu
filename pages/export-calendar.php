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

$currentUser = getCurrentUser();
$registrationId = isset($_GET['registration']) ? (int)$_GET['registration'] : 0;

if (!$registrationId) {
    header('Location: my-classes.php');
    exit;
}

// Get registration details
$registration = db()->fetch("
    SELECT r.*, yc.name, yc.description, yc.date, yc.time_start, yc.time_end, yc.location,
           u.name as lektor_name, u.email as instructor_email
    FROM registrations r 
    JOIN yoga_classes yc ON r.class_id = yc.id 
    LEFT JOIN users u ON yc.instructor_id = u.id
    WHERE r.id = ? AND r.user_id = ?
", [$registrationId, $currentUser['id']]);

if (!$registration) {
    header('Location: my-classes.php');
    exit;
}

// Generate iCal content
function generateICalContent($registration, $currentUser) {
    $startDateTime = new DateTime($registration['date'] . ' ' . $registration['time_start']);
    $endDateTime = new DateTime($registration['date'] . ' ' . $registration['time_end']);
    
    // Format dates for iCal (UTC)
    $startDateTime->setTimezone(new DateTimeZone('UTC'));
    $endDateTime->setTimezone(new DateTimeZone('UTC'));
    
    $startDate = $startDateTime->format('Ymd\THis\Z');
    $endDate = $endDateTime->format('Ymd\THis\Z');
    $now = gmdate('Ymd\THis\Z');
    
    $summary = $registration['name'];
    $description = $registration['description'] ?: 'Lekcia v Láskavom Priestore';
    $location = $registration['location'] ?: 'Láskavý Priestor';
    $instructor = $registration['lektor_name'] ?: 'Lektor';
    
    // Escape special characters for iCal
    $summary = str_replace([',', ';', '\\', "\n"], ['\\,', '\\;', '\\\\', '\\n'], $summary);
    $description = str_replace([',', ';', '\\', "\n"], ['\\,', '\\;', '\\\\', '\\n'], $description . "\\n\\nLektor: " . $instructor);
    $location = str_replace([',', ';', '\\', "\n"], ['\\,', '\\;', '\\\\', '\\n'], $location);
    
    $uid = 'class-' . $registration['id'] . '@laskavypriestor.eu';
    
    $ical = "BEGIN:VCALENDAR\r\n";
    $ical .= "VERSION:2.0\r\n";
    $ical .= "PRODID:-//Láskavý Priestor//Classes//SK\r\n";
    $ical .= "CALSCALE:GREGORIAN\r\n";
    $ical .= "METHOD:PUBLISH\r\n";
    $ical .= "X-WR-CALNAME:Láskavý Priestor - Lekcie\r\n";
    $ical .= "X-WR-CALDESC:Vaše registrované lekcie\r\n";
    $ical .= "X-WR-TIMEZONE:Europe/Bratislava\r\n";
    
    $ical .= "BEGIN:VEVENT\r\n";
    $ical .= "UID:" . $uid . "\r\n";
    $ical .= "DTSTAMP:" . $now . "\r\n";
    $ical .= "DTSTART:" . $startDate . "\r\n";
    $ical .= "DTEND:" . $endDate . "\r\n";
    $ical .= "SUMMARY:" . $summary . "\r\n";
    $ical .= "DESCRIPTION:" . $description . "\r\n";
    $ical .= "LOCATION:" . $location . "\r\n";
    $ical .= "STATUS:CONFIRMED\r\n";
    $ical .= "CATEGORIES:YOGA,HEALTH,FITNESS\r\n";
    
    // Add alarm 1 hour before
    $ical .= "BEGIN:VALARM\r\n";
    $ical .= "TRIGGER:-PT1H\r\n";
    $ical .= "ACTION:DISPLAY\r\n";
    $ical .= "DESCRIPTION:Pripomienka: Lekcia " . $summary . " začína o hodinu\r\n";
    $ical .= "END:VALARM\r\n";
    
    // Add alarm 15 minutes before
    $ical .= "BEGIN:VALARM\r\n";
    $ical .= "TRIGGER:-PT15M\r\n";
    $ical .= "ACTION:DISPLAY\r\n";
    $ical .= "DESCRIPTION:Pripomienka: Lekcia " . $summary . " začína o 15 minút\r\n";
    $ical .= "END:VALARM\r\n";
    
    $ical .= "END:VEVENT\r\n";
    $ical .= "END:VCALENDAR\r\n";
    
    return $ical;
}

$icalContent = generateICalContent($registration, $currentUser);

// Set headers for download
$filename = 'laska-priestor-' . $registration['name'] . '-' . $registration['date'] . '.ics';
$filename = preg_replace('/[^a-zA-Z0-9-.]/', '-', $filename);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($icalContent));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

echo $icalContent;
exit;
?>