<?php
// MS Outlook-style calendar with proportional event heights
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

/**
 * Calculate MS Outlook-style event positioning
 */
function calculateEventPosition($startTime, $endTime, $activeHours) {
    // Convert times to minutes from start of day
    $startMinutes = (int)substr($startTime, 0, 2) * 60 + (int)substr($startTime, 3, 2);
    $endMinutes = (int)substr($endTime, 0, 2) * 60 + (int)substr($endTime, 3, 2);
    
    $firstHour = min($activeHours);
    $hourHeight = 80; // pixels per hour
    
    // Calculate position within the visible hour grid
    // Find which hour slot this event starts in relative to active hours
    $startHour = (int)substr($startTime, 0, 2);
    $startHourIndex = array_search($startHour, $activeHours);
    
    if ($startHourIndex === false) {
        // If hour not found, find closest
        $startHourIndex = 0;
        foreach ($activeHours as $index => $hour) {
            if ($hour >= $startHour) {
                $startHourIndex = $index;
                break;
            }
        }
    }
    
    // Position from the start hour slot
    $startMinuteInHour = (int)substr($startTime, 3, 2);
    $topPosition = ($startHourIndex * $hourHeight) + ($startMinuteInHour / 60 * $hourHeight);
    
    // Calculate height based on duration
    $durationMinutes = $endMinutes - $startMinutes;
    $height = ($durationMinutes / 60) * $hourHeight;
    
    // Minimum height for readability
    $height = max($height, 20);
    
    return [
        'top' => $topPosition,
        'height' => $height
    ];
}

/**
 * Detect collision conflicts between events
 */
function detectCollisions($events) {
    $collisions = [];
    
    for ($i = 0; $i < count($events); $i++) {
        for ($j = $i + 1; $j < count($events); $j++) {
            if (eventsOverlap($events[$i], $events[$j])) {
                if (!isset($collisions[$events[$i]['id']])) {
                    $collisions[$events[$i]['id']] = [];
                }
                if (!isset($collisions[$events[$j]['id']])) {
                    $collisions[$events[$j]['id']] = [];
                }
                $collisions[$events[$i]['id']][] = $events[$j]['id'];
                $collisions[$events[$j]['id']][] = $events[$i]['id'];
            }
        }
    }
    
    return $collisions;
}

/**
 * Check if two events overlap in time
 */
function eventsOverlap($event1, $event2) {
    $start1 = strtotime($event1['date'] . ' ' . $event1['time_start']);
    $end1 = strtotime($event1['date'] . ' ' . $event1['time_end']);
    $start2 = strtotime($event2['date'] . ' ' . $event2['time_start']);
    $end2 = strtotime($event2['date'] . ' ' . $event2['time_end']);
    
    return ($start1 < $end2) && ($end1 > $start2);
}

/**
 * Get CSS collision class for event positioning
 */
function getCollisionClass($eventId, $collisions) {
    if (!isset($collisions[$eventId]) || empty($collisions[$eventId])) {
        return '';
    }
    
    $conflictCount = count($collisions[$eventId]) + 1; // +1 for the event itself
    
    if ($conflictCount === 2) {
        // For 2 overlapping events, alternate left/right
        return rand(0, 1) ? 'collision-left' : 'collision-right';
    } elseif ($conflictCount >= 3) {
        // For 3+ overlapping events, use thirds
        $position = array_rand(['collision-third-1', 'collision-third-2', 'collision-third-3']);
        return ['collision-third-1', 'collision-third-2', 'collision-third-3'][$position];
    }
    
    return '';
}

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../pages/login.php');
    exit;
}

$user = getCurrentUser();
$userId = $user['id'];

// Set current page for navigation
$currentPage = 'calendar';
$pageTitle = 'Online kalendár';

// Get view type (3day, week, month)
$viewType = $_GET['view'] ?? '3day';
$eventTypeFilter = $_GET['type'] ?? 'all';

// Calculate dates based on view type
$currentDate = $_GET['date'] ?? date('Y-m-d');
$currentMonth = (int)($_GET['month'] ?? date('n'));
$currentYear = (int)($_GET['year'] ?? date('Y'));

// Get 3-day period
$threeDays = [];
for ($i = 0; $i < 3; $i++) {
    $threeDays[] = date('Y-m-d', strtotime($currentDate . " +{$i} days"));
}

// Active hours will be calculated dynamically after loading events

