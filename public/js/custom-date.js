/**
 * Custom Date Input Components
 * Replaces native date inputs with Month/Day/Year dropdowns
 * Provides better UX especially on mobile devices
 * NOTE: Time inputs remain as text inputs with manual entry
 */

// Wait for page to fully load (including skeleton removal)
window.addEventListener('load', function() {
    // Add a small delay to ensure skeleton is removed
    setTimeout(function() {
        console.log('Initializing custom date components...');

        // Replace all date inputs
        document.querySelectorAll('input[type="date"]').forEach(function(input) {
            try {
                replaceDateInput(input);
            } catch(e) {
                console.error('Error replacing date input:', e);
            }
        });

        // Replace all datetime-local inputs
        document.querySelectorAll('input[type="datetime-local"]').forEach(function(input) {
            try {
                replaceDateTimeInput(input);
            } catch(e) {
                console.error('Error replacing datetime input:', e);
            }
        });

        console.log('Custom date components initialized successfully');
    }, 500); // Wait 500ms after page load
});

/**
 * Replace date input with Month/Day/Year dropdowns
 */
function replaceDateInput(originalInput) {
    const isRequired = originalInput.hasAttribute('required');
    const fieldName = originalInput.name;
    const fieldId = originalInput.id;
    const existingValue = originalInput.value;

    // Create wrapper
    const wrapper = document.createElement('div');
    wrapper.className = 'custom-date-input';
    wrapper.style.display = 'flex';
    wrapper.style.gap = '8px';

    // Parse existing value
    let selectedMonth = '';
    let selectedDay = '';
    let selectedYear = '';

    if (existingValue) {
        const parts = existingValue.split('-');
        if (parts.length === 3) {
            selectedYear = parts[0];
            selectedMonth = parts[1];
            selectedDay = parts[2];
        }
    }

    // Month dropdown
    const monthSelect = document.createElement('select');
    monthSelect.className = 'form-control';
    monthSelect.name = fieldName + '_month';
    monthSelect.id = fieldId + '_month';
    monthSelect.style.flex = '2';
    if (isRequired) monthSelect.required = true;

    monthSelect.innerHTML = `
        <option value="">Month</option>
        <option value="01" ${selectedMonth === '01' ? 'selected' : ''}>January</option>
        <option value="02" ${selectedMonth === '02' ? 'selected' : ''}>February</option>
        <option value="03" ${selectedMonth === '03' ? 'selected' : ''}>March</option>
        <option value="04" ${selectedMonth === '04' ? 'selected' : ''}>April</option>
        <option value="05" ${selectedMonth === '05' ? 'selected' : ''}>May</option>
        <option value="06" ${selectedMonth === '06' ? 'selected' : ''}>June</option>
        <option value="07" ${selectedMonth === '07' ? 'selected' : ''}>July</option>
        <option value="08" ${selectedMonth === '08' ? 'selected' : ''}>August</option>
        <option value="09" ${selectedMonth === '09' ? 'selected' : ''}>September</option>
        <option value="10" ${selectedMonth === '10' ? 'selected' : ''}>October</option>
        <option value="11" ${selectedMonth === '11' ? 'selected' : ''}>November</option>
        <option value="12" ${selectedMonth === '12' ? 'selected' : ''}>December</option>
    `;

    // Day input (manual entry)
    const dayInput = document.createElement('input');
    dayInput.type = 'text';
    dayInput.className = 'form-control';
    dayInput.name = fieldName + '_day';
    dayInput.id = fieldId + '_day';
    dayInput.style.flex = '1';
    dayInput.placeholder = 'DD';
    dayInput.maxLength = 2;
    dayInput.pattern = '(0?[1-9]|[12][0-9]|3[01])';
    dayInput.value = selectedDay ? parseInt(selectedDay) : '';
    if (isRequired) dayInput.required = true;

    // Auto-format day input (add leading zero)
    dayInput.addEventListener('blur', function() {
        if (dayInput.value && dayInput.value.length > 0) {
            const day = parseInt(dayInput.value);
            if (day >= 1 && day <= 31) {
                dayInput.value = String(day).padStart(2, '0');
            }
        }
    });

    // Year input (manual entry)
    const yearInput = document.createElement('input');
    yearInput.type = 'text';
    yearInput.className = 'form-control';
    yearInput.name = fieldName + '_year';
    yearInput.id = fieldId + '_year';
    yearInput.style.flex = '1.5';
    yearInput.placeholder = 'YYYY';
    yearInput.maxLength = 4;
    yearInput.pattern = '[0-9]{4}';
    yearInput.value = selectedYear;
    if (isRequired) yearInput.required = true;

    // Hidden input to store the combined value
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = fieldName;
    hiddenInput.id = fieldId;
    hiddenInput.value = existingValue || '';

    // Update hidden input when any field changes
    function updateDateValue() {
        const month = monthSelect.value;
        let day = dayInput.value.trim();
        const year = yearInput.value.trim();

        // Ensure day is 2 digits
        if (day && day.length > 0) {
            const dayNum = parseInt(day);
            if (dayNum >= 1 && dayNum <= 31) {
                day = String(dayNum).padStart(2, '0');
            }
        }

        if (month && day && year && year.length === 4) {
            hiddenInput.value = `${year}-${month}-${day}`;
        } else {
            hiddenInput.value = '';
        }

        // Trigger change event for autosave
        const event = new Event('change', { bubbles: true });
        hiddenInput.dispatchEvent(event);
    }

    monthSelect.addEventListener('change', updateDateValue);
    dayInput.addEventListener('change', updateDateValue);
    dayInput.addEventListener('blur', updateDateValue);
    yearInput.addEventListener('change', updateDateValue);
    yearInput.addEventListener('blur', updateDateValue);

    // Create separators
    const separator1 = document.createElement('span');
    separator1.textContent = '/';
    separator1.style.fontWeight = 'bold';
    separator1.style.fontSize = '20px';
    separator1.style.color = '#666';
    separator1.style.lineHeight = '38px';
    separator1.style.margin = '0 8px';

    const separator2 = document.createElement('span');
    separator2.textContent = '/';
    separator2.style.fontWeight = 'bold';
    separator2.style.fontSize = '20px';
    separator2.style.color = '#666';
    separator2.style.lineHeight = '38px';
    separator2.style.margin = '0 8px';

    // Add all elements to wrapper with separators
    wrapper.appendChild(monthSelect);
    wrapper.appendChild(separator1);
    wrapper.appendChild(dayInput);
    wrapper.appendChild(separator2);
    wrapper.appendChild(yearInput);
    wrapper.appendChild(hiddenInput);

    // Replace original input
    originalInput.parentNode.replaceChild(wrapper, originalInput);
}

