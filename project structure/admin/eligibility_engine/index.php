<?php
// ============================================================
// admin/eligibility_engine/index.php
// ============================================================

$pageTitle = 'Eligibility Engine';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Handle Run All
if (isset($_GET['run_all'])) {
    require_once '../../includes/eligibility.php';
    $stmtPending = $pdo->query("SELECT id FROM applications WHERE eligible IS NULL");
    $count = 0;
    while ($row = $stmtPending->fetch()) {
        checkEligibility($pdo, (int)$row['id']);
        $count++;
    }
    setFlash('success', "Engine processed $count pending application(s).");
    header("Location: index.php");
    exit;
}

// Handle Check Single
if (isset($_GET['check_id'])) {
    require_once '../../includes/eligibility.php';
    $checkId = (int)$_GET['check_id'];
    checkEligibility($pdo, $checkId);
    setFlash('success', "Eligibility check completed for Application #$checkId.");
    header("Location: index.php");
    exit;
}

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';

// ── Server-side filter (Show Pending Only / Show All) ───────
$filter = $_GET['filter'] ?? 'all';

if ($filter === 'pending') {
    $sql = "SELECT a.*, u.full_name, u.student_code, sp.name AS program_name
            FROM applications a
            JOIN users u ON a.student_id = u.id
            JOIN scholarship_programs sp ON a.program_id = sp.id
            WHERE a.eligible IS NULL
            ORDER BY a.id ASC";
} else {
    // 'all' — fetch everything so JS status-filter can work over full dataset
    $sql = "SELECT a.*, u.full_name, u.student_code, sp.name AS program_name
            FROM applications a
            JOIN users u ON a.student_id = u.id
            JOIN scholarship_programs sp ON a.program_id = sp.id
            ORDER BY a.id DESC";
}
$applications = $pdo->query($sql)->fetchAll();

// ── Eligibility Overview stats (always whole table) ─────────
$overviewStats = $pdo->query("
    SELECT
        COUNT(*)              AS total,
        SUM(eligible IS NULL) AS pending,
        SUM(eligible = 1)     AS eligible_count,
        SUM(eligible = 0)     AS ineligible_count
    FROM applications
")->fetch();

$statTotal      = (int)($overviewStats['total']            ?? 0);
$statPending    = (int)($overviewStats['pending']          ?? 0);
$statEligible   = (int)($overviewStats['eligible_count']   ?? 0);
$statIneligible = (int)($overviewStats['ineligible_count'] ?? 0);
?>

<style>
/* ════════════════════════════════════════════════════════════
   OVERVIEW CARDS — display-only, no click interaction
   ════════════════════════════════════════════════════════════ */
.ov-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 22px 20px;
    box-shadow: 0 1px 4px rgba(15,23,42,.06), 0 0 0 1px #f1f5f9;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.ov-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.ov-badge {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .04em;
    padding: 3px 10px;
    border-radius: 20px;
    line-height: 1.4;
}
.ov-num {
    font-size: 36px;
    font-weight: 900;
    color: #0f172a;
    line-height: 1;
    letter-spacing: -0.035em;
}
.ov-label {
    font-size: 12.5px;
    font-weight: 500;
    color: #64748b;
    margin-top: 5px;
}

/* ════════════════════════════════════════════════════════════
   STATUS FILTER BAR — tab-style filter above the table
   ════════════════════════════════════════════════════════════ */
