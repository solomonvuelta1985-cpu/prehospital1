// ============================================
// TAB NAVIGATION & FORM SUMMARY
// Handles tab switching, progress tracking, and summary generation
// ============================================

var currentTab = 0;
var isEditMode = Boolean(
    document.getElementById('editForm') ||
    document.querySelector('[data-workflow-mode="edit"]') ||
    document.body.classList.contains('edit-record-page') ||
    /(?:^|\/)edit_record\.php$/i.test(window.location.pathname)
);
var totalTabs = isEditMode ? 6 : 7;
var completedTabs = new Set();

function getStepTabs() {
    return Array.from(document.querySelectorAll('.nav-tabs .nav-link'));
}

function getStepPane(index) {
    var tabs = getStepTabs();
    var target = tabs[index] ? tabs[index].getAttribute('data-bs-target') : `#section${index + 1}`;
    return target ? document.querySelector(target) : null;
}

function getStepMetadata(tab, index) {
    return {
        title: tab?.dataset.stepTitle || tab?.querySelector('.step-label')?.textContent.trim() || tab?.textContent.trim() || `Step ${index + 1}`,
        description: tab?.dataset.stepDescription || 'Complete the fields in this step.',
        icon: tab?.dataset.stepIcon || 'bi-clipboard-check'
    };
}

function saveCurrentTab() {
    var key = document.getElementById('editForm') ? 'editFormCurrentTab' : 'createFormCurrentTab';
    sessionStorage.setItem(key, currentTab);
    sessionStorage.setItem(`${key}Completed`, JSON.stringify(Array.from(completedTabs)));
}

function restoreSavedTab() {
    var navEntries = performance.getEntriesByType('navigation');
    var isReload = navEntries.length > 0 && navEntries[0].type === 'reload';
    var key = document.getElementById('editForm') ? 'editFormCurrentTab' : 'createFormCurrentTab';

    if (!isReload) {
        sessionStorage.removeItem(key);
        sessionStorage.removeItem(`${key}Completed`);
        return;
    }

    try {
        var savedCompleted = JSON.parse(sessionStorage.getItem(`${key}Completed`) || '[]');
        completedTabs = new Set(savedCompleted.filter(index => Number.isInteger(index)));
    } catch (error) {
        completedTabs = new Set();
    }

    var savedTab = parseInt(sessionStorage.getItem(key), 10);
    if (!Number.isNaN(savedTab) && savedTab >= 0 && savedTab < totalTabs) {
        currentTab = savedTab;
    }
}

function hasMeaningfulValue(field) {
    if (!field || field.disabled || field.type === 'hidden' || field.type === 'file') return false;
    if (field.type === 'checkbox' || field.type === 'radio') return field.checked;
    return String(field.value || '').trim() !== '';
}

function getStepState(index) {
    var pane = getStepPane(index);
    if (!pane) return { hasData: false, hasError: false };

    var fields = Array.from(pane.querySelectorAll('input, select, textarea'));
    var hasData = fields.some(hasMeaningfulValue);
    var hasError = fields.some(field => field.classList.contains('is-invalid'));
    return { hasData, hasError };
}

function updateStepStates() {
    var tabs = getStepTabs();
    tabs.forEach((tab, index) => {
        var state = getStepState(index);
        // An existing record is already saved, so its edit-mode stages are
        // complete even when the user is currently reviewing one of them.
        var isCompleted = isEditMode || completedTabs.has(index);
        // New records must be completed in sequence. Existing records opened
        // in edit mode may be reviewed and updated from any stage, so their
        // tabs must never be presented as locked.
        var isLocked = !isEditMode && index > currentTab && !completedTabs.has(index - 1);
        var stateText = tab.querySelector('.step-state');

        tab.classList.toggle('completed', isCompleted);
        tab.classList.toggle('has-error', state.hasError);
        tab.classList.toggle('locked', isLocked);
        tab.setAttribute('aria-selected', index === currentTab ? 'true' : 'false');
        tab.setAttribute('aria-disabled', isLocked ? 'true' : 'false');
        tab.tabIndex = isLocked ? -1 : 0;

        if (stateText) {
            stateText.textContent = state.hasError ? 'Needs attention' : isLocked ? 'Locked' : isCompleted ? 'Complete' : index === currentTab ? 'In progress' : state.hasData ? 'In progress' : 'Not started';
        }
    });
}

