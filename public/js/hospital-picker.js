/**
 * Hospital Endorsement picker.
 * Keeps a friendly dropdown for common destinations while retaining an
 * editable "Other" path for destinations not present in the database list.
 */
(function () {
    'use strict';

    var select = document.getElementById('hospital');
    var otherWrap = document.getElementById('hospitalOtherWrap');
    var otherInput = document.getElementById('hospitalOther');
    var hiddenInput = document.getElementById('hospitalNameValue');

    if (!select || !otherWrap || !otherInput || !hiddenInput) return;

    function normalize(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toUpperCase();
    }

    function setOtherVisibility(showOther) {
        otherWrap.style.display = showOther ? '' : 'none';
        otherInput.required = showOther;
        otherInput.setAttribute('aria-hidden', showOther ? 'false' : 'true');
    }

    function sync() {
        var isOther = select.value === '__other__';
        setOtherVisibility(isOther);
        hiddenInput.value = isOther ? otherInput.value.trim() : select.value;
    }

    function hydrate(value) {
        var raw = String(value || '').trim();
        var normalized = normalize(raw);
        var matched = null;

        Array.prototype.forEach.call(select.options, function (option) {
            if (matched || !option.value || option.value === '__other__') return;

            var aliases = String(option.getAttribute('data-aliases') || '')
                .split('|')
                .map(normalize);
            if (aliases.indexOf(normalized) !== -1 || normalize(option.value) === normalized) {
                matched = option;
            }
        });

        if (matched) {
            select.value = matched.value;
            otherInput.value = '';
        } else if (raw) {
            select.value = '__other__';
            otherInput.value = raw;
        } else {
            select.value = '';
            otherInput.value = '';
        }

        sync();
    }

    select.addEventListener('change', function () {
        if (select.value !== '__other__') otherInput.value = '';
        sync();
    });
    otherInput.addEventListener('input', sync);
    otherInput.addEventListener('blur', sync);

    window.hydrateHospitalSelect = hydrate;
    window.syncHospitalSelect = sync;
    hydrate(hiddenInput.value);
})();

// Arrival-at-hospital picker uses the same destinations and legacy aliases
// as the Hospital Endorsement picker, but stores its value separately.
(function () {
    'use strict';

    var select = document.getElementById('arrHospName');
    var otherWrap = document.getElementById('arrivalHospitalOtherWrap');
    var otherInput = document.getElementById('arrivalHospitalOther');
    var hiddenInput = document.getElementById('arrivalHospitalNameValue');

    if (!select || !otherWrap || !otherInput || !hiddenInput) return;

    function normalize(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toUpperCase();
    }

    function setOtherVisibility(showOther) {
        otherWrap.style.display = showOther ? '' : 'none';
        otherInput.required = showOther;
        otherInput.setAttribute('aria-hidden', showOther ? 'false' : 'true');
    }

    function sync() {
        var isOther = select.value === '__other__';
        setOtherVisibility(isOther);
        hiddenInput.value = isOther ? otherInput.value.trim() : select.value;
    }

    function hydrate(value) {
        var raw = String(value || '').trim();
        var normalized = normalize(raw);
        var matched = null;

        Array.prototype.forEach.call(select.options, function (option) {
            if (matched || !option.value || option.value === '__other__') return;

            var aliases = String(option.getAttribute('data-aliases') || '')
                .split('|')
                .map(normalize);
            if (aliases.indexOf(normalized) !== -1 || normalize(option.value) === normalized) {
                matched = option;
            }
        });

        if (matched) {
            select.value = matched.value;
            otherInput.value = '';
        } else if (raw) {
            select.value = '__other__';
            otherInput.value = raw;
        } else {
            select.value = '';
            otherInput.value = '';
        }

        sync();
    }

    select.addEventListener('change', function () {
        if (select.value !== '__other__') otherInput.value = '';
        sync();
    });
    otherInput.addEventListener('input', sync);
    otherInput.addEventListener('blur', sync);

    window.hydrateArrivalHospitalSelect = hydrate;
    window.syncArrivalHospitalSelect = sync;
    hydrate(hiddenInput.value);
})();
