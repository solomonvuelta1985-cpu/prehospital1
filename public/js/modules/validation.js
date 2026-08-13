// ============================================
// VALIDATION MODULE
// Form validation, submission, age calculation, time input formatting
// ============================================

// ============================================
// AGE CALCULATION & GROWTH STATUS
// ============================================

document.getElementById('dateOfBirth')?.addEventListener('change', function() {
    const dob = new Date(this.value);
    const today = new Date();

    const totalMonths = (today.getFullYear() - dob.getFullYear()) * 12 + (today.getMonth() - dob.getMonth());

    const ageElement = document.getElementById('age');
    const ageUnitElement = document.getElementById('ageUnit');

    if (totalMonths < 24) {
        if (ageElement) ageElement.value = totalMonths;
        if (ageUnitElement) ageUnitElement.value = 'months';
    } else {
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        if (ageElement) ageElement.value = age;
        if (ageUnitElement) ageUnitElement.value = 'years';
    }

    updateGrowthStatus();
});

function updateGrowthStatus() {
    const ageElement = document.getElementById('age');
    const ageUnitElement = document.getElementById('ageUnit');
    const growthStatusEl = document.getElementById('growthStatus');

    if (!growthStatusEl || !ageElement || !ageElement.value) return;

    const age = parseInt(ageElement.value);
    const ageUnit = ageUnitElement ? ageUnitElement.value : 'years';

    if (isNaN(age)) return;

    const ageInYears = ageUnit === 'months' ? age / 12 : age;

    if (ageInYears < 2) {
        growthStatusEl.value = 'infant';
    } else if (ageInYears < 13) {
        growthStatusEl.value = 'child';
    } else if (ageInYears < 60) {
        growthStatusEl.value = 'adult';
    } else {
        growthStatusEl.value = 'senior';
    }
}

document.getElementById('age')?.addEventListener('change', updateGrowthStatus);
document.getElementById('ageUnit')?.addEventListener('change', updateGrowthStatus);

// ============================================
// FORM SUBMISSION & VALIDATION
// ============================================

function submitForm() {
    // Ensure the hidden emergency *_specify fields reflect the current dropdown/Other
    // selection before we validate and submit them.
    if (typeof window.syncEmergencySpecify === 'function') {
        window.syncEmergencySpecify();
    }

    if (typeof window.validateWaiverSelection === 'function' && !window.validateWaiverSelection()) {
        return;
    }

    const requiredFields = [
        { id: 'formDate', name: 'Date' },
        { id: 'patientName', name: 'Patient Name' },
        { id: 'age', name: 'Age' }
    ];

    let missingFields = [];
    for (let field of requiredFields) {
        const element = document.getElementById(field.id);
        if (!element || !element.value.trim()) {
            missingFields.push(field.name);
        }
    }

    const ageElement = document.getElementById('age');
    const ageUnitElement = document.getElementById('ageUnit');
    if (ageElement) {
        const ageValue = parseInt(ageElement.value);
        const ageUnit = ageUnitElement ? ageUnitElement.value : 'years';

        if (ageValue < 0) {
            missingFields.push('Age (cannot be negative)');
        } else if (ageUnit === 'months' && ageValue > 24) {
            missingFields.push('Age (use years for age > 24 months)');
        } else if (ageValue === 0 && ageUnit === 'years') {
            missingFields.push('Age (use months for infants under 1 year)');
        }
    }

    const formDateElement = document.getElementById('formDate');
    if (formDateElement && formDateElement.value) {
        const now = new Date();
        const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        if (formDateElement.value > today) {
            missingFields.push('Date (cannot be in the future)');
        }
    }

    const genderSelect = document.getElementById('gender');
    if (!genderSelect || !genderSelect.value) {
        missingFields.push('Gender');
    }

    // At least one Type of Emergency Call must be selected (Medical/Trauma/OB/General),
    // and any checked type MUST have its corresponding "specify" field filled.
    const emergencyTypeSelected = document.querySelector('input[name="emergency_type[]"]:checked');
    if (!emergencyTypeSelected) {
        missingFields.push('Type of Emergency Call (select at least one)');
    } else {
        const emergencySpecifyMap = {
            'medical': { id: 'medicalSpecify', label: 'Medical specify' },
            'trauma':  { id: 'traumaSpecify',  label: 'Trauma specify' },
            'ob':      { id: 'obSpecify',      label: 'OB specify' },
            'general': { id: 'generalSpecify', label: 'General specify' }
        };
        const checkedEmergencies = document.querySelectorAll('input[name="emergency_type[]"]:checked');
        checkedEmergencies.forEach(function(cb) {
            const map = emergencySpecifyMap[cb.value];
            if (map) {
                const specifyField = document.getElementById(map.id);
                if (!specifyField || !specifyField.value.trim()) {
                    missingFields.push(map.label + ' (must fill when checked)');
                }
            }
        });
    }

    if (missingFields.length > 0) {
        const fieldsList = missingFields.map(field => `<strong style="color: #dc3545;">•</strong> <strong>${field}</strong>`).join('<br>');
        Notiflix.Report.warning(
            'Required Fields Missing',
            `Please fill out the following required fields:<br><br>${fieldsList}`,
            'OK'
        );
        return;
    }

    Notiflix.Confirm.show(
        'Confirm Submission',
        'Are you sure you want to save this form? Please review all information before proceeding.',
        'Yes, Save Form',
        'Cancel',
        function okCb() {
            submitFormData();
        },
        function cancelCb() {
            // Do nothing
        }
    );
}

