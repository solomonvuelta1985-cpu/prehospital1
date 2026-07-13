// ============================================
// INJURY TRACKER MODULE
// Body diagram, injury markers, coordinate tracking
// ============================================

// Body Diagram Variables
let injuries = [];
let injuryCounter = 0;
let selectedInjuryType = 'laceration';
let activeMobileView = 'front'; // 'front' or 'back' for mobile tab toggle

// Hover preview tooltip element
let hoverPreview = null;

// Body Part Coordinate Mapping System
// NOTE: Regions are ordered from MOST specific (smallest) to LEAST specific (broadest).
// detectBodyPart() returns the first match, so this ordering prevents misclassification
// at overlapping boundary edges (e.g., Elbow before Upper Arm, Knee before Thigh).
const bodyPartMaps = {
    front: [
        { name: "Head", xMin: 30, xMax: 70, yMin: 0, yMax: 10 },
        { name: "Face (Eyes)", xMin: 35, xMax: 65, yMin: 10, yMax: 13.5 },
        { name: "Face (Nose/Mouth)", xMin: 35, xMax: 65, yMin: 13.5, yMax: 16.5 },
        { name: "Face (Chin)", xMin: 35, xMax: 65, yMin: 16.5, yMax: 20 },
        { name: "Neck", xMin: 35, xMax: 65, yMin: 18, yMax: 22 },
        { name: "Right Shoulder", xMin: 10, xMax: 35, yMin: 20, yMax: 28 },
        { name: "Left Shoulder", xMin: 65, xMax: 90, yMin: 20, yMax: 28 },
        { name: "Upper Chest", xMin: 35, xMax: 65, yMin: 22, yMax: 28 },
        { name: "Middle Chest", xMin: 33, xMax: 67, yMin: 28, yMax: 33 },
        { name: "Lower Chest", xMin: 33, xMax: 67, yMin: 33, yMax: 37 },
        { name: "Right Elbow", xMin: 5, xMax: 28, yMin: 42, yMax: 48 },
        { name: "Left Elbow", xMin: 72, xMax: 95, yMin: 42, yMax: 48 },
        { name: "Right Upper Arm", xMin: 5, xMax: 33, yMin: 28, yMax: 42 },
        { name: "Left Upper Arm", xMin: 67, xMax: 95, yMin: 28, yMax: 42 },
        { name: "Upper Abdomen", xMin: 33, xMax: 67, yMin: 37, yMax: 42 },
        { name: "Lower Abdomen", xMin: 33, xMax: 67, yMin: 42, yMax: 47 },
        { name: "Pelvis", xMin: 33, xMax: 67, yMin: 47, yMax: 53 },
        { name: "Right Groin", xMin: 38, xMax: 50, yMin: 53, yMax: 58 },
        { name: "Left Groin", xMin: 50, xMax: 62, yMin: 53, yMax: 58 },
        { name: "Right Wrist", xMin: 0, xMax: 22, yMin: 62, yMax: 66 },
        { name: "Left Wrist", xMin: 78, xMax: 100, yMin: 62, yMax: 66 },
        { name: "Right Forearm", xMin: 0, xMax: 27, yMin: 48, yMax: 62 },
        { name: "Left Forearm", xMin: 73, xMax: 100, yMin: 48, yMax: 62 },
        { name: "Right Hand", xMin: 0, xMax: 22, yMin: 66, yMax: 78 },
        { name: "Left Hand", xMin: 78, xMax: 100, yMin: 66, yMax: 78 },
        { name: "Right Knee", xMin: 33, xMax: 50, yMin: 72, yMax: 78 },
        { name: "Left Knee", xMin: 50, xMax: 67, yMin: 72, yMax: 78 },
        { name: "Right Thigh", xMin: 33, xMax: 50, yMin: 58, yMax: 72 },
        { name: "Left Thigh", xMin: 50, xMax: 67, yMin: 58, yMax: 72 },
        { name: "Right Ankle", xMin: 33, xMax: 50, yMin: 92, yMax: 95 },
        { name: "Left Ankle", xMin: 50, xMax: 67, yMin: 92, yMax: 95 },
        { name: "Right Lower Leg", xMin: 33, xMax: 50, yMin: 78, yMax: 92 },
        { name: "Left Lower Leg", xMin: 50, xMax: 67, yMin: 78, yMax: 92 },
        { name: "Right Foot", xMin: 30, xMax: 50, yMin: 95, yMax: 100 },
        { name: "Left Foot", xMin: 50, xMax: 70, yMin: 95, yMax: 100 }
    ],
    back: [
        { name: "Back of Head", xMin: 30, xMax: 70, yMin: 0, yMax: 12 },
        { name: "Back of Neck", xMin: 35, xMax: 65, yMin: 12, yMax: 20 },
        { name: "Right Shoulder Blade", xMin: 10, xMax: 35, yMin: 20, yMax: 32 },
        { name: "Left Shoulder Blade", xMin: 65, xMax: 90, yMin: 20, yMax: 32 },
        { name: "Spine (Cervical/Thoracic)", xMin: 44, xMax: 56, yMin: 20, yMax: 32 },
        { name: "Upper Back", xMin: 33, xMax: 67, yMin: 20, yMax: 28 },
        { name: "Right Elbow (Back)", xMin: 5, xMax: 28, yMin: 42, yMax: 48 },
        { name: "Left Elbow (Back)", xMin: 72, xMax: 95, yMin: 42, yMax: 48 },
        { name: "Right Upper Arm (Back)", xMin: 5, xMax: 33, yMin: 28, yMax: 42 },
        { name: "Left Upper Arm (Back)", xMin: 67, xMax: 95, yMin: 28, yMax: 42 },
        { name: "Spine (Thoracic)", xMin: 44, xMax: 56, yMin: 28, yMax: 42 },
        { name: "Middle Back", xMin: 33, xMax: 67, yMin: 28, yMax: 42 },
        { name: "Right Wrist (Back)", xMin: 0, xMax: 22, yMin: 62, yMax: 66 },
        { name: "Left Wrist (Back)", xMin: 78, xMax: 100, yMin: 62, yMax: 66 },
        { name: "Right Forearm (Back)", xMin: 0, xMax: 27, yMin: 48, yMax: 62 },
        { name: "Left Forearm (Back)", xMin: 73, xMax: 100, yMin: 48, yMax: 62 },
        { name: "Right Hand (Back)", xMin: 0, xMax: 22, yMin: 66, yMax: 78 },
        { name: "Left Hand (Back)", xMin: 78, xMax: 100, yMin: 66, yMax: 78 },
        { name: "Spine (Lumbar)", xMin: 44, xMax: 56, yMin: 42, yMax: 52 },
        { name: "Lower Back", xMin: 33, xMax: 67, yMin: 42, yMax: 52 },
        { name: "Right Buttock", xMin: 38, xMax: 50, yMin: 52, yMax: 60 },
        { name: "Left Buttock", xMin: 50, xMax: 62, yMin: 52, yMax: 60 },
        { name: "Right Knee (Back)", xMin: 33, xMax: 50, yMin: 72, yMax: 78 },
        { name: "Left Knee (Back)", xMin: 50, xMax: 67, yMin: 72, yMax: 78 },
        { name: "Right Thigh (Back)", xMin: 33, xMax: 50, yMin: 60, yMax: 72 },
        { name: "Left Thigh (Back)", xMin: 50, xMax: 67, yMin: 60, yMax: 72 },
        { name: "Right Ankle (Back)", xMin: 33, xMax: 50, yMin: 92, yMax: 95 },
        { name: "Left Ankle (Back)", xMin: 50, xMax: 67, yMin: 92, yMax: 95 },
        { name: "Right Lower Leg (Back)", xMin: 33, xMax: 50, yMin: 78, yMax: 92 },
        { name: "Left Lower Leg (Back)", xMin: 50, xMax: 67, yMin: 78, yMax: 92 },
        { name: "Right Foot (Back)", xMin: 30, xMax: 50, yMin: 95, yMax: 100 },
        { name: "Left Foot (Back)", xMin: 50, xMax: 70, yMin: 95, yMax: 100 }
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

// ============================================
// HOVER PREVIEW
// ============================================

function createHoverPreview() {
    if (hoverPreview) return;
    hoverPreview = document.createElement('div');
    hoverPreview.className = 'body-part-hover-preview';
    hoverPreview.style.cssText = 'position:fixed;pointer-events:none;z-index:9999;background:rgba(0,102,204,0.9);color:#fff;padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;white-space:nowrap;display:none;';
    document.body.appendChild(hoverPreview);
}

function showHoverPreview(e, view) {
    if (!hoverPreview) createHoverPreview();
    var container = view === 'front' ? document.getElementById('frontContainer') : document.getElementById('backContainer');
    if (!container) return;
    var image = container.querySelector('.body-image');
    if (!image) return;
    var imageRect = image.getBoundingClientRect();
    if (e.clientX < imageRect.left || e.clientX > imageRect.right || e.clientY < imageRect.top || e.clientY > imageRect.bottom) {
        hoverPreview.style.display = 'none';
        return;
    }
    var x = e.clientX - imageRect.left;
    var y = e.clientY - imageRect.top;
    var xPercent = (x / imageRect.width) * 100;
    var yPercent = (y / imageRect.height) * 100;
    hoverPreview.textContent = detectBodyPart(xPercent, yPercent, view);
    hoverPreview.style.left = (e.clientX + 15) + 'px';
    hoverPreview.style.top = (e.clientY - 30) + 'px';
    hoverPreview.style.display = 'block';
}

function hideHoverPreview() {
    if (hoverPreview) hoverPreview.style.display = 'none';
}

// ============================================
// INJURY TYPE
// ============================================

function setupInjuryTypeButtons() {
    var buttons = document.querySelectorAll('.injury-type-btn');
    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            buttons.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            selectedInjuryType = this.dataset.type;
        });
    });
}

