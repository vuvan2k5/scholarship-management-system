<?php
// ============================================================
// admin/scholarship_programs/index.php
// AJAX CRUD — Create, Edit, Delete without page reload
// ============================================================

$pageTitle = 'Scholarship Programs';

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
requireRole('admin');

require_once '../../includes/header.php';
require_once '../../includes/navbar.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Scholarship Programs</h1>
    <p class="page-subtitle">Manage scholarship program specifications and availability.
      <span class="badge bg-primary ms-2">AJAX — No Reload</span>
    </p>
  </div>
  <button id="btn-add-program" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i> Add Program
  </button>
</div>

<!-- Page-level alert container (for success/error outside modal) -->
<div id="page-alert-container" class="mb-3"></div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table" id="programs-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Budget</th>
          <th>Slots</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="programs-tbody">
        <!-- Populated via AJAX -->
        <tr>
          <td colspan="8" class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary"></div> Loading…
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     CREATE / EDIT MODAL
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="programModal" tabindex="-1" aria-labelledby="programModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="programModalLabel">Add New Program</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <!-- Modal alert (validation/server errors) -->
        <div id="programModalAlert"></div>

        <form id="programForm" novalidate>
          <input type="hidden" id="program-id">

          <div class="row">
            <div class="col-12 mb-3">
              <label class="form-label fw-semibold">Program Name <span class="text-danger">*</span></label>
              <input type="text" id="program-name" class="form-control"
                     placeholder="e.g. Academic Excellence Scholarship">
              <div class="invalid-feedback" id="err-name"></div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea id="program-description" class="form-control" rows="3"
                      placeholder="Brief description of the scholarship program..."></textarea>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Budget (VND) <span class="text-danger">*</span></label>
              <input type="number" id="program-budget" class="form-control"
                     min="1" placeholder="e.g. 10000000">
              <div class="invalid-feedback" id="err-budget"></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Slots (Max Recipients) <span class="text-danger">*</span></label>
              <input type="number" id="program-slots" class="form-control"
                     min="1" placeholder="e.g. 5">
              <div class="invalid-feedback" id="err-slots"></div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label fw-semibold">Start Date</label>
              <input type="date" id="program-start" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label fw-semibold">End Date</label>
              <input type="date" id="program-end" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label fw-semibold">Status</label>
              <select id="program-status" class="form-select">
                <option value="open">Open</option>
                <option value="closed">Closed</option>
              </select>
            </div>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-save-program">
          <i class="bi bi-check-lg me-1"></i> Save Program
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Bootstrap JS (needed for Modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- AJAX CRUD script — fetch() based, no page reload -->
<script src="<?= BASE_URL ?>/assets/js/programs_crud.js"></script>

<?php require_once '../../includes/footer.php'; ?>