function updateStepContext() {
    var tabs = getStepTabs();
    var metadata = getStepMetadata(tabs[currentTab], currentTab);
    var title = document.getElementById('currentStepTitle');
    var description = document.getElementById('currentStepDescription');
    var icon = document.getElementById('activeStepIcon');
    var state = document.getElementById('currentStepState');

    if (title) title.textContent = metadata.title;
    if (description) description.textContent = metadata.description;
    if (icon) icon.innerHTML = `<i class="bi ${metadata.icon}" aria-hidden="true"></i>`;
    if (state) {
        var stepState = getStepState(currentTab);
        var isComplete = isEditMode || completedTabs.has(currentTab);
        state.textContent = stepState.hasError ? 'Needs attention' : isComplete ? 'Complete' : 'In progress';
        state.classList.toggle('has-error', stepState.hasError);
        state.classList.toggle('is-complete', isComplete && !stepState.hasError);
    }
}

function updateNavigation() {
    var prevBtn = document.getElementById('prevBtn');
    var nextBtn = document.getElementById('nextBtn');
    var submitBtn = document.getElementById('submitBtn');
    var updateBtn = document.getElementById('updateBtn');
    var navigationHelp = document.getElementById('navigationContextHelp');
    var isLastStep = currentTab === totalTabs - 1;

    if (prevBtn) prevBtn.style.display = currentTab === 0 ? 'none' : 'inline-flex';

    if (document.getElementById('editForm')) {
        if (nextBtn) nextBtn.style.display = isLastStep ? 'none' : 'inline-flex';
        if (updateBtn) updateBtn.style.display = isLastStep ? 'inline-flex' : 'none';
        if (submitBtn) submitBtn.style.display = 'none';
    } else {
        if (nextBtn) nextBtn.style.display = isLastStep ? 'none' : 'inline-flex';
        if (submitBtn) submitBtn.style.display = isLastStep ? 'inline-flex' : 'none';
        if (updateBtn) updateBtn.style.display = 'none';
    }

    if (navigationHelp) {
        navigationHelp.textContent = isLastStep
            ? 'Review the record before saving your changes.'
            : isEditMode
                ? 'Open any stage to review or update its details.'
                : 'Complete the required fields to continue.';
    }
}

function updateProgress() {
    var progress = ((currentTab + 1) / totalTabs) * 100;
    var progressBar = document.getElementById('progressBar');
    var stepIndicator = document.getElementById('stepIndicator');
    var progressPercent = document.getElementById('stepProgressPercent');
    var progressTrack = progressBar ? progressBar.parentElement : null;

    if (progressBar) progressBar.style.width = progress + '%';
    if (progressTrack) progressTrack.setAttribute('aria-valuenow', String(Math.round(progress)));
    if (stepIndicator) stepIndicator.textContent = `Step ${currentTab + 1} of ${totalTabs}`;
    if (progressPercent) progressPercent.textContent = `${Math.round(progress)}%`;
}

function validateCurrentStep() {
    var pane = getStepPane(currentTab);
    if (!pane) return true;

    var invalidFields = Array.from(pane.querySelectorAll('input, select, textarea')).filter(field => {
        if (field.disabled || field.type === 'hidden' || field.type === 'file') return false;
        if (field.offsetParent === null && !field.matches('select')) return false;
        return !field.checkValidity();
    });

    pane.querySelectorAll('.is-invalid').forEach(field => {
        if (!invalidFields.includes(field)) field.classList.remove('is-invalid');
    });
    invalidFields.forEach(field => field.classList.add('is-invalid'));

    if (!invalidFields.length) return true;

    var firstInvalid = invalidFields[0];
    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    firstInvalid.focus({ preventScroll: true });
    updateStepStates();
    updateStepContext();
    return false;
}

