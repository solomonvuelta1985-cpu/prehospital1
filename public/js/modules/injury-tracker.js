// ============================================
// INJURY TRACKER MODULE
// Body diagram, injury markers, coordinate tracking
// ============================================

// Body Diagram Variables
let injuries = [];
let injuryCounter = 0;
let selectedInjuryType = 'laceration';

// Body Part Coordinate Mapping System
const bodyPartMaps = {
    front: [
        { name: "Head", xMin: 35, xMax: 65, yMin: 0, yMax: 10 },
        { name: "Face", xMin: 35, xMax: 65, yMin: 10, yMax: 15 },
        { name: "Neck", xMin: 40, xMax: 60, yMin: 15, yMax: 20 },
        { name: "Right Shoulder", xMin: 15, xMax: 35, yMin: 20, yMax: 28 },
        { name: "Left Shoulder", xMin: 65, xMax: 85, yMin: 20, yMax: 28 },
        { name: "Chest", xMin: 35, xMax: 65, yMin: 20, yMax: 35 },
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
        { name: "Abdomen", xMin: 35, xMax: 65, yMin: 35, yMax: 45 },
        { name: "Pelvis", xMin: 35, xMax: 65, yMin: 45, yMax: 52 },
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
        { name: "Back of Head", xMin: 35, xMax: 65, yMin: 0, yMax: 12 },
        { name: "Back of Neck", xMin: 40, xMax: 60, yMin: 12, yMax: 20 },
        { name: "Right Shoulder Blade", xMin: 20, xMax: 40, yMin: 20, yMax: 32 },
        { name: "Left Shoulder Blade", xMin: 60, xMax: 80, yMin: 20, yMax: 32 },
        { name: "Upper Back", xMin: 35, xMax: 65, yMin: 20, yMax: 32 },
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
        { name: "Middle Back", xMin: 35, xMax: 65, yMin: 32, yMax: 42 },
        { name: "Lower Back", xMin: 35, xMax: 65, yMin: 42, yMax: 52 },
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

function detectBodyPart(xPercent, yPercent, view) {
    const regions = bodyPartMaps[view];

    for (let region of regions) {
        if (xPercent >= region.xMin && xPercent <= region.xMax &&
            yPercent >= region.yMin && yPercent <= region.yMax) {
            return region.name;
        }
    }

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
        frontContainer.setAttribute('role', 'application');
        frontContainer.setAttribute('aria-label', 'Front body diagram - click to mark injury locations');
        frontContainer.setAttribute('tabindex', '0');
        frontContainer.addEventListener('click', function(e) {
            handleBodyClick(e, 'front', frontContainer);
        });
        frontContainer.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const image = frontContainer.querySelector('.body-image');
                if (image) {
                    const rect = image.getBoundingClientRect();
                    addInjury(50, 50, 'front', frontContainer, rect, frontContainer.getBoundingClientRect());
                }
            }
        });
    }

    if (backContainer) {
        backContainer.setAttribute('role', 'application');
        backContainer.setAttribute('aria-label', 'Back body diagram - click to mark injury locations');
        backContainer.setAttribute('tabindex', '0');
        backContainer.addEventListener('click', function(e) {
            handleBodyClick(e, 'back', backContainer);
        });
        backContainer.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const image = backContainer.querySelector('.body-image');
                if (image) {
                    const rect = image.getBoundingClientRect();
                    addInjury(50, 50, 'back', backContainer, rect, backContainer.getBoundingClientRect());
                }
            }
        });
    }
}

function handleBodyClick(e, view, container) {
    const container_rect = container.getBoundingClientRect();
    const image = container.querySelector('.body-image');
    const image_rect = image.getBoundingClientRect();

    if (e.clientX < image_rect.left || e.clientX > image_rect.right ||
        e.clientY < image_rect.top || e.clientY > image_rect.bottom) {
        return;
    }

    const x = e.clientX - image_rect.left;
    const y = e.clientY - image_rect.top;

    const xPercent = (x / image_rect.width) * 100;
    const yPercent = (y / image_rect.height) * 100;

    addInjury(xPercent, yPercent, view, container, image_rect, container_rect);
}

function addInjury(x, y, view, container, image_rect, container_rect) {
    injuryCounter++;

    const bodyPart = detectBodyPart(x, y, view);

    const injury = {
        id: injuryCounter,
        type: selectedInjuryType,
        x: x,
        y: y,
        view: view,
        bodyPart: bodyPart,
        notes: ''
    };

    injuries.push(injury);

    const containerX = image_rect.left - container_rect.left + (x / 100) * image_rect.width;
    const containerY = image_rect.top - container_rect.top + (y / 100) * image_rect.height;

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
                    <span class="injury-type-badge ${escapeHtml(injury.type)}">${escapeHtml(injury.type).toUpperCase()}</span>
                </div>
                <div style="font-size: 0.85rem; color: #0066cc; margin-bottom: 0.5rem; font-weight: 600;">
                    <strong style="color: #666;">Location:</strong> ${injury.bodyPart ? escapeHtml(injury.bodyPart) : (injury.view === 'front' ? 'Front (Unspecified)' : 'Back (Unspecified)')}
                </div>
                <textarea class="injury-notes" placeholder="Notes about this injury..."
                          onchange="updateInjuryNotes(${injury.id}, this.value)">${escapeHtml(injury.notes)}</textarea>
            </div>
        `).join('');
    }

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
// LOAD EXISTING INJURIES (FOR EDIT MODE)
// ============================================

function loadExistingInjuries() {
    const injuriesDataField = document.getElementById('injuriesData');

    if (!injuriesDataField || !injuriesDataField.value) {
        return;
    }

    try {
        const existingInjuries = JSON.parse(injuriesDataField.value);

        if (!Array.isArray(existingInjuries) || existingInjuries.length === 0) {
            return;
        }

        const renderInjuries = () => {
            injuries = [];
            injuryCounter = 0;

            existingInjuries.forEach((dbInjury) => {
                const injury = {
                    id: dbInjury.injury_number || dbInjury.id || ++injuryCounter,
                    type: dbInjury.injury_type || dbInjury.type || 'other',
                    x: parseFloat(dbInjury.coordinate_x || dbInjury.x || 0),
                    y: parseFloat(dbInjury.coordinate_y || dbInjury.y || 0),
                    view: dbInjury.body_view || dbInjury.view || 'front',
                    bodyPart: dbInjury.body_part || dbInjury.bodyPart || '',
                    notes: dbInjury.notes || ''
                };

                if (injury.id >= injuryCounter) {
                    injuryCounter = injury.id + 1;
                }

                injuries.push(injury);
                renderExistingMarker(injury);
            });

            updateInjuryList();

            if (injuriesDataField) {
                injuriesDataField.value = JSON.stringify(injuries);
            }
        };

        const waitForImagesAndRender = () => {
            const frontImg = document.querySelector('#frontContainer .body-image');
            const backImg = document.querySelector('#backContainer .body-image');
            const images = [frontImg, backImg].filter(Boolean);

            if (images.length === 0) {
                console.error('Body diagram images not found');
                return;
            }

            const allLoaded = images.every(img => img.complete && img.naturalHeight > 0);

            if (allLoaded) {
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        renderInjuries();
                    });
                });
            } else {
                let loadedCount = 0;
                const onImageReady = () => {
                    loadedCount++;
                    if (loadedCount >= images.filter(img => !img.complete || img.naturalHeight === 0).length) {
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                renderInjuries();
                            });
                        });
                    }
                };
                images.forEach(img => {
                    if (!img.complete || img.naturalHeight === 0) {
                        img.addEventListener('load', onImageReady, { once: true });
                    }
                });
                setTimeout(() => {
                    if (injuries.length === 0) {
                        renderInjuries();
                    }
                }, 2000);
            }
        };

        if (document.readyState === 'complete') {
            waitForImagesAndRender();
        } else {
            window.addEventListener('load', waitForImagesAndRender);
        }

    } catch (error) {
        console.error('Error loading existing injuries:', error);
    }
}

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

    const container_rect = container.getBoundingClientRect();
    const image_rect = img.getBoundingClientRect();

    const containerX = image_rect.left - container_rect.left + (injury.x / 100) * image_rect.width;
    const containerY = image_rect.top - container_rect.top + (injury.y / 100) * image_rect.height;

    const abbreviations = {
        'laceration': 'LC',
        'fracture': 'FX',
        'burn': 'BN',
        'contusion': 'CT',
        'abrasion': 'AB',
        'other': 'OT'
    };
    const abbreviation = abbreviations[injury.type] || 'OT';

    const marker = document.createElement('div');
    marker.className = `injury-marker ${injury.type}`;
    marker.style.left = containerX + 'px';
    marker.style.top = containerY + 'px';
    marker.textContent = abbreviation;
    marker.dataset.id = injury.id;
    marker.title = `Injury #${injury.id} - ${injury.type} - ${injury.bodyPart || injury.view}`;

    container.appendChild(marker);
}

function serializeInjuriesToField() {
    const injuriesDataField = document.getElementById('injuriesData');
    if (injuriesDataField) {
        injuriesDataField.value = JSON.stringify(injuries);
    }
}
