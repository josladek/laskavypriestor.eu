<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Only admins can access this page
requireRole('admin');

// Get all courses with instructor info, types, levels and registration counts  
$courses = db()->fetchAll("
    SELECT c.id, c.name, c.description, c.total_lessons, c.start_date, c.end_date,
           c.day_of_week, c.time_start, c.time_end, c.capacity, c.price,
           c.status, c.location, c.type_id, c.level_id, u.name as lektor_name,
           lt.name as lesson_type, lv.name as level_name,
           COUNT(cr.id) as registered_count
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    LEFT JOIN lesson_types lt ON c.type_id = lt.id
    LEFT JOIN levels lv ON c.level_id = lv.id
    LEFT JOIN course_registrations cr ON c.id = cr.course_id AND cr.status = 'confirmed'
    GROUP BY c.id, c.name, c.description, c.total_lessons, c.start_date, c.end_date,
             c.day_of_week, c.time_start, c.time_end, c.capacity, c.price,
             c.status, c.location, c.type_id, c.level_id, u.name, lt.name, lv.name
    ORDER BY c.start_date DESC
");

$currentPage = 'admin_courses';
$pageTitle = 'Správa kurzov';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="text-charcoal">Správa kurzov</h1>
                <div class="btn-group">
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard
                    </a>
                    <a href="create-course.php" class="btn btn-sage">
                        <i class="fas fa-plus me-2"></i>Nový kurz
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Názov kurzu</th>
                                    <th>Lektor</th>
                                    <th>Druh / Úroveň</th>
                                    <th>Obdobie</th>
                                    <th>Čas</th>
                                    <th>Kapacita</th>
                                    <th>Cena</th>
                                    <th>Status</th>
                                    <th>Akcie</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?= $course['id'] ?></td>
                                    <td>
                                        <strong><?= e($course['name']) ?></strong>
                                        <br><small class="text-muted"><?= $course['total_lessons'] ?> lekcií</small>
                                    </td>
                                    <td><?= e($course['lektor_name']) ?></td>
                                    <td>
                                        <?php if ($course['lesson_type']): ?>
                                            <span class="badge bg-primary"><?= e($course['lesson_type']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($course['level_name']): ?>
                                            <span class="badge bg-secondary"><?= e($course['level_name']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!$course['lesson_type'] && !$course['level_name']): ?>
                                            <small class="text-muted">Neurčené</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= formatDate($course['start_date']) ?></strong>
                                        <br><small class="text-muted">do <?= formatDate($course['end_date']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= getDayName($course['day_of_week']) ?></strong>
                                        <br><small><?= formatTime($course['time_start']) ?> - <?= formatTime($course['time_end']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $course['registered_count'] >= $course['capacity'] ? 'bg-danger' : 'bg-success' ?>">
                                            <?= $course['registered_count'] ?>/<?= $course['capacity'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= formatPrice($course['price']) ?></strong>
                                        <br><small class="text-muted">Platba bankovým prevodom</small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $course['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= ucfirst($course['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit-course.php?id=<?= $course['id'] ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="course-registrations.php?id=<?= $course['id'] ?>" 
                                               class="btn btn-outline-info">
                                                <i class="fas fa-users"></i>
                                            </a>
                                            <a href="delete-course.php?id=<?= $course['id'] ?>" 
                                               class="btn btn-outline-danger"
                                               onclick="return confirm('Naozaj chcete zmazať tento kurz?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>