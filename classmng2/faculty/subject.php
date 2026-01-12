<?php
require_once '../config.php';
require_login();
$user = current_user();
if($user['role'] !== 'faculty'){ header("Location: ../index.php"); exit; }
$pdo = pdo();
$id = intval($_GET['id'] ?? 0);
$sub = $pdo->prepare("SELECT * FROM subjects WHERE id=? AND faculty_id=?");
$sub->execute([$id,$user['id']]);
$sub = $sub->fetch();
if(!$sub){ header("Location: subjects.php"); exit; }

// Add student
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_student'])){
    $ln = $_POST['lastname']; $fn = $_POST['firstname']; $course = $_POST['course']; $year = $_POST['year'];
    $avatar = 'assets/img/default-avatar.png';
    $stmt = $pdo->prepare("INSERT INTO students (subject_id,lastname,firstname,course,year_level,avatar) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$id,$ln,$fn,$course,$year,$avatar]);
    $sid = $pdo->lastInsertId();
    // create initial grades row
    $pdo->prepare("INSERT INTO grades (student_id,subject_id) VALUES (?,?)")->execute([$sid,$id]);
    header("Location: subject.php?id=$id");
    exit;
}

// Multi-add students (simple newline-separated rows: firstname,lastname,course,year)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['multi_add'])){
    $bulk = trim($_POST['bulk']);
    $lines = preg_split("/\r\n|\n|\r/", $bulk);
    foreach($lines as $ln){
        if(trim($ln)==='') continue;
        // expecting: Lastname, Firstname, Course, Year
        $parts = array_map('trim', explode(',', $ln));
        if(count($parts) < 2) continue;
        $lastname = $parts[0];
        $firstname = $parts[1];
        $course = $parts[2] ?? '';
        $year = $parts[3] ?? '';
        $pdo->prepare("INSERT INTO students (subject_id,lastname,firstname,course,year_level) VALUES (?,?,?,?,?)")->execute([$id,$lastname,$firstname,$course,$year]);
        $last = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO grades (student_id,subject_id) VALUES (?,?)")->execute([$last,$id]);
    }
    header("Location: subject.php?id=$id");
    exit;
}

// fetch students
$students = $pdo->prepare("SELECT * FROM students WHERE subject_id = ? AND archived = 0 ORDER BY lastname");
$students->execute([$id]);
$students = $students->fetchAll();

