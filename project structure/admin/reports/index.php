<?php
// ============================================================
// admin/reports/index.php
// ============================================================

$pageTitle = 'Reports Generation';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

$pdo = getDB();
// Fetch programs for the dropdown
$programs = $pdo->query("SELECT id, name FROM scholarship_programs ORDER BY name ASC")->fetchAll();
?>

<div class="container py-4">
    <div class="mb-4">
        <h1 class="page-title">Reports & Exports</h1>
        <p class="page-subtitle">Generate dynamic reports, print to PDF, or export to CSV.</p>
    </div>

    <div class="row">
        <!-- Report Generator Form -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="card-title mb-0"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i> Generate Report</h5>
                </div>
                <div class="card-body">
                    <form action="view.php" method="GET" target="_blank">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Program <span class="text-danger">*</span></label>
                            <select name="program_id" class="form-select" required>
                                <option value="">-- Select Scholarship Program --</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?= $prog['id'] ?>"><?= e($prog['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Report Type <span class="text-danger">*</span></label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="report_type" id="typeRanking" value="ranking" checked>
                                <label class="form-check-label" for="typeRanking">
                                    Ranking Report <small class="text-muted d-block">Final scores and ranking of candidates.</small>
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="report_type" id="typeDisbursement" value="disbursement">
                                <label class="form-check-label" for="typeDisbursement">
                                    Disbursement Report <small class="text-muted d-block">List of recommended students for financial payout.</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="report_type" id="typeSummary" value="summary">
                                <label class="form-check-label" for="typeSummary">
                                    Eligibility Summary <small class="text-muted d-block">Pass/Fail overview of applications.</small>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="action" value="view" class="btn btn-primary">
                                <i class="bi bi-printer me-1"></i> View / Print
                            </button>
                            <button type="submit" name="action" value="pdf" class="btn btn-danger" formaction="export_pdf.php" formtarget="_blank">
                                <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
                            </button>
                            <button type="submit" name="action" value="csv" class="btn btn-success" formaction="export_csv.php" formtarget="_self">
                                <i class="bi bi-file-earmark-excel me-1"></i> Export CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Info / Instructions -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 bg-light">
                <div class="card-body">
                    <h5 class="card-title text-secondary"><i class="bi bi-info-circle me-2"></i> Instructions</h5>
                    <p class="mb-2"><strong>View / Print PDF:</strong> Generates a web-based report. Use your browser's print feature (<code>Ctrl+P</code> or <code>Cmd+P</code>) to save it as a PDF or print it.</p>
                    <p class="mb-2"><strong>Export CSV:</strong> Downloads a raw data file suitable for Excel or other spreadsheet software.</p>
                    <hr>
                    <ul class="text-muted small mb-0 ps-3">
                        <li>Make sure eligibility checks are run before generating the Eligibility Summary.</li>
                        <li>Ranking reports reflect the current state of evaluation scores.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
