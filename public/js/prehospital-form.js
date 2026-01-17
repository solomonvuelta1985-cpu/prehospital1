// Pre-Hospital Care Form JavaScript
// Form navigation and interaction logic

let currentTab = 0;
let totalTabs = 7;

// Adjust for edit form which has 6 tabs
if (document.getElementById('editForm')) {
    totalTabs = 6;
}

// Body Diagram Variables
let injuries = [];
let injuryCounter = 0;
let selectedInjuryType = 'laceration';

document.addEventListener('DOMContentLoaded', function() {
    // Restore saved tab state from localStorage
    restoreSavedTab();

    updateNavigation();
    updateProgress();
    setupInjuryTypeButtons();
    setupBodyDiagrams();
    initializeAmbulanceList();
    setupVehicleModals();

    // Load existing injuries for edit form
    loadExistingInjuries();

    // Initialize progress bar for edit form
    if (document.getElementById('editForm')) {
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.style.width = '16.67%'; // 1/6 tabs
        }
    }
});

// Reposition markers on window resize
window.addEventListener('resize', repositionMarkers);

// ============================================
// TAB NAVIGATION FUNCTIONS
// ============================================

// Save current tab to localStorage
function saveCurrentTab() {
    localStorage.setItem('prehospitalFormCurrentTab', currentTab);
}

// Restore saved tab from localStorage
function restoreSavedTab() {
    const savedTab = localStorage.getItem('prehospitalFormCurrentTab');

    if (savedTab !== null) {
        const tabIndex = parseInt(savedTab, 10);

        // Validate the saved tab index
        if (tabIndex >= 0 && tabIndex < totalTabs) {
            currentTab = tabIndex;

            // Hide all tab-panes
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });

            // Show the saved pane
            const targetPane = document.querySelector(`#section${currentTab + 1}`);
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }

            // Update tab buttons
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

    // Hide all tab-panes
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('show', 'active');
    });

    // Show the target pane
    const targetPane = document.querySelector(`#section${currentTab + 1}`);
    if (targetPane) {
        targetPane.classList.add('show', 'active');
    }

    // Update tab buttons
    tabs.forEach((tab, index) => {
        if (index === currentTab) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });

    // Save the current tab to localStorage
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
        // Edit form navigation
        if (currentTab === totalTabs - 1) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (updateBtn) updateBtn.style.display = 'block';
        } else {
            if (nextBtn) nextBtn.style.display = 'block';
            if (updateBtn) updateBtn.style.display = 'none';
        }
        if (submitBtn) submitBtn.style.display = 'none'; // Hide submit button in edit mode
    } else {
        // Create form navigation
        if (currentTab === totalTabs - 1) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'block';
            generateFormSummary(); // Generate summary when reaching the last tab
        } else {
            if (nextBtn) nextBtn.style.display = 'block';
            if (submitBtn) submitBtn.style.display = 'none';
        }
        if (updateBtn) updateBtn.style.display = 'none'; // Hide update button in create mode
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
        e.preventDefault(); // Prevent Bootstrap's default tab behavior
        currentTab = index;

        // Hide all tab-panes
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });

        // Show the target pane
        const targetPane = document.querySelector(`#section${currentTab + 1}`);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
        }

        // Update tab buttons
        document.querySelectorAll('.nav-tabs .nav-link').forEach((t, i) => {
            if (i === currentTab) {
                t.classList.add('active');
            } else {
                t.classList.remove('active');
            }
        });

        // Save the current tab to localStorage
        saveCurrentTab();

        updateNavigation();
        updateProgress();
        if (currentTab === totalTabs - 1) {
            generateFormSummary();
        }
    });
});

// ============================================
// FORM UTILITY FUNCTIONS
// ============================================

// Auto-calculate age from date of birth
document.getElementById('dateOfBirth')?.addEventListener('change', function() {
    const dob = new Date(this.value);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    
    document.getElementById('age').value = age;
});

// ============================================
// FORM SUMMARY
// ============================================