.sf-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 5px 6px;
    flex-wrap: wrap;
}
.sf-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 16px;
    border-radius: 8px;
    border: none;
    background: transparent;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, box-shadow .15s ease;
    font-family: inherit;
    white-space: nowrap;
}
.sf-btn:hover {
    background: #ffffff;
    color: #1e293b;
    box-shadow: 0 1px 4px rgba(15,23,42,.08);
}
.sf-btn.active {
    background: #ffffff;
    color: #1e293b;
    box-shadow: 0 1px 6px rgba(15,23,42,.10);
}
/* Colored dot indicator on active tab */
.sf-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    display: inline-block;
}
/* Count pill inside each tab */
.sf-count {
    font-size: 11px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 10px;
    line-height: 1.5;
    min-width: 22px;
    text-align: center;
}
/* "All" tab — blue */
.sf-btn[data-sf="all"]        .sf-dot   { background: #2563eb; }
.sf-btn[data-sf="all"]        .sf-count { background: #dbeafe; color: #1e40af; }
/* "Pending" tab — amber */
.sf-btn[data-sf="pending"]    .sf-dot   { background: #d97706; }
.sf-btn[data-sf="pending"]    .sf-count { background: #fef3c7; color: #92400e; }
/* "Eligible" tab — green */
.sf-btn[data-sf="eligible"]   .sf-dot   { background: #16a34a; }
.sf-btn[data-sf="eligible"]   .sf-count { background: #dcfce7; color: #14532d; }
/* "Ineligible" tab — red */
.sf-btn[data-sf="ineligible"] .sf-dot   { background: #dc2626; }
.sf-btn[data-sf="ineligible"] .sf-count { background: #fee2e2; color: #7f1d1d; }

/* Active tab — colored left border accent */
.sf-btn.active[data-sf="all"]        { border-left: 3px solid #2563eb; padding-left: 13px; }
.sf-btn.active[data-sf="pending"]    { border-left: 3px solid #d97706; padding-left: 13px; }
.sf-btn.active[data-sf="eligible"]   { border-left: 3px solid #16a34a; padding-left: 13px; }
.sf-btn.active[data-sf="ineligible"] { border-left: 3px solid #dc2626; padding-left: 13px; }

/* Results info strip below the filter bar */
.sf-results-info {
    font-size: 12.5px;
    color: #94a3b8;
    font-weight: 500;
}
.sf-results-info strong {
    color: #475569;
}

/* Table row hiding */
#appsTableBody tr.sf-hidden { display: none; }
</style>

<div class="container py-4">

    <!-- ── Page header ── -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title"><i class="bi bi-cpu me-2 text-primary"></i> Eligibility Engine</h1>
            <p class="page-subtitle">Automatic validation of GPA, Extracurricular Activities, and Failed Subjects against rules.</p>
        </div>
        <div>
            <a href="index.php?run_all=1" class="btn btn-primary"
               onclick="return confirm('Run Eligibility Engine on all pending applications?');">
                <i class="bi bi-play-circle-fill me-2"></i> Run Engine (Check All Pending)
            </a>
        </div>
    </div>

    <!-- ══ ELIGIBILITY OVERVIEW CARDS (display only) ══════════════ -->
    <div class="mb-4">
        <h6 style="font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#94a3b8;margin-bottom:14px;">
            Eligibility Overview
        </h6>
        <div class="row g-3">

            <!-- Total Applications -->
            <div class="col-6 col-lg-3">
                <div class="ov-card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <div class="ov-icon" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);">
                            <i class="bi bi-file-text" style="color:#2563eb;"></i>
                        </div>
                        <span class="ov-badge" style="color:#1e40af;background:#dbeafe;">All</span>
                    </div>
                    <div>
                        <div class="ov-num"><?= $statTotal ?></div>
                        <div class="ov-label">Total Applications</div>
                    </div>
                </div>
            </div>

            <!-- Pending Check -->
            <div class="col-6 col-lg-3">
                <div class="ov-card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <div class="ov-icon" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);">
                            <i class="bi bi-clock-history" style="color:#d97706;"></i>
                        </div>
                        <span class="ov-badge" style="color:#92400e;background:#fef3c7;">Pending</span>
                    </div>
                    <div>
                        <div class="ov-num"><?= $statPending ?></div>
                        <div class="ov-label">Pending Check</div>
                    </div>
                </div>
            </div>

            <!-- Eligible -->
            <div class="col-6 col-lg-3">
                <div class="ov-card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <div class="ov-icon" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);">
                            <i class="bi bi-patch-check-fill" style="color:#16a34a;"></i>
                        </div>
                        <span class="ov-badge" style="color:#14532d;background:#dcfce7;">Pass</span>
                    </div>
                    <div>
                        <div class="ov-num"><?= $statEligible ?></div>
                        <div class="ov-label">Eligible Applications</div>
                    </div>
                </div>
            </div>

            <!-- Ineligible -->
            <div class="col-6 col-lg-3">
                <div class="ov-card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <div class="ov-icon" style="background:linear-gradient(135deg,#fef2f2,#fee2e2);">
                            <i class="bi bi-shield-exclamation" style="color:#dc2626;"></i>
                        </div>
                        <span class="ov-badge" style="color:#7f1d1d;background:#fee2e2;">Fail</span>
                    </div>
                    <div>
                        <div class="ov-num"><?= $statIneligible ?></div>
                        <div class="ov-label">Ineligible Applications</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ══ TABLE CONTROLS ══════════════════════════════════════════ -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">

        <!-- Left: Status filter tab bar -->
        <div class="sf-bar" role="group" aria-label="Filter applications by eligibility status" id="sfBar">
            <button class="sf-btn active" data-sf="all"
                    onclick="applyStatusFilter(this)" type="button"
                    aria-pressed="true" id="sfAll">
                <span class="sf-dot"></span>
                All Applications
                <span class="sf-count" id="sfCountAll"><?= $statTotal ?></span>
            </button>
            <button class="sf-btn" data-sf="pending"
                    onclick="applyStatusFilter(this)" type="button"
                    aria-pressed="false" id="sfPending">
                <span class="sf-dot"></span>
                Pending
                <span class="sf-count" id="sfCountPending"><?= $statPending ?></span>
            </button>
            <button class="sf-btn" data-sf="eligible"
                    onclick="applyStatusFilter(this)" type="button"
                    aria-pressed="false" id="sfEligible">
                <span class="sf-dot"></span>
                Eligible
                <span class="sf-count" id="sfCountEligible"><?= $statEligible ?></span>
            </button>
            <button class="sf-btn" data-sf="ineligible"
                    onclick="applyStatusFilter(this)" type="button"
                    aria-pressed="false" id="sfIneligible">
                <span class="sf-dot"></span>
                Ineligible
                <span class="sf-count" id="sfCountIneligible"><?= $statIneligible ?></span>
            </button>
        </div>

        <!-- Right: Existing server-side filter buttons -->
        <div class="d-flex gap-2 flex-shrink-0">
            <a href="index.php?filter=pending"
               class="btn btn-sm <?= $filter === 'pending' ? 'btn-dark' : 'btn-outline-dark' ?>">
                <i class="bi bi-clock-history me-1"></i>Show Pending Only
            </a>
            <a href="index.php?filter=all"
               class="btn btn-sm <?= $filter === 'all' ? 'btn-dark' : 'btn-outline-dark' ?>">
                <i class="bi bi-list-ul me-1"></i>Show All
            </a>
        </div>
    </div>

    <!-- Results count strip -->
    <div class="sf-results-info mb-2" id="sfResultsInfo">
        Showing <strong id="sfVisibleCount"><?= count($applications) ?></strong>
        of <strong><?= count($applications) ?></strong> applications
    </div>

    <!-- ── Applications table ── -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="appsTable">
                <thead>
                    <tr>
                        <th>App ID</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Submitted At</th>
                        <th>
                            <span class="d-flex align-items-center gap-1">
                                Eligibility Status
                                <i class="bi bi-info-circle text-muted" style="font-size:12px;"
                                   title="Use the filter tabs above to narrow results by status"></i>
                            </span>
                        </th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="appsTableBody">
                    <?php foreach ($applications as $app): ?>
                        <?php
                        // data-eligible: 'null' = pending check, '1' = pass, '0' = fail
                        if ($app['eligible'] === null) {
                            $eligibleVal = 'null';
                        } elseif ($app['eligible'] == 1) {
                            $eligibleVal = '1';
                        } else {
                            $eligibleVal = '0';
                        }
                        ?>
                        <tr data-eligible="<?= $eligibleVal ?>">
                            <td>
                                <span style="font-size:12px;font-weight:700;color:#94a3b8;">#</span><?= e($app['id']) ?>
                            </td>
                            <td>
                                <strong><?= e($app['full_name']) ?></strong><br>
                                <small class="text-muted"><?= e($app['student_code']) ?></small>
                            </td>
                            <td><?= e($app['program_name']) ?></td>
                            <td style="white-space:nowrap;font-size:13px;"><?= e($app['submitted_at']) ?></td>
                            <td>
                                <?php if ($app['eligible'] === null): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-clock-history me-1"></i>Pending Check
                                    </span>
                                <?php elseif ($app['eligible'] == 1): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-patch-check-fill me-1"></i>Eligible
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-shield-exclamation me-1"></i>Ineligible
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="index.php?check_id=<?= $app['id'] ?>"
                                       class="btn btn-sm btn-info text-white">
                                        <i class="bi bi-shield-check me-1"></i>Check
                                    </a>
                                    <?php if ($app['eligible'] !== null): ?>
                                        <a href="../eligibility_results/index.php"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye me-1"></i>Result
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4;"></i>
                                No applications found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <!-- JS-driven empty state (hidden until filter finds 0 rows) -->
                    <tr id="jsEmptyRow" style="display:none;">
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-funnel" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No applications match the selected filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const TOTAL_ROWS = <?= count($applications) ?>;

    /* ── Row-to-filter matching logic ─────────────────── */
    function rowMatches(row, sfKey) {
        const val = row.dataset.eligible;  // 'null' | '1' | '0'
        switch (sfKey) {
            case 'all':        return true;
            case 'pending':    return val === 'null';
            case 'eligible':   return val === '1';
            case 'ineligible': return val === '0';
            default:           return true;
        }
    }

    /* ── Main filter function ─────────────────────────── */
    window.applyStatusFilter = function (btn) {
        const sfKey = btn.dataset.sf;

        // Update tab active states
        document.querySelectorAll('#sfBar .sf-btn').forEach(b => {
            const isActive = b === btn;
            b.classList.toggle('active', isActive);
            b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        // Show / hide rows
        const rows = document.querySelectorAll('#appsTableBody tr[data-eligible]');
        let visible = 0;

        rows.forEach(row => {
            if (rowMatches(row, sfKey)) {
                row.classList.remove('sf-hidden');
                visible++;
            } else {
                row.classList.add('sf-hidden');
            }
        });

        // Toggle empty-state row
        const jsEmpty = document.getElementById('jsEmptyRow');
        if (jsEmpty) jsEmpty.style.display = (visible === 0 && TOTAL_ROWS > 0) ? '' : 'none';

        // Update results count
        const countEl = document.getElementById('sfVisibleCount');
        if (countEl) countEl.textContent = visible;
    };

    /* ── Keyboard: Enter / Space triggers filter ──────── */
    document.querySelectorAll('#sfBar .sf-btn').forEach(btn => {
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                applyStatusFilter(this);
            }
        });
    });

})();
</script>

<?php require_once '../../includes/footer.php'; ?>