/**
 * Replace datetime-local input with date dropdowns + text time input
 */
function replaceDateTimeInput(originalInput) {
    const isRequired = originalInput.hasAttribute('required');
    const fieldName = originalInput.name;
    const fieldId = originalInput.id;
    const existingValue = originalInput.value;

    // Create wrapper
    const wrapper = document.createElement('div');
    wrapper.className = 'custom-datetime-input';
    wrapper.style.display = 'flex';
    wrapper.style.gap = '12px';
    wrapper.style.flexWrap = 'wrap';

    // Parse existing value (format: YYYY-MM-DDTHH:MM)
    let dateValue = '';
    let timeValue = '';

    if (existingValue) {
        const parts = existingValue.split('T');
        if (parts.length === 2) {
            dateValue = parts[0];
            timeValue = parts[1];
        }
    }

    // Create temporary date input to be replaced with dropdowns
    const tempDateInput = document.createElement('input');
    tempDateInput.type = 'date';
    tempDateInput.value = dateValue;
    if (isRequired) tempDateInput.required = true;

    // Create regular text input for time (uses 12-hour manual entry)
    const timeInput = document.createElement('input');
    timeInput.type = 'text';
    timeInput.className = 'form-control time-input-12hr';
    timeInput.placeholder = '2:30 PM';
    timeInput.maxLength = 8;
    timeInput.pattern = '^(0?[1-9]|1[0-2]):[0-5][0-9]\\s?(AM|PM|am|pm)$';
    timeInput.setAttribute('data-time-field', 'true');
    timeInput.style.flex = '0 0 120px';
    if (isRequired) timeInput.required = true;

    // Convert 24-hour time to 12-hour for display
    if (timeValue) {
        const parts = timeValue.split(':');
        if (parts.length >= 2) {
            let hour24 = parseInt(parts[0]);
            const minute = parts[1];
            let period = 'AM';

            if (hour24 === 0) {
                hour24 = 12;
            } else if (hour24 === 12) {
                period = 'PM';
            } else if (hour24 > 12) {
                hour24 -= 12;
                period = 'PM';
            }

            timeInput.value = `${String(hour24).padStart(2, '0')}:${minute} ${period}`;
        }
    }

    // Add temporary date input to wrapper
    wrapper.appendChild(tempDateInput);
    wrapper.appendChild(timeInput);

    // Hidden input to store the combined value
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = fieldName;
    hiddenInput.id = fieldId;
    hiddenInput.value = existingValue || '';

    wrapper.appendChild(hiddenInput);

    // Replace original input
    originalInput.parentNode.replaceChild(wrapper, originalInput);

    // Now replace the temporary date input with custom dropdowns
    replaceDateInput(tempDateInput);

    // Update the hidden input when date or time changes
    function updateDateTimeValue() {
        const dateHidden = wrapper.querySelector('input[type="hidden"]');
        const dateVal = dateHidden.value;
        const timeVal = timeInput.value.trim();

        // Convert time from 12-hour to 24-hour if needed
        let time24h = timeVal;
        if (timeVal && timeVal.match(/AM|PM/i)) {
            // Convert 12-hour to 24-hour
            const timeRegex = /^(0?[1-9]|1[0-2]):([0-5][0-9])\s?(AM|PM)$/i;
            const match = timeVal.match(timeRegex);
            if (match) {
                let hour = parseInt(match[1]);
                const minute = match[2];
                const period = match[3].toUpperCase();

                if (period === 'AM') {
                    if (hour === 12) hour = 0;
                } else {
                    if (hour !== 12) hour += 12;
                }

                time24h = `${String(hour).padStart(2, '0')}:${minute}`;
            }
        }

        if (dateVal && time24h) {
            hiddenInput.value = dateVal + 'T' + time24h;
        } else {
            hiddenInput.value = '';
        }

        // Trigger change event
        const event = new Event('change', { bubbles: true });
        hiddenInput.dispatchEvent(event);
    }

    // Listen to changes in the date dropdowns
    setTimeout(function() {
        wrapper.querySelectorAll('select').forEach(function(select) {
            select.addEventListener('change', updateDateTimeValue);
        });
    }, 100);

    // Listen to time input changes
    timeInput.addEventListener('change', updateDateTimeValue);
    timeInput.addEventListener('blur', updateDateTimeValue);
}