// Get events for the selected period
$events = [];
if ($viewType === '3day') {
    $startDate = $threeDays[0];
    $endDate = $threeDays[2];
} elseif ($viewType === 'week') {
    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($currentDate)));
    $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($currentDate)));
    $startDate = $weekStart;
    $endDate = $weekEnd;
} else { // month
    $startDate = date('Y-m-01', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
    $endDate = date('Y-m-t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
}

// Get classes
$classes = db()->fetchAll("
    SELECT 
        yc.*,
        c.id as course_id,
        c.name as course_name,
        'class' as event_type,
        (SELECT COUNT(*) FROM registrations cr WHERE cr.class_id = yc.id AND cr.status = 'confirmed') as registered_count,
        CASE 
            WHEN EXISTS(SELECT 1 FROM registrations cr WHERE cr.class_id = yc.id AND cr.user_id = ? AND cr.status = 'confirmed') 
            THEN 1 ELSE 0 
        END as user_registered
    FROM yoga_classes yc
    LEFT JOIN courses c ON yc.course_id = c.id
    WHERE yc.date BETWEEN ? AND ? 
        AND yc.status = 'active'
    ORDER BY yc.date, yc.time_start
", [$userId, $startDate, $endDate]);

// Add classes to events array
foreach ($classes as $class) {
    $events[] = $class;
}

// DEBUG: Output date range being searched
error_log("Calendar DEBUG: Searching workshops between $startDate and $endDate (view: $viewType)");

// Get workshops
$workshops = db()->fetchAll("
    SELECT 
        w.id,
        w.title as name,
        w.date,
        w.time_start,
        w.time_end,
        w.capacity,
        w.description,
        'workshop' as event_type,
        NULL as course_id,
        NULL as course_name,
        (SELECT COUNT(*) FROM workshop_registrations wr WHERE wr.workshop_id = w.id AND wr.status IN ('confirmed', 'pending')) as registered_count,
        CASE 
            WHEN EXISTS(SELECT 1 FROM workshop_registrations wr WHERE wr.workshop_id = w.id AND wr.user_id = ? AND wr.status IN ('confirmed', 'pending')) 
            THEN 1 ELSE 0 
        END as user_registered
    FROM workshops w
    WHERE w.date BETWEEN ? AND ? 
        AND w.status = 'active'
    ORDER BY w.date, w.time_start
", [$userId, $startDate, $endDate]);

// DEBUG: Output workshop results
error_log("Calendar DEBUG: Found " . count($workshops) . " workshops");
foreach ($workshops as $ws) {
    error_log("Calendar DEBUG: Workshop - ID: {$ws['id']}, Title: {$ws['name']}, Date: {$ws['date']}");
}

// Add workshops to events array
foreach ($workshops as $workshop) {
    $events[] = $workshop;
}

// Calculate dynamic active hours based on events - show all hours that events touch
$activeHours = [];
foreach ($events as $event) {
    $startHour = (int)date('H', strtotime($event['time_start']));
    $endHour = (int)date('H', strtotime($event['time_end']));
    $endMinute = (int)date('i', strtotime($event['time_end']));
    
    // Add all hours from start to end
    for ($h = $startHour; $h <= $endHour; $h++) {
        if (!in_array($h, $activeHours)) {
            $activeHours[] = $h;
        }
    }
    
    // If event ends after the hour (e.g., 19:30), add next hour too
    if ($endMinute > 0 && $endHour < 23) {
        if (!in_array($endHour + 1, $activeHours)) {
            $activeHours[] = $endHour + 1;
        }
    }
}

// If no events, show default hours
if (empty($activeHours)) {
    $activeHours = [8, 9, 10, 17, 18, 19];
} else {
    sort($activeHours);
}

// Sort all events by date and time
usort($events, function($a, $b) {
    $dateCompare = strcmp($a['date'], $b['date']);
    if ($dateCompare === 0) {
        return strcmp($a['time_start'], $b['time_start']);
    }
    return $dateCompare;
});

// Store unfiltered events for counting in tiles
$allEvents = $events;

// Apply filter for display
if ($eventTypeFilter !== 'all') {
    $events = array_filter($events, function($event) use ($eventTypeFilter) {
        if ($eventTypeFilter === 'classes') {
            return $event['event_type'] === 'class' && empty($event['course_id']);
        } elseif ($eventTypeFilter === 'courses') {
            return $event['event_type'] === 'class' && !empty($event['course_id']);
        } elseif ($eventTypeFilter === 'workshops') {
            return $event['event_type'] === 'workshop';
        }
        return true;
    });
}

include '../includes/header.php';
?>

        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>📅 Online kalendár</h2>
                <div class="btn-group" role="group">
                    <a href="?view=3day&date=<?= date('Y-m-d') ?>&type=all" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-home me-2"></i>Dnes
                    </a>
                    <a href="?view=3day&date=<?= $currentDate ?>&type=<?= $eventTypeFilter ?>" 
                       class="btn <?= $viewType === '3day' ? 'btn-success' : 'btn-outline-success' ?>">
                        <i class="fas fa-calendar-day me-2"></i>3 dni
                    </a>
                    <a href="?view=week&date=<?= $currentDate ?>&type=<?= $eventTypeFilter ?>" 
                       class="btn <?= $viewType === 'week' ? 'btn-success' : 'btn-outline-success' ?>">
                        <i class="fas fa-calendar-week me-2"></i>Týždeň
                    </a>
                    <a href="?view=month&month=<?= $currentMonth ?>&year=<?= $currentYear ?>&type=<?= $eventTypeFilter ?>" 
                       class="btn <?= $viewType === 'month' ? 'btn-success' : 'btn-outline-success' ?>">
                        <i class="fas fa-calendar me-2"></i>Mesiac
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Event type filter tiles -->
    <div class="row g-3 mb-4">
        <?php 
        $filterColors = [
            'all' => ['bg' => '#E8F5E8', 'border' => '#A8D8A8', 'icon' => 'fas fa-th-large'],
            'classes' => ['bg' => '#E8F0FF', 'border' => '#B3D1FF', 'icon' => 'fas fa-dumbbell'],
            'courses' => ['bg' => '#F0E8FF', 'border' => '#D1B3FF', 'icon' => 'fas fa-graduation-cap'],
            'workshops' => ['bg' => '#FFF8E8', 'border' => '#FFE8B3', 'icon' => 'fas fa-tools'],
        ];
        
        $filterOptions = [
            'all' => 'Všetko',
            'classes' => 'Lekcie', 
            'courses' => 'Kurzy',
            'workshops' => 'Workshopy'
        ];
        
        $eventCounts = [
            'all' => count($allEvents),
            'classes' => count(array_filter($allEvents, fn($e) => $e['event_type'] === 'class' && empty($e['course_id']))),
            'courses' => count(array_filter($allEvents, fn($e) => $e['event_type'] === 'class' && !empty($e['course_id']))),
            'workshops' => count(array_filter($allEvents, fn($e) => $e['event_type'] === 'workshop'))
        ];
        ?>
        
        <?php foreach ($filterOptions as $filterKey => $filterName): 
            $color = $filterColors[$filterKey];
            $count = $eventCounts[$filterKey];
        ?>
            <div class="col-lg-3 col-md-6">
                <a href="?view=<?= $viewType ?>&<?= $viewType === 'month' ? 'month=' . $currentMonth . '&year=' . $currentYear : 'date=' . $currentDate ?>&type=<?= $filterKey ?>" 
                   class="text-decoration-none">
                    <div class="card h-100 filter-tile <?= $eventTypeFilter === $filterKey ? 'active' : '' ?>" 
                         style="background-color: <?= $color['bg'] ?>; border: 2px solid <?= $color['border'] ?>;">
                        <div class="card-body text-center">
                            <div class="mb-2">
                                <i class="<?= $color['icon'] ?> fa-2x" style="color: <?= $color['border'] ?>;"></i>
                            </div>
                            <h6 class="card-title fw-bold mb-2"><?= $filterName ?></h6>
                            <span class="badge rounded-pill" style="background-color: <?= $color['border'] ?>; color: white;">
                                <?= $count ?> <?= $count === 1 ? 'udalosť' : ($count < 5 ? 'udalosti' : 'udalostí') ?>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 3-DAY VIEW with MS Outlook positioning -->
    <?php if ($viewType === '3day'): ?>
        <div class="outlook-calendar">
            <div class="calendar-header text-center">
                <div class="row align-items-center">
                    <div class="col-2">
                        <a href="?view=3day&date=<?= date('Y-m-d', strtotime($currentDate . ' -3 days')) ?>&type=<?= $eventTypeFilter ?>" 
                           class="btn btn-outline-success">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>
                    <div class="col-8">
                        <h4 class="mb-0">Najbližšie 3 dni s udalosťami</h4>
                        <small>Počnúc dňom <?= date('d.m.Y', strtotime($currentDate)) ?></small>
                    </div>
                    <div class="col-2">
                        <a href="?view=3day&date=<?= date('Y-m-d', strtotime($currentDate . ' +3 days')) ?>&type=<?= $eventTypeFilter ?>" 
                           class="btn btn-outline-success">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="calendar-grid d-flex">
                <!-- Time column -->
                <div class="time-column" style="width: 80px; background: #f8f9fa;">
                    <div class="time-header" style="height: 60px; display: flex; align-items: center; justify-content: center; font-weight: bold; background: #8db3a0; color: white;">
                        Čas
                    </div>
                    <?php foreach ($activeHours as $hour): ?>
                        <div class="time-slot" style="height: 80px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #eee; font-size: 14px;">
                            <?= sprintf('%02d:00', $hour) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Day columns -->
                <?php 
                $dayNames = ['Nedeľa', 'Pondelok', 'Utorok', 'Streda', 'Štvrtok', 'Piatok', 'Sobota'];
                
                // Group events by date
                $eventsByDate = [];
                foreach ($events as $event) {
                    $eventsByDate[$event['date']][] = $event;
                }
                
                foreach ($threeDays as $currentDay):
                    $dayOfWeek = date('w', strtotime($currentDay));
                    $isToday = $currentDay === date('Y-m-d');
                ?>
                    <div class="day-column" style="flex: 1; border-left: 1px solid #ddd;">
                        <div class="day-header" style="height: 60px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: bold; background: <?= $isToday ? '#ffc107' : '#8db3a0' ?>; color: white; font-size: 12px; text-align: center;">
                            <?= $dayNames[$dayOfWeek] ?><br>
                            <small><?= date('d.m', strtotime($currentDay)) ?></small>
                        </div>
                        
                        <!-- MS Outlook-style positioned events -->
                        <div class="day-events" style="position: relative; height: <?= count($activeHours) * 80 ?>px;">
                            <!-- Hour background slots -->
                            <?php foreach ($activeHours as $index => $hour): ?>
                                <div class="hour-background" style="position: absolute; top: <?= $index * 80 ?>px; height: 80px; width: 100%; border-bottom: 1px solid #eee;"></div>
                            <?php endforeach; ?>
                            
                            <!-- Events positioned absolutely with proportional heights -->
                            <?php 
                            if (isset($eventsByDate[$currentDay])):
                                foreach ($eventsByDate[$currentDay] as $event):
                                    $maxParticipants = $event['capacity'] ?? 10;
                                    $registeredCount = $event['registered_count'] ?? 0;
                                    $isFull = $registeredCount >= $maxParticipants;
                                    $isRegistered = $event['user_registered'] ?? false;
                                    $isWorkshop = $event['event_type'] === 'workshop';
                                    $isCourseClass = !empty($event['course_id']) && $event['event_type'] === 'class';
                                    
                                    // Calculate MS Outlook-style positioning
                                    $position = calculateEventPosition($event['time_start'], $event['time_end'], $activeHours);
                                    
                                    // Event styling
                                    $bgColor = $isWorkshop ? '#FFF8E8' : ($isCourseClass ? '#F0E8FF' : '#E8F5E8');
                                    $borderColor = $isWorkshop ? '#FFE8B3' : ($isCourseClass ? '#D1B3FF' : '#A8D8A8');
                                    $typeIcon = $isWorkshop ? 'fas fa-tools' : ($isCourseClass ? 'fas fa-graduation-cap' : 'fas fa-dumbbell');
                                    
                                    // Click handlers
                                    $canClick = !$isCourseClass;
                                    $clickHandler = '';
                                    if ($canClick) {
                                        if ($isWorkshop) {
                                            $clickHandler = "showWorkshopDetails({$event['id']}, '" . htmlspecialchars($event['name']) . "', '{$event['date']}', '{$event['time_start']}', '{$event['time_end']}', {$registeredCount}, {$maxParticipants}, " . ($isRegistered ? 'true' : 'false') . ", " . ($isFull ? 'true' : 'false') . ")";
                                        } else {
                                            $clickHandler = "showClassDetails({$event['id']}, '" . htmlspecialchars($event['name']) . "', '{$event['date']}', '{$event['time_start']}', '{$event['time_end']}', {$registeredCount}, {$maxParticipants}, " . ($isRegistered ? 'true' : 'false') . ", " . ($isFull ? 'true' : 'false') . ")";
                                        }
                                    }
                            ?>
                                <div class="event-block" 
                                     style="position: absolute; 
                                            top: <?= $position['top'] + 6 ?>px; 
                                            height: <?= $position['height'] - 12 ?>px; 
                                            width: calc(100% - 16px); 
                                            left: 8px; 
                                            background: <?= $bgColor ?>; 
                                            border: 2px solid <?= $borderColor ?>; 
                                            border-radius: 6px; 
                                            padding: 6px; 
                                            font-size: 11px; 
                                            overflow: hidden;
                                            cursor: <?= $canClick ? 'pointer' : 'default' ?>;
                                            z-index: 10;"
                                     <?php if ($canClick): ?>
                                     onclick="<?= $clickHandler ?>"
                                     <?php endif; ?>
                                     title="<?= htmlspecialchars($event['name']) ?> <?= $isCourseClass ? '(Kurz: ' . htmlspecialchars($event['course_name']) . ')' : '' ?> (<?= $event['time_start'] ?>-<?= $event['time_end'] ?>)">
                                    
                                    <!-- Event type icon in top-right corner -->
                                    <i class="<?= $typeIcon ?> fa-2x" style="position: absolute; top: 4px; right: 4px; color: <?= $borderColor ?>; opacity: 0.8;"></i>
                                    
                                    <!-- Registration status icon in bottom-right corner -->
                                    <?php if ($isRegistered): ?>
                                        <i class="fas fa-check-circle fa-2x" style="position: absolute; bottom: 4px; right: 4px; color: #28a745;"></i>
                                    <?php endif; ?>
                                    
                                    <div style="font-weight: bold; margin-bottom: 2px; line-height: 1.2; padding-right: 30px; padding-bottom: <?= $isRegistered ? '30px' : '10px' ?>;">
                                        <?= htmlspecialchars($event['name']) ?>
                                    </div>
                                    <div style="margin-bottom: 2px; color: #666;">
                                        <?= date('H:i', strtotime($event['time_start'])) ?>-<?= date('H:i', strtotime($event['time_end'])) ?>
                                    </div>
                                    <div style="margin-bottom: 2px; color: #666;">
                                        <?= $registeredCount ?>/<?= $maxParticipants ?>
                                    </div>
<!--                                    <?php if ($isCourseClass): ?>
                                        <div style="font-size: 9px; opacity: 0.8; color: #666;">Kurz</div>
                                    <?php elseif ($isWorkshop): ?>
                                        <div style="font-size: 9px; opacity: 0.8; color: #666;">Workshop</div>
                                    <?php endif; ?> -->
                                </div>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Legend -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card legend-card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Legenda</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: linear-gradient(135deg, #E3F2FD, #90CAF9); border-color: #2196F3;"></span>
                                        <div>
                                            <i class="fas fa-dumbbell fa-2x me-2" style="color: #2196F3;"></i>
                                            <strong>Lekcie</strong> - Jednotlivé hodiny jogy
                                        </div>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: linear-gradient(135deg, #F3E5F5, #CE93D8); border-color: #9C27B0;"></span>
                                        <div>
                                            <i class="fas fa-graduation-cap fa-2x me-2" style="color: #9C27B0;"></i>
                                            <strong>Kurzy</strong> - Série prepojených lekcií
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: linear-gradient(135deg, #FFF3E0, #FFB74D); border-color: #FF9800;"></span>
                                        <div>
                                            <i class="fas fa-tools fa-2x me-2" style="color: #FF9800;"></i>
                                            <strong>Workshopy</strong> - Špeciálne tematické podujatia
                                        </div>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: #28a745; border-color: #28a745;"></span>
                                        <div>
                                            <i class="fas fa-check-circle fa-2x me-2" style="color: #28a745;"></i>
                                            <strong>Prihlásený</strong> - Ste registrovaný na túto udalosť
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- WEEKLY VIEW with MS Outlook positioning -->
    <?php if ($viewType === 'week'): ?>
        <div class="outlook-calendar">
            <div class="calendar-header text-center">
                <div class="row align-items-center">
                    <div class="col-2">
                        <a href="?view=week&date=<?= date('Y-m-d', strtotime($currentDate . ' -7 days')) ?>&type=<?= $eventTypeFilter ?>" 
                           class="btn btn-outline-success">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>
                    <div class="col-8">
                        <h4 class="mb-0">Týždňový prehľad</h4>
                        <small>Týždeň <?= date('d.m.Y', strtotime('monday this week', strtotime($currentDate))) ?> - <?= date('d.m.Y', strtotime('sunday this week', strtotime($currentDate))) ?></small>
                    </div>
                    <div class="col-2">
                        <a href="?view=week&date=<?= date('Y-m-d', strtotime($currentDate . ' +7 days')) ?>&type=<?= $eventTypeFilter ?>" 
                           class="btn btn-outline-success">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="week-grid d-flex">
                <!-- Time column -->
                <div class="time-column" style="width: 80px; background: #f8f9fa;">
                    <div class="time-header" style="height: 60px; display: flex; align-items: center; justify-content: center; font-weight: bold; background: #28a745; color: white;">
                        Čas
                    </div>
                    <?php foreach ($activeHours as $hour): ?>
                        <div class="time-slot" style="height: 80px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #eee; font-size: 14px;">
                            <?= sprintf('%02d:00', $hour) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Week day columns -->
                <?php 
                $dayNames = ['Nedeľa', 'Pondelok', 'Utorok', 'Streda', 'Štvrtok', 'Piatok', 'Sobota'];
                
                // Calculate week start (Monday)
                $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($currentDate)));
                $weekDays = [];
                for ($i = 0; $i < 7; $i++) {
                    $weekDays[] = date('Y-m-d', strtotime($weekStart . ' +' . $i . ' days'));
                }
                
                // Group events by date
                $eventsByDate = [];
                foreach ($events as $event) {
                    $eventsByDate[$event['date']][] = $event;
                }
                
                foreach ($weekDays as $dayDate):
                    $dayOfWeek = date('w', strtotime($dayDate));
                    $isToday = $dayDate === date('Y-m-d');
                    $dayEvents = isset($eventsByDate[$dayDate]) ? $eventsByDate[$dayDate] : [];
                ?>
                    <div class="day-column" style="flex: 1; border-left: 1px solid #ddd;">
                        <div class="day-header" style="height: 60px; display: flex; align-items: center; justify-content: center; font-weight: bold; background: <?= $isToday ? '#ffc107' : '#28a745' ?>; color: white; font-size: 12px; text-align: center;">
                            <?= $dayNames[$dayOfWeek] ?><br>
                            <small><?= date('d.m', strtotime($dayDate)) ?></small>
                        </div>
                        
                        <!-- MS Outlook-style positioned events -->
                        <div class="day-events" style="position: relative; height: <?= count($activeHours) * 80 ?>px;">
                            <?php 
                            if (!empty($dayEvents)):
                                // Detect collisions
                                $collisions = detectCollisions($dayEvents);
                                
                                foreach ($dayEvents as $event):
                                    // Event positioning
                                    $position = calculateEventPosition($event['time_start'], $event['time_end'], $activeHours);
                                    $collisionClass = getCollisionClass($event['id'], $collisions);
                                    
                                    // Event type and colors
                                    $isWorkshop = $event['event_type'] === 'workshop';
                                    $isCourseClass = !empty($event['course_id']);
                                    $isRegistered = $event['user_registered'] == 1;
                                    
                                    $registeredCount = $event['registered_count'] ?? 0;
                                    $maxParticipants = $event['capacity'] ?? 0;
                                    
                                    if ($isWorkshop) {
                                        $bgColor = 'linear-gradient(135deg, #FFF3E0, #FFB74D)';
                                        $borderColor = '#FF9800';
                                        $typeIcon = 'fas fa-tools';
                                        $canClick = true;
                                        $clickHandler = "showWorkshopDetails({$event['id']}, '" . addslashes($event['name']) . "', '{$event['date']}', '{$event['time_start']}', '{$event['time_end']}', {$registeredCount}, {$maxParticipants}, " . ($isRegistered ? 'true' : 'false') . ", " . ($registeredCount >= $maxParticipants ? 'true' : 'false') . ")";
                                    } elseif ($isCourseClass) {
                                        $bgColor = 'linear-gradient(135deg, #F3E5F5, #CE93D8)';
                                        $borderColor = '#9C27B0';
                                        $typeIcon = 'fas fa-graduation-cap';
                                        $canClick = false;
                                        $clickHandler = '';
                                    } else {
                                        $bgColor = 'linear-gradient(135deg, #E3F2FD, #90CAF9)';
                                        $borderColor = '#2196F3';
                                        $typeIcon = 'fas fa-dumbbell';
                                        $canClick = true;
                                        $clickHandler = "showClassDetails({$event['id']}, '" . addslashes($event['name']) . "', '{$event['date']}', '{$event['time_start']}', '{$event['time_end']}', {$registeredCount}, {$maxParticipants}, " . ($isRegistered ? 'true' : 'false') . ", " . ($registeredCount >= $maxParticipants ? 'true' : 'false') . ")";
                                    }
                            ?>
                                <div class="event-block <?= $collisionClass ?>" 
                                     style="position: absolute; 
                                            top: <?= $position['top'] + 6 ?>px; 
                                            height: <?= $position['height'] - 12 ?>px; 
                                            width: calc(100% - 16px); 
                                            left: 8px; 
                                            background: <?= $bgColor ?>; 
                                            border: 2px solid <?= $borderColor ?>; 
                                            border-radius: 6px; 
                                            padding: 8px; 
                                            font-size: 11px; 
                                            overflow: hidden;
                                            cursor: <?= $canClick ? 'pointer' : 'default' ?>;
                                            z-index: 10;"
                                     <?php if ($canClick): ?>
                                     onclick="<?= $clickHandler ?>"
                                     <?php endif; ?>
                                     title="<?= htmlspecialchars($event['name']) ?> <?= $isCourseClass ? '(Kurz: ' . htmlspecialchars($event['course_name']) . ')' : '' ?> (<?= $event['time_start'] ?>-<?= $event['time_end'] ?>)">
                                    
                                    <!-- Event type icon in top-right corner -->
                                    <i class="<?= $typeIcon ?> fa-2x" style="position: absolute; top: 4px; right: 4px; color: <?= $borderColor ?>; opacity: 0.8;"></i>
                                    
                                    <!-- Registration status icon in bottom-right corner -->
                                    <?php if ($isRegistered): ?>
                                        <i class="fas fa-check-circle fa-2x" style="position: absolute; bottom: 4px; right: 4px; color: #28a745;"></i>
                                    <?php endif; ?>
                                    
                                    <div style="font-weight: bold; margin-bottom: 2px; line-height: 1.2; padding-right: 30px; padding-bottom: <?= $isRegistered ? '30px' : '10px' ?>;">
                                        <?= htmlspecialchars($event['name']) ?>
                                    </div>
                                    <div style="margin-bottom: 2px; color: #666;">
                                        <?= date('H:i', strtotime($event['time_start'])) ?>-<?= date('H:i', strtotime($event['time_end'])) ?>
                                    </div>
                                    <div style="margin-bottom: 2px; color: #666;">
                                        <?= $registeredCount ?>/<?= $maxParticipants ?>
                                    </div>
<!--                                    <?php if ($isCourseClass): ?>
                                        <div style="font-size: 9px; opacity: 0.8; color: #666;">Kurz</div>
                                    <?php elseif ($isWorkshop): ?>
                                        <div style="font-size: 9px; opacity: 0.8; color: #666;">Workshop</div>
                                    <?php endif; ?> -->
                                </div>
                            <?php 
                                endforeach;
                            endif; 
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Legend -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card legend-card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Legenda</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: linear-gradient(135deg, #E3F2FD, #90CAF9); border-color: #2196F3;"></span>
                                        <div>
                                            <i class="fas fa-dumbbell fa-2x me-2" style="color: #2196F3;"></i>
                                            <strong>Lekcie</strong> - Jednotlivé hodiny jogy
                                        </div>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: linear-gradient(135deg, #F3E5F5, #CE93D8); border-color: #9C27B0;"></span>
                                        <div>
                                            <i class="fas fa-graduation-cap fa-2x me-2" style="color: #9C27B0;"></i>
                                            <strong>Kurzy</strong> - Série prepojených lekcií
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: linear-gradient(135deg, #FFF3E0, #FFB74D); border-color: #FF9800;"></span>
                                        <div>
                                            <i class="fas fa-tools fa-2x me-2" style="color: #FF9800;"></i>
                                            <strong>Workshopy</strong> - Špeciálne tematické podujatia
                                        </div>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: #28a745; border-color: #28a745;"></span>
                                        <div>
                                            <i class="fas fa-check-circle fa-2x me-2" style="color: #28a745;"></i>
                                            <strong>Prihlásený</strong> - Ste registrovaný na túto udalosť
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- MONTHLY VIEW -->
    <?php if ($viewType === 'month'): ?>
        <div class="outlook-calendar">
            <div class="calendar-header text-center">
                <div class="row align-items-center">
                    <div class="col-2">
                        <a href="?view=month&month=<?= $currentMonth == 1 ? 12 : $currentMonth - 1 ?>&year=<?= $currentMonth == 1 ? $currentYear - 1 : $currentYear ?>&type=<?= $eventTypeFilter ?>" 
                           class="btn btn-outline-success">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>
                    <div class="col-8">
                        <h4 class="mb-0"><?= date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear)) ?></h4>
                        <small>Mesačný prehľad udalostí</small>
                    </div>
                    <div class="col-2">
                        <a href="?view=month&month=<?= $currentMonth == 12 ? 1 : $currentMonth + 1 ?>&year=<?= $currentMonth == 12 ? $currentYear + 1 : $currentYear ?>&type=<?= $eventTypeFilter ?>" 
                           class="btn btn-outline-success">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="month-grid">
                <!-- Month calendar grid -->
                <div class="row g-0" style="border: 1px solid #ddd;">
                    <!-- Day headers -->
                    <?php 
                    $dayHeaders = ['Pondelok', 'Utorok', 'Streda', 'Štvrtok', 'Piatok', 'Sobota', 'Nedeľa'];
                    foreach ($dayHeaders as $dayHeader): ?>
                        <div class="col" style="background: #28a745; color: white; text-align: center; padding: 10px; font-weight: bold; border-right: 1px solid #fff;">
                            <?= $dayHeader ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="row g-0">
                    <?php
                    // Calculate month calendar
                    $firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
                    $firstDayOfWeek = date('N', $firstDay); // 1 = Monday, 7 = Sunday
                    $daysInMonth = date('t', $firstDay);
                    
                    // Group events by date
                    $eventsByDate = [];
                    foreach ($events as $event) {
                        $eventsByDate[$event['date']][] = $event;
                    }
                    
                    $dayCounter = 1;
                    
                    for ($week = 0; $week < 6; $week++):
                        for ($day = 1; $day <= 7; $day++):
                            $cellDate = '';
                            $isCurrentMonth = false;
                            $isToday = false;
                            $dayEvents = [];
                            
                            if ($week == 0 && $day < $firstDayOfWeek) {
                                // Previous month days
                                $prevMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
                                $prevYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;
                                $prevMonthDays = date('t', mktime(0, 0, 0, $prevMonth, 1, $prevYear));
                                $dayNum = $prevMonthDays - ($firstDayOfWeek - $day) + 1;
                                $cellDate = sprintf('%04d-%02d-%02d', $prevYear, $prevMonth, $dayNum);
                            } elseif ($dayCounter <= $daysInMonth) {
                                // Current month days
                                $dayNum = $dayCounter;
                                $cellDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $dayNum);
                                $isCurrentMonth = true;
                                $isToday = $cellDate === date('Y-m-d');
                                $dayEvents = isset($eventsByDate[$cellDate]) ? $eventsByDate[$cellDate] : [];
                                $dayCounter++;
                            } else {
                                // Next month days
                                $nextMonth = $currentMonth == 12 ? 1 : $currentMonth + 1;
                                $nextYear = $currentMonth == 12 ? $currentYear + 1 : $currentYear;
                                $dayNum = $dayCounter - $daysInMonth;
                                $cellDate = sprintf('%04d-%02d-%02d', $nextYear, $nextMonth, $dayNum);
                                $dayCounter++;
                            }
                    ?>
                            <div class="col month-day <?= $isToday ? 'today-highlight' : '' ?>" style="border: 1px solid #e9ecef; min-height: 120px;">
                                <div class="month-day-header" style="background: <?= $isCurrentMonth ? '#f8f9fa' : '#e9ecef' ?>; color: <?= $isCurrentMonth ? '#000' : '#999' ?>;">
                                    <?= date('j', strtotime($cellDate)) ?>
                                </div>
                                <div class="month-day-content" style="padding: 4px; height: calc(100% - 40px); overflow: hidden;">
                                    <?php foreach ($dayEvents as $event): 
                                        $isWorkshop = $event['event_type'] === 'workshop';
                                        $isCourseClass = !empty($event['course_id']);
                                        $isRegistered = $event['user_registered'] == 1;
                                        
                                        $registeredCount = $event['registered_count'] ?? 0;
                                        $maxParticipants = $event['capacity'] ?? 0;
                                        
                                        if ($isWorkshop) {
                                            $bgColor = '#FFB74D';
                                            $typeIcon = 'fas fa-tools';
                                            $clickHandler = "showWorkshopDetails({$event['id']}, '" . addslashes($event['name']) . "', '{$event['date']}', '{$event['time_start']}', '{$event['time_end']}', {$registeredCount}, {$maxParticipants}, " . ($isRegistered ? 'true' : 'false') . ", " . ($registeredCount >= $maxParticipants ? 'true' : 'false') . ")";
                                        } elseif ($isCourseClass) {
                                            $bgColor = '#CE93D8';
                                            $typeIcon = 'fas fa-graduation-cap';
                                            $clickHandler = '';
                                        } else {
                                            $bgColor = '#90CAF9';
                                            $typeIcon = 'fas fa-dumbbell';
                                            $clickHandler = "showClassDetails({$event['id']}, '" . addslashes($event['name']) . "', '{$event['date']}', '{$event['time_start']}', '{$event['time_end']}', {$registeredCount}, {$maxParticipants}, " . ($isRegistered ? 'true' : 'false') . ", " . ($registeredCount >= $maxParticipants ? 'true' : 'false') . ")";
                                        }
                                    ?>
                                        <div class="month-event" 
                                             style="background: <?= $bgColor ?>; color: white; margin-bottom: 2px; display: block;"
                                             onclick="<?= $clickHandler ?>"
                                             title="<?= htmlspecialchars($event['name']) ?> (<?= $event['time_start'] ?>-<?= $event['time_end'] ?>)">
                                            <i class="<?= $typeIcon ?> me-1"></i>
                                            <?= date('H:i', strtotime($event['time_start'])) ?> <?= htmlspecialchars(substr($event['name'], 0, 10)) ?><?= strlen($event['name']) > 10 ? '...' : '' ?>
                                            <?php if ($isRegistered): ?>
                                                <i class="fas fa-check-circle ms-1" style="color: #28a745;"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                    <?php 
                        endfor;
                        // Break if we've filled all days of the month and we're past the first week
                        if ($dayCounter > $daysInMonth && $week > 0) break;
                    endfor; 
                    ?>
                </div>
            </div>
            
            <!-- Legend -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card legend-card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Legenda</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: #90CAF9; border-color: #2196F3;"></span>
                                        <div>
                                            <i class="fas fa-dumbbell fa-2x me-2" style="color: #2196F3;"></i>
                                            <strong>Lekcie</strong> - Jednotlivé hodiny jogy
                                        </div>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: #CE93D8; border-color: #9C27B0;"></span>
                                        <div>
                                            <i class="fas fa-graduation-cap fa-2x me-2" style="color: #9C27B0;"></i>
                                            <strong>Kurzy</strong> - Série prepojených lekcií
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: #FFB74D; border-color: #FF9800;"></span>
                                        <div>
                                            <i class="fas fa-tools fa-2x me-2" style="color: #FF9800;"></i>
                                            <strong>Workshopy</strong> - Špeciálne tematické podujatia
                                        </div>
                                    </div>
                                    <div class="legend-item">
                                        <span class="legend-color" style="background: #28a745; border-color: #28a745;"></span>
                                        <div>
                                            <i class="fas fa-check-circle fa-2x me-2" style="color: #28a745;"></i>
                                            <strong>Prihlásený</strong> - Ste registrovaný na túto udalosť
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<!-- Event details modals -->
<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail lekcie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="classModalBody">
                <!-- Class details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="workshopModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail workshopu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="workshopModalBody">
                <!-- Workshop details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function showClassDetails(classId, className, date, timeStart, timeEnd, registeredCount, maxParticipants, isRegistered, isFull) {
    const modal = document.getElementById('classModalBody');
    modal.innerHTML = `
        <h6>${className}</h6>
        <p><strong>Dátum:</strong> ${date}</p>
        <p><strong>Čas:</strong> ${timeStart} - ${timeEnd}</p>
        <p><strong>Kapacita:</strong> ${registeredCount}/${maxParticipants}</p>
        <p><strong>Status:</strong> ${isRegistered ? 'Prihlásený' : (isFull ? 'Plná kapacita' : 'Voľné miesta')}</p>
        ${!isRegistered && !isFull ? `<a href="../pages/class-registration-confirm.php?id=${classId}" class="btn btn-success">Prihlásiť sa</a>` : ''}
    `;
    new bootstrap.Modal(document.getElementById('classModal')).show();
}

function showWorkshopDetails(workshopId, workshopName, date, timeStart, timeEnd, registeredCount, maxParticipants, isRegistered, isFull) {
    const modal = document.getElementById('workshopModalBody');
    modal.innerHTML = `
        <h6>${workshopName}</h6>
        <p><strong>Dátum:</strong> ${date}</p>
        <p><strong>Čas:</strong> ${timeStart} - ${timeEnd}</p>
        <p><strong>Kapacita:</strong> ${registeredCount}/${maxParticipants}</p>
        <p><strong>Status:</strong> ${isRegistered ? 'Prihlásený' : (isFull ? 'Plná kapacita' : 'Voľné miesta')}</p>
        ${!isRegistered && !isFull ? `<a href="../pages/workshop-registration-confirm.php?id=${workshopId}" class="btn btn-success">Prihlásiť sa</a>` : ''}
    `;
    new bootstrap.Modal(document.getElementById('workshopModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>