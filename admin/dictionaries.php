<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Kontrola oprávnení
if (!isLoggedIn() || !hasRole('admin')) {
    redirect('/pages/login.php');
}

$message = '';

// Spracovanie POST požiadaviek
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_lesson_type':
            $name = trim($_POST['name'] ?? '');
            if ($name) {
                try {
                    db()->query("INSERT INTO lesson_types (name) VALUES (?)", [$name]);
                    $message = '<div class="alert alert-success">Druh lekcie bol úspešne pridaný.</div>';
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Chyba: ' . $e->getMessage() . '</div>';
                }
            }
            break;
            
        case 'add_level':
            $name = trim($_POST['name'] ?? '');
            if ($name) {
                try {
                    db()->query("INSERT INTO levels (name) VALUES (?)", [$name]);
                    $message = '<div class="alert alert-success">Úroveň bola úspešne pridaná.</div>';
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Chyba: ' . $e->getMessage() . '</div>';
                }
            }
            break;
            
        case 'update_lesson_type':
            $id = (int)$_POST['id'];
            $name = trim($_POST['name'] ?? '');
            if ($id && $name) {
                try {
                    db()->query("UPDATE lesson_types SET name = ? WHERE id = ?", [$name, $id]);
                    $message = '<div class="alert alert-success">Druh lekcie bol úspešne aktualizovaný.</div>';
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Chyba: ' . $e->getMessage() . '</div>';
                }
            }
            break;
            
        case 'update_level':
            $id = (int)$_POST['id'];
            $name = trim($_POST['name'] ?? '');
            if ($id && $name) {
                try {
                    db()->query("UPDATE levels SET name = ? WHERE id = ?", [$name, $id]);
                    $message = '<div class="alert alert-success">Úroveň bola úspešne aktualizovaná.</div>';
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Chyba: ' . $e->getMessage() . '</div>';
                }
            }
            break;
            
        case 'delete_lesson_type':
            $id = (int)$_POST['id'];
            if ($id) {
                try {
                    // Kontrola použitia v kurzoch
                    $usage = db()->fetch("SELECT COUNT(*) as count FROM courses WHERE type_id = ?", [$id]);
                    if ($usage && $usage['count'] > 0) {
                        $message = '<div class="alert alert-warning">Nemožno zmazať - druh lekcie sa používa v ' . $usage['count'] . ' kurzoch.</div>';
                    } else {
                        db()->query("DELETE FROM lesson_types WHERE id = ?", [$id]);
                        $message = '<div class="alert alert-success">Druh lekcie bol úspešne zmazaný.</div>';
                    }
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Chyba: ' . $e->getMessage() . '</div>';
                }
            }
            break;
            
        case 'delete_level':
            $id = (int)$_POST['id'];
            if ($id) {
                try {
                    // Kontrola použitia v kurzoch
                    $usage = db()->fetch("SELECT COUNT(*) as count FROM courses WHERE level_id = ?", [$id]);
                    if ($usage && $usage['count'] > 0) {
                        $message = '<div class="alert alert-warning">Nemožno zmazať - úroveň sa používa v ' . $usage['count'] . ' kurzoch.</div>';
                    } else {
                        db()->query("DELETE FROM levels WHERE id = ?", [$id]);
                        $message = '<div class="alert alert-success">Úroveň bola úspešne zmazaná.</div>';
                    }
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Chyba: ' . $e->getMessage() . '</div>';
                }
            }
            break;
    }
}

// Načítanie údajov
$lessonTypes = getLessonTypes();
$levels = getLevels();