function submitFormData() {
    Notiflix.Loading.standard('Saving form...', {
        backgroundColor: 'rgba(0,0,0,0.8)',
    });

    const injuriesDataField = document.getElementById('injuriesData');
    if (injuriesDataField) {
        injuriesDataField.value = JSON.stringify(injuries);
    }

    const narrativeField = document.getElementById('narrativeReportField');
    const narrativeTextEl = document.querySelector('#narrativeContent .narrative-text');
    if (narrativeField && narrativeTextEl) {
        narrativeField.value = narrativeTextEl.textContent.trim();
    }

    const dateFieldNames = ['date_of_birth', 'form_date', 'lmp', 'edc'];
    dateFieldNames.forEach(name => {
        const input = document.querySelector(`[name="${name}"]`);
        if (input) {
            const val = input.value ? input.value.trim() : '';
            if (val === '' || val === '0000-00-00') {
                input.value = '';
            }
        }
    });

    const form = document.getElementById('preHospitalForm');
    const formData = new FormData(form);

    if (typeof clearSectionMemory === 'function') {
        clearSectionMemory();
    }

    sessionStorage.removeItem('createFormCurrentTab');

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        return response.json().then(data => {
            return {
                ok: response.ok,
                status: response.status,
                data: data
            };
        });
    })
    .then(result => {
        Notiflix.Loading.remove();

        if (result.ok && result.data.success) {
            clearAutoSave();

            Notiflix.Report.success(
                'Form Saved Successfully',
                result.data.message || 'Your form has been saved successfully.',
                'OK',
                function() {
                    window.location.href = result.data.redirect_url || '../public/records.php';
                }
            );
        } else {
            throw new Error(result.data.message || 'Form submission failed');
        }
    })
    .catch(error => {
        Notiflix.Loading.remove();
        console.error('Form submission error:', error);
        Notiflix.Report.failure(
            'Submission Failed',
            error.message || 'An error occurred while saving the form. Please try again.',
            'OK'
        );
    });
}

function printForm() {
    window.print();
}

function clearForm() {
    Notiflix.Confirm.show(
        'Clear Form Data',
        'Are you sure you want to clear all form data? This action cannot be undone.',
        'Yes, Clear All',
        'Cancel',
        function okCb() {
            document.getElementById('preHospitalForm').reset();
            if (typeof window.resetWaiverPicker === 'function') {
                window.resetWaiverPicker();
            }
            if (typeof window.hydrateHospitalSelect === 'function') {
                window.hydrateHospitalSelect('');
            }
            if (typeof window.hydrateArrivalHospitalSelect === 'function') {
                window.hydrateArrivalHospitalSelect('');
            }
            clearAllInjuries();
            currentTab = 0;
            navigateTab(0);
            document.querySelectorAll('.nav-link').forEach(tab => {
                tab.classList.remove('completed');
            });

            if (typeof clearSectionMemory === 'function') {
                clearSectionMemory();
            }

            sessionStorage.removeItem('createFormCurrentTab');

            Notiflix.Notify.success('Form data cleared successfully');
        },
        function cancelCb() {
            // Do nothing
        }
    );
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            navigateTab(-1);
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            navigateTab(1);
        }
    }
});

// ============================================
// 12-HOUR TIME INPUT FORMATTING
// ============================================

function initialize12HourTimeInputs() {
    const timeInputs = document.querySelectorAll('.time-input-12hr, input[data-time-field="true"]');

    timeInputs.forEach(input => {
        input.addEventListener('input', handleTimeInput);
        input.addEventListener('blur', handleTimeBlur);

        const form = input.closest('form');
        if (form && !form.hasAttribute('data-time-conversion-attached')) {
            form.setAttribute('data-time-conversion-attached', 'true');
            form.addEventListener('submit', handleFormSubmit);
        }
    });
}

