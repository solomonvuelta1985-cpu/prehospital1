// ============================================
// TAB NAVIGATION & FORM SUMMARY
// Handles tab switching, progress tracking, and summary generation
// ============================================

var currentTab = 0;
var totalTabs = 7;

// Adjust for edit form which has 6 tabs
if (document.getElementById('editForm')) {
    totalTabs = 6;
}

// Save current tab to sessionStorage
function saveCurrentTab() {
    var key = document.getElementById('editForm') ? 'editFormCurrentTab' : 'createFormCurrentTab';
    sessionStorage.setItem(key, currentTab);
}

// Restore saved tab (only on page refresh, not on fresh navigation)
function restoreSavedTab() {
    var navEntries = performance.getEntriesByType('navigation');
    var isReload = navEntries.length > 0 && navEntries[0].type === 'reload';
    var key = document.getElementById('editForm') ? 'editFormCurrentTab' : 'createFormCurrentTab';

    if (!isReload) {
        sessionStorage.removeItem(key);
        return;
    }

    var savedTab = sessionStorage.getItem(key);

    if (savedTab !== null) {
        var tabIndex = parseInt(savedTab, 10);

        if (tabIndex >= 0 && tabIndex < totalTabs) {
            currentTab = tabIndex;

            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });

            var targetPane = document.querySelector(`#section${currentTab + 1}`);
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }

            document.querySelectorAll('.nav-tabs .nav-link').forEach((tab, index) => {
                if (index === currentTab) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }
    }
}

function navigateTab(direction) {
    const tabs = document.querySelectorAll('.nav-tabs .nav-link');

    if (direction === 1 && currentTab < totalTabs - 1) {
        tabs[currentTab].classList.add('completed');
    }

    currentTab += direction;

    if (currentTab >= totalTabs) currentTab = totalTabs - 1;
    if (currentTab < 0) currentTab = 0;

    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('show', 'active');
    });

    const targetPane = document.querySelector(`#section${currentTab + 1}`);
    if (targetPane) {
        targetPane.classList.add('show', 'active');
    }

    if (currentTab === 4 && typeof repositionMarkers === 'function') {
        requestAnimationFrame(() => requestAnimationFrame(() => repositionMarkers()));
    }

    tabs.forEach((tab, index) => {
        if (index === currentTab) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });

    const activeTab = tabs[currentTab];
    if (activeTab) {
        activeTab.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center'
        });
    }

    saveCurrentTab();
    updateNavigation();
    updateProgress();

    document.querySelector('.form-body').scrollTop = 0;
}

function updateNavigation() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const updateBtn = document.getElementById('updateBtn');

    if (prevBtn) {
        prevBtn.style.display = currentTab === 0 ? 'none' : 'block';
    }

    if (document.getElementById('editForm')) {
        if (currentTab === totalTabs - 1) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (updateBtn) updateBtn.style.display = 'block';
        } else {
            if (nextBtn) nextBtn.style.display = 'block';
            if (updateBtn) updateBtn.style.display = 'none';
        }
        if (submitBtn) submitBtn.style.display = 'none';
    } else {
        if (currentTab === totalTabs - 1) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'block';
            generateFormSummary();
        } else {
            if (nextBtn) nextBtn.style.display = 'block';
            if (submitBtn) submitBtn.style.display = 'none';
        }
        if (updateBtn) updateBtn.style.display = 'none';
    }
}

function updateProgress() {
    const progress = ((currentTab + 1) / totalTabs) * 100;
    const progressBar = document.getElementById('progressBar');
    const stepIndicator = document.getElementById('stepIndicator');

    if (progressBar) {
        progressBar.style.width = progress + '%';
    }

    if (stepIndicator) {
        stepIndicator.textContent = `Step ${currentTab + 1} of ${totalTabs}`;
    }
}

// Tab click event listeners
document.querySelectorAll('.nav-tabs .nav-link').forEach((tab, index) => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        currentTab = index;

        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });

        const targetPane = document.querySelector(`#section${currentTab + 1}`);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
        }

        if (currentTab === 4 && typeof repositionMarkers === 'function') {
            requestAnimationFrame(() => requestAnimationFrame(() => repositionMarkers()));
        }

        document.querySelectorAll('.nav-tabs .nav-link').forEach((t, i) => {
            if (i === currentTab) {
                t.classList.add('active');
            } else {
                t.classList.remove('active');
            }
        });

        saveCurrentTab();
        updateNavigation();
        updateProgress();
        if (currentTab === totalTabs - 1) {
            generateFormSummary();
        }
    });
});

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

    const gender = document.querySelector('input[name="gender"]:checked');
    summaryHTML += `<tr><td><strong>Gender:</strong></td><td>${gender ? escapeHtml(gender.value) : 'Not specified'}</td></tr>`;

    const civilStatus = document.querySelector('input[name="civil_status"]:checked');
    summaryHTML += `<tr><td><strong>Civil Status:</strong></td><td>${civilStatus ? escapeHtml(civilStatus.value) : 'Not specified'}</td></tr>`;

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
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