?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Číselníky - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../static/css/roboto-fonts.css" rel="stylesheet">
    <link href="../static/css/yoga-minimal.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Číselníky</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Číselníky</li>
                        </ol>
                    </nav>
                </div>

                <?= $message ?>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="lesson-types-tab" data-bs-toggle="tab" 
                                data-bs-target="#lesson-types" type="button" role="tab">
                            <i class="fas fa-yoga me-1"></i> Druhy lekcií
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="levels-tab" data-bs-toggle="tab" 
                                data-bs-target="#levels" type="button" role="tab">
                            <i class="fas fa-signal me-1"></i> Úrovne
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content mt-4">
                    <!-- Lesson Types Tab -->
                    <div class="tab-pane fade show active" id="lesson-types" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Druhy lekcií</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Názov</th>
                                                        <th>Akcie</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($lessonTypes as $type): ?>
                                                    <tr>
                                                        <td><?= $type['id'] ?></td>
                                                        <td>
                                                            <span id="type_name_display_<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></span>
                                                            <input type="text" class="form-control form-control-sm d-none" 
                                                                   value="<?= htmlspecialchars($type['name']) ?>" 
                                                                   id="type_name_input_<?= $type['id'] ?>">
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary me-1" 
                                                                    onclick="editType(<?= $type['id'] ?>)" id="edit_type_<?= $type['id'] ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-success me-1 d-none" 
                                                                    onclick="saveType(<?= $type['id'] ?>)" id="save_type_<?= $type['id'] ?>">
                                                                <i class="fas fa-save"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-secondary me-1 d-none" 
                                                                    onclick="cancelEdit(<?= $type['id'] ?>, 'type')" id="cancel_type_<?= $type['id'] ?>">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="deleteType(<?= $type['id'] ?>, '<?= htmlspecialchars($type['name']) ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Pridať nový druh lekcie</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post">
                                            <input type="hidden" name="action" value="add_lesson_type">
                                            <div class="mb-3">
                                                <label for="new_type_name" class="form-label">Názov</label>
                                                <input type="text" class="form-control" id="new_type_name" name="name" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-1"></i> Pridať
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Levels Tab -->
                    <div class="tab-pane fade" id="levels" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Úrovne</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Názov</th>
                                                        <th>Akcie</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($levels as $level): ?>
                                                    <tr>
                                                        <td><?= $level['id'] ?></td>
                                                        <td>
                                                            <span id="level_name_display_<?= $level['id'] ?>"><?= htmlspecialchars($level['name']) ?></span>
                                                            <input type="text" class="form-control form-control-sm d-none" 
                                                                   value="<?= htmlspecialchars($level['name']) ?>" 
                                                                   id="level_name_input_<?= $level['id'] ?>">
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary me-1" 
                                                                    onclick="editLevel(<?= $level['id'] ?>)" id="edit_level_<?= $level['id'] ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-success me-1 d-none" 
                                                                    onclick="saveLevel(<?= $level['id'] ?>)" id="save_level_<?= $level['id'] ?>">
                                                                <i class="fas fa-save"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-secondary me-1 d-none" 
                                                                    onclick="cancelEdit(<?= $level['id'] ?>, 'level')" id="cancel_level_<?= $level['id'] ?>">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="deleteLevel(<?= $level['id'] ?>, '<?= htmlspecialchars($level['name']) ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Pridať novú úroveň</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post">
                                            <input type="hidden" name="action" value="add_level">
                                            <div class="mb-3">
                                                <label for="new_level_name" class="form-label">Názov</label>
                                                <input type="text" class="form-control" id="new_level_name" name="name" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-1"></i> Pridať
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden forms for AJAX operations -->
    <form id="updateForm" method="post" style="display: none;">
        <input type="hidden" name="action" id="updateAction">
        <input type="hidden" name="id" id="updateId">
        <input type="hidden" name="name" id="updateName">
    </form>

    <form id="deleteForm" method="post" style="display: none;">
        <input type="hidden" name="action" id="deleteAction">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editType(id) {
            document.getElementById('type_name_display_' + id).classList.add('d-none');
            document.getElementById('type_name_input_' + id).classList.remove('d-none');
            document.getElementById('edit_type_' + id).classList.add('d-none');
            document.getElementById('save_type_' + id).classList.remove('d-none');
            document.getElementById('cancel_type_' + id).classList.remove('d-none');
        }

        function saveType(id) {
            const name = document.getElementById('type_name_input_' + id).value.trim();
            if (name) {
                document.getElementById('updateAction').value = 'update_lesson_type';
                document.getElementById('updateId').value = id;
                document.getElementById('updateName').value = name;
                document.getElementById('updateForm').submit();
            }
        }

        function editLevel(id) {
            document.getElementById('level_name_display_' + id).classList.add('d-none');
            document.getElementById('level_name_input_' + id).classList.remove('d-none');
            document.getElementById('edit_level_' + id).classList.add('d-none');
            document.getElementById('save_level_' + id).classList.remove('d-none');
            document.getElementById('cancel_level_' + id).classList.remove('d-none');
        }

        function saveLevel(id) {
            const name = document.getElementById('level_name_input_' + id).value.trim();
            if (name) {
                document.getElementById('updateAction').value = 'update_level';
                document.getElementById('updateId').value = id;
                document.getElementById('updateName').value = name;
                document.getElementById('updateForm').submit();
            }
        }

        function cancelEdit(id, type) {
            document.getElementById(type + '_name_display_' + id).classList.remove('d-none');
            document.getElementById(type + '_name_input_' + id).classList.add('d-none');
            document.getElementById('edit_' + type + '_' + id).classList.remove('d-none');
            document.getElementById('save_' + type + '_' + id).classList.add('d-none');
            document.getElementById('cancel_' + type + '_' + id).classList.add('d-none');
        }

        function deleteType(id, name) {
            if (confirm('Naozaj chcete zmazať druh lekcie "' + name + '"?')) {
                document.getElementById('deleteAction').value = 'delete_lesson_type';
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function deleteLevel(id, name) {
            if (confirm('Naozaj chcete zmazať úroveň "' + name + '"?')) {
                document.getElementById('deleteAction').value = 'delete_level';
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>