function generateFormSummary() {
    console.log('generateFormSummary() called');
    const summaryContainer = document.getElementById('formSummary');
    if (!summaryContainer) {
        console.error('Summary container not found!');
        return;
    }
    console.log('Summary container found, generating summary...');

    let summaryHTML = '<div class="summary-content">';

    // Basic Information Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>📋 Basic Information</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Date:</strong></td><td>${document.getElementById('formDate').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Departure Time:</strong></td><td>${document.getElementById('depTime').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Arrival Time:</strong></td><td>${document.getElementById('arrTime').value || 'Not specified'}</td></tr>`;

    const vehicleUsed = document.querySelector('input[name="vehicle_used"]:checked');
    summaryHTML += `<tr><td><strong>Vehicle Used:</strong></td><td>${vehicleUsed ? vehicleUsed.value : 'Not specified'}</td></tr>`;

    summaryHTML += `<tr><td><strong>Driver:</strong></td><td>${document.getElementById('driver').value || 'Not specified'}</td></tr>`;

    const personsPresent = Array.from(document.querySelectorAll('input[name="persons_present[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Persons Present:</strong></td><td>${personsPresent.length > 0 ? personsPresent.join(', ') : 'None'}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Patient Information Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>👤 Patient Information</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Name:</strong></td><td>${document.getElementById('patientName').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Date of Birth:</strong></td><td>${document.getElementById('dateOfBirth').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Age:</strong></td><td>${document.getElementById('age').value || 'Not specified'}</td></tr>`;

    const gender = document.querySelector('input[name="gender"]:checked');
    summaryHTML += `<tr><td><strong>Gender:</strong></td><td>${gender ? gender.value : 'Not specified'}</td></tr>`;

    const civilStatus = document.querySelector('input[name="civil_status"]:checked');
    summaryHTML += `<tr><td><strong>Civil Status:</strong></td><td>${civilStatus ? civilStatus.value : 'Not specified'}</td></tr>`;

    summaryHTML += `<tr><td><strong>Address:</strong></td><td>${document.getElementById('address').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Occupation:</strong></td><td>${document.getElementById('occupation').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Place of Incident:</strong></td><td>${document.getElementById('placeOfIncident').value || 'Not specified'}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Emergency Type & Care Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>🚑 Emergency Type & Care</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';

    const emergencyTypes = Array.from(document.querySelectorAll('input[name="emergency_type[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Emergency Types:</strong></td><td>${emergencyTypes.length > 0 ? emergencyTypes.join(', ') : 'None specified'}</td></tr>`;

    const careManagement = Array.from(document.querySelectorAll('input[name="care_management[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Care Management:</strong></td><td>${careManagement.length > 0 ? careManagement.join(', ') : 'None specified'}</td></tr>`;

    summaryHTML += `<tr><td><strong>O² LPM:</strong></td><td>${document.getElementById('o2LPM').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Other Care:</strong></td><td>${document.getElementById('othersCare').value || 'Not specified'}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Vital Signs Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>❤️ Vital Signs</h6>';
    summaryHTML += '<h7>Initial:</h7>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Time:</strong></td><td>${document.getElementById('initialTime').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>BP:</strong></td><td>${document.getElementById('initialBP').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Temp:</strong></td><td>${document.getElementById('initialTemp').value || 'Not specified'}°C</td></tr>`;
    summaryHTML += `<tr><td><strong>Pulse:</strong></td><td>${document.getElementById('initialPulse').value || 'Not specified'} BPM</td></tr>`;
    summaryHTML += `<tr><td><strong>Resp Rate:</strong></td><td>${document.getElementById('initialResp').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Pain Score:</strong></td><td>${document.getElementById('initialPainScore').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>SPO2:</strong></td><td>${document.getElementById('initialSPO2').value || 'Not specified'}%</td></tr>`;

    const initialConsciousness = Array.from(document.querySelectorAll('input[name="initial_consciousness[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Level of Consciousness:</strong></td><td>${initialConsciousness.length > 0 ? initialConsciousness.join(', ') : 'Not specified'}</td></tr>`;
    summaryHTML += '</tbody></table>';

    summaryHTML += '<h7>Follow-up:</h7>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Time:</strong></td><td>${document.getElementById('followupTime').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>BP:</strong></td><td>${document.getElementById('followupBP').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Temp:</strong></td><td>${document.getElementById('followupTemp').value || 'Not specified'}°C</td></tr>`;
    summaryHTML += `<tr><td><strong>Pulse:</strong></td><td>${document.getElementById('followupPulse').value || 'Not specified'} BPM</td></tr>`;
    summaryHTML += `<tr><td><strong>Resp Rate:</strong></td><td>${document.getElementById('followupResp').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Pain Score:</strong></td><td>${document.getElementById('followupPainScore').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>SPO2:</strong></td><td>${document.getElementById('followupSPO2').value || 'Not specified'}%</td></tr>`;

    const followupConsciousness = Array.from(document.querySelectorAll('input[name="followup_consciousness[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Level of Consciousness:</strong></td><td>${followupConsciousness.length > 0 ? followupConsciousness.join(', ') : 'Not specified'}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Assessment Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>🔍 Assessment</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';

    const chiefComplaints = Array.from(document.querySelectorAll('input[name="chief_complaints[]"]:checked')).map(cb => cb.value);
    summaryHTML += `<tr><td><strong>Chief Complaints:</strong></td><td>${chiefComplaints.length > 0 ? chiefComplaints.join(', ') : 'None specified'}</td></tr>`;

    summaryHTML += `<tr><td><strong>Other Complaints:</strong></td><td>${document.getElementById('othersComplaint').value || 'None'}</td></tr>`;

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

    summaryHTML += `<tr><td><strong>SAMPLE Details:</strong></td><td>${document.getElementById('fastDetails').value || 'Not provided'}</td></tr>`;

    // OB Section
    summaryHTML += '<tr><td colspan="2"><strong>OB Patient Info:</strong></td></tr>';
    summaryHTML += `<tr><td><strong>Baby Status:</strong></td><td>${document.getElementById('babyDelivery').value || 'Not applicable'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Delivery Time:</strong></td><td>${document.getElementById('timeOfDelivery').value || 'Not applicable'}</td></tr>`;
    summaryHTML += `<tr><td><strong>LMP:</strong></td><td>${document.getElementById('lmp').value || 'Not applicable'}</td></tr>`;
    summaryHTML += `<tr><td><strong>AOG:</strong></td><td>${document.getElementById('aog').value || 'Not applicable'}</td></tr>`;
    summaryHTML += `<tr><td><strong>EDC:</strong></td><td>${document.getElementById('edc').value || 'Not applicable'}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Team Information Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>👥 Team Information</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Team Leader:</strong></td><td>${document.getElementById('teamLeader').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Data Recorder:</strong></td><td>${document.getElementById('dataRecorder').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Logistic:</strong></td><td>${document.getElementById('logistic').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>1st Aider:</strong></td><td>${document.getElementById('aider1').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>2nd Aider:</strong></td><td>${document.getElementById('aider2').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Team Leader Notes:</strong></td><td>${document.getElementById('teamLeaderNotes').value || 'None'}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    // Hospital Endorsement Table
    summaryHTML += '<div class="summary-section">';
    summaryHTML += '<h6>🏥 Hospital Endorsement</h6>';
    summaryHTML += '<table class="summary-table">';
    summaryHTML += '<tbody>';
    summaryHTML += `<tr><td><strong>Endorsement:</strong></td><td>${document.getElementById('endorsement').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Hospital Name:</strong></td><td>${document.getElementById('hospital').value || 'Not specified'}</td></tr>`;
    summaryHTML += `<tr><td><strong>Date & Time:</strong></td><td>${document.getElementById('dateTime').value || 'Not specified'}</td></tr>`;
    summaryHTML += '</tbody></table>';
    summaryHTML += '</div>';

    summaryHTML += '</div>';
    summaryContainer.innerHTML = summaryHTML;

    // Ensure the container is visible
    summaryContainer.style.display = 'block';
    summaryContainer.style.visibility = 'visible';
    summaryContainer.style.opacity = '1';

    console.log('Summary generated successfully!');
    console.log('Summary HTML length:', summaryHTML.length);
    console.log('Summary container display:', window.getComputedStyle(summaryContainer).display);
}

// ============================================
// FORM SUBMISSION
// ============================================

function submitForm() {
    // Validate required fields
    const requiredFields = [
        { id: 'formDate', name: 'Date' },
        { id: 'depTime', name: 'Departure Time' },
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

    // Check age is greater than 0
    const ageElement = document.getElementById('age');
    if (ageElement && parseInt(ageElement.value) <= 0) {
        missingFields.push('Age (must be greater than 0)');
    }

    // Check gender radio buttons
    const genderSelected = document.querySelector('input[name="gender"]:checked');
    if (!genderSelected) {
        missingFields.push('Gender');
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

    // Confirmation prompt
    Notiflix.Confirm.show(
        'Confirm Submission',
        'Are you sure you want to save this form? Please review all information before proceeding.',
        'Yes, Save Form',
        'Cancel',
        function okCb() {
            // Submit the form
            submitFormData();
        },
        function cancelCb() {
            // Do nothing
        }
    );
}

function submitFormData() {
    // Show loading indicator
    Notiflix.Loading.standard('Saving form...', {
        backgroundColor: 'rgba(0,0,0,0.8)',
    });

    // Serialize injuries to hidden field before submission
    const injuriesDataField = document.getElementById('injuriesData');
    if (injuriesDataField) {
        injuriesDataField.value = JSON.stringify(injuries);
    }

    // Get the form element
    const form = document.getElementById('preHospitalForm');
    const formData = new FormData(form);

    console.log('Submitting form to:', form.action);
    console.log('Injuries being submitted:', injuries);

    // Clear section memory before submitting
    if (typeof clearSectionMemory === 'function') {
        clearSectionMemory();
    }

    // Clear saved tab state from localStorage
    localStorage.removeItem('prehospitalFormCurrentTab');

    // Submit using Fetch API
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
            // Success - show message and redirect
            Notiflix.Report.success(
                'Form Saved Successfully',
                result.data.message || 'Your form has been saved successfully.',
                'OK',
                function() {
                    window.location.href = result.data.redirect_url || '../public/records.php';
                }
            );
        } else {
            // Error from API
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
            clearAllInjuries();
            currentTab = 0;
            navigateTab(0);
            document.querySelectorAll('.nav-link').forEach(tab => {
                tab.classList.remove('completed');
            });

            // Clear section memory
            if (typeof clearSectionMemory === 'function') {
                clearSectionMemory();
            }

            // Clear saved tab state from localStorage
            localStorage.removeItem('prehospitalFormCurrentTab');

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
// BODY DIAGRAM FUNCTIONS
// ============================================

// Body Part Coordinate Mapping System
// Maps percentage coordinates to specific anatomical regions
const bodyPartMaps = {
    front: [
        // Head and Neck (0-15% height)
        { name: "Head", xMin: 35, xMax: 65, yMin: 0, yMax: 10 },
        { name: "Face", xMin: 35, xMax: 65, yMin: 10, yMax: 15 },
        { name: "Neck", xMin: 40, xMax: 60, yMin: 15, yMax: 20 },

        // Upper Torso (20-40% height)
        { name: "Right Shoulder", xMin: 15, xMax: 35, yMin: 20, yMax: 28 },
        { name: "Left Shoulder", xMin: 65, xMax: 85, yMin: 20, yMax: 28 },
        { name: "Chest", xMin: 35, xMax: 65, yMin: 20, yMax: 35 },

        // Arms
        { name: "Right Upper Arm", xMin: 10, xMax: 25, yMin: 28, yMax: 42 },
        { name: "Left Upper Arm", xMin: 75, xMax: 90, yMin: 28, yMax: 42 },
        { name: "Right Elbow", xMin: 8, xMax: 22, yMin: 42, yMax: 48 },
        { name: "Left Elbow", xMin: 78, xMax: 92, yMin: 42, yMax: 48 },
        { name: "Right Forearm", xMin: 5, xMax: 20, yMin: 48, yMax: 62 },
        { name: "Left Forearm", xMin: 80, xMax: 95, yMin: 48, yMax: 62 },
        { name: "Right Wrist", xMin: 3, xMax: 18, yMin: 62, yMax: 66 },
        { name: "Left Wrist", xMin: 82, xMax: 97, yMin: 62, yMax: 66 },
        { name: "Right Hand", xMin: 0, xMax: 18, yMin: 66, yMax: 75 },
        { name: "Left Hand", xMin: 82, xMax: 100, yMin: 66, yMax: 75 },

        // Lower Torso (35-52% height)
        { name: "Abdomen", xMin: 35, xMax: 65, yMin: 35, yMax: 45 },
        { name: "Pelvis", xMin: 35, xMax: 65, yMin: 45, yMax: 52 },

        // Legs
        { name: "Right Groin", xMin: 40, xMax: 50, yMin: 52, yMax: 58 },
        { name: "Left Groin", xMin: 50, xMax: 60, yMin: 52, yMax: 58 },
        { name: "Right Thigh", xMin: 35, xMax: 50, yMin: 58, yMax: 72 },
        { name: "Left Thigh", xMin: 50, xMax: 65, yMin: 58, yMax: 72 },
        { name: "Right Knee", xMin: 35, xMax: 50, yMin: 72, yMax: 78 },
        { name: "Left Knee", xMin: 50, xMax: 65, yMin: 72, yMax: 78 },
        { name: "Right Lower Leg", xMin: 35, xMax: 50, yMin: 78, yMax: 92 },
        { name: "Left Lower Leg", xMin: 50, xMax: 65, yMin: 78, yMax: 92 },
        { name: "Right Ankle", xMin: 35, xMax: 50, yMin: 92, yMax: 95 },
        { name: "Left Ankle", xMin: 50, xMax: 65, yMin: 92, yMax: 95 },
        { name: "Right Foot", xMin: 32, xMax: 50, yMin: 95, yMax: 100 },
        { name: "Left Foot", xMin: 50, xMax: 68, yMin: 95, yMax: 100 }
    ],
    back: [
        // Head and Neck (0-15% height)
        { name: "Back of Head", xMin: 35, xMax: 65, yMin: 0, yMax: 12 },
        { name: "Back of Neck", xMin: 40, xMax: 60, yMin: 12, yMax: 20 },

        // Upper Back (20-40% height)
        { name: "Right Shoulder Blade", xMin: 20, xMax: 40, yMin: 20, yMax: 32 },
        { name: "Left Shoulder Blade", xMin: 60, xMax: 80, yMin: 20, yMax: 32 },
        { name: "Upper Back", xMin: 35, xMax: 65, yMin: 20, yMax: 32 },

        // Arms (back view)
        { name: "Right Upper Arm (Back)", xMin: 10, xMax: 25, yMin: 28, yMax: 42 },
        { name: "Left Upper Arm (Back)", xMin: 75, xMax: 90, yMin: 28, yMax: 42 },
        { name: "Right Elbow (Back)", xMin: 8, xMax: 22, yMin: 42, yMax: 48 },
        { name: "Left Elbow (Back)", xMin: 78, xMax: 92, yMin: 42, yMax: 48 },
        { name: "Right Forearm (Back)", xMin: 5, xMax: 20, yMin: 48, yMax: 62 },
        { name: "Left Forearm (Back)", xMin: 80, xMax: 95, yMin: 48, yMax: 62 },
        { name: "Right Wrist (Back)", xMin: 3, xMax: 18, yMin: 62, yMax: 66 },
        { name: "Left Wrist (Back)", xMin: 82, xMax: 97, yMin: 62, yMax: 66 },
        { name: "Right Hand (Back)", xMin: 0, xMax: 18, yMin: 66, yMax: 75 },
        { name: "Left Hand (Back)", xMin: 82, xMax: 100, yMin: 66, yMax: 75 },

        // Lower Back (32-52% height)
        { name: "Middle Back", xMin: 35, xMax: 65, yMin: 32, yMax: 42 },
        { name: "Lower Back", xMin: 35, xMax: 65, yMin: 42, yMax: 52 },

        // Buttocks and Legs
        { name: "Right Buttock", xMin: 40, xMax: 50, yMin: 52, yMax: 60 },
        { name: "Left Buttock", xMin: 50, xMax: 60, yMin: 52, yMax: 60 },
        { name: "Right Thigh (Back)", xMin: 35, xMax: 50, yMin: 60, yMax: 72 },
        { name: "Left Thigh (Back)", xMin: 50, xMax: 65, yMin: 60, yMax: 72 },
        { name: "Right Knee (Back)", xMin: 35, xMax: 50, yMin: 72, yMax: 78 },
        { name: "Left Knee (Back)", xMin: 50, xMax: 65, yMin: 72, yMax: 78 },
        { name: "Right Calf", xMin: 35, xMax: 50, yMin: 78, yMax: 92 },
        { name: "Left Calf", xMin: 50, xMax: 65, yMin: 78, yMax: 92 },
        { name: "Right Ankle (Back)", xMin: 35, xMax: 50, yMin: 92, yMax: 95 },
        { name: "Left Ankle (Back)", xMin: 50, xMax: 65, yMin: 92, yMax: 95 },
        { name: "Right Heel", xMin: 35, xMax: 50, yMin: 95, yMax: 100 },
        { name: "Left Heel", xMin: 50, xMax: 65, yMin: 95, yMax: 100 }
    ]
};

// Function to detect body part from coordinates
function detectBodyPart(xPercent, yPercent, view) {
    const regions = bodyPartMaps[view];

    // Find the first matching region
    for (let region of regions) {
        if (xPercent >= region.xMin && xPercent <= region.xMax &&
            yPercent >= region.yMin && yPercent <= region.yMax) {
            return region.name;
        }
    }

    // Default fallback if no specific region matched
    return view === 'front' ? 'Front (Unspecified)' : 'Back (Unspecified)';
}

function setupInjuryTypeButtons() {
    const buttons = document.querySelectorAll('.injury-type-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', function() {
            buttons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            selectedInjuryType = this.dataset.type;
        });
    });
}

function setupBodyDiagrams() {
    const frontContainer = document.getElementById('frontContainer');
    const backContainer = document.getElementById('backContainer');

    if (frontContainer) {
        frontContainer.addEventListener('click', function(e) {
            handleBodyClick(e, 'front', frontContainer);
        });
    }

    if (backContainer) {
        backContainer.addEventListener('click', function(e) {
            handleBodyClick(e, 'back', backContainer);
        });
    }
}

function handleBodyClick(e, view, container) {
    const container_rect = container.getBoundingClientRect();
    const image = container.querySelector('.body-image');
    const image_rect = image.getBoundingClientRect();

    // Check if click is on the image
    if (e.clientX < image_rect.left || e.clientX > image_rect.right ||
        e.clientY < image_rect.top || e.clientY > image_rect.bottom) {
        return;
    }

    const x = e.clientX - image_rect.left;
    const y = e.clientY - image_rect.top;

    // Calculate percentages relative to image dimensions
    const xPercent = (x / image_rect.width) * 100;
    const yPercent = (y / image_rect.height) * 100;

    addInjury(xPercent, yPercent, view, container, image_rect, container_rect);
}

function addInjury(x, y, view, container, image_rect, container_rect) {
    injuryCounter++;

    // Detect specific body part from coordinates
    const bodyPart = detectBodyPart(x, y, view);
    console.log('Detected body part:', bodyPart, 'at coordinates:', x, y, 'view:', view);

    const injury = {
        id: injuryCounter,
        type: selectedInjuryType,
        x: x,
        y: y,
        view: view,
        bodyPart: bodyPart,
        notes: ''
    };

    console.log('Created injury:', injury);
    injuries.push(injury);

    // Calculate marker position relative to container
    const containerX = image_rect.left - container_rect.left + (x / 100) * image_rect.width;
    const containerY = image_rect.top - container_rect.top + (y / 100) * image_rect.height;

    // Get injury type abbreviation
    const abbreviations = {
        'laceration': 'LC',
        'fracture': 'FX',
        'burn': 'BN',
        'contusion': 'CT',
        'abrasion': 'AB',
        'other': 'OT'
    };
    const abbreviation = abbreviations[selectedInjuryType] || 'OT';

    const marker = document.createElement('div');
    marker.className = `injury-marker ${selectedInjuryType}`;
    marker.style.left = containerX + 'px';
    marker.style.top = containerY + 'px';
    marker.textContent = abbreviation;
    marker.dataset.id = injuryCounter;
    marker.title = `Injury #${injuryCounter} - ${selectedInjuryType} - ${bodyPart}`;

    container.appendChild(marker);
    updateInjuryList();
}

function updateInjuryList() {
    const container = document.getElementById('injuryListContainer');
    const countElement = document.getElementById('injuryCount');

    if (!container || !countElement) return;

    countElement.textContent = injuries.length;

    if (injuries.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-pin-map"></i>
                </div>
                <p class="empty-state-title">No injuries marked</p>
                <p class="empty-state-subtitle">Click on the body diagram to mark an injury location</p>
            </div>
        `;
    } else {
        container.innerHTML = injuries.map(injury => `
            <div class="injury-item" data-injury-id="${injury.id}">
                <button class="delete-btn" onclick="deleteInjury(${injury.id})" title="Delete injury">×</button>
                <div class="injury-item-header">
                    <span class="injury-number">Injury #${injury.id}</span>
                    <span class="injury-type-badge ${injury.type}">${injury.type.toUpperCase()}</span>
                </div>
                <div style="font-size: 0.85rem; color: #0066cc; margin-bottom: 0.5rem; font-weight: 600;">
                    <strong style="color: #666;">Location:</strong> ${injury.bodyPart ? injury.bodyPart : (injury.view === 'front' ? 'Front (Unspecified)' : 'Back (Unspecified)')}
                </div>
                <textarea class="injury-notes" placeholder="Notes about this injury..."
                          onchange="updateInjuryNotes(${injury.id}, this.value)">${injury.notes}</textarea>
            </div>
        `).join('');
    }

    // CRITICAL: Always serialize injuries to hidden field after any change
    serializeInjuriesToField();
}

function updateInjuryNotes(id, notes) {
    const injury = injuries.find(i => i.id === id);
    if (injury) {
        injury.notes = notes;
        serializeInjuriesToField();
    }
}

function deleteInjury(id) {
    Notiflix.Confirm.show(
        'Delete Injury',
        'Are you sure you want to delete this injury marker?',
        'Yes, Delete',
        'Cancel',
        function okCb() {
            injuries = injuries.filter(i => i.id !== id);

            const marker = document.querySelector(`.injury-marker[data-id="${id}"]`);
            if (marker) {
                marker.remove();
            }

            updateInjuryList();
            Notiflix.Notify.success('Injury marker deleted');
        },
        function cancelCb() {
            // Do nothing
        }
    );
}

function clearAllInjuries() {
    if (injuries.length === 0) {
        Notiflix.Notify.info('No injury markers to clear');
        return;
    }

    Notiflix.Confirm.show(
        'Clear All Injuries',
        `Are you sure you want to clear all ${injuries.length} injury markers?`,
        'Yes, Clear All',
        'Cancel',
        function okCb() {
            injuries = [];
            injuryCounter = 0;
            document.querySelectorAll('.injury-marker').forEach(m => m.remove());
            updateInjuryList();
            Notiflix.Notify.success('All injury markers cleared');
        },
        function cancelCb() {
            // Do nothing
        }
    );
}

function repositionMarkers() {
    injuries.forEach(injury => {
        const marker = document.querySelector(`.injury-marker[data-id="${injury.id}"]`);
        if (marker) {
            const container = marker.parentElement;
            const image = container.querySelector('.body-image');
            const container_rect = container.getBoundingClientRect();
            const image_rect = image.getBoundingClientRect();

            // Recalculate marker position relative to container
            const containerX = image_rect.left - container_rect.left + (injury.x / 100) * image_rect.width;
            const containerY = image_rect.top - container_rect.top + (injury.y / 100) * image_rect.height;

            marker.style.left = containerX + 'px';
            marker.style.top = containerY + 'px';
        }
    });
}

function exportInjuryData() {
    if (injuries.length === 0) {
        Notiflix.Notify.warning('No injuries to export! Please mark some injuries first.');
        return;
    }

    const data = {
        formTitle: 'Pre-Hospital Care - Injury Assessment',
        timestamp: new Date().toISOString(),
        totalInjuries: injuries.length,
        injuries: injuries.map(i => ({
            injuryNumber: i.id,
            type: i.type,
            bodyPart: i.bodyPart || (i.view === 'front' ? 'Front (Unspecified)' : 'Back (Unspecified)'),
            view: i.view,
            coordinates: { x: Math.round(i.x), y: Math.round(i.y) },
            notes: i.notes || 'No notes provided'
        }))
    };

    const dataStr = JSON.stringify(data, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `injury-assessment-${Date.now()}.json`;
    link.click();

    Notiflix.Notify.success(`Successfully exported ${injuries.length} injury markers!`);
}

// ============================================
// VEHICLE MODAL FUNCTIONS
// ============================================

function initializeAmbulanceList() {
    const ambulanceList = document.getElementById('ambulanceList');
    if (!ambulanceList) return;
    
    ambulanceList.innerHTML = '';
    
    // Generate ambulance options V1 to V12
    for (let i = 1; i <= 12; i++) {
        const ambulanceId = `V${i}`;
        const plateNumber = generatePlateNumber();
        
        const ambulanceOption = document.createElement('div');
        ambulanceOption.className = 'vehicle-option';
        ambulanceOption.dataset.id = ambulanceId;
        ambulanceOption.dataset.plate = plateNumber;
        
        ambulanceOption.innerHTML = `
            <div class="vehicle-name">${ambulanceId}</div>
            <div class="vehicle-details">Plate Number: ${plateNumber}</div>
        `;
        
        ambulanceOption.addEventListener('click', function() {
            // Remove selected class from all options
            document.querySelectorAll('#ambulanceList .vehicle-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            this.classList.add('selected');
        });
        
        ambulanceList.appendChild(ambulanceOption);
    }
}

function generatePlateNumber() {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const numbers = '0123456789';
    
    let plate = '';
    // First 3 letters
    for (let i = 0; i < 3; i++) {
        plate += letters.charAt(Math.floor(Math.random() * letters.length));
    }
    // Then 4 numbers
    for (let i = 0; i < 4; i++) {
        plate += numbers.charAt(Math.floor(Math.random() * numbers.length));
    }
    
    return plate;
}

// ============================================
// CAMERA FUNCTIONS
// ============================================

let cameraStream = null;

function initializeCameraButton() {
    const openCameraBtn = document.getElementById('openCameraBtn');
    if (openCameraBtn) {
        openCameraBtn.addEventListener('click', function() {
            openCamera();
        });
    }
}

function openCamera() {
    const cameraContainer = document.getElementById('cameraContainer');
    const video = document.getElementById('cameraVideo');
    const openCameraBtn = document.getElementById('openCameraBtn');

    // Check if browser supports getUserMedia
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        Notiflix.Notify.failure('Camera access is not supported in your browser. Please use a modern browser or upload a file instead.');
        return;
    }

    // Request camera access
    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: 'environment',
            width: { ideal: 1920 },
            height: { ideal: 1080 }
        }
    })
    .then(function(stream) {
        cameraStream = stream;
        video.srcObject = stream;
        cameraContainer.style.display = 'block';
        openCameraBtn.style.display = 'none';
    })
    .catch(function(error) {
        console.error('Error accessing camera:', error);
        let errorMessage = 'Unable to access camera. ';

        if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
            errorMessage += 'Please allow camera permissions in your browser settings.';
        } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
            errorMessage += 'No camera device found. If using a PC, please connect a webcam or use the file upload option.';
        } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
            errorMessage += 'Camera is already in use by another application.';
        } else {
            errorMessage += 'Error: ' + error.message;
        }

        Notiflix.Notify.failure(errorMessage);
    });
}

function capturePhoto() {
    const video = document.getElementById('cameraVideo');
    const preview = document.getElementById('attachmentPreview');
    const removeBtn = document.getElementById('removeAttachmentBtn');
    const fileUpload = document.getElementById('fileUpload');

    // Create canvas to capture image
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Convert canvas to blob and create file
    canvas.toBlob(function(blob) {
        // Create a file from the blob
        const file = new File([blob], `endorsement_${Date.now()}.jpg`, { type: 'image/jpeg' });

        // Create a DataTransfer to set the file input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileUpload.files = dataTransfer.files;

        // Display preview
        const imageUrl = URL.createObjectURL(blob);
        preview.src = imageUrl;
        preview.style.display = 'block';
        removeBtn.style.display = 'inline-block';

        // Close camera
        closeCamera();

        Notiflix.Notify.success('Photo captured successfully!');
    }, 'image/jpeg', 0.9);
}

function closeCamera() {
    const cameraContainer = document.getElementById('cameraContainer');
    const video = document.getElementById('cameraVideo');
    const openCameraBtn = document.getElementById('openCameraBtn');

    // Stop all video tracks
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }

    video.srcObject = null;
    cameraContainer.style.display = 'none';
    openCameraBtn.style.display = 'inline-block';
}

function validateFileUpload(input) {
    const file = input.files[0];
    const uploadError = document.getElementById('uploadError');
    const preview = document.getElementById('attachmentPreview');
    const removeBtn = document.getElementById('removeAttachmentBtn');

    if (!file) return;

    // Validate file size (5MB max)
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if (file.size > maxSize) {
        uploadError.textContent = 'File size exceeds 5MB limit.';
        uploadError.style.display = 'block';
        input.value = '';
        return;
    }

    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        uploadError.textContent = 'Invalid file format. Allowed: JPG, PNG, GIF, WebP';
        uploadError.style.display = 'block';
        input.value = '';
        return;
    }

    // Clear error and show preview
    uploadError.style.display = 'none';
    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
        removeBtn.style.display = 'inline-block';
    };
    reader.readAsDataURL(file);
}

function removeAttachment() {
    const fileUpload = document.getElementById('fileUpload');
    const preview = document.getElementById('attachmentPreview');
    const removeBtn = document.getElementById('removeAttachmentBtn');

    fileUpload.value = '';
    preview.src = '';
    preview.style.display = 'none';
    removeBtn.style.display = 'none';
}

// Patient Documentation Camera Functions
let patientCameraStream = null;

function initializePatientCameraButton() {
    const openPatientCameraBtn = document.getElementById('openPatientCameraBtn');
    if (openPatientCameraBtn) {
        openPatientCameraBtn.addEventListener('click', function() {
            openPatientCamera();
        });
    }
}

function openPatientCamera() {
    const cameraContainer = document.getElementById('patientCameraContainer');
    const video = document.getElementById('patientCameraVideo');
    const openCameraBtn = document.getElementById('openPatientCameraBtn');

    // Check if browser supports getUserMedia
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        Notiflix.Notify.failure('Camera access is not supported in your browser. Please use a modern browser or upload a file instead.');
        return;
    }

    // Request camera access
    navigator.mediaDevices.getUserMedia({
        video: {
            facingMode: 'environment',
            width: { ideal: 1920 },
            height: { ideal: 1080 }
        }
    })
    .then(function(stream) {
        patientCameraStream = stream;
        video.srcObject = stream;
        cameraContainer.style.display = 'block';
        openCameraBtn.style.display = 'none';
    })
    .catch(function(error) {
        console.error('Error accessing camera:', error);
        let errorMessage = 'Unable to access camera. ';

        if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
            errorMessage += 'Please allow camera permissions in your browser settings.';
        } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
            errorMessage += 'No camera device found. If using a PC, please connect a webcam or use the file upload option.';
        } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
            errorMessage += 'Camera is already in use by another application.';
        } else {
            errorMessage += 'Error: ' + error.message;
        }

        Notiflix.Notify.failure(errorMessage);
    });
}

function capturePatientPhoto() {
    const video = document.getElementById('patientCameraVideo');
    const preview = document.getElementById('patientAttachmentPreview');
    const removeBtn = document.getElementById('removePatientAttachmentBtn');
    const fileUpload = document.getElementById('patientFileUpload');

    // Create canvas to capture image
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Convert canvas to blob and create file
    canvas.toBlob(function(blob) {
        // Create a file from the blob
        const file = new File([blob], `patient_${Date.now()}.jpg`, { type: 'image/jpeg' });

        // Create a DataTransfer to set the file input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileUpload.files = dataTransfer.files;

        // Display preview
        const imageUrl = URL.createObjectURL(blob);
        preview.src = imageUrl;
        preview.style.display = 'block';
        removeBtn.style.display = 'inline-block';

        // Close camera
        closePatientCamera();

        Notiflix.Notify.success('Photo captured successfully!');
    }, 'image/jpeg', 0.9);
}

function closePatientCamera() {
    const cameraContainer = document.getElementById('patientCameraContainer');
    const video = document.getElementById('patientCameraVideo');
    const openCameraBtn = document.getElementById('openPatientCameraBtn');

    // Stop all video tracks
    if (patientCameraStream) {
        patientCameraStream.getTracks().forEach(track => track.stop());
        patientCameraStream = null;
    }

    video.srcObject = null;
    cameraContainer.style.display = 'none';
    openCameraBtn.style.display = 'inline-block';
}

function validatePatientFileUpload(input) {
    const file = input.files[0];
    const uploadError = document.getElementById('patientUploadError');
    const preview = document.getElementById('patientAttachmentPreview');
    const removeBtn = document.getElementById('removePatientAttachmentBtn');

    if (!file) return;

    // Validate file size (5MB max)
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if (file.size > maxSize) {
        uploadError.textContent = 'File size exceeds 5MB limit.';
        uploadError.style.display = 'block';
        input.value = '';
        return;
    }

    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        uploadError.textContent = 'Invalid file format. Allowed: JPG, PNG, GIF, WebP';
        uploadError.style.display = 'block';
        input.value = '';
        return;
    }

    // Clear error and show preview
    uploadError.style.display = 'none';
    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
        removeBtn.style.display = 'inline-block';
    };
    reader.readAsDataURL(file);
}

function removePatientAttachment() {
    const fileUpload = document.getElementById('patientFileUpload');
    const preview = document.getElementById('patientAttachmentPreview');
    const removeBtn = document.getElementById('removePatientAttachmentBtn');

    fileUpload.value = '';
    preview.src = '';
    preview.style.display = 'none';
    removeBtn.style.display = 'none';
}

function setupVehicleModals() {
    // Initialize camera buttons
    initializeCameraButton();
    initializePatientCameraButton();

    // Ambulance selection
    const ambulanceRadio = document.getElementById('ambulance');
    if (ambulanceRadio) {
        ambulanceRadio.addEventListener('click', function() {
            // Hide previous selection display while choosing new vehicle
            const displayDiv = document.getElementById('selectedVehicleDisplay');
            displayDiv.style.display = 'none';

            const ambulanceModal = new bootstrap.Modal(document.getElementById('ambulanceModal'));
            ambulanceModal.show();
        });
    }

    // Fire truck selection
    const fireTruckRadio = document.getElementById('fireTruck');
    if (fireTruckRadio) {
        fireTruckRadio.addEventListener('click', function() {
            // Hide previous selection display while choosing new vehicle
            const displayDiv = document.getElementById('selectedVehicleDisplay');
            displayDiv.style.display = 'none';

            const fireTruckModal = new bootstrap.Modal(document.getElementById('fireTruckModal'));
            fireTruckModal.show();
        });
    }

    // Others vehicle selection
    const othersRadio = document.getElementById('othersVehicle');
    if (othersRadio) {
        othersRadio.addEventListener('click', function() {
            // Prompt user to specify vehicle
            Notiflix.Confirm.show(
                'Specify Vehicle',
                'Please enter the vehicle type/name:',
                'Save',
                'Cancel',
                function() {
                    // Show input prompt
                    const vehicleName = prompt('Enter vehicle type/name:');
                    if (vehicleName && vehicleName.trim() !== '') {
                        // Store vehicle details
                        document.getElementById('vehicleDetails').value = JSON.stringify({
                            type: 'others',
                            name: vehicleName.trim()
                        });

                        // Display selected vehicle
                        const displayDiv = document.getElementById('selectedVehicleDisplay');
                        const displayText = document.getElementById('selectedVehicleText');
                        displayText.textContent = `Other Vehicle - ${vehicleName.trim()}`;
                        displayDiv.style.display = 'block';

                        Notiflix.Notify.success(`${vehicleName.trim()} selected`);
                    } else {
                        // Uncheck the radio if canceled
                        othersRadio.checked = false;
                    }
                },
                function() {
                    // Uncheck the radio if canceled
                    othersRadio.checked = false;
                }
            );
        });
    }
    
    // Fire truck selection
    document.querySelectorAll('#fireTruckModal .vehicle-option').forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected class from all options
            document.querySelectorAll('#fireTruckModal .vehicle-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            this.classList.add('selected');
        });
    });
    
    // Confirm ambulance selection
    const confirmAmbulanceBtn = document.getElementById('confirmAmbulance');
    if (confirmAmbulanceBtn) {
        confirmAmbulanceBtn.addEventListener('click', function() {
            const selectedAmbulance = document.querySelector('#ambulanceList .vehicle-option.selected');

            if (selectedAmbulance) {
                const ambulanceId = selectedAmbulance.dataset.id;
                const plateNumber = selectedAmbulance.dataset.plate;

                // Store vehicle details in hidden field
                document.getElementById('vehicleDetails').value = JSON.stringify({
                    type: 'ambulance',
                    id: ambulanceId,
                    plate: plateNumber
                });

                // Display selected vehicle
                const displayDiv = document.getElementById('selectedVehicleDisplay');
                const displayText = document.getElementById('selectedVehicleText');
                displayText.textContent = `Ambulance ${ambulanceId} (${plateNumber})`;
                displayDiv.style.display = 'block';

                Notiflix.Notify.success(`Ambulance ${ambulanceId} (${plateNumber}) selected`);

                // Close modal
                const ambulanceModal = bootstrap.Modal.getInstance(document.getElementById('ambulanceModal'));
                ambulanceModal.hide();
            } else {
                Notiflix.Notify.warning('Please select an ambulance');
            }
        });
    }
    
    // Confirm fire truck selection
    const confirmFireTruckBtn = document.getElementById('confirmFireTruck');
    if (confirmFireTruckBtn) {
        confirmFireTruckBtn.addEventListener('click', function() {
            const selectedFireTruck = document.querySelector('#fireTruckModal .vehicle-option.selected');

            if (selectedFireTruck) {
                const fireTruckType = selectedFireTruck.dataset.type;
                const fireTruckName = selectedFireTruck.querySelector('.vehicle-name').textContent;

                // Store vehicle details in hidden field
                document.getElementById('vehicleDetails').value = JSON.stringify({
                    type: 'firetruck',
                    subtype: fireTruckType,
                    name: fireTruckName
                });

                // Display selected vehicle
                const displayDiv = document.getElementById('selectedVehicleDisplay');
                const displayText = document.getElementById('selectedVehicleText');
                displayText.textContent = `Fire Truck - ${fireTruckName}`;
                displayDiv.style.display = 'block';

                Notiflix.Notify.success(`${fireTruckName} selected`);

                // Close modal
                const fireTruckModal = bootstrap.Modal.getInstance(document.getElementById('fireTruckModal'));
                fireTruckModal.hide();
            } else {
                Notiflix.Notify.warning('Please select a fire truck type');
            }
        });
    }
}

// ============================================
// LOAD EXISTING INJURIES (FOR EDIT MODE)
// ============================================
function loadExistingInjuries() {
    const injuriesDataField = document.getElementById('injuriesData');

    if (!injuriesDataField || !injuriesDataField.value) {
        return; // No existing injuries to load
    }

    try {
        const existingInjuries = JSON.parse(injuriesDataField.value);

        if (!Array.isArray(existingInjuries) || existingInjuries.length === 0) {
            return; // No injuries to load
        }

        console.log('Loading existing injuries:', existingInjuries);

        const renderInjuries = () => {
            // Clear current injuries array
            injuries = [];
            injuryCounter = 0;

            // Convert database format to JavaScript format and render markers
            existingInjuries.forEach((dbInjury) => {
                // Map database fields to JavaScript object format
                const injury = {
                    id: dbInjury.injury_number || dbInjury.id || ++injuryCounter,
                    type: dbInjury.injury_type || dbInjury.type || 'other',
                    x: parseFloat(dbInjury.coordinate_x || dbInjury.x || 0),
                    y: parseFloat(dbInjury.coordinate_y || dbInjury.y || 0),
                    view: dbInjury.body_view || dbInjury.view || 'front',
                    bodyPart: dbInjury.body_part || dbInjury.bodyPart || '',
                    notes: dbInjury.notes || ''
                };

                // Update counter to highest ID
                if (injury.id >= injuryCounter) {
                    injuryCounter = injury.id + 1;
                }

                // Add to injuries array
                injuries.push(injury);

                // Render marker on body diagram
                renderExistingMarker(injury);
            });

            // Update the injury list display
            updateInjuryList();

            // Serialize to hidden field
            if (injuriesDataField) {
                injuriesDataField.value = JSON.stringify(injuries);
            }

            console.log('Loaded injuries:', injuries);
        };

        // Wait for window load to ensure all images and layout are ready
        if (document.readyState === 'complete') {
            // Page already loaded, render immediately
            setTimeout(renderInjuries, 100); // Small delay to ensure layout is settled
        } else {
            // Wait for full page load including images
            window.addEventListener('load', () => {
                setTimeout(renderInjuries, 100); // Small delay to ensure layout is settled
            });
        }

    } catch (error) {
        console.error('Error loading existing injuries:', error);
    }
}

// Render an existing injury marker on the body diagram
function renderExistingMarker(injury) {
    const containerId = injury.view === 'front' ? 'frontContainer' : 'backContainer';
    const container = document.getElementById(containerId);

    if (!container) {
        console.error('Container not found:', containerId);
        return;
    }

    const img = container.querySelector('.body-image');
    if (!img) {
        console.error('Body image not found in container:', containerId);
        return;
    }

    // Get container and image dimensions
    const container_rect = container.getBoundingClientRect();
    const image_rect = img.getBoundingClientRect();

    // Calculate marker position relative to container (same logic as addInjury)
    const containerX = image_rect.left - container_rect.left + (injury.x / 100) * image_rect.width;
    const containerY = image_rect.top - container_rect.top + (injury.y / 100) * image_rect.height;

    // Get abbreviation for injury type
    const abbreviations = {
        'laceration': 'LC',
        'fracture': 'FX',
        'burn': 'BN',
        'contusion': 'CT',
        'abrasion': 'AB',
        'other': 'OT'
    };
    const abbreviation = abbreviations[injury.type] || 'OT';

    // Create marker element
    const marker = document.createElement('div');
    marker.className = `injury-marker ${injury.type}`;
    marker.style.left = containerX + 'px';
    marker.style.top = containerY + 'px';
    marker.textContent = abbreviation;
    marker.dataset.id = injury.id;
    marker.title = `Injury #${injury.id} - ${injury.type} - ${injury.bodyPart || injury.view}`;

    container.appendChild(marker);
}

// ============================================
// SERIALIZE INJURIES TO HIDDEN FIELD
// ============================================
function serializeInjuriesToField() {
    const injuriesDataField = document.getElementById('injuriesData');
    if (injuriesDataField) {
        injuriesDataField.value = JSON.stringify(injuries);
    }
}