// ============================================
// MOBILE VIEW TOGGLE
// ============================================

function switchMobileView(view) {
    activeMobileView = view;
    var frontView = document.querySelector('.body-view[data-view="front"]');
    var backView = document.querySelector('.body-view[data-view="back"]');
    var frontTab = document.getElementById('mobileFrontTab');
    var backTab = document.getElementById('mobileBackTab');
    if (frontView && backView) {
        frontView.classList.toggle('mobile-hidden', view !== 'front');
        backView.classList.toggle('mobile-hidden', view !== 'back');
    }
    if (frontTab && backTab) {
        frontTab.classList.toggle('active', view === 'front');
        backTab.classList.toggle('active', view === 'back');
    }
    updateMobileTabCounts();
    setTimeout(function() { repositionMarkers(); }, 150);
}

function updateMobileTabCounts() {
    var frontCount = injuries.filter(function(i) { return i.view === 'front'; }).length;
    var backCount = injuries.filter(function(i) { return i.view === 'back'; }).length;
    var frontBadge = document.getElementById('mobileFrontCount');
    var backBadge = document.getElementById('mobileBackCount');
    if (frontBadge) { frontBadge.textContent = frontCount; frontBadge.style.display = frontCount > 0 ? 'inline-flex' : 'none'; }
    if (backBadge) { backBadge.textContent = backCount; backBadge.style.display = backCount > 0 ? 'inline-flex' : 'none'; }
}

