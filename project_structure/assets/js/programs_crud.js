/**
 * assets/js/programs_crud.js
 * AJAX CRUD for Scholarship Programs — uses fetch() API
 * No page reload for Create, Edit, Delete operations.
 */

const ProgramsCRUD = (() => {

    const API = '../../admin/api/programs_api.php'; // resolved from page context

    // ── Utility ──────────────────────────────────────────────

    /** Escape HTML to prevent XSS in dynamically inserted content */
    const esc = str => String(str ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
    );

    /** Format number as VND */
    const vnd = n => Number(n).toLocaleString('vi-VN') + ' VND';

    /** Show Bootstrap alert inside a container */
    function showAlert(container, type, message) {
        container.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${esc(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
    }

    /** Clear modal form errors */
    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
    }

    // ── Render one table row ──────────────────────────────────

    function buildRow(p) {
        const statusBadge = p.status === 'open'
            ? `<span class="badge badge-status-open">Open</span>`
            : `<span class="badge badge-status-closed">Closed</span>`;

        return `
        <tr id="program-row-${p.id}">
            <td><span class="text-muted">#${esc(p.id)}</span></td>
            <td><strong>${esc(p.name)}</strong></td>
            <td class="text-success fw-semibold">${vnd(p.budget)}</td>
            <td>${esc(p.slots)}</td>
            <td class="text-muted">${esc(p.start_date ?? '—')}</td>
            <td class="text-muted">${esc(p.end_date ?? '—')}</td>
            <td>${statusBadge}</td>
            <td>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-warning btn-action btn-edit-program"
                            data-id="${p.id}">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-danger btn-action btn-delete-program"
                            data-id="${p.id}" data-name="${esc(p.name)}">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </div>
            </td>
        </tr>`;
    }

    // ── Load all rows via AJAX ────────────────────────────────

    async function loadTable() {
        const tbody = document.getElementById('programs-tbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary"></div> Loading…
        </td></tr>`;

        try {
            const res  = await fetch(`${API}?action=list`);
            const json = await res.json();

            if (!json.success) throw new Error(json.message);

            if (json.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">No programs found.</td></tr>`;
                return;
            }

            tbody.innerHTML = json.data.map(buildRow).join('');
            bindRowButtons();
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-danger text-center">${esc(err.message)}</td></tr>`;
        }
    }

    // ── Bind row-level edit/delete buttons ────────────────────

    function bindRowButtons() {
        document.querySelectorAll('.btn-edit-program').forEach(btn => {
            btn.addEventListener('click', () => openEditModal(parseInt(btn.dataset.id)));
        });
        document.querySelectorAll('.btn-delete-program').forEach(btn => {
            btn.addEventListener('click', () => confirmDelete(parseInt(btn.dataset.id), btn.dataset.name));
        });
    }

    // ── CREATE modal ─────────────────────────────────────────

    function openCreateModal() {
        const modal = document.getElementById('programModal');
        const form  = document.getElementById('programForm');
        const title = document.getElementById('programModalLabel');
        const alertBox = document.getElementById('programModalAlert');

        title.textContent = 'Add New Program';
        alertBox.innerHTML = '';
        form.reset();
        clearErrors(form);

        document.getElementById('program-id').value = '';
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    // ── EDIT modal — fetch existing data ─────────────────────

    async function openEditModal(id) {
        const modal    = document.getElementById('programModal');
        const form     = document.getElementById('programForm');
        const title    = document.getElementById('programModalLabel');
        const alertBox = document.getElementById('programModalAlert');

        title.textContent = 'Edit Program';
        alertBox.innerHTML = '';
        form.reset();
        clearErrors(form);

        // Show modal immediately with spinner
        bootstrap.Modal.getOrCreateInstance(modal).show();
        form.innerHTML += ''; // keep form visible

        try {
            const res  = await fetch(`${API}?action=get&id=${id}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            const p = json.data;
            document.getElementById('program-id').value          = p.id;
            document.getElementById('program-name').value        = p.name;
            document.getElementById('program-description').value = p.description ?? '';
            document.getElementById('program-budget').value      = p.budget;
            document.getElementById('program-slots').value       = p.slots;
            document.getElementById('program-start').value       = p.start_date ?? '';
            document.getElementById('program-end').value         = p.end_date ?? '';
            document.getElementById('program-status').value      = p.status;
        } catch (err) {
            showAlert(alertBox, 'danger', err.message);
        }
    }

    // ── SAVE (create or update) via fetch() ──────────────────

    async function saveProgram() {
        const form     = document.getElementById('programForm');
        const alertBox = document.getElementById('programModalAlert');
        const saveBtn  = document.getElementById('btn-save-program');

        clearErrors(form);
        alertBox.innerHTML = '';

        const id     = document.getElementById('program-id').value;
        const action = id ? 'update' : 'create';

        const payload = {
            id:          id ? parseInt(id) : undefined,
            name:        document.getElementById('program-name').value.trim(),
            description: document.getElementById('program-description').value.trim(),
            budget:      parseFloat(document.getElementById('program-budget').value),
            slots:       parseInt(document.getElementById('program-slots').value),
            start_date:  document.getElementById('program-start').value,
            end_date:    document.getElementById('program-end').value,
            status:      document.getElementById('program-status').value,
        };

        // Client-side validation
        let valid = true;
        if (!payload.name) {
            document.getElementById('program-name').classList.add('is-invalid');
            document.getElementById('err-name').textContent = 'Program name is required.';
            valid = false;
        }
        if (!payload.budget || payload.budget <= 0) {
            document.getElementById('program-budget').classList.add('is-invalid');
            document.getElementById('err-budget').textContent = 'Enter a valid budget.';
            valid = false;
        }
        if (!payload.slots || payload.slots <= 0) {
            document.getElementById('program-slots').classList.add('is-invalid');
            document.getElementById('err-slots').textContent = 'Enter a valid slot count.';
            valid = false;
        }
        if (!valid) return;

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

        try {
            const res  = await fetch(`${API}?action=${action}`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            const json = await res.json();

            if (!json.success) throw new Error(json.message);

            // Close modal and update table without reload
            bootstrap.Modal.getInstance(document.getElementById('programModal')).hide();

            if (action === 'create') {
                // Prepend new row to table
                document.getElementById('programs-tbody').insertAdjacentHTML(
                    'afterbegin', buildRow(json.data)
                );
                showPageAlert('success', `✅ Program "${json.data.name}" created successfully.`);
            } else {
                // Replace existing row in-place
                const existing = document.getElementById(`program-row-${json.data.id}`);
                if (existing) existing.outerHTML = buildRow(json.data);
                showPageAlert('success', `✅ Program "${json.data.name}" updated successfully.`);
            }

            bindRowButtons();

        } catch (err) {
            showAlert(alertBox, 'danger', err.message);
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Save Program';
        }
    }

    // ── DELETE with confirmation ──────────────────────────────

    async function confirmDelete(id, name) {
        const confirmed = window.confirm(`Delete program "${name}"?\n\nThis cannot be undone.`);
        if (!confirmed) return;

        try {
            const res  = await fetch(`${API}?action=delete`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ id }),
            });
            const json = await res.json();

            if (!json.success) throw new Error(json.message);

            // Remove row from DOM without reload
            const row = document.getElementById(`program-row-${id}`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity    = '0';
                setTimeout(() => row.remove(), 300);
            }
            showPageAlert('success', `✅ Program deleted successfully.`);

        } catch (err) {
            showPageAlert('danger', `❌ ${err.message}`);
        }
    }

    // ── Page-level alert (outside modal) ─────────────────────

    function showPageAlert(type, message) {
        const container = document.getElementById('page-alert-container');
        if (!container) return;
        container.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${esc(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ── Init ─────────────────────────────────────────────────

    function init() {
        // Load table on page load
        loadTable();

        // "Add Program" button
        const addBtn = document.getElementById('btn-add-program');
        if (addBtn) addBtn.addEventListener('click', openCreateModal);

        // Save button inside modal
        const saveBtn = document.getElementById('btn-save-program');
        if (saveBtn) saveBtn.addEventListener('click', saveProgram);
    }

    return { init };

})();

document.addEventListener('DOMContentLoaded', ProgramsCRUD.init);
