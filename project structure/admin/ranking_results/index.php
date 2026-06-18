<?php
// ============================================================
// admin/ranking_results/index.php
// Full ranking dashboard: generate, configure slots, view,
// publish, export. Admin controls all; scores come from engine.
// ============================================================
$pageTitle = 'Ranking Results';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

$pdo     = getDB();
$adminId = currentUserId();

require_once '../../includes/ranking.php';
ensureRankingColumns($pdo);

// ── Handle: Publish results for a program ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_program'])) {
    $pubPid = (int)$_POST['publish_program'];
    // Check already published
    $alreadyStmt = $pdo->prepare("
        SELECT COUNT(*) FROM ranking_results rr
        JOIN applications a ON rr.application_id = a.id
        WHERE a.program_id = ? AND rr.published = 1
    ");
    $alreadyStmt->execute([$pubPid]);
    if ($alreadyStmt->fetchColumn() > 0 && !isset($_POST['force_republish'])) {
        setFlash('warning', 'Results for this program have already been published. Use "Re-publish" to update.');
    } else {
        $pubResult = publishRanking($pdo, $pubPid, $adminId);
        $progNamePub = $pdo->prepare("SELECT name FROM scholarship_programs WHERE id = ?");
        $progNamePub->execute([$pubPid]);
        $pn = $progNamePub->fetchColumn() ?: "Program #$pubPid";
        setFlash('success',
            "Results published for \"{$pn}\". "
            . "{$pubResult['notified']} students notified. "
            . "{$pubResult['certificates']} certificate(s) generated."
        );
    }
    header('Location: index.php?program_id=' . $pubPid);
    exit;
}

// ── Handle: Generate ranking for ONE program (POST form) ──────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_program'])) {
    $genPid   = (int)$_POST['generate_program'];
    $genSlots = isset($_POST['slots']) && $_POST['slots'] !== '' ? (int)$_POST['slots'] : null;
    $genNotes = trim($_POST['notes'] ?? '');

    $result = generateRanking($pdo, $genPid, $genSlots, $adminId, $genNotes);
    if (!empty($result['errors'])) {
        setFlash('error', implode(' ', $result['errors']));
    } else {
        setFlash('success',
            "Ranking generated for \"{$result['program']}\": "
            . "{$result['ranked']} ranked, {$result['awarded']} awarded "
            . "(of {$result['slots']} slots)."
        );
    }
    header('Location: index.php?program_id=' . $genPid);
    exit;
}

// ── Handle: Generate ALL rankings (GET) ───────────────────────
if (isset($_GET['generate_all'])) {
    $summaries = generateAllRankings($pdo, $adminId, 'All Programs batch run');
    $total = array_sum(array_column($summaries, 'ranked'));
    $aw    = array_sum(array_column($summaries, 'awarded'));
    setFlash('success', "Rankings generated for all programs. Total ranked: {$total}, Awarded: {$aw}.");
    header('Location: index.php');
    exit;
}

// ── Filter: selected program ──────────────────────────────────
$filterPid = (int)($_GET['program_id'] ?? 0);

// ── Programs list ─────────────────────────────────────────────
$programs = $pdo->query("SELECT id, name, slots FROM scholarship_programs ORDER BY name ASC")->fetchAll();

