// ============================================
// SERVER-SIDE DRAFT AUTOSAVE COMPATIBILITY
// ============================================
//
// Patient and medical form data must not be persisted in browser storage.
// The clinical form's primary autosave flow is implemented in
// public/prehospital_form.php and posts authenticated drafts to
// ../api/autosave_draft.php. That works on offline localhost/XAMPP because the
// local Apache and MySQL services remain available.
//
// This small module keeps the existing status/cleanup hooks used by the form
// and removes the legacy localStorage draft from older browser sessions.

const LEGACY_AUTO_SAVE_KEY = 'prehospital_autosave_draft';

function setFormDraftStatus(message, state) {
    const status = document.getElementById('formDraftStatus');
    const statusText = document.getElementById('formDraftStatusText');
    if (statusText) statusText.textContent = message;
    if (!status) return;

    status.classList.remove('is-hidden');
    status.classList.toggle('is-saving', state === 'saving');
    status.classList.toggle('is-saved', state === 'saved');
    status.classList.toggle('is-error', state === 'error');
}

function clearAutoSave() {
    // Remove only the legacy sensitive draft key. Other localStorage values
    // are UI preferences and are not form or patient data.
    try {
        window.localStorage.removeItem(LEGACY_AUTO_SAVE_KEY);
    } catch (error) {
        // Storage may be disabled by browser policy; server autosave is still
        // the authoritative recovery mechanism.
    }
}

function initAutoSave() {
    clearAutoSave();
    setFormDraftStatus('Server autosave ready', 'ready');
}
