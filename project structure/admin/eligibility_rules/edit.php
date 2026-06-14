<?php
// ============================================================
// admin/eligibility_rules/edit.php
// ============================================================

$pageTitle = 'Edit Eligibility Rule';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();
$error = "";
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch existing rule
$stmt = $pdo->prepare("SELECT * FROM eligibility_rules WHERE id = ?");
$stmt->execute([$id]);
$rule = $stmt->fetch();

if (!$rule) {
    require_once '../../includes/header.php';
    require_once '../../includes/navbar.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Eligibility rule not found.</div></div>';
    require_once '../../includes/footer.php';
    exit;
}

// Fetch active programs
$programs = $pdo->query("SELECT * FROM scholarship_programs ORDER BY id DESC")->fetchAll();

if (isset($_POST['submit'])) {
    $program_id = $_POST['program_id'];
    $rule_type = trim($_POST['rule_type']);
    $operator = trim($_POST['operator']);
    $value = trim($_POST['value']);

    if (empty($program_id) || empty($rule_type) || empty($operator) || empty($value)) {
        $error = "Please fill in all required fields.";
    } else {
        $sql = "UPDATE eligibility_rules 
                SET program_id = :program_id, rule_type = :rule_type, operator = :operator, value = :value 
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':program_id' => $program_id,
            ':rule_type' => $rule_type,
            ':operator' => $operator,
            ':value' => $value,
            ':id' => $id
        ]);

        header("Location: index.php");
        exit;
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="container py-4">
    <!-- PAGE HEADER -->
    <div class="mb-4">
        <h1 class="page-title">Edit Eligibility Rule</h1>
        <p class="page-subtitle">Modify candidate filtering standard for a scholarship program</p>
    </div>

    <!-- ALERTS -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= e($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-card">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Scholarship Program <span class="text-danger">*</span></label>
                        <select name="program_id" class="form-select" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= e($program['id']) ?>" <?= $program['id'] == $rule['program_id'] ? 'selected' : '' ?>>
                                    <?= e($program['name']) ?> (Slots: <?= e($program['slots']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Rule Type / Attribute <span class="text-danger">*</span></label>
                        <input type="text" name="rule_type" class="form-control" value="<?= e($rule['rule_type']) ?>" placeholder="e.g. GPA, credits, faculty, major" required>
                        <div class="form-text">Case-sensitive database attribute mapping (e.g. 'GPA').</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Comparison Operator <span class="text-danger">*</span></label>
                            <select name="operator" class="form-select" required>
                                <option value=">=" <?= $rule['operator'] === '>=' ? 'selected' : '' ?>>&gt;= (Greater or equal)</option>
                                <option value="<=" <?= $rule['operator'] === '<=' ? 'selected' : '' ?>>&lt;= (Less or equal)</option>
                                <option value="=" <?= $rule['operator'] === '=' ? 'selected' : '' ?>>= (Exactly equal)</option>
                                <option value=">" <?= $rule['operator'] === '>' ? 'selected' : '' ?>>&gt; (Strictly greater)</option>
                                <option value="<" <?= $rule['operator'] === '<' ? 'selected' : '' ?>>&lt; (Strictly less)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Threshold Value <span class="text-danger">*</span></label>
                            <input type="text" name="value" class="form-control" value="<?= e($rule['value']) ?>" placeholder="e.g. 3.2, 120, IT" required>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" name="submit" class="btn btn-primary">
                            Update Rule
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