function activateStep(index, options) {
    var tabs = getStepTabs();
    if (index < 0 || index >= totalTabs || !tabs[index]) return;
    options = options || {};
    currentTab = index;

    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('show', 'active'));
    var targetPane = getStepPane(currentTab);
    if (targetPane) {
        targetPane.classList.add('show', 'active');
        targetPane.setAttribute('aria-hidden', 'false');
    }
    document.querySelectorAll('.tab-pane').forEach(pane => {
        if (pane !== targetPane) pane.setAttribute('aria-hidden', 'true');
    });

    tabs.forEach((tab, tabIndex) => {
        tab.classList.toggle('active', tabIndex === currentTab);
        tab.setAttribute('aria-selected', tabIndex === currentTab ? 'true' : 'false');
    });

    if (currentTab === 4 && typeof repositionMarkers === 'function') {
        requestAnimationFrame(() => requestAnimationFrame(() => repositionMarkers()));
    }

    var activeTab = tabs[currentTab];
    if (activeTab) activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    if (!options.skipScroll) {
        var workspace = document.getElementById('formWorkspace');
        if (workspace && options.scrollWorkspace !== false) workspace.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        var pageContent = document.querySelector('.content');
        if (pageContent) pageContent.scrollLeft = 0;
    }

    saveCurrentTab();
    if (typeof saveCurrentSection === 'function') saveCurrentSection();
    updateNavigation();
    updateProgress();
    updateStepStates();
    updateStepContext();
    if (currentTab === totalTabs - 1 && typeof generateFormSummary === 'function') generateFormSummary();
}

function navigateTab(direction) {
    if (direction === 1 && currentTab < totalTabs - 1) {
        if (!validateCurrentStep()) return;
        completedTabs.add(currentTab);
    }

    var nextIndex = Math.max(0, Math.min(totalTabs - 1, currentTab + direction));
    activateStep(nextIndex);
}

function handleStepClick(event, index) {
    event.preventDefault();
    if (!isEditMode && index > currentTab && !completedTabs.has(index - 1)) {
        var navigationHelp = document.getElementById('navigationContextHelp');
        if (navigationHelp) navigationHelp.textContent = 'Complete the current step before opening a later step.';
        return;
    }
    activateStep(index);
}

// Bind the stepper after the markup is present. The existing Bootstrap data
// attributes remain in place for compatibility, while this controller owns
// the visual state and current-step behavior.
getStepTabs().forEach((tab, index) => {
    tab.addEventListener('click', event => handleStepClick(event, index));
});

document.querySelectorAll('#preHospitalForm input, #preHospitalForm select, #preHospitalForm textarea').forEach(field => {
    ['input', 'change'].forEach(eventName => field.addEventListener(eventName, () => {
        field.classList.remove('is-invalid');
        updateStepStates();
        updateStepContext();
    }));
});

restoreSavedTab();
activateStep(currentTab, { skipScroll: true, scrollWorkspace: false });

// ============================================
// FORM SUMMARY GENERATION
// ============================================