function handleTimeInput(event) {
    const input = event.target;
    let value = input.value.toUpperCase();

    value = value.replace(/[^0-9:APM\s]/g, '');

    if (!value.includes(':') && !value.includes('A') && !value.includes('P')) {
        if (value.length === 3) {
            const hour = value[0];
            const minutes = value.substring(1);
            value = hour + ':' + minutes;
        }
        else if (value.length === 4) {
            const hour = value.substring(0, 2);
            const minutes = value.substring(2);
            if (parseInt(hour) >= 10 && parseInt(hour) <= 12) {
                value = hour + ':' + minutes;
            }
        }
        else if (value.length === 1 && parseInt(value) > 1) {
            value = value + ':';
        }
        else if (value.length === 2) {
            const hour = parseInt(value);
            if (hour >= 1 && hour <= 12) {
                value = value + ':';
            }
        }
    }

    if (value.match(/\d[AP]/)) {
        value = value.replace(/(\d)([AP])/, '$1 $2');
    }

    const parts = value.split(':');
    if (parts[0]) {
        let hour = parseInt(parts[0]) || 0;
        if (hour > 12) {
            parts[0] = '12';
            value = parts.join(':');
        } else if (hour === 0) {
            parts[0] = '';
            value = parts.join(':');
        }
    }

    if (parts.length > 1 && parts[1]) {
        const minuteMatch = parts[1].match(/^(\d+)/);
        if (minuteMatch) {
            let minutes = parseInt(minuteMatch[1]);
            if (minutes > 59) {
                parts[1] = parts[1].replace(/^\d+/, '59');
                value = parts.join(':');
            }
        }
    }

    input.value = value;
}

function handleTimeBlur(event) {
    const input = event.target;
    let value = input.value.trim().toUpperCase();

    if (!value) {
        input.classList.remove('is-invalid');
        removeTimeInputError(input);
        return;
    }

    const timeRegex = /^(0?[1-9]|1[0-2]):([0-5][0-9])\s?(AM|PM)$/;
    const match = value.match(timeRegex);

    if (match) {
        const hour = match[1].padStart(2, '0');
        const minute = match[2];
        const period = match[3];
        input.value = `${hour}:${minute} ${period}`;
        input.classList.remove('is-invalid');
        removeTimeInputError(input);

        input.dataset.time24 = convert12to24Hour(input.value);
    } else {
        input.classList.add('is-invalid');
        showTimeInputError(input, 'Invalid time format. Use: HH:MM AM/PM (e.g., 2:30 PM)');
    }
}

function convert12to24Hour(time12h) {
    if (!time12h) return '';

    const timeRegex = /^(0?[1-9]|1[0-2]):([0-5][0-9])\s?(AM|PM)$/i;
    const match = time12h.trim().match(timeRegex);

    if (!match) return '';

    let hour = parseInt(match[1]);
    const minute = match[2];
    const period = match[3].toUpperCase();

    if (period === 'AM') {
        if (hour === 12) hour = 0;
    } else {
        if (hour !== 12) hour += 12;
    }

    return `${String(hour).padStart(2, '0')}:${minute}`;
}

function convert24to12Hour(time24h) {
    if (!time24h) return '';

    const parts = time24h.split(':');
    if (parts.length < 2) return '';

    let hour = parseInt(parts[0]);
    const minute = parts[1];

    let period = 'AM';

    if (hour === 0) {
        hour = 12;
    } else if (hour === 12) {
        period = 'PM';
    } else if (hour > 12) {
        hour -= 12;
        period = 'PM';
    }

    return `${String(hour).padStart(2, '0')}:${minute} ${period}`;
}

function showTimeInputError(input, message) {
    removeTimeInputError(input);

    const errorDiv = document.createElement('div');
    errorDiv.className = 'time-input-error invalid-feedback d-block';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.color = '#dc3545';
    errorDiv.style.marginTop = '0.25rem';
    errorDiv.textContent = message;

    input.parentElement.appendChild(errorDiv);
}

function removeTimeInputError(input) {
    const existingError = input.parentElement.querySelector('.time-input-error');
    if (existingError) {
        existingError.remove();
    }
}

function handleFormSubmit(event) {
    const form = event.target;
    const timeInputs = form.querySelectorAll('.time-input-12hr, input[data-time-field="true"]');

    let hasError = false;

    timeInputs.forEach(input => {
        const value = input.value.trim();

        if (!value) {
            if (input.hasAttribute('required')) {
                input.classList.add('is-invalid');
                showTimeInputError(input, 'This field is required.');
                hasError = true;
            }
            return;
        }

        const timeRegex = /^(0?[1-9]|1[0-2]):([0-5][0-9])\s?(AM|PM)$/i;
        if (!timeRegex.test(value)) {
            input.classList.add('is-invalid');
            showTimeInputError(input, 'Invalid time format. Use: HH:MM AM/PM (e.g., 2:30 PM)');
            hasError = true;
            return;
        }

        const time24h = convert12to24Hour(value);
        if (!time24h) {
            input.classList.add('is-invalid');
            showTimeInputError(input, 'Invalid time value.');
            hasError = true;
            return;
        }

        input.value = time24h;
        input.classList.remove('is-invalid');
        removeTimeInputError(input);
    });

    if (hasError) {
        event.preventDefault();
        const firstError = form.querySelector('.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
        return false;
    }

    return true;
}

function loadExistingTimeValues() {
    const timeInputs = document.querySelectorAll('.time-input-12hr, input[data-time-field="true"]');

    timeInputs.forEach(input => {
        const value = input.value.trim();

        if (value && value.includes(':')) {
            if (!value.match(/AM|PM/i)) {
                input.value = convert24to12Hour(value);
            }
        }
    });
}