// counts present today
$today = date('Y-m-d');
$present_counts = $pdo->prepare("SELECT student_id, COUNT(*) AS cnt FROM attendance WHERE date = ? AND status='present' GROUP BY student_id");
$present_counts->execute([$today]);
$present_map = [];
foreach($present_counts->fetchAll() as $p) $present_map[$p['student_id']] = $p['cnt'];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars($sub['name']) ?> - ClassFlow</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <?php include '../shared/left_nav.php'; ?>
  <main class="main-content">
    <div class="container py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h3><?php echo htmlspecialchars($sub['name']); ?></h3>
          <div class="text-muted small"><?php echo htmlspecialchars($sub['code']) ?> • <?php echo htmlspecialchars($sub['schedule']) ?></div>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#multiAddModal">Add Multiple</button>
          <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addStudentModal">Add Student</button>
        </div>
      </div>
       <input id="search" class="form-control form-control-sm search-input" placeholder="Search by name or subject..."> <br>
       <br>
      <div class="row g-3">
        <?php if(empty($students)): ?>
          <div class="col-12"><div class="card p-4 text-center">No students yet. Add some.</div></div>
        <?php endif; ?>
        <?php foreach($students as $st): ?>
        <div class="col-sm-6 col-md-4">
  <div class="card student-tile h-100" 
       data-id="<?php echo $st['id'] ?>" 
       data-lastname="<?php echo strtolower($st['lastname']) ?>" 
       data-firstname="<?php echo strtolower($st['firstname']) ?>">
              <div class="card-body text-center">
                <img src="../<?php echo htmlspecialchars($st['avatar']) ?>" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">
                <h6 class="mb-0"><?php echo htmlspecialchars($st['lastname'] . ', ' . $st['firstname']) ?></h6>
                <div class="small text-muted"><?php echo htmlspecialchars($st['course'].' • '.$st['year_level']) ?></div>
                <div class="mt-2">
                  <button class="btn btn-sm btn-secondary view-student" data-id="<?php echo $st['id'] ?>">View</button>
                </div>
                <div class="mt-2">
                  <?php if(!empty($present_map[$st['id']])): ?>
                    <span class="badge bg-success">Present</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </main>

  <!-- Add Student Modal -->
  <div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <input type="hidden" name="add_student" value="1">
        <div class="modal-header"><h5 class="modal-title">Add Student</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label>Lastname</label><input name="lastname" class="form-control" required></div>
          <div class="mb-2"><label>Firstname</label><input name="firstname" class="form-control" required></div>
          <div class="mb-2"><label>Course</label><input name="course" class="form-control"></div>
          <div class="mb-2"><label>Year</label><select name="year" class="form-select"><option>1st year</option>
            <option>2nd year</option>
            <option>3rd year</option>
            <option>4th year</option>
            </select></div>
        </div>
        <div class="modal-footer"><button class="btn btn-orange">Add Student</button></div>
      </form>
    </div>
  </div>

  <!-- Multi Add Modal -->
  <div class="modal fade" id="multiAddModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <form method="post" class="modal-content">
        <input type="hidden" name="multi_add" value="1">
        <div class="modal-header"><h5 class="modal-title">Add Multiple Students</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <p class="small text-muted">Enter one student per line (Lastname, Firstname, Course, Year). Only lastname and firstname are required.</p>
          <textarea name="bulk" class="form-control" rows="8" placeholder="Doe, John, BSIT, 2nd"></textarea>
        </div>
        <div class="modal-footer"><button class="btn btn-orange">Add All</button></div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  // open student modal via AJAX
  document.querySelectorAll('.view-student').forEach(btn=>{
    btn.addEventListener('click', function(){
      const id = this.dataset.id;
      const modal = new bootstrap.Modal(document.createElement('div'));
      // fetch modal content
      fetch('student_modal.php?id='+id+'&subject=<?php echo $id ?>')
        .then(r=>r.text()).then(html=>{
          const wrapper = document.createElement('div');
          wrapper.innerHTML = html;
          document.body.appendChild(wrapper);
          var m = new bootstrap.Modal(wrapper.querySelector('.modal'));
          m.show();
          wrapper.querySelector('.modal').addEventListener('hidden.bs.modal', function(){ wrapper.remove();});
        });
    });
  });
  </script>
  <script>
    // client-side search filter
    // client-side search filter
$('#search').on('input', function(){
  var q = $(this).val().trim().toLowerCase();
  if(q === ''){
    $('.student-tile').show();
    return;
  }
  $('.student-tile').each(function(){
    var lastname = $(this).data('lastname');
    var firstname = $(this).data('firstname');
    if(lastname.indexOf(q) !== -1 || firstname.indexOf(q) !== -1){
      $(this).show();
    } else {
      $(this).hide();
    }
  });
});
  </script>
  <script>
async function loadStudentGrades(subjectId, studentId) {
  try {
    const res = await fetch('/faculty/get_grades.php?subject_id=' + encodeURIComponent(subjectId) + '&student_id=' + encodeURIComponent(studentId));
    const data = await res.json();
    if (data.success) {
      const grades = data.data;
      // Example: append grades to modal body area with id #studentGrades
      const container = document.getElementById('studentGrades');
      if (container) {
        if (grades.length === 0) container.innerHTML = '<div class="text-muted">No grades found</div>';
        else {
          container.innerHTML = grades.map(g => `<div>Grade: ${g.grade} <small class="text-muted">(${g.updated_at})</small></div>`).join('');
        }
      }
    } else {
      console.error('get_grades error', data.message);
    }
  } catch (err) {
    console.error(err);
  }
}

// Example: call loadStudentGrades when opening the student modal
// $('#studentModal').on('show.bs.modal', function (e) {
//    const studentId = ...; // extract from clicked element
//    const subjectId = ...;
//    loadStudentGrades(subjectId, studentId);
// });
</script>

</body>
</html>