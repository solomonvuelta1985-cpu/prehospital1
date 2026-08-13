// ============================================
// AUTO-SAVE TO LOCALSTORAGE
// Saves form data periodically and restores on page load
// ============================================

const AUTO_SAVE_KEY = 'prehospital_autosave_draft';
const AUTO_SAVE_INTERVAL = 30000; // 30 seconds
let autoSaveTimer = null;
let draftStatusHideTimer = null;

function setFormDraftStatus(message, state) {
    const status = document.getElementById('formDraftStatus');
    const statusText = document.getElementById('formDraftStatusText');
    if (statusText) statusText.textContent = message;
    if (status) {
        if (draftStatusHideTimer) {
            clearTimeout(draftStatusHideTimer);
            draftStatusHideTimer = null;
        }
        status.classList.remove('is-hidden');
        status.classList.toggle('is-saving', state === 'saving');
        status.classList.toggle('is-saved', state === 'saved');
        status.classList.toggle('is-error', state === 'error');

        // Saved/restored is a transient confirmation, not a permanent header label.
        if (state === 'saved') {
            draftStatusHideTimer = setTimeout(() => {
                status.classList.add('is-hidden');
            }, 3000);
        }
    }
}

function initAutoSave() {
    const form = document.getElementById('preHospitalForm');
    if (!form || document.getElementById('editForm')) return; // Only for new forms

    setFormDraftStatus('Autosave ready', 'ready');

    // A server draft opened with ?draft_id= is already the authoritative
    // record. Do not interrupt it with the separate localStorage restore prompt.
    const hasServerDraft = new URLSearchParams(window.location.search).has('draft_id');

    // Check for an existing local draft only on a fresh form
    const savedDraft = localStorage.getItem(AUTO_SAVE_KEY);
    if (savedDraft && !hasServerDraft) {
        try {
            const draftData = JSON.parse(savedDraft);
            const savedTime = new Date(draftData._savedAt);
            const timeAgo = Math.round((Date.now() - savedTime.getTime()) / 60000);

            if (typeof Notiflix !== 'undefined') {
                Notiflix.Confirm.show(
                    'Restore Unsaved Draft?',
                    `You have an unsaved draft from ${timeAgo} minute(s) ago. Would you like to restore it?`,
                    'Restore',
                    'Discard',
                    function() { restoreAutoSave(draftData); },
                    function() { clearAutoSave(); }
                );
            }
        } catch (e) {
            clearAutoSave();
        }
    }

    // Start auto-save timer
    autoSaveTimer = setInterval(autoSaveForm, AUTO_SAVE_INTERVAL);

    // Also save on tab switch
    const origShowTab = window.showTab;
    if (typeof origShowTab === 'function') {
        window.showTab = function(n) {
            origShowTab(n);
            autoSaveForm();
        };
    }
}

function autoSaveForm() {
    const form = document.getElementById('preHospitalForm');
    if (!form) return;

    try {
        const formData = {};
        const inputs = form.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            if (!input.name || input.type === 'hidden' || input.name === 'csrf_token') return;

            if (input.type === 'checkbox') {
                if (!formData[input.name]) formData[input.name] = [];
                if (input.checked) formData[input.name].push(input.value);
            } else if (input.type === 'radio') {
                if (input.checked) formData[input.name] = input.value;
            } else if (input.type === 'file') {
                // Skip file inputs
            } else {
                formData[input.name] = input.value;
            }
        });

        // Save injuries too
        if (typeof injuries !== 'undefined' && injuries && injuries.length > 0) {
            formData._injuries = JSON.stringify(injuries);
        }

        formData._savedAt = new Date().toISOString();
        localStorage.setItem(AUTO_SAVE_KEY, JSON.stringify(formData));

        // Show subtle indicator
        setFormDraftStatus('Draft saved just now', 'saved');
        showAutoSaveIndicator();
    } catch (e) {
        // Silent fail for auto-save
    }
}

function restoreAutoSave(draftData) {
    const form = document.getElementById('preHospitalForm');
    if (!form || !draftData) return;

    Object.keys(draftData).forEach(key => {
        if (key.startsWith('_')) return; // Skip metadata

        const value = draftData[key];

        if (Array.isArray(value)) {
            // Checkboxes
            value.forEach(v => {
                const cb = form.querySelector(`input[name="${key}"][value="${v}"]`);
                if (cb) cb.checked = true;
            });
        } else {
            // Radio buttons
            const radio = form.querySelector(`input[name="${key}"][value="${value}"]`);
            if (radio) {
                radio.checked = true;
                return;
            }

            // Regular inputs
            const input = form.querySelector(`[name="${key}"]`);
            if (input && input.type !== 'file' && input.type !== 'hidden') {
                input.value = value;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    });

    // Restore injuries if present
    if (draftData._injuries) {
        try {
            const savedInjuries = JSON.parse(draftData._injuries);
            if (Array.isArray(savedInjuries) && savedInjuries.length > 0) {
                injuries = savedInjuries;
                injuryCounter = Math.max(...injuries.map(i => i.id)) + 1;
                updateInjuryList();
            }
        } catch (e) { /* ignore */ }
    }

    if (typeof Notiflix !== 'undefined') {
        Notiflix.Notify.success('Draft restored successfully!');
    }
    setFormDraftStatus('Draft restored', 'saved');
}

function clearAutoSave() {
    localStorage.removeItem(AUTO_SAVE_KEY);
}

function showAutoSaveIndicator() {
    let indicator = document.getElementById('autoSaveIndicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'autoSaveIndicator';
        indicator.className = 'clinical-autosave-toast';
        document.body.appendChild(indicator);
    }
    indicator.textContent = 'Draft saved';
    indicator.style.opacity = '1';
    setTimeout(() => { indicator.style.opacity = '0'; }, 2000);
}