// ── Fetch ranking rows for selected / all programs ────────────
$where  = $filterPid ? "WHERE a.program_id = $filterPid" : '';
$rankings = $pdo->query("
    SELECT
        rr.*,
        a.id            AS app_id,
        a.submitted_at,
        a.program_id,
        u.full_name     AS student_name,
        u.student_code,
        sp.name         AS program_name,
        sp.slots,
        sp.budget,
        COALESCE(spr.gpa, 0) AS gpa,
        ac.certificate_code,
        ac.issued_at    AS cert_issued_at
    FROM ranking_results rr
    JOIN applications a         ON rr.application_id = a.id
    JOIN users u                ON a.student_id = u.id
    JOIN scholarship_programs sp ON a.program_id = sp.id
    LEFT JOIN student_profiles spr ON spr.student_id = a.student_id
    LEFT JOIN award_certificates ac ON ac.application_id = a.id
    $where
    ORDER BY sp.id ASC, rr.rank ASC
")->fetchAll();

// ── Group by program ──────────────────────────────────────────
$grouped = [];
foreach ($rankings as $row) {
    $pid = $row['program_id'];
    if (!isset($grouped[$pid])) {
        $grouped[$pid] = [
            'name'       => $row['program_name'],
            'slots'      => (int)$row['slots'],
            'budget'     => (float)$row['budget'],
            'published'  => false,
            'rows'       => [],
        ];
    }
    if ($row['published']) $grouped[$pid]['published'] = true;
    $grouped[$pid]['rows'][] = $row;
}

// ── Summary stats (overall) ───────────────────────────────────
$statsQ = $pdo->query("
    SELECT
        COUNT(DISTINCT a.id)                                   AS total_applicants,
        SUM(a.eligible = 1)                                    AS eligible_count,
        SUM(rr.awarded = 1)                                    AS awarded_count,
        (SELECT COALESCE(SUM(slots),0) FROM scholarship_programs) AS total_slots
    FROM applications a
    LEFT JOIN ranking_results rr ON rr.application_id = a.id
")->fetch();

// ── Run history ───────────────────────────────────────────────
$history = $pdo->query("
    SELECT rrh.*, sp.name AS program_name, u.full_name AS generated_by_name
    FROM ranking_run_history rrh
    LEFT JOIN scholarship_programs sp ON rrh.program_id = sp.id
    JOIN users u ON rrh.generated_by = u.id
    ORDER BY rrh.generated_at DESC
    LIMIT 15
")->fetchAll();

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">
      <i class="bi bi-bar-chart-steps me-2" style="color:var(--primary);"></i>Ranking Results
    </h1>
    <p class="page-subtitle">
      Auto-generated from reviewer scores · tie-break by GPA then submission date ·
      admin controls generation and publication.
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="export.php?format=excel<?= $filterPid ? '&program_id='.$filterPid : '' ?>"
       class="btn btn-success" id="btn-export-excel">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
    </a>
    <a href="export.php?format=pdf<?= $filterPid ? '&program_id='.$filterPid : '' ?>"
       class="btn btn-danger" id="btn-export-pdf">
      <i class="bi bi-file-earmark-pdf me-1"></i> PDF
    </a>
    <a href="index.php?generate_all=1" class="btn btn-primary" id="btn-generate-all"
       onclick="return confirm('Re-generate rankings for ALL programs from current evaluation scores?')">
      <i class="bi bi-magic me-1"></i> Generate All
    </a>
  </div>
</div>

<?php showFlash(); ?>

<!-- ── Workflow Banner ───────────────────────────────────────── -->
<div style="background:linear-gradient(135deg,rgba(37,99,235,.05),rgba(124,58,237,.05));
            border:1px solid rgba(37,99,235,.12);border-radius:var(--radius-md);
            padding:12px 20px;margin-bottom:24px;overflow-x:auto;">
  <div style="display:flex;align-items:center;min-width:620px;">
    <?php
    $wSteps = [
        ['icon'=>'bi-star-half',          'label'=>'Evaluation Scores',   'active'=>false],
        ['icon'=>'bi-bar-chart-steps',    'label'=>'Ranking Results',     'active'=>true ],
        ['icon'=>'bi-trophy',             'label'=>'Award Selection',     'active'=>false],
        ['icon'=>'bi-megaphone',          'label'=>'Result Publication',  'active'=>false],
        ['icon'=>'bi-award',              'label'=>'Certificate Generation','active'=>false],
    ];
    foreach ($wSteps as $i => $st):
      $c  = $st['active'] ? 'var(--primary)' : 'var(--gray-300)';
      $bg = $st['active'] ? 'rgba(37,99,235,.12)' : 'transparent';
    ?>
      <?php if ($i > 0): ?>
        <div style="flex:1;height:2px;background:var(--gray-200);margin:0 4px;"></div>
      <?php endif; ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:3px;min-width:80px;">
        <div style="width:36px;height:36px;border-radius:50%;background:<?= $bg ?>;
                    border:2px solid <?= $c ?>;display:flex;align-items:center;justify-content:center;">
          <i class="bi <?= $st['icon'] ?>" style="color:<?= $c ?>;font-size:14px;"></i>
        </div>
        <span style="font-size:10px;font-weight:<?= $st['active'] ? '700' : '500' ?>;
                     color:<?= $c ?>;text-align:center;line-height:1.3;"><?= $st['label'] ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="row g-4">

  <!-- ── LEFT: Generate Panel ─────────────────────────────── -->
  <div class="col-lg-4">

    <!-- Generate Panel -->
    <div class="card mb-4" style="position:sticky;top:80px;">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-magic me-2" style="color:var(--primary);"></i>Generate Ranking
        </div>
        <form method="POST" id="generate-form">
          <!-- Program selection -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Scholarship Program <span class="text-danger">*</span></label>
            <select name="generate_program" class="form-select" id="gen-program-sel" required>
              <option value="">— Select Program —</option>
              <?php foreach ($programs as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $filterPid == $p['id'] ? 'selected' : '' ?>
                        data-slots="<?= e($p['slots']) ?>">
                  <?= e($p['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Scholarship slots -->
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Scholarship Slots
              <span style="font-size:11px;font-weight:400;color:var(--gray-400);">(leave blank = use program default)</span>
            </label>
            <input type="number" name="slots" id="gen-slots-input" class="form-control"
                   placeholder="e.g. 5" min="1" max="9999">
            <div class="form-text" id="gen-slots-help">Slots determine how many applicants are Awarded.</div>
          </div>

          <!-- Tie-break info (readonly) -->
          <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;
                       padding:10px 14px;margin-bottom:12px;font-size:12.5px;color:var(--gray-600);">
            <div style="font-weight:700;margin-bottom:4px;color:var(--gray-700);">
              <i class="bi bi-arrow-down-up me-1" style="color:var(--primary);"></i>Tie-Break Rules
            </div>
            <div>1. Higher GPA</div>
            <div>2. Earlier Submission Date</div>
          </div>

          <!-- Notes -->
          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:13px;">Run Notes
              <span style="font-weight:400;color:var(--gray-400);">(optional)</span>
            </label>
            <input type="text" name="notes" class="form-control form-control-sm"
                   placeholder="e.g. After updated GPA scores">
          </div>

          <button type="submit" class="btn btn-primary w-100" id="btn-generate"
                  onclick="return confirm('Generate ranking for the selected program?\n\nThis will recalculate all ranks from current evaluation scores.')">
            <i class="bi bi-bar-chart-steps me-2"></i>Generate Ranking
          </button>
        </form>
      </div>
    </div>

    <!-- Ranking Summary Stats -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-graph-up me-2" style="color:var(--info);"></i>Ranking Summary
        </div>
        <?php
        $total     = (int)($statsQ['total_applicants'] ?? 0);
        $eligible  = (int)($statsQ['eligible_count']  ?? 0);
        $awarded   = (int)($statsQ['awarded_count']   ?? 0);
        $slots     = (int)($statsQ['total_slots']     ?? 0);
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <?php
          $sumItems = [
              ['Total Applicants',   $total,   'bi-file-text',         'var(--primary)'],
              ['Eligible',           $eligible, 'bi-patch-check-fill', 'var(--success)'],
              ['Awarded',            $awarded,  'bi-trophy-fill',      '#f59e0b'],
              ['Total Slots',        $slots,    'bi-person-check',     'var(--info)'],
          ];
          foreach ($sumItems as [$label, $val, $icon, $color]): ?>
            <div style="background:var(--gray-50);border-radius:8px;padding:12px 14px;text-align:center;">
              <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:20px;margin-bottom:4px;display:block;"></i>
              <div style="font-size:22px;font-weight:900;color:#0f172a;"><?= $val ?></div>
              <div style="font-size:11px;color:var(--gray-400);"><?= $label ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div><!-- /col-lg-4 -->

  <!-- ── RIGHT: Results + History ─────────────────────────── -->
  <div class="col-lg-8">

    <!-- Program filter bar -->
    <div class="table-card mb-4" style="padding:12px 16px;">
      <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
        <label class="form-label mb-0" style="font-weight:600;white-space:nowrap;">
          <i class="bi bi-funnel me-1"></i>View Program:
        </label>
        <select name="program_id" class="form-select form-select-sm" style="max-width:240px;"
                onchange="this.form.submit()">
          <option value="">All Programs</option>
          <?php foreach ($programs as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filterPid == $p['id'] ? 'selected' : '' ?>>
              <?= e($p['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($filterPid): ?>
          <a href="index.php" class="btn btn-sm btn-secondary"><i class="bi bi-x-lg"></i></a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Per-program ranking tables -->
    <?php if (empty($grouped)): ?>
      <div class="table-card mb-4">
        <div style="padding:60px 24px;text-align:center;color:var(--gray-400);">
          <i class="bi bi-bar-chart-steps" style="font-size:48px;display:block;margin-bottom:12px;opacity:.3;"></i>
          <div style="font-weight:700;font-size:15px;">No ranking data found</div>
          <div style="font-size:13px;margin-top:6px;">
            Select a program and click <strong>Generate Ranking</strong> to get started.
          </div>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($grouped as $pid => $pg): ?>
        <?php
        $isPublished = $pg['published'];
        $pgRows      = $pg['rows'];
        $pgAwarded   = count(array_filter($pgRows, fn($r) => $r['awarded'] == 1));
        ?>
        <div class="card mb-4">
          <div class="card-body">
            <!-- Program header row -->
            <div style="display:flex;align-items:center;justify-content:space-between;
                         flex-wrap:wrap;gap:10px;margin-bottom:16px;
                         padding-bottom:12px;border-bottom:1px solid var(--gray-100);">
              <div>
                <div style="font-size:15px;font-weight:800;color:#0f172a;"><?= e($pg['name']) ?></div>
                <div style="font-size:12px;color:var(--gray-400);">
                  <?= count($pgRows) ?> ranked ·
                  <?= $pgAwarded ?> awarded ·
                  <?= $pg['slots'] ?> slots ·
                  Budget: <?= number_format($pg['budget'], 0, ',', '.') ?> VNĐ
                </div>
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <?php if ($isPublished): ?>
                  <span class="badge badge-eligible" style="padding:6px 12px;">
                    <i class="bi bi-megaphone-fill me-1"></i>Published
                  </span>
                <?php else: ?>
                  <form method="POST" style="margin:0;" id="pub-form-<?= $pid ?>">
                    <input type="hidden" name="publish_program" value="<?= $pid ?>">
                    <button type="submit" class="btn btn-sm btn-warning" id="btn-publish-<?= $pid ?>"
                            onclick="return confirm('Publish results for \'<?= addslashes($pg['name']) ?>\'?\n\nThis will:\n• Notify all students\n• Generate certificates for awarded students\n• Create disbursement records')">
                      <i class="bi bi-megaphone me-1"></i>Publish Results
                    </button>
                  </form>
                <?php endif; ?>
                <form method="POST" style="margin:0;" id="repub-form-<?= $pid ?>">
                  <input type="hidden" name="publish_program" value="<?= $pid ?>">
                  <input type="hidden" name="force_republish" value="1">
                  <button type="submit" class="btn btn-sm btn-outline-secondary"
                          id="btn-republish-<?= $pid ?>"
                          onclick="return confirm('Re-publish results for \'<?= addslashes($pg['name']) ?>\'?\n\nThis will send notifications again.')">
                    <i class="bi bi-arrow-repeat me-1"></i>Re-publish
                  </button>
                </form>
              </div>
            </div>

            <!-- Ranking table -->
            <div class="table-responsive">
              <table class="table" style="font-size:13px;">
                <thead>
                  <tr>
                    <th style="width:60px;">Rank</th>
                    <th>Student</th>
                    <th style="text-align:center;">GPA</th>
                    <th style="text-align:center;">Score</th>
                    <th>Award Status</th>
                    <th>Tie-Break</th>
                    <th>Certificate</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pgRows as $row):
                    $rank    = (int)$row['rank'];
                    $awarded = (int)$row['awarded'];
                    $isTop3  = $rank <= 3;
                    $rankColor = match(true) {
                        $rank === 1  => '#f59e0b',
                        $rank === 2  => '#94a3b8',
                        $rank === 3  => '#cd7c2f',
                        $awarded     => 'var(--primary)',
                        default      => 'var(--gray-400)',
                    };
                  ?>
                    <tr style="<?= $awarded ? 'background:rgba(22,163,74,.03);' : '' ?>
                                <?= !$awarded ? 'opacity:.75;' : '' ?>">
                      <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                          <?php if ($rank === 1): ?>
                            <i class="bi bi-trophy-fill" style="color:#f59e0b;font-size:15px;"></i>
                          <?php elseif ($rank === 2): ?>
                            <i class="bi bi-trophy-fill" style="color:#94a3b8;font-size:15px;"></i>
                          <?php elseif ($rank === 3): ?>
                            <i class="bi bi-trophy-fill" style="color:#cd7c2f;font-size:15px;"></i>
                          <?php endif; ?>
                          <span style="font-size:15px;font-weight:900;color:<?= $rankColor ?>;">#<?= $rank ?></span>
                        </div>
                      </td>
                      <td>
                        <strong><?= e($row['student_name']) ?></strong>
                        <?php if ($row['student_code']): ?>
                          <div style="font-size:10.5px;color:var(--gray-400);"><?= e($row['student_code']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td style="text-align:center;font-weight:700;color:var(--primary);">
                        <?= number_format((float)$row['gpa'], 2) ?>
                      </td>
                      <td style="text-align:center;">
                        <span style="font-size:16px;font-weight:900;color:#0f172a;">
                          <?= number_format((float)$row['total_score'], 2) ?>
                        </span>
                        <!-- Score bar -->
                        <div style="width:80%;margin:3px auto;height:4px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                          <div style="width:<?= min(100, round((float)$row['total_score'])) ?>%;height:100%;background:var(--primary);border-radius:99px;"></div>
                        </div>
                      </td>
                      <td>
                        <?php if ($awarded): ?>
                          <span class="badge badge-eligible" style="font-size:11px;">
                            <i class="bi bi-trophy me-1"></i>Awarded
                          </span>
                        <?php else: ?>
                          <span class="badge badge-ineligible" style="font-size:11px;">
                            Not Awarded
                          </span>
                        <?php endif; ?>
                      </td>
                      <td style="font-size:11.5px;color:var(--gray-400);">
                        <?php if ($row['tie_break_reason']): ?>
                          <span style="background:rgba(234,179,8,.1);color:#92400e;
                                       padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;">
                            <i class="bi bi-arrow-down-up me-1"></i><?= e($row['tie_break_reason']) ?>
                          </span>
                        <?php else: ?>—<?php endif; ?>
                      </td>
                      <td style="font-size:12px;">
                        <?php if ($row['certificate_code']): ?>
                          <span style="font-family:monospace;font-size:11px;color:var(--success);font-weight:600;">
                            <i class="bi bi-award me-1"></i><?= e($row['certificate_code']) ?>
                          </span>
                          <?php if ($row['cert_issued_at']): ?>
                            <div style="font-size:10px;color:var(--gray-400);">
                              Issued: <?= e(date('d M Y', strtotime($row['cert_issued_at']))) ?>
                            </div>
                          <?php endif; ?>
                        <?php elseif ($awarded && !$isPublished): ?>
                          <span style="font-size:11px;color:var(--gray-400);">Publish to generate</span>
                        <?php else: ?>—<?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- Ranking Run History -->
    <div class="card">
      <div class="card-body">
        <div class="card-title mb-3" style="padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
          <i class="bi bi-clock-history me-2" style="color:var(--info);"></i>Ranking History
          <span style="font-size:12px;font-weight:400;color:var(--gray-400);">Last 15 runs</span>
        </div>
        <?php if (empty($history)): ?>
          <p style="font-size:13px;color:var(--gray-400);margin:0;">No ranking runs recorded yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table" style="font-size:12.5px;margin:0;">
              <thead>
                <tr>
                  <th>Run #</th>
                  <th>Program</th>
                  <th>Total Ranked</th>
                  <th>Awarded</th>
                  <th>Generated By</th>
                  <th>Date</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $h): ?>
                  <tr>
                    <td><span class="text-muted">#<?= e($h['id']) ?></span></td>
                    <td>
                      <?= $h['program_name']
                          ? e($h['program_name'])
                          : '<span class="badge badge-info">All Programs</span>' ?>
                    </td>
                    <td style="font-weight:700;"><?= e($h['total_ranked']) ?></td>
                    <td style="color:var(--success);font-weight:700;"><?= e($h['awarded_count']) ?></td>
                    <td><?= e($h['generated_by_name']) ?></td>
                    <td class="text-muted" style="white-space:nowrap;">
                      <?= e(date('d M Y, H:i', strtotime($h['generated_at']))) ?>
                    </td>
                    <td style="color:var(--gray-400);"><?= $h['notes'] ? e($h['notes']) : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /col-lg-8 -->
</div><!-- /row -->

<script>
// Auto-fill slots from selected program
document.getElementById('gen-program-sel')?.addEventListener('change', function() {
    const sel = this.options[this.selectedIndex];
    const slots = sel ? sel.dataset.slots : '';
    const inp   = document.getElementById('gen-slots-input');
    const help  = document.getElementById('gen-slots-help');
    if (inp) inp.placeholder = slots ? 'Default: ' + slots : 'e.g. 5';
    if (help) help.textContent = slots
        ? 'Program default: ' + slots + ' slots. Override to change.'
        : 'Slots determine how many applicants receive the award.';
});
// Trigger on load if pre-selected
document.getElementById('gen-program-sel')?.dispatchEvent(new Event('change'));
</script>

<?php require_once '../../includes/footer.php'; ?>