// ============================================
// CSP-SAFE DELEGATED EVENT LISTENER
// (No inline onclick/onchange — all handlers registered via addEventListener)
// ============================================

function setupInjuryListDelegation() {
    var container = document.getElementById('injuryListContainer');
    if (!container) return;

    // Remove old listeners by replacing the node
    var newContainer = container.cloneNode(true);
    container.parentNode.replaceChild(newContainer, container);

    // CLICK delegation: toggle card header or delete injury
    newContainer.addEventListener('click', function(e) {
        var card = e.target.closest('.injury-card');
        if (!card) return;
        var id = parseInt(card.getAttribute('data-injury-id'), 10);
        if (!id) return;

        // Delete button
        if (e.target.closest('.injury-card-delete')) {
            e.stopPropagation();
            deleteInjury(id);
            return;
        }

        // Card header toggle
        if (e.target.closest('.injury-card-header')) {
            toggleInjuryCard(id);
        }
    });

    // CHANGE delegation: severity select or notes textarea
    newContainer.addEventListener('change', function(e) {
        var card = e.target.closest('.injury-card');
        if (!card) return;
        var id = parseInt(card.getAttribute('data-injury-id'), 10);
        if (!id) return;

        if (e.target.matches('.injury-severity-select')) {
            updateInjurySeverity(id, e.target.value);
        }
        if (e.target.matches('.injury-notes')) {
            updateInjuryNotes(id, e.target.value);
        }
    });
}

