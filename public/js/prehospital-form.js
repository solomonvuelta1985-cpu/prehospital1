// ============================================
// Pre-Hospital Care Form - Main Orchestrator
// This file initializes all modules and wires up shared event handlers.
// Module files (loaded before this): utils.js, injury-tracker.js,
// camera.js, form-tabs.js, auto-save.js, validation.js
// ============================================

// ============================================
// VEHICLE FUNCTIONS
// ============================================

function initializeAmbulanceList() {
    const ambulanceList = document.getElementById('ambulanceList');
    if (!ambulanceList) return;

    ambulanceList.innerHTML = '';

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
            document.querySelectorAll('#ambulanceList .vehicle-option').forEach(option => {
                option.classList.remove('selected');
            });
            this.classList.add('selected');
        });

        ambulanceList.appendChild(ambulanceOption);
    }
}

function setupVehicleModals() {
    // Initialize camera buttons
    initializeCameraButton();
    initializePatientCameraButton();

    // Ambulance selection
    const ambulanceRadio = document.getElementById('ambulance');
    if (ambulanceRadio) {
        ambulanceRadio.addEventListener('click', function() {
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
            Notiflix.Confirm.show(
                'Specify Vehicle',
                'Please enter the vehicle type/name:',
                'Save',
                'Cancel',
                function() {
                    const vehicleName = prompt('Enter vehicle type/name:');
                    if (vehicleName && vehicleName.trim() !== '') {
                        document.getElementById('vehicleDetails').value = JSON.stringify({
                            type: 'others',
                            name: vehicleName.trim()
                        });

                        const displayDiv = document.getElementById('selectedVehicleDisplay');
                        const displayText = document.getElementById('selectedVehicleText');
                        displayText.textContent = `Other Vehicle - ${vehicleName.trim()}`;
                        displayDiv.style.display = 'block';

                        Notiflix.Notify.success(`${vehicleName.trim()} selected`);
                    } else {
                        othersRadio.checked = false;
                    }
                },
                function() {
                    othersRadio.checked = false;
                }
            );
        });
    }

    // Fire truck option clicks
    document.querySelectorAll('#fireTruckModal .vehicle-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('#fireTruckModal .vehicle-option').forEach(opt => {
                opt.classList.remove('selected');
            });
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

                document.getElementById('vehicleDetails').value = JSON.stringify({
                    type: 'ambulance',
                    id: ambulanceId,
                    plate: plateNumber
                });

                const displayDiv = document.getElementById('selectedVehicleDisplay');
                const displayText = document.getElementById('selectedVehicleText');
                displayText.textContent = `Ambulance ${ambulanceId} (${plateNumber})`;
                displayDiv.style.display = 'block';

                Notiflix.Notify.success(`Ambulance ${ambulanceId} (${plateNumber}) selected`);

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

                document.getElementById('vehicleDetails').value = JSON.stringify({
                    type: 'firetruck',
                    subtype: fireTruckType,
                    name: fireTruckName
                });

                const displayDiv = document.getElementById('selectedVehicleDisplay');
                const displayText = document.getElementById('selectedVehicleText');
                displayText.textContent = `Fire Truck - ${fireTruckName}`;
                displayDiv.style.display = 'block';

                Notiflix.Notify.success(`${fireTruckName} selected`);

                const fireTruckModal = bootstrap.Modal.getInstance(document.getElementById('fireTruckModal'));
                fireTruckModal.hide();
            } else {
                Notiflix.Notify.warning('Please select a fire truck type');
            }
        });
    }
}

// ============================================
// INITIALIZATION
// ============================================

// Reposition injury markers on window resize
window.addEventListener('resize', repositionMarkers);

document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation
    restoreSavedTab();
    updateNavigation();
    updateProgress();

    // Injury tracker
    setupInjuryTypeButtons();
    setupBodyDiagrams();
    loadExistingInjuries();

    // Vehicle & camera
    initializeAmbulanceList();
    setupVehicleModals();

    // Initialize progress bar for edit form
    if (document.getElementById('editForm')) {
        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.style.width = '16.67%';
        }
    }

    // Auto-save (new forms only)
    initAutoSave();

    // Time inputs (slight delay to ensure page is fully loaded)
    setTimeout(function() {
        initialize12HourTimeInputs();
        loadExistingTimeValues();
    }, 100);

    // Live duplicate Arrival at Scene Location -> Departure from Scene Location
    const arrSceneLocation = document.getElementById('arrSceneLocation');
    const depSceneLocation = document.getElementById('depSceneLocation');
    if (arrSceneLocation && depSceneLocation) {
        arrSceneLocation.addEventListener('input', function() {
            if (!depSceneLocation.value || depSceneLocation.dataset.autoFilled === 'true') {
                depSceneLocation.value = arrSceneLocation.value;
                depSceneLocation.dataset.autoFilled = 'true';
            }
        });
        depSceneLocation.addEventListener('input', function() {
            if (depSceneLocation.value !== arrSceneLocation.value) {
                depSceneLocation.dataset.autoFilled = 'false';
            }
        });
    }
});
