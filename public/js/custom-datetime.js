/**
 * Custom Date and Time Input Components
 * Replaces native date/time inputs with separate dropdown/input fields
 * Provides better UX especially on mobile devices
 */

// Wait for page to fully load (including skeleton removal)
window.addEventListener('load', function() {
    // Add a small delay to ensure skeleton is removed
    setTimeout(function() {
        console.log('Initializing custom date/time components...');

        // Replace all date inputs
        document.querySelectorAll('input[type="date"]').forEach(function(input) {
            try {
                replaceDateInput(input);
            } catch(e) {
                console.error('Error replacing date input:', e);
            }
        });

        // Replace all time inputs
        document.querySelectorAll('input[type="time"]').forEach(function(input) {
            try {
                replaceTimeInput(input);
            } catch(e) {
                console.error('Error replacing time input:', e);
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

        console.log('Custom date/time components initialized successfully');
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

    // Day dropdown
    const daySelect = document.createElement('select');
    daySelect.className = 'form-control';
    daySelect.name = fieldName + '_day';
    daySelect.id = fieldId + '_day';
    daySelect.style.flex = '1';
    if (isRequired) daySelect.required = true;

    let dayOptions = '<option value="">Day</option>';
    for (let i = 1; i <= 31; i++) {
        const dayValue = String(i).padStart(2, '0');
        dayOptions += `<option value="${dayValue}" ${selectedDay === dayValue ? 'selected' : ''}>${i}</option>`;
    }
    daySelect.innerHTML = dayOptions;

    // Year dropdown (current year ± 100 years)
    const yearSelect = document.createElement('select');
    yearSelect.className = 'form-control';
    yearSelect.name = fieldName + '_year';
    yearSelect.id = fieldId + '_year';
    yearSelect.style.flex = '1.5';
    if (isRequired) yearSelect.required = true;

    const currentYear = new Date().getFullYear();
    let yearOptions = '<option value="">Year</option>';
    for (let i = currentYear + 10; i >= currentYear - 100; i--) {
        yearOptions += `<option value="${i}" ${selectedYear === String(i) ? 'selected' : ''}>${i}</option>`;
    }
    yearSelect.innerHTML = yearOptions;

    // Hidden input to store the combined value
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = fieldName;
    hiddenInput.id = fieldId;
    hiddenInput.value = existingValue || '';

    // Update hidden input when any dropdown changes
    function updateDateValue() {
        const month = monthSelect.value;
        const day = daySelect.value;
        const year = yearSelect.value;

        if (month && day && year) {
            hiddenInput.value = `${year}-${month}-${day}`;
        } else {
            hiddenInput.value = '';
        }

        // Trigger change event for autosave
        const event = new Event('change', { bubbles: true });
        hiddenInput.dispatchEvent(event);
    }

    monthSelect.addEventListener('change', updateDateValue);
    daySelect.addEventListener('change', updateDateValue);
    yearSelect.addEventListener('change', updateDateValue);

    // Add all elements to wrapper
    wrapper.appendChild(monthSelect);
    wrapper.appendChild(daySelect);
    wrapper.appendChild(yearSelect);
    wrapper.appendChild(hiddenInput);

    // Replace original input
    originalInput.parentNode.replaceChild(wrapper, originalInput);
}

/**
 * Replace time input with Hour/Minute/AM-PM inputs
 */
function replaceTimeInput(originalInput) {
    const isRequired = originalInput.hasAttribute('required');
    const fieldName = originalInput.name;
    const fieldId = originalInput.id;
    const existingValue = originalInput.value;

    // Create wrapper
    const wrapper = document.createElement('div');
    wrapper.className = 'custom-time-input';
    wrapper.style.display = 'flex';
    wrapper.style.gap = '8px';
    wrapper.style.alignItems = 'center';

    // Parse existing value (24-hour format HH:MM)
    let selectedHour = '';
    let selectedMinute = '';
    let selectedPeriod = 'AM';

    if (existingValue) {
        const parts = existingValue.split(':');
        if (parts.length >= 2) {
            let hour24 = parseInt(parts[0]);
            selectedMinute = parts[1];

            // Convert to 12-hour format
            if (hour24 === 0) {
                selectedHour = '12';
                selectedPeriod = 'AM';
            } else if (hour24 < 12) {
                selectedHour = String(hour24);
                selectedPeriod = 'AM';
            } else if (hour24 === 12) {
                selectedHour = '12';
                selectedPeriod = 'PM';
            } else {
                selectedHour = String(hour24 - 12);
                selectedPeriod = 'PM';
            }
        }
    }

    // Hour dropdown
    const hourSelect = document.createElement('select');
    hourSelect.className = 'form-control';
    hourSelect.name = fieldName + '_hour';
    hourSelect.id = fieldId + '_hour';
    hourSelect.style.flex = '1';
    if (isRequired) hourSelect.required = true;

    let hourOptions = '<option value="">HH</option>';
    for (let i = 1; i <= 12; i++) {
        hourOptions += `<option value="${i}" ${selectedHour === String(i) ? 'selected' : ''}>${i}</option>`;
    }
    hourSelect.innerHTML = hourOptions;

    // Minute dropdown
    const minuteSelect = document.createElement('select');
    minuteSelect.className = 'form-control';
    minuteSelect.name = fieldName + '_minute';
    minuteSelect.id = fieldId + '_minute';
    minuteSelect.style.flex = '1';
    if (isRequired) minuteSelect.required = true;

    let minuteOptions = '<option value="">MM</option>';
    for (let i = 0; i < 60; i++) {
        const minuteValue = String(i).padStart(2, '0');
        minuteOptions += `<option value="${minuteValue}" ${selectedMinute === minuteValue ? 'selected' : ''}>${minuteValue}</option>`;
    }
    minuteSelect.innerHTML = minuteOptions;

    // AM/PM dropdown
    const periodSelect = document.createElement('select');
    periodSelect.className = 'form-control';
    periodSelect.name = fieldName + '_period';
    periodSelect.id = fieldId + '_period';
    periodSelect.style.flex = '1';
    if (isRequired) periodSelect.required = true;

    periodSelect.innerHTML = `
        <option value="AM" ${selectedPeriod === 'AM' ? 'selected' : ''}>AM</option>
        <option value="PM" ${selectedPeriod === 'PM' ? 'selected' : ''}>PM</option>
    `;

    // Hidden input to store the combined value in 24-hour format
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = fieldName;
    hiddenInput.id = fieldId;
    hiddenInput.value = existingValue || '';

    // Update hidden input when any dropdown changes
    function updateTimeValue() {
        const hour = hourSelect.value;
        const minute = minuteSelect.value;
        const period = periodSelect.value;

        if (hour && minute && period) {
            // Convert to 24-hour format
            let hour24 = parseInt(hour);
            if (period === 'AM') {
                if (hour24 === 12) hour24 = 0;
            } else {
                if (hour24 !== 12) hour24 += 12;
            }

            hiddenInput.value = String(hour24).padStart(2, '0') + ':' + minute;
        } else {
            hiddenInput.value = '';
        }

        // Trigger change event for autosave
        const event = new Event('change', { bubbles: true });
        hiddenInput.dispatchEvent(event);
    }

    hourSelect.addEventListener('change', updateTimeValue);
    minuteSelect.addEventListener('change', updateTimeValue);
    periodSelect.addEventListener('change', updateTimeValue);

    // Add colon separator
    const separator1 = document.createElement('span');
    separator1.textContent = ':';
    separator1.style.fontWeight = 'bold';
    separator1.style.fontSize = '18px';

    // Add all elements to wrapper
    wrapper.appendChild(hourSelect);
    wrapper.appendChild(separator1);
    wrapper.appendChild(minuteSelect);
    wrapper.appendChild(periodSelect);
    wrapper.appendChild(hiddenInput);

    // Replace original input
    originalInput.parentNode.replaceChild(wrapper, originalInput);
}

/**
 * Replace datetime-local input with combined date and time inputs
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

    // Create temporary date and time inputs to be replaced
    const tempDateInput = document.createElement('input');
    tempDateInput.type = 'date';
    tempDateInput.value = dateValue;
    if (isRequired) tempDateInput.required = true;

    const tempTimeInput = document.createElement('input');
    tempTimeInput.type = 'time';
    tempTimeInput.value = timeValue;
    if (isRequired) tempTimeInput.required = true;

    // Add temporary inputs to wrapper
    wrapper.appendChild(tempDateInput);
    wrapper.appendChild(tempTimeInput);

    // Hidden input to store the combined value
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = fieldName;
    hiddenInput.id = fieldId;
    hiddenInput.value = existingValue || '';

    wrapper.appendChild(hiddenInput);

    // Replace original input
    originalInput.parentNode.replaceChild(wrapper, originalInput);

    // Now replace the temporary inputs with custom components
    replaceDateInput(tempDateInput);
    replaceTimeInput(tempTimeInput);

    // Update the hidden input when date or time changes
    function updateDateTimeValue() {
        const dateVal = wrapper.querySelector('input[type="hidden"]').value; // Date hidden input
        const timeVal = wrapper.querySelectorAll('input[type="hidden"]')[1].value; // Time hidden input

        if (dateVal && timeVal) {
            hiddenInput.value = dateVal + 'T' + timeVal;
        } else {
            hiddenInput.value = '';
        }

        // Trigger change event
        const event = new Event('change', { bubbles: true });
        hiddenInput.dispatchEvent(event);
    }

    // Listen to changes in the replaced components
    setTimeout(function() {
        wrapper.querySelectorAll('select').forEach(function(select) {
            select.addEventListener('change', updateDateTimeValue);
        });
    }, 100);
}
