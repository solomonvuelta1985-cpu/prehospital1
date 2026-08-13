(function () {
    'use strict';

    const checkbox = document.getElementById('waiverRequired');
    const fields = document.getElementById('waiverFields');
    const input = document.getElementById('waiverAttachment');
    const error = document.getElementById('waiverUploadError');
    const preview = document.getElementById('waiverPreview');
    const previewImage = document.getElementById('waiverPreviewImage');
    const hasExistingAttachment = Boolean(document.getElementById('waiverExisting'));
    const maxSize = 20 * 1024 * 1024;
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!checkbox || !fields || !input) return;

    function setError(message) {
        if (!error) return;
        error.textContent = message || '';
        error.classList.toggle('is-visible', Boolean(message));
    }

    function sync() {
        const enabled = checkbox.checked;
        fields.hidden = !enabled && !hasExistingAttachment;
        input.required = enabled && !hasExistingAttachment;
        checkbox.setAttribute('aria-expanded', enabled ? 'true' : 'false');
    }

    function showPreview(file) {
        if (!preview || !previewImage) return;
        if (previewImage.dataset.objectUrl) {
            URL.revokeObjectURL(previewImage.dataset.objectUrl);
        }
        const objectUrl = URL.createObjectURL(file);
        previewImage.dataset.objectUrl = objectUrl;
        previewImage.src = objectUrl;
        preview.hidden = false;
    }

    input.addEventListener('change', function () {
        setError('');
        const file = input.files && input.files[0];
        if (!file) {
            if (preview) preview.hidden = true;
            return;
        }

        const extension = (file.name.split('.').pop() || '').toLowerCase();
        const validExtension = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);
        if (!allowedTypes.includes(file.type) || !validExtension) {
            input.value = '';
            if (preview) preview.hidden = true;
            setError('Please choose a JPG, PNG, GIF, or WebP image.');
            return;
        }

        if (file.size > maxSize) {
            input.value = '';
            if (preview) preview.hidden = true;
            setError('The waiver image must be 20MB or smaller.');
            return;
        }

        showPreview(file);
    });

    checkbox.addEventListener('change', function () {
        setError('');
        sync();
    });

    window.validateWaiverSelection = function () {
        if (!checkbox.checked) return true;
        const hasNewFile = Boolean(input.files && input.files.length);
        if (hasNewFile || hasExistingAttachment) return true;
        setError('A signed waiver image is required when the waiver is enabled.');
        fields.hidden = false;
        input.focus();
        return false;
    };

    window.syncWaiverSelection = sync;

    window.resetWaiverPicker = function () {
        checkbox.checked = false;
        input.value = '';
        if (preview) preview.hidden = true;
        setError('');
        sync();
    };

    sync();
}());