function generateFormSummary() {
    const summaryContainer = document.getElementById('summaryModalBody');
    if (!summaryContainer) {
        console.error('Summary modal body not found!');
        return;
    }

    let summaryHTML = '<div class="summary-content">';

    // Basic Information Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>Basic Information</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Date:</strong></td><td>${safeVal('formDate')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Departure Time:</strong></td><td>${safeVal('depTime')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Arrival Time:</strong></td><td>${safeVal('arrTime')}</td></tr>`;

    const vehicleUsed = document.querySelector('input[name="vehicle_used"]:checked');
    summaryHTML += `<tr><td><strong>Vehicle Used:</strong></td><td>${vehicleUsed ? escapeHtml(vehicleUsed.value) : 'Not specified'}</td></tr>`;

    summaryHTML += `<tr><td><strong>Driver:</strong></td><td>${safeVal('driver')}</td></tr>`;

    const personsPresent = Array.from(document.querySelectorAll('input[name="persons_present[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Persons Present:</strong></td><td>${personsPresent.length > 0 ? personsPresent.join(', ') : 'None'}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Patient Information Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>Patient Information</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Name:</strong></td><td>${safeVal('patientName')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Date of Birth:</strong></td><td>${safeVal('dateOfBirth')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Age:</strong></td><td>${safeVal('age')}</td></tr>`;

    const genderSelect = document.getElementById('gender');
    const genderValue = genderSelect ? genderSelect.value : '';
    summaryHTML += `<tr><td><strong>Gender:</strong></td><td>${genderValue ? escapeHtml(genderValue) : 'Not specified'}</td></tr>`;

    const civilStatusSelect = document.getElementById('civilStatus');
    const civilStatusValue = civilStatusSelect ? civilStatusSelect.value : '';
    summaryHTML += `<tr><td><strong>Civil Status:</strong></td><td>${civilStatusValue ? escapeHtml(civilStatusValue) : 'Not specified'}</td></tr>`;

    summaryHTML += `<tr><td><strong>Address:</strong></td><td>${safeVal('address')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Zone:</strong></td><td>${safeVal('zone')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Occupation:</strong></td><td>${safeVal('occupation')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Place of Incident:</strong></td><td>${safeVal('placeOfIncident')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Zone/Landmark:</strong></td><td>${safeVal('zoneLandmark')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Incident Time:</strong></td><td>${safeVal('incidentTime')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Call Arrival Time:</strong></td><td>${safeVal('callArrTime')}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Emergency Type & Care Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>Emergency Type & Care</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';

    const emergencyTypes = Array.from(document.querySelectorAll('input[name="emergency_type[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Emergency Types:</strong></td><td>${emergencyTypes.length > 0 ? emergencyTypes.join(', ') : 'None specified'}</td></tr>`;

    const careManagement = Array.from(document.querySelectorAll('input[name="care_management[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Care Management:</strong></td><td>${careManagement.length > 0 ? careManagement.join(', ') : 'None specified'}</td></tr>`;

    summaryHTML += `<tr><td><strong>O2 LPM:</strong></td><td>${safeVal('o2LPM')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Other Care:</strong></td><td>${safeVal('othersCare')}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Vital Signs Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>Vital Signs</h6>';
    summaryHTML += '<h7>Initial:</h7>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Time:</strong></td><td>${safeVal('initialTime')}</td></tr>`;
    summaryHTML += `<tr><td><strong>BP:</strong></td><td>${safeVal('initialBP')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Temp:</strong></td><td>${safeVal('initialTemp')}°C</td></tr>`;
    summaryHTML += `<tr><td><strong>Pulse:</strong></td><td>${safeVal('initialPulse')} BPM</td></tr>`;
    summaryHTML += `<tr><td><strong>Resp Rate:</strong></td><td>${safeVal('initialResp')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Pain Score:</strong></td><td>${safeVal('initialPainScore')}</td></tr>`;
    summaryHTML += `<tr><td><strong>SPO2:</strong></td><td>${safeVal('initialSPO2')}%</td></tr>`;

    const initialConsciousness = Array.from(document.querySelectorAll('input[name="initial_consciousness[]"]:checked')).map(cb => {
        const labels = { 'alert': 'Alert', 'verbal': 'Verbal', 'pain': 'Pain', 'unconscious': 'Unconscious' };
        return labels[cb.value] || cb.value;
    });
    summaryHTML += `<tr><td><strong>Level of Consciousness:</strong></td><td>${initialConsciousness.length > 0 ? initialConsciousness.join(', ') : 'Not specified'}</td></tr>`;

    const initialHelmet = Array.from(document.querySelectorAll('input[name="initial_helmet[]"]:checked')).map(cb => {
        const labels = { 'ab': '+ AB', 'none': 'No Helmet' };
        return labels[cb.value] || cb.value;
    });
    summaryHTML += `<tr><td><strong>Helmet Status:</strong></td><td>${initialHelmet.length > 0 ? initialHelmet.join(', ') : 'Not specified'}</td></tr>`;
    summaryHTML += '</tbody></table>';

    summaryHTML += '<h7>Follow-up:</h7>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Time:</strong></td><td>${safeVal('followupTime')}</td></tr>`;
    summaryHTML += `<tr><td><strong>BP:</strong></td><td>${safeVal('followupBP')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Temp:</strong></td><td>${safeVal('followupTemp')}°C</td></tr>`;
    summaryHTML += `<tr><td><strong>Pulse:</strong></td><td>${safeVal('followupPulse')} BPM</td></tr>`;
    summaryHTML += `<tr><td><strong>Resp Rate:</strong></td><td>${safeVal('followupResp')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Pain Score:</strong></td><td>${safeVal('followupPainScore')}</td></tr>`;
    summaryHTML += `<tr><td><strong>SPO2:</strong></td><td>${safeVal('followupSPO2')}%</td></tr>`;

    const followupConsciousness = Array.from(document.querySelectorAll('input[name="followup_consciousness[]"]:checked')).map(cb => {
        const labels = { 'alert': 'Alert', 'verbal': 'Verbal', 'pain': 'Pain', 'unconscious': 'Unconscious' };
        return labels[cb.value] || cb.value;
    });
    summaryHTML += `<tr><td><strong>Level of Consciousness:</strong></td><td>${followupConsciousness.length > 0 ? followupConsciousness.join(', ') : 'Not specified'}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Assessment Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>Assessment</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';

    const chiefComplaints = Array.from(document.querySelectorAll('input[name="chief_complaints[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Chief Complaints:</strong></td><td>${chiefComplaints.length > 0 ? chiefComplaints.join(', ') : 'None specified'}</td></tr>`;

    summaryHTML += `<tr><td><strong>Other Complaints:</strong></td><td>${safeVal('othersComplaint', 'None')}</td></tr>`;

    summaryHTML += `<tr><td><strong>Injuries Marked:</strong></td><td>${injuries.length}</td></tr>`;

    // FAST Assessment
    const faceDrooping = document.querySelector('input[name="face_drooping"]:checked');
    const armWeakness = document.querySelector('input[name="arm_weakness"]:checked');
    const speechDifficulty = document.querySelector('input[name="speech_difficulty"]:checked');
    const timeToCall = document.querySelector('input[name="time_to_call"]:checked');

    summaryHTML += '<tr><td colspan="2"><strong>FAST Assessment:</strong></td></tr>';
    summaryHTML += `<tr><td><strong>Face Drooping:</strong></td><td>${faceDrooping ? faceDrooping.value : 'Not assessed'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Arm Weakness:</strong></td><td>${armWeakness ? armWeakness.value : 'Not assessed'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Speech Difficulty:</strong></td><td>${speechDifficulty ? speechDifficulty.value : 'Not assessed'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Time to Call:</strong></td><td>${timeToCall ? timeToCall.value : 'Not assessed'}</td></tr>`;

    summaryHTML += `<tr><td><strong>SAMPLE Details:</strong></td><td>${safeVal('fastDetails', 'Not provided')}</td></tr>`;

    // OB Section
    summaryHTML += '<tr><td colspan="2"><strong>OB Patient Info:</strong></td></tr>';
    summaryHTML += `<tr><td><strong>Baby Status:</strong></td><td>${safeVal('babyDelivery', 'Not applicable')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Delivery Time:</strong></td><td>${safeVal('timeOfDelivery', 'Not applicable')}</td></tr>`;
    summaryHTML += `<tr><td><strong>LMP:</strong></td><td>${safeVal('lmp', 'Not applicable')}</td></tr>`;
    summaryHTML += `<tr><td><strong>AOG:</strong></td><td>${safeVal('aog', 'Not applicable')}</td></tr>`;
    summaryHTML += `<tr><td><strong>EDC:</strong></td><td>${safeVal('edc', 'Not applicable')}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Team Information Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>Team Information</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Team Leader:</strong></td><td>${safeVal('teamLeader')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Data Recorder:</strong></td><td>${safeVal('dataRecorder')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Logistic:</strong></td><td>${safeVal('logistic')}</td></tr>`;
    summaryHTML += `<tr><td><strong>1st Aider:</strong></td><td>${safeVal('aider1')}</td></tr>`;
    summaryHTML += `<tr><td><strong>2nd Aider:</strong></td><td>${safeVal('aider2')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Team Leader Notes:</strong></td><td>${safeVal('teamLeaderNotes', 'None')}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Hospital Endorsement Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>Hospital Endorsement</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Hospital Name:</strong></td><td>${safeVal('hospital')}</td></tr>`;
    summaryHTML += `<tr><td><strong>Date & Time:</strong></td><td>${safeVal('dateTime')}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    summaryHTML += '</div>';
    summaryContainer.innerHTML = summaryHTML;
}

// Open Summary Modal
function openSummaryModal() {
    generateFormSummary();
    const summaryModal = new bootstrap.Modal(document.getElementById('formSummaryModal'));
    summaryModal.show();
}

// Print Summary
function printSummary() {
    const summaryContent = document.getElementById('summaryModalBody').innerHTML;

    const printWindow = window.open('', '_blank', 'width=800,height=600');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Pre-Hospital Care Form Summary</title>
            <link href="vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; font-family: Arial, sans-serif; }
                .summary-section { margin-bottom: 30px; page-break-inside: avoid; }
                .summary-section h6 {
                    color: #0066cc;
                    border-bottom: 2px solid #0066cc;
                    padding-bottom: 8px;
                    margin-bottom: 15px;
                    font-size: 1.1rem;
                    font-weight: 600;
                }
                .summary-table { width: 100%; border-collapse: collapse; }
                .summary-table td { padding: 8px 12px; border-bottom: 1px solid #eee; }
                .summary-table td:first-child { font-weight: 600; width: 30%; color: #666; }
                @media print {
                    body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
                    .summary-section { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <h2 style="text-align: center; color: #0066cc; margin-bottom: 30px;">
                Pre-Hospital Care Form Summary
            </h2>
            ${summaryContent}
        </body>
        </html>
    `);

    printWindow.document.close();
    // Trigger print via JS after document loads (CSP blocks inline scripts in new windows)
    printWindow.onload = function() {
        printWindow.print();
        setTimeout(function() { printWindow.close(); }, 100);
    };
}

// Copy Summary to Clipboard
function copySummaryToClipboard() {
    const summaryContent = document.getElementById('summaryModalBody');

    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = summaryContent.innerHTML;

    let textContent = 'PRE-HOSPITAL CARE FORM SUMMARY\n';
    textContent += '='.repeat(50) + '\n\n';

    const sections = tempDiv.querySelectorAll('.summary-section');
    sections.forEach(section => {
        const title = section.querySelector('h6')?.textContent || '';
        textContent += title + '\n';
        textContent += '-'.repeat(title.length) + '\n';

        const rows = section.querySelectorAll('tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 2) {
                const label = cells[0].textContent.trim();
                const value = cells[1].textContent.trim();
                textContent += `${label} ${value}\n`;
            }
        });

        textContent += '\n';
    });

    navigator.clipboard.writeText(textContent).then(() => {
        alert('Summary copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy summary to clipboard');
    });
}
