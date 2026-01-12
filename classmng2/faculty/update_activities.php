<?php
require_once '../config.php';
require_login();
$user = current_user();
if ($user['role'] !== 'faculty') { header("Location: ../index.php"); exit; }
$pdo = pdo();

$student_id = intval($_GET['student'] ?? 0);
$subject_id = intval($_GET['subject'] ?? 0);

if (!$student_id || !$subject_id) {
    header("Location: activities.php");
    exit;
}

// load student and subject for display
$stStmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND subject_id = ?");
$stStmt->execute([$student_id, $subject_id]);
$student = $stStmt->fetch();
if (!$student) {
    header("Location: activities.php?subject=" . $subject_id);
    exit;
}

$subStmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
$subStmt->execute([$subject_id]);
$subject = $subStmt->fetch();

// Handle updates and deletes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update' && isset($_POST['activity_id'])) {
        $aid = intval($_POST['activity_id']);
        $title = trim($_POST['title'] ?? '');
        $score = floatval($_POST['score'] ?? 0);
        $u = $pdo->prepare("UPDATE activities SET title = ?, score = ? WHERE id = ? AND student_id = ? AND subject_id = ?");
        $u->execute([$title, $score, $aid, $student_id, $subject_id]);
        header("Location: update_activities.php?student={$student_id}&subject={$subject_id}");
        exit;
    }
    if ($action === 'delete' && isset($_POST['activity_id'])) {
        $aid = intval($_POST['activity_id']);
        $d = $pdo->prepare("DELETE FROM activities WHERE id = ? AND student_id = ? AND subject_id = ?");
        $d->execute([$aid, $student_id, $subject_id]);
        header("Location: update_activities.php?student={$student_id}&subject={$subject_id}");
        exit;
    }
}

// fetch activities
$actsStmt = $pdo->prepare("SELECT * FROM activities WHERE student_id = ? AND subject_id = ? ORDER BY id DESC");
$actsStmt->execute([$student_id, $subject_id]);
$activities = $actsStmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Update Activities - ClassFlow</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <?php include '../shared/left_nav.php'; ?>
  <main class="main-content">
    <div class="container py-4">
      <h4>Activities for <?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?> <?php echo isset($subject['name']) ? '(' . htmlspecialchars($subject['name']) . ')' : '' ?></h4>
      <p>
        <a href="activities.php?subject=<?php echo (int)$subject_id ?>" class="btn btn-outline-secondary btn-sm">&larr; Back to Activities</a>
      </p>

      <?php if (empty($activities)): ?>
        <div class="alert alert-info">No activities recorded yet.</div>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>#</th><th>Title</th><th>Score</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($activities as $a): ?>
              <tr>
                <td><?php echo (int)$a['id'] ?></td>
                <td><?php echo htmlspecialchars($a['title']) ?></td>
                <td><?php echo htmlspecialchars($a['score']) ?></td>
                <td>
                  <!-- Edit button opens modal -->
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editActModal-<?php echo (int)$a['id'] ?>">Edit</button>

                  <!-- Delete form -->
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete this activity?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="activity_id" value="<?php echo (int)$a['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Render edit modals outside the table to avoid nesting issues -->
        <?php foreach ($activities as $a): ?>
          <div class="modal fade" id="editActModal-<?php echo (int)$a['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <form method="post" class="modal-content">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="activity_id" value="<?php echo (int)$a['id'] ?>">
                <div class="modal-header">
                  <h5 class="modal-title">Edit Activity #<?php echo (int)$a['id'] ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div class="mb-2">
                    <label class="form-label">Title</label>
                    <input name="title" class="form-control" value="<?php echo htmlspecialchars($a['title']) ?>" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Score</label>
                    <input name="score" class="form-control" type="number" step="0.01" value="<?php echo htmlspecialchars($a['score']) ?>">
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="submit" class="btn btn-primary">Save</button>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

      <?php endif; ?>

    </div>
  </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>