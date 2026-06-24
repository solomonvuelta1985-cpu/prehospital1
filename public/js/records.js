/**
 * Records Page JavaScript — Full-featured
 * - AJAX live browsing (sort/filter/per-page)
 * - Toast notifications (no alert())
 * - Batch delete, batch status change, batch export
 * - Column visibility toggle (saved to localStorage)
 */
(function() {
    'use strict';

    var recordIdsToDelete = null;
    var currentRecordId = null;
    var deleteModal, viewRecordModal;

    document.addEventListener('DOMContentLoaded', function() {
        deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        viewRecordModal = new bootstrap.Modal(document.getElementById('viewRecordModal'));

        initFilters();
        initSearch();
        initBatchActions();
        initEventDelegation();
        initAJAX();
        initColumnToggle();
        initBackToTop();

        // Restore column visibility
        restoreColumnVisibility();
    });

    // ===== TOAST HELPER =====
    function showToast(message, type) {
        type = type || 'secondary';
        var container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }

        var icons = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-triangle-fill', warning: 'bi-exclamation-circle-fill', secondary: 'bi-info-circle-fill' };
        var icon = icons[type] || icons.secondary;

        var toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center border-0';
        toastEl.setAttribute('role', 'alert');
        toastEl.style.background = type === 'success' ? '#059669' : type === 'danger' ? '#dc2626' : type === 'warning' ? '#d97706' : '#64748b';
        toastEl.innerHTML = '<div class="d-flex"><div class="toast-body text-white d-flex align-items-center gap-2"><i class="bi ' + icon + '"></i>' + escapeHtml(message) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        container.appendChild(toastEl);

        var toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', function() { toastEl.remove(); });
    }

    // ===== FILTERS TOGGLE =====
    function initFilters() {
        var btn = document.getElementById('btnToggleFilters');
        var panel = document.getElementById('filtersPanel');
        if (!btn || !panel) return;
        btn.addEventListener('click', function() {
            var willOpen = !panel.classList.contains('is-open');
            panel.classList.toggle('is-open', willOpen);
            btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    }

    // ===== SEARCH =====
    function initSearch() {
        var input = document.getElementById('searchInput');
        if (!input) return;

        var searchTimeout = null;
        input.addEventListener('keyup', function() {
            var filter = input.value.trim().toUpperCase();
            clearTimeout(searchTimeout);

            if (filter.length === 0) {
                document.querySelectorAll('.records-table tbody tr.record-row').forEach(function(row) { row.style.display = ''; });
                return;
            }

            document.querySelectorAll('.records-table tbody tr.record-row').forEach(function(row) {
                var text = (row.textContent || '').toUpperCase();
                row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });

            searchTimeout = setTimeout(function() {
                fetch('../api/search_records.php?search=' + encodeURIComponent(filter))
                    .then(function(r) { return r.json(); })
                    .then(function(data) { if (data.success && data.records) renderSearchResults(data.records); })
                    .catch(function() {});
            }, 400);
        });
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value).replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"').replace(/'/g, '&#39;');
    }

    function ucfirst(s) { if (!s) return ''; return String(s).charAt(0).toUpperCase() + String(s).slice(1); }

    function formatDateDisplay(value) {
        if (!value || value === '0000-00-00') return '—';
        var d = new Date(value);
        if (isNaN(d.getTime())) return escapeHtml(value);
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[d.getMonth()] + ' ' + String(d.getDate()).padStart(2, '0') + ', ' + d.getFullYear();
    }

    function patientInitial(name) { var t = (name || '').trim(); return t ? t.charAt(0).toUpperCase() : '?'; }

    function renderSearchResults(records) {
        var tbody = document.querySelector('.records-table tbody');
        if (!tbody) return;
        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-search"></i></div><div class="empty-state-title">No matching records</div><div class="empty-state-description">Try adjusting your search terms.</div></div></td></tr>';
            return;
        }
        var html = '';
        records.forEach(function(record, idx) {
            var statusClass = {draft:'draft',completed:'completed',archived:'archived'}[record.status] || 'draft';
            var statusIcon = statusClass === 'completed' ? '<i class="bi bi-check-circle-fill"></i> ' : (statusClass === 'draft' ? '<i class="bi bi-pencil-fill"></i> ' : '');
            var vehicleHtml = record.vehicle_used ? '<span class="badge-vehicle"><i class="bi bi-truck"></i> ' + escapeHtml(ucfirst(record.vehicle_used)) + '</span>' : '<span style="color:#94a3b8;">—</span>';
            var draftResume = record.status === 'draft' ? '<li><a class="dropdown-item" href="prehospital_form.php?draft_id=' + record.id + '"><i class="bi bi-play-fill"></i> Resume</a></li><li><hr class="dropdown-divider"></li>' : '';
            var markCompletedHtml = record.status === 'draft' ? '<li><a class="dropdown-item" href="javascript:void(0)" data-mark-completed="' + record.id + '"><i class="bi bi-check-circle"></i> Mark Completed</a></li>' : '';
            html += '<tr class="record-row" data-record-id="' + record.id + '">' +
                '<td class="col-check" data-label="Select"><input type="checkbox" class="record-checkbox" value="' + record.id + '"></td>' +
                '<td class="col-form-number" data-label="Form #"><a href="javascript:void(0)" data-view-record="' + record.id + '" class="form-number-link"><strong>' + escapeHtml(record.form_number) + '</strong></a></td>' +
                '<td class="col-date" data-label="Date">' + formatDateDisplay(record.form_date) + '</td>' +
'<td class="col-patient" data-label="Patient"><a href="javascript:void(0)" data-view-record="' + record.id + '" class="patient-link">' + escapeHtml(record.patient_name || '—') + '</a></td>' +
                '<td class="col-age-gender" data-label="Age/Gender">' + escapeHtml(record.age || '—') + ' &middot; ' + (record.gender ? escapeHtml(ucfirst(record.gender)) : '—') + '</td>' +
                '<td class="col-vehicle" data-label="Vehicle">' + vehicleHtml + '</td>' +
                '<td class="col-status" data-label="Status"><span class="badge-status-pill ' + statusClass + '">' + statusIcon + escapeHtml(ucfirst(record.status)) + '</span></td>' +
                '<td class="col-modified" data-label="Last Modified">' + escapeHtml(record.time_ago || '') + '</td>' +
                '<td class="col-actions" data-label="Actions">' +
                    '<div class="dropdown action-dropdown"><button class="btn-view-sm" type="button" data-bs-toggle="dropdown">Actions <i class="bi bi-chevron-down ms-1" style="font-size:0.625rem;"></i></button>' +
                    '<ul class="dropdown-menu dropdown-menu-end">' + draftResume +
                    '<li><a class="dropdown-item" href="javascript:void(0)" data-view-record="' + record.id + '"><i class="bi bi-eye"></i> View</a></li>' +
                    '<li><a class="dropdown-item" href="edit_record.php?id=' + record.id + '"><i class="bi bi-pencil"></i> Edit</a></li>' +
                    markCompletedHtml + '<li><hr class="dropdown-divider"></li>' +
                    '<li><a class="dropdown-item dropdown-item-danger" href="javascript:void(0)" data-delete-record="' + record.id + '"><i class="bi bi-trash"></i> Delete</a></li>' +
                    '</ul></div></td></tr>';
        });
        tbody.innerHTML = html;
    }

    // ===== BATCH ACTIONS =====
    function initBatchActions() {
        var selectAllCheckbox = document.getElementById('selectAllCheckbox');
        var batchToolbar = document.getElementById('batchToolbar');
        var batchCount = document.getElementById('batchCount');

        if (!selectAllCheckbox || !batchToolbar) return;

        function getCheckedIds() {
            var checked = document.querySelectorAll('.record-checkbox:checked');
            return Array.from(checked).map(function(cb) { return parseInt(cb.value, 10); });
        }

        function updateBatchUI() {
            var ids = getCheckedIds();
            var count = ids.length;
            if (count > 0) { batchToolbar.style.display = 'flex'; batchCount.textContent = count; }
            else { batchToolbar.style.display = 'none'; selectAllCheckbox.checked = false; }
        }

        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.record-checkbox').forEach(function(cb) { cb.checked = selectAllCheckbox.checked; });
            updateBatchUI();
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('record-checkbox')) { updateBatchUI(); if (!e.target.checked) selectAllCheckbox.checked = false; }
        });

        // Select All / Deselect All
        var btnSelectAll = document.getElementById('btnSelectAll');
        if (btnSelectAll) {
            btnSelectAll.addEventListener('click', function() {
                var action = btnSelectAll.getAttribute('data-select');
                var checkboxes = document.querySelectorAll('.record-checkbox');
                if (action === 'all') {
                    checkboxes.forEach(function(cb) { cb.checked = true; });
                    selectAllCheckbox.checked = true;
                    btnSelectAll.setAttribute('data-select', 'none');
                    btnSelectAll.innerHTML = '<i class="bi bi-dash-circle"></i> Deselect All';
                } else {
                    checkboxes.forEach(function(cb) { cb.checked = false; });
                    selectAllCheckbox.checked = false;
                    btnSelectAll.setAttribute('data-select', 'all');
                    btnSelectAll.innerHTML = '<i class="bi bi-check-all"></i> Select All';
                }
                updateBatchUI();
            });
        }

        // Batch Delete
        var btnBatchDelete = document.getElementById('btnBatchDelete');
        if (btnBatchDelete) {
            btnBatchDelete.addEventListener('click', function() {
                var ids = getCheckedIds();
                if (ids.length === 0) return;
                document.getElementById('deleteConfirmMessage').textContent = 'Delete ' + ids.length + ' record' + (ids.length !== 1 ? 's' : '') + '? This cannot be undone.';
                recordIdsToDelete = ids;
                deleteModal.show();
            });
        }

        // Batch Export
        var btnBatchExport = document.getElementById('btnBatchExport');
        if (btnBatchExport) {
            btnBatchExport.addEventListener('click', function() {
                var ids = getCheckedIds();
                if (ids.length === 0) return;
                window.location.href = '../api/export_records.php?ids=' + ids.join(',');
            });
        }

        // Batch Mark Completed
        var btnBatchComplete = document.getElementById('btnBatchComplete');
        if (btnBatchComplete) {
            btnBatchComplete.addEventListener('click', function() {
                var ids = getCheckedIds();
                if (ids.length === 0) return;
                var csrfToken = document.getElementById('csrfToken').value;
                var promises = ids.map(function(id) {
                    return fetch('../api/update_record.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, status: 'completed', csrf_token: csrfToken })
                    }).then(function(r) { return r.json(); });
                });
                Promise.all(promises).then(function(results) {
                    var ok = results.filter(function(r) { return r.success; }).length;
                    showToast(ok + ' record(s) marked as completed', 'success');
                    setTimeout(function() { window.location.reload(); }, 800);
                }).catch(function() { showToast('Error updating records', 'danger'); });
            });
        }

        // Clear selection
        var btnClearSelection = document.getElementById('btnClearSelection');
        if (btnClearSelection) {
            btnClearSelection.addEventListener('click', function() {
                document.querySelectorAll('.record-checkbox').forEach(function(cb) { cb.checked = false; });
                selectAllCheckbox.checked = false;
                updateBatchUI();
            });
        }
    }

    // ===== EVENT DELEGATION =====
    function initEventDelegation() {
        document.addEventListener('click', function(e) {
            var deleteBtn = e.target.closest('[data-delete-record]');
            if (deleteBtn) {
                e.preventDefault();
                var id = parseInt(deleteBtn.getAttribute('data-delete-record'), 10);
                document.getElementById('deleteConfirmMessage').textContent = 'Delete this record? This cannot be undone.';
                recordIdsToDelete = [id];
                deleteModal.show();
                return;
            }
            var viewBtn = e.target.closest('[data-view-record]');
            if (viewBtn) { e.preventDefault(); viewRecord(parseInt(viewBtn.getAttribute('data-view-record'), 10)); return; }
            var markBtn = e.target.closest('[data-mark-completed]');
            if (markBtn) { e.preventDefault(); markCompleted(parseInt(markBtn.getAttribute('data-mark-completed'), 10)); return; }
        });
    }

    // ===== DELETE CONFIRMATION =====
    var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (!recordIdsToDelete || recordIdsToDelete.length === 0) return;
            var btn = document.getElementById('confirmDeleteBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Deleting...';
            var csrfToken = document.getElementById('csrfToken').value;
            var promises = recordIdsToDelete.map(function(id) {
                return fetch('../api/delete_record.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, csrf_token: csrfToken }) }).then(function(r) { return r.json(); });
            });
            Promise.all(promises).then(function(results) {
                deleteModal.hide();
                var allSuccess = results.every(function(r) { return r.success; });
                if (allSuccess) { showToast('Record(s) deleted successfully', 'success'); setTimeout(function() { window.location.reload(); }, 600); }
                else { showToast('Some records could not be deleted', 'danger'); setTimeout(function() { window.location.reload(); }, 1500); }
            }).catch(function() { deleteModal.hide(); showToast('Error deleting records', 'danger'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-trash"></i> Delete'; });
        });
    }

    // ===== VIEW RECORD MODAL =====
    function viewRecord(id) {
        currentRecordId = id;
        var modalContent = document.getElementById('modalRecordContent');
        var editBtn = document.getElementById('editRecordBtn');
        var viewFullDetailsBtn = document.getElementById('viewFullDetailsBtn');
        modalContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border" role="status" style="color:var(--primary);"><span class="visually-hidden">Loading...</span></div><p class="mt-3" style="color:#64748b;">Loading record details...</p></div>';
        editBtn.onclick = function() { window.location.href = 'edit_record.php?id=' + id; };
        viewFullDetailsBtn.onclick = function() { window.location.href = 'view_record.php?id=' + id; };
        viewRecordModal.show();
        fetch('../api/get_record.php?id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(data) { modalContent.innerHTML = data.success ? data.html : '<div class="alert" style="background:var(--danger-light);color:var(--danger);border:1px solid var(--danger-border);border-radius:8px;padding:1rem;"><i class="bi bi-exclamation-triangle-fill"></i> Error: ' + escapeHtml(data.message) + '</div>'; })
            .catch(function() { modalContent.innerHTML = '<div class="alert" style="background:var(--danger-light);color:var(--danger);border:1px solid var(--danger-border);border-radius:8px;padding:1rem;"><i class="bi bi-exclamation-triangle-fill"></i> Failed to load record.</div>'; });
    }

    // ===== MARK AS COMPLETED =====
    function markCompleted(id) {
        var csrfToken = document.getElementById('csrfToken').value;
        fetch('../api/update_record.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, status: 'completed', csrf_token: csrfToken }) })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success) { showToast('Record marked completed', 'success'); setTimeout(function() { window.location.reload(); }, 600); } else { showToast('Error: ' + (data.message || 'Could not update'), 'danger'); } })
            .catch(function() { showToast('Error updating record', 'danger'); });
    }

    // ===== AJAX: Sort, Per-page, Filter auto-submit via fetch =====
    function initAJAX() {
        var perPageSel = document.getElementById('perPageSelect');
        var sortSel = document.getElementById('sortQuick');

        function ajaxSubmit() {
            var form = document.getElementById('filtersForm');
            if (!form) return;
            var formData = new FormData(form);
            var params = new URLSearchParams(formData).toString();
            var url = 'records.php?' + params;

            // Update history
            if (history.pushState) history.pushState(null, '', url);

            // Show subtle loading state on table
            var table = document.querySelector('.table-card');
            if (table) table.style.opacity = '0.6';

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var newTbody = doc.querySelector('.records-table tbody');
                    var newResultBar = doc.querySelector('.result-bar');
                    var newPagination = doc.querySelector('.pagination-nav');
                    if (newTbody) document.querySelector('.records-table tbody').innerHTML = newTbody.innerHTML;
                    if (newResultBar) document.querySelector('.result-bar').innerHTML = newResultBar.innerHTML;
                    if (newPagination) {
                        var existingPagination = document.querySelector('.pagination-nav');
                        if (existingPagination) existingPagination.innerHTML = newPagination.innerHTML;
                        else document.querySelector('.table-card').insertAdjacentHTML('afterend', newPagination.outerHTML);
                    }
                    document.querySelector('.page-subtitle strong').textContent = doc.querySelector('.page-subtitle strong') ? doc.querySelector('.page-subtitle strong').textContent : '';
                    if (table) table.style.opacity = '1';
                    // Re-bind AJAX listeners after DOM update
                    initAJAX();
                })
                .catch(function() { if (table) table.style.opacity = '1'; });
        }

        if (perPageSel) perPageSel.addEventListener('change', ajaxSubmit);
        if (sortSel) sortSel.addEventListener('change', ajaxSubmit);

        // Also intercept filter form submit for AJAX
        var filterForm = document.getElementById('filtersForm');
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                ajaxSubmit();
            });
        }
    }

    // ===== COLUMN VISIBILITY TOGGLE =====
    function initColumnToggle() {
        var btn = document.getElementById('btnColumnToggle');
        var menu = document.getElementById('columnToggleMenu');
        if (!btn || !menu) return;

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!btn.contains(e.target) && !menu.contains(e.target)) menu.classList.remove('show');
        });

        menu.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var col = cb.getAttribute('data-column');
                toggleColumn(col, cb.checked);
                saveColumnVisibility();
            });
        });
    }

    function toggleColumn(col, visible) {
        document.querySelectorAll('.col-' + col).forEach(function(td) { td.style.display = visible ? '' : 'none'; });
        var th = document.querySelector('th.col-header-' + col);
        if (th) th.style.display = visible ? '' : 'none';
    }

    function saveColumnVisibility() {
        var state = {};
        document.querySelectorAll('#columnToggleMenu input[type="checkbox"]').forEach(function(cb) {
            state[cb.getAttribute('data-column')] = cb.checked;
        });
        localStorage.setItem('recordsColumnVisibility', JSON.stringify(state));
    }

    function restoreColumnVisibility() {
        var saved = localStorage.getItem('recordsColumnVisibility');
        if (!saved) return;
        try {
            var state = JSON.parse(saved);
            Object.keys(state).forEach(function(col) {
                var cb = document.querySelector('#columnToggleMenu input[data-column="' + col + '"]');
                if (cb) { cb.checked = state[col]; toggleColumn(col, state[col]); }
            });
        } catch(e) {}
    }

    // ===== BACK TO TOP =====
    function initBackToTop() {
        var btn = document.getElementById('backToTop');
        if (!btn) return;
        window.addEventListener('scroll', function() { btn.classList.toggle('show', window.pageYOffset > 300); });
        btn.addEventListener('click', function() { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    }

    // ===== EXPORT & PRINT =====
    var btnExport = document.getElementById('btnExportCSV');
    if (btnExport) btnExport.addEventListener('click', function() { window.location.href = '../api/export_records.php?' + new URLSearchParams(window.location.search).toString(); });
    var btnPrint = document.getElementById('btnPrint');
    if (btnPrint) btnPrint.addEventListener('click', function() { window.print(); });

})();