// ============================================
// BODY DIAGRAM SETUP
// ============================================

function setupBodyDiagrams() {
    var frontContainer = document.getElementById('frontContainer');
    var backContainer = document.getElementById('backContainer');
    createHoverPreview();
    injectMobileViewToggle();

    if (frontContainer) {
        frontContainer.setAttribute('role', 'application');
        frontContainer.setAttribute('aria-label', 'Front body diagram - click to mark injury locations');
        frontContainer.setAttribute('tabindex', '0');
        frontContainer.addEventListener('click', function(e) { handleBodyClick(e, 'front', frontContainer); });
        frontContainer.addEventListener('mousemove', function(e) { showHoverPreview(e, 'front'); });
        frontContainer.addEventListener('mouseleave', hideHoverPreview);
        frontContainer.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                var image = frontContainer.querySelector('.body-image');
                if (image) {
                    var rect = image.getBoundingClientRect();
                    addInjury(50, 50, 'front', frontContainer, rect, frontContainer.getBoundingClientRect());
                }
            }
        });
    }

    if (backContainer) {
        backContainer.setAttribute('role', 'application');
        backContainer.setAttribute('aria-label', 'Back body diagram - click to mark injury locations');
        backContainer.setAttribute('tabindex', '0');
        backContainer.addEventListener('click', function(e) { handleBodyClick(e, 'back', backContainer); });
        backContainer.addEventListener('mousemove', function(e) { showHoverPreview(e, 'back'); });
        backContainer.addEventListener('mouseleave', hideHoverPreview);
        backContainer.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                var image = backContainer.querySelector('.body-image');
                if (image) {
                    var rect = image.getBoundingClientRect();
                    addInjury(50, 50, 'back', backContainer, rect, backContainer.getBoundingClientRect());
                }
            }
        });
    }

    injectInjuryLegend();
    setupInjuryListDelegation();
}

function handleBodyClick(e, view, container) {
    var container_rect = container.getBoundingClientRect();
    var image = container.querySelector('.body-image');
    var image_rect = image.getBoundingClientRect();
    if (e.clientX < image_rect.left || e.clientX > image_rect.right || e.clientY < image_rect.top || e.clientY > image_rect.bottom) return;
    var x = e.clientX - image_rect.left;
    var y = e.clientY - image_rect.top;
    addInjury((x / image_rect.width) * 100, (y / image_rect.height) * 100, view, container, image_rect, container_rect);
}

function addInjury(x, y, view, container, image_rect, container_rect) {
    injuryCounter++;
    var bodyPart = detectBodyPart(x, y, view);
    var injury = { id: injuryCounter, type: selectedInjuryType, severity: 'moderate', x: x, y: y, view: view, bodyPart: bodyPart, notes: '' };
    injuries.push(injury);

    var containerX = image_rect.left - container_rect.left + (x / 100) * image_rect.width;
    var containerY = image_rect.top - container_rect.top + (y / 100) * image_rect.height;
    var abbreviations = { 'laceration': 'LC', 'fracture': 'FX', 'burn': 'BN', 'contusion': 'CT', 'abrasion': 'AB', 'other': 'OT' };
    var abbreviation = abbreviations[selectedInjuryType] || 'OT';

    var marker = document.createElement('div');
    marker.className = 'injury-marker ' + selectedInjuryType;
    marker.style.left = containerX + 'px';
    marker.style.top = containerY + 'px';
    marker.textContent = abbreviation;
    marker.dataset.id = injuryCounter;
    marker.title = 'Injury #' + injuryCounter + ' - ' + selectedInjuryType + ' - ' + bodyPart;
    container.appendChild(marker);
    updateInjuryList();
}

// ============================================
// INJURY LIST UI — Collapsible Cards (CSP-safe: no inline handlers)
// ============================================

function updateInjuryList() {
    var container = document.getElementById('injuryListContainer');
    var countElement = document.getElementById('injuryCount');
    if (!container || !countElement) return;
    countElement.textContent = injuries.length;
    updateMobileTabCounts();

    if (injuries.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i class="bi bi-pin-map"></i></div><p class="empty-state-title">No injuries marked</p><p class="empty-state-subtitle">Tap on the body diagram to mark an injury</p></div>';
    } else {
        container.innerHTML = injuries.map(function(injury) {
            var severityOptions = ['minor', 'moderate', 'severe', 'critical']
                .map(function(s) { return '<option value="' + s + '"' + (injury.severity === s ? ' selected' : '') + '>' + s.charAt(0).toUpperCase() + s.slice(1) + '</option>'; })
                .join('');
            var severityLabels = { 'minor': 'Minor', 'moderate': 'Moderate', 'severe': 'Severe', 'critical': 'Critical' };
            return '<div class="injury-card" data-injury-id="' + injury.id + '">' +
                '<div class="injury-card-header" title="Tap to expand/collapse">' +
                    '<div class="injury-card-summary">' +
                        '<span class="injury-card-number">#' + injury.id + '</span>' +
                        '<span class="injury-marker-dot ' + escapeHtml(injury.type) + '"></span>' +
                        '<span class="injury-card-type">' + escapeHtml(injury.type).toUpperCase() + '</span>' +
                        '<span class="injury-card-severity injury-severity-' + escapeHtml(injury.severity) + '">' + (severityLabels[injury.severity] || 'Moderate') + '</span>' +
                        '<span class="injury-card-part">' + (injury.bodyPart ? escapeHtml(injury.bodyPart) : (injury.view === 'front' ? 'Front' : 'Back')) + '</span>' +
                    '</div>' +
                    '<div class="injury-card-actions">' +
                        '<span class="injury-card-chevron"><i class="bi bi-chevron-down"></i></span>' +
                        '<button type="button" class="injury-card-delete" title="Delete injury">&times;</button>' +
                    '</div>' +
                '</div>' +
                '<div class="injury-card-body" style="display:none;">' +
                    '<div class="injury-card-detail">' +
                        '<label class="injury-detail-label">Severity</label>' +
                        '<select class="injury-severity-select injury-severity-' + escapeHtml(injury.severity) + '">' + severityOptions + '</select>' +
                    '</div>' +
                    '<div class="injury-card-detail">' +
                        '<label class="injury-detail-label">Notes</label>' +
                        '<textarea class="injury-notes" placeholder="Notes about this injury...">' + escapeHtml(injury.notes) + '</textarea>' +
                    '</div>' +
                    '<div class="injury-card-detail-text">' +
                        '<small><strong>Location:</strong> ' + (injury.bodyPart ? escapeHtml(injury.bodyPart) : (injury.view === 'front' ? 'Front (Unspecified)' : 'Back (Unspecified)')) + '</small>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }
    serializeInjuriesToField();
    setupInjuryListDelegation();
}

function toggleInjuryCard(id) {
    var card = document.querySelector('.injury-card[data-injury-id="' + id + '"]');
    if (!card) return;
    var body = card.querySelector('.injury-card-body');
    var chevron = card.querySelector('.injury-card-chevron i');
    var header = card.querySelector('.injury-card-header');
    if (!body) return;
    var isOpen = body.style.display !== 'none';
    if (isOpen) {
        body.style.display = 'none';
        if (chevron) chevron.className = 'bi bi-chevron-down';
        if (header) header.classList.remove('expanded');
    } else {
        document.querySelectorAll('.injury-card-body').forEach(function(b) { b.style.display = 'none'; });
        document.querySelectorAll('.injury-card-chevron i').forEach(function(c) { c.className = 'bi bi-chevron-down'; });
        document.querySelectorAll('.injury-card-header').forEach(function(h) { h.classList.remove('expanded'); });
        body.style.display = 'block';
        if (chevron) chevron.className = 'bi bi-chevron-up';
        if (header) header.classList.add('expanded');
    }
}

function updateInjuryNotes(id, notes) {
    var injury = injuries.find(function(i) { return i.id === id; });
    if (injury) { injury.notes = notes; serializeInjuriesToField(); }
}

function updateInjurySeverity(id, severity) {
    var injury = injuries.find(function(i) { return i.id === id; });
    if (injury) {
        injury.severity = severity;
        var card = document.querySelector('.injury-card[data-injury-id="' + id + '"]');
        if (card) {
            var severityEl = card.querySelector('.injury-card-severity');
            var severitySelect = card.querySelector('.injury-severity-select');
            var severityLabels = { 'minor': 'Minor', 'moderate': 'Moderate', 'severe': 'Severe', 'critical': 'Critical' };
            if (severityEl) { severityEl.textContent = severityLabels[severity] || 'Moderate'; severityEl.className = 'injury-card-severity injury-severity-' + severity; }
            if (severitySelect) { severitySelect.className = 'injury-severity-select injury-severity-' + severity; }
        }
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
            injuries = injuries.filter(function(i) { return i.id !== id; });
            var marker = document.querySelector('.injury-marker[data-id="' + id + '"]');
            if (marker) marker.remove();
            updateInjuryList();
            Notiflix.Notify.success('Injury marker deleted');
        },
        function cancelCb() {}
    );
}

function clearAllInjuries() {
    if (injuries.length === 0) { Notiflix.Notify.info('No injury markers to clear'); return; }
    Notiflix.Confirm.show(
        'Clear All Injuries',
        'Are you sure you want to clear all ' + injuries.length + ' injury markers?',
        'Yes, Clear All',
        'Cancel',
        function okCb() {
            injuries = [];
            injuryCounter = 0;
            document.querySelectorAll('.injury-marker').forEach(function(m) { m.remove(); });
            updateInjuryList();
            Notiflix.Notify.success('All injury markers cleared');
        },
        function cancelCb() {}
    );
}

function repositionMarkers() {
    injuries.forEach(function(injury) {
        var marker = document.querySelector('.injury-marker[data-id="' + injury.id + '"]');
        if (marker) {
            var container = marker.parentElement;
            var image = container.querySelector('.body-image');
            var container_rect = container.getBoundingClientRect();
            var image_rect = image.getBoundingClientRect();
            marker.style.left = (image_rect.left - container_rect.left + (injury.x / 100) * image_rect.width) + 'px';
            marker.style.top = (image_rect.top - container_rect.top + (injury.y / 100) * image_rect.height) + 'px';
        }
    });
}

// ============================================
// INJURY LEGEND
// ============================================

function injectInjuryLegend() {
    if (document.getElementById('injuryLegend')) return;
    var legendContainer = document.querySelector('.injury-sidebar');
    if (!legendContainer) return;
    var legend = document.createElement('div');
    legend.id = 'injuryLegend';
    legend.className = 'injury-legend';
    legend.innerHTML = '<div class="legend-title">Marker Legend</div>' +
        '<div class="legend-items">' +
            '<span class="legend-item"><span class="legend-dot laceration"></span> Laceration</span>' +
            '<span class="legend-item"><span class="legend-dot fracture"></span> Fracture</span>' +
            '<span class="legend-item"><span class="legend-dot burn"></span> Burn</span>' +
            '<span class="legend-item"><span class="legend-dot contusion"></span> Contusion</span>' +
            '<span class="legend-item"><span class="legend-dot abrasion"></span> Abrasion</span>' +
            '<span class="legend-item"><span class="legend-dot other"></span> Other</span>' +
        '</div>';
    var injuryListContainer = document.getElementById('injuryListContainer');
    if (injuryListContainer) {
        injuryListContainer.parentNode.insertBefore(legend, injuryListContainer);
    } else {
        legendContainer.appendChild(legend);
    }
}

// ============================================
// MOBILE VIEW TOGGLE INJECTION (CSP-safe: addEventListener, not onclick)
// ============================================

function injectMobileViewToggle() {
    if (document.getElementById('mobileViewTabs')) return;
    // Always inject tabs — CSS controls visibility via media query
    var frontContainer = document.getElementById('frontContainer');
    var backContainer = document.getElementById('backContainer');
    if (!frontContainer || !backContainer) return;

    var tabsDiv = document.createElement('div');
    tabsDiv.id = 'mobileViewTabs';
    tabsDiv.className = 'mobile-view-tabs';

    var frontTab = document.createElement('button');
    frontTab.type = 'button';
    frontTab.id = 'mobileFrontTab';
    frontTab.className = 'mobile-view-tab active';
    frontTab.innerHTML = 'Front <span id="mobileFrontCount" class="view-count-badge" style="display:none;">0</span>';
    frontTab.addEventListener('click', function() { switchMobileView('front'); });

    var backTab = document.createElement('button');
    backTab.type = 'button';
    backTab.id = 'mobileBackTab';
    backTab.className = 'mobile-view-tab';
    backTab.innerHTML = 'Back <span id="mobileBackCount" class="view-count-badge" style="display:none;">0</span>';
    backTab.addEventListener('click', function() { switchMobileView('back'); });

    tabsDiv.appendChild(frontTab);
    tabsDiv.appendChild(backTab);

    var bodyViews = document.querySelector('.body-views');
    if (bodyViews) bodyViews.parentNode.insertBefore(tabsDiv, bodyViews);

    var frontView = document.querySelector('.body-view:first-child');
    var backView = document.querySelector('.body-view:last-child');
    if (frontView) frontView.setAttribute('data-view', 'front');
    if (backView) backView.setAttribute('data-view', 'back');
    switchMobileView('front');
}

function exportInjuryData() {
    if (injuries.length === 0) { Notiflix.Notify.warning('No injuries to export!'); return; }
    var data = {
        formTitle: 'Pre-Hospital Care - Injury Assessment',
        timestamp: new Date().toISOString(),
        totalInjuries: injuries.length,
        injuries: injuries.map(function(i) { return { injuryNumber: i.id, type: i.type, severity: i.severity, bodyPart: i.bodyPart || (i.view === 'front' ? 'Front (Unspecified)' : 'Back (Unspecified)'), view: i.view, coordinates: { x: Math.round(i.x), y: Math.round(i.y) }, notes: i.notes || 'No notes provided' }; })
    };
    var dataStr = JSON.stringify(data, null, 2);
    var dataBlob = new Blob([dataStr], { type: 'application/json' });
    var url = URL.createObjectURL(dataBlob);
    var link = document.createElement('a');
    link.href = url;
    link.download = 'injury-assessment-' + Date.now() + '.json';
    link.click();
    Notiflix.Notify.success('Exported ' + injuries.length + ' injury markers!');
}

// ============================================
// LOAD EXISTING INJURIES (EDIT MODE)
// ============================================

function loadExistingInjuries() {
    var injuriesDataField = document.getElementById('injuriesData');
    if (!injuriesDataField || !injuriesDataField.value) return;
    try {
        var existingInjuries = JSON.parse(injuriesDataField.value);
        if (!Array.isArray(existingInjuries) || existingInjuries.length === 0) return;

        var renderInjuries = function() {
            injuries = [];
            injuryCounter = 0;
            existingInjuries.forEach(function(dbInjury) {
                var injury = {
                    id: dbInjury.injury_number || dbInjury.id || ++injuryCounter,
                    type: dbInjury.injury_type || dbInjury.type || 'other',
                    severity: dbInjury.severity || 'moderate',
                    x: parseFloat(dbInjury.coordinate_x || dbInjury.x || 0),
                    y: parseFloat(dbInjury.coordinate_y || dbInjury.y || 0),
                    view: dbInjury.body_view || dbInjury.view || 'front',
                    bodyPart: dbInjury.body_part || dbInjury.bodyPart || '',
                    notes: dbInjury.notes || ''
                };
                if (injury.id >= injuryCounter) injuryCounter = injury.id + 1;
                injuries.push(injury);
                renderExistingMarker(injury);
            });
            updateInjuryList();
            if (injuriesDataField) injuriesDataField.value = JSON.stringify(injuries);
        };

        var waitForImagesAndRender = function() {
            var frontImg = document.querySelector('#frontContainer .body-image');
            var backImg = document.querySelector('#backContainer .body-image');
            var images = [frontImg, backImg].filter(Boolean);
            if (images.length === 0) { console.error('Body diagram images not found'); return; }
            var allLoaded = images.every(function(img) { return img.complete && img.naturalHeight > 0; });
            if (allLoaded) {
                requestAnimationFrame(function() { requestAnimationFrame(renderInjuries); });
            } else {
                var toWait = images.filter(function(img) { return !img.complete || img.naturalHeight === 0; });
                var loadedCount = 0;
                var onReady = function() { loadedCount++; if (loadedCount >= toWait.length) requestAnimationFrame(function() { requestAnimationFrame(renderInjuries); }); };
                toWait.forEach(function(img) { img.addEventListener('load', onReady, { once: true }); });
                setTimeout(function() { if (injuries.length === 0) renderInjuries(); }, 2000);
            }
        };

        if (document.readyState === 'complete') waitForImagesAndRender();
        else window.addEventListener('load', waitForImagesAndRender);
    } catch (error) { console.error('Error loading existing injuries:', error); }
}

function renderExistingMarker(injury) {
    var container = document.getElementById(injury.view === 'front' ? 'frontContainer' : 'backContainer');
    if (!container) return;
    var img = container.querySelector('.body-image');
    if (!img) return;
    var container_rect = container.getBoundingClientRect();
    var image_rect = img.getBoundingClientRect();
    var abbreviations = { 'laceration': 'LC', 'fracture': 'FX', 'burn': 'BN', 'contusion': 'CT', 'abrasion': 'AB', 'other': 'OT' };
    var marker = document.createElement('div');
    marker.className = 'injury-marker ' + injury.type;
    marker.style.left = (image_rect.left - container_rect.left + (injury.x / 100) * image_rect.width) + 'px';
    marker.style.top = (image_rect.top - container_rect.top + (injury.y / 100) * image_rect.height) + 'px';
    marker.textContent = abbreviations[injury.type] || 'OT';
    marker.dataset.id = injury.id;
    marker.title = 'Injury #' + injury.id + ' - ' + injury.type + ' - ' + (injury.bodyPart || injury.view);
    container.appendChild(marker);
}

function serializeInjuriesToField() {
    var field = document.getElementById('injuriesData');
    if (field) field.value = JSON.stringify(injuries);
}