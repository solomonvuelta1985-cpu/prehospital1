/**
 * Material Design Time Picker Modal
 * Modal/Dialog-based interface for 12-hour time selection.
 * Attaches to all input[data-time-field="true"] elements.
 *
 * Features:
 * - Modal overlay with click-to-close backdrop
 * - Hour and minute text input fields
 * - AM/PM segmented toggle (pill-style)
 * - Large typography for readability
 * - Material Design minimal appearance
 * - CANCEL / OK action buttons
 * - Keyboard/input mode with validation
 * - Works with existing autosave (dispatches 'change' event)
 */

(function () {
    'use strict';

    /** Currently active input that triggered the picker */
    let activeInput = null;
    /** Original value before editing (for cancel) */
    let originalValue = '';
    /** Current hour value (1-12) */
    let currentHour = '';
    /** Current minute value (00-59) */
    let currentMinute = '';
    /** Current period (AM/PM) */
    let currentPeriod = 'AM';

    /** Cached modal elements (populated on first open) */
    let modal = null;
    let backdrop = null;
    let hourInput = null;
    let minuteInput = null;
    let amBtn = null;
    let pmBtn = null;
    let cancelBtn = null;
    let okBtn = null;

    // ──────────────────────────────────────
    //  BUILD & INJECT MODAL ONCE
    // ──────────────────────────────────────

    function ensureModalExists() {
        if (modal) return;

        const html = `
        <div class="time-picker-modal" id="timePickerModal" role="dialog" aria-modal="true" aria-label="Time Picker">
            <div class="time-picker-backdrop" data-action="timePickerClose"></div>
            <div class="time-picker-dialog">
                <div class="time-picker-header">
                    <span class="time-picker-title">Select Time</span>
                </div>

                <div class="time-picker-body">
                    <!-- Time display area -->
                    <div class="time-picker-display">
                        <div class="time-picker-col">
                            <input
                                type="text"
                                class="time-picker-digit"
                                id="timePickerHour"
                                placeholder="HH"
                                maxlength="2"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                autocomplete="off"
                            >
                            <span class="time-picker-label">Hour</span>
                        </div>
                        <span class="time-picker-separator">:</span>
                        <div class="time-picker-col">
                            <input
                                type="text"
                                class="time-picker-digit"
                                id="timePickerMinute"
                                placeholder="MM"
                                maxlength="2"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                autocomplete="off"
                            >
                            <span class="time-picker-label">Minute</span>
                        </div>
                        <div class="time-picker-col time-picker-period-col">
                            <div class="time-picker-period-toggle" id="timePickerPeriodToggle">
                                <button type="button" class="time-picker-period-btn active" data-period="AM">AM</button>
                                <button type="button" class="time-picker-period-btn" data-period="PM">PM</button>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="time-picker-actions">
                    <button type="button" class="time-picker-btn time-picker-btn-cancel" id="timePickerCancel">CANCEL</button>
                    <button type="button" class="time-picker-btn time-picker-btn-ok" id="timePickerOk">OK</button>
                </div>
            </div>
        </div>`;

        const container = document.createElement('div');
        container.innerHTML = html;
        document.body.appendChild(container.firstElementChild);

        // Cache references
        modal = document.getElementById('timePickerModal');
        backdrop = modal.querySelector('.time-picker-backdrop');
        hourInput = document.getElementById('timePickerHour');
        minuteInput = document.getElementById('timePickerMinute');
        amBtn = modal.querySelector('.time-picker-period-btn[data-period="AM"]');
        pmBtn = modal.querySelector('.time-picker-period-btn[data-period="PM"]');
        cancelBtn = document.getElementById('timePickerCancel');
        okBtn = document.getElementById('timePickerOk');

        bindEvents();
    }

    // ──────────────────────────────────────
    //  EVENT BINDING
    // ──────────────────────────────────────

    function bindEvents() {
        // Close on backdrop click
        backdrop.addEventListener('click', function () {
            cancel();
        });

        // CANCEL button
        cancelBtn.addEventListener('click', function () {
            cancel();
        });

        // OK button
        okBtn.addEventListener('click', function () {
            confirm();
        });

        // Period toggle
        amBtn.addEventListener('click', function () {
            setPeriod('AM');
        });
        pmBtn.addEventListener('click', function () {
            setPeriod('PM');
        });

        // Hour input: auto-advance to minute after 2 digits, validate on blur
        hourInput.addEventListener('input', function () {
            sanitizeHourInput();
            if (hourInput.value.length === 2) {
                minuteInput.focus();
            }
        });
        hourInput.addEventListener('blur', function () {
            validateHour();
        });
        hourInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirm();
            }
        });

        // Minute input: validate on blur
        minuteInput.addEventListener('input', function () {
            sanitizeMinuteInput();
        });
        minuteInput.addEventListener('blur', function () {
            validateMinute();
        });
        minuteInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirm();
            }
        });

        // Escape key closes
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
                cancel();
            }
        });

    }

    // ──────────────────────────────────────
    //  OPEN / CLOSE / CONFIRM / CANCEL
    // ──────────────────────────────────────

    /**
     * Open the time picker for a given input element.
     */
    function open(input) {
        ensureModalExists();

        activeInput = input;
        originalValue = input.value || '';

        // Parse existing value
        parseValue(originalValue);

        // Populate fields
        hourInput.value = currentHour;
        minuteInput.value = currentMinute;
        setPeriodUI(currentPeriod);

        // Show modal
        modal.classList.add('active');
        document.body.classList.add('time-picker-open');

        // Focus hour input after transition
        setTimeout(function () {
            hourInput.focus();
            hourInput.select();
        }, 150);
    }

    function close() {
        if (!modal) return;
        modal.classList.remove('active');
        document.body.classList.remove('time-picker-open');
        activeInput = null;
    }

    function cancel() {
        // Restore original value
        if (activeInput) {
            activeInput.value = originalValue;
        }
        close();
    }

    function confirm() {
        // Validate before confirming
        validateHour();
        validateMinute();

        const h = hourInput.value.trim();
        const m = minuteInput.value.trim();

        if (!h || !m) {
            // Highlight empty fields
            if (!h) hourInput.classList.add('error');
            if (!m) minuteInput.classList.add('error');
            return;
        }

        const formatted = formatTime(h, m, currentPeriod);

        if (activeInput) {
            activeInput.value = formatted;
            // Dispatch change for autosave compatibility
            activeInput.dispatchEvent(new Event('change', { bubbles: true }));
            // Also dispatch input for any listeners
            activeInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        close();
    }

    // ──────────────────────────────────────
    //  PARSING & FORMATTING
    // ──────────────────────────────────────

    function parseValue(value) {
        currentHour = '';
        currentMinute = '';
        currentPeriod = 'AM';

        if (!value || typeof value !== 'string') return;

        // Match patterns: "2:45 PM", "02:45 PM", "14:45", "2:45PM", etc.
        const match = value.trim().match(/^(\d{1,2}):(\d{2})\s?(AM|PM|am|pm)?$/i);
        if (match) {
            let hour = parseInt(match[1], 10);
            const minute = match[2];
            const period = match[3] ? match[3].toUpperCase() : null;

            if (period) {
                // 12-hour format provided
                currentHour = String(hour);
                currentMinute = minute;
                currentPeriod = period;
            } else {
                // 24-hour format — convert to 12-hour
                if (hour === 0) {
                    currentHour = '12';
                    currentPeriod = 'AM';
                } else if (hour < 12) {
                    currentHour = String(hour);
                    currentPeriod = 'AM';
                } else if (hour === 12) {
                    currentHour = '12';
                    currentPeriod = 'PM';
                } else {
                    currentHour = String(hour - 12);
                    currentPeriod = 'PM';
                }
                currentMinute = minute;
            }
        }
    }

    function formatTime(hour, minute, period) {
        const h = String(parseInt(hour, 10)).padStart(1); // No zero-padding for display
        const m = String(minute).padStart(2, '0');
        return h + ':' + m + ' ' + period;
    }

    // ──────────────────────────────────────
    //  PERIOD TOGGLE
    // ──────────────────────────────────────

    function setPeriod(period) {
        currentPeriod = period;
        setPeriodUI(period);
    }

    function setPeriodUI(period) {
        if (amBtn) amBtn.classList.toggle('active', period === 'AM');
        if (pmBtn) pmBtn.classList.toggle('active', period === 'PM');
    }

    // ──────────────────────────────────────
    //  VALIDATION & SANITIZATION
    // ──────────────────────────────────────

    function sanitizeHourInput() {
        // Remove non-digits
        hourInput.value = hourInput.value.replace(/[^0-9]/g, '');
    }

    function sanitizeMinuteInput() {
        // Remove non-digits
        minuteInput.value = minuteInput.value.replace(/[^0-9]/g, '');
    }

    function validateHour() {
        sanitizeHourInput();
        let val = parseInt(hourInput.value, 10);

        if (isNaN(val) || val < 1) {
            hourInput.classList.add('error');
        } else if (val > 12) {
            // If they type 13+, auto-cap at 12
            hourInput.value = '12';
            hourInput.classList.remove('error');
        } else {
            hourInput.classList.remove('error');
        }
    }

    function validateMinute() {
        sanitizeMinuteInput();
        let val = parseInt(minuteInput.value, 10);

        if (isNaN(val)) {
            minuteInput.classList.add('error');
        } else if (val > 59) {
            minuteInput.value = '59';
            minuteInput.classList.remove('error');
        } else {
            // Pad to 2 digits
            if (minuteInput.value.length === 1) {
                minuteInput.value = '0' + minuteInput.value;
            }
            minuteInput.classList.remove('error');
        }
    }

    // ──────────────────────────────────────
    //  INIT — ATTACH TO ALL TIME INPUTS
    // ──────────────────────────────────────

    function attachToTimeInputs() {
        const timeInputs = document.querySelectorAll('input[data-time-field="true"], .time-input-12hr');

        timeInputs.forEach(function (input) {
            // Prevent double-binding
            if (input._timePickerBound) return;
            input._timePickerBound = true;

            // Open picker on focus or click
            input.addEventListener('focus', function (e) {
                open(input);
                // Prevent native keyboard if we have a modal
                input.blur();
            });

            input.addEventListener('click', function (e) {
                open(input);
            });

            // Make the input readonly to prevent direct typing
            // (all editing happens in the modal)
            input.setAttribute('readonly', 'readonly');
            input.style.cursor = 'pointer';
        });

        console.log('Time Picker Modal: attached to ' + timeInputs.length + ' time input(s)');
    }

    // Run on load + observe for dynamically added inputs
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachToTimeInputs);
    } else {
        // DOM already loaded
        if (document.body) {
            attachToTimeInputs();
        } else {
            document.addEventListener('DOMContentLoaded', attachToTimeInputs);
        }
    }

    // Also watch for dynamically added fields (e.g., draft resume populating fields)
    // Re-scan when any form is submitted or draft is loaded
    const observer = new MutationObserver(function (mutations) {
        let shouldRescan = false;
        mutations.forEach(function (mutation) {
            if (mutation.type === 'childList' || mutation.type === 'attributes') {
                shouldRescan = true;
            }
        });
        if (shouldRescan) {
            attachToTimeInputs();
        }
    });

    // Start observing once body is available
    function startObserving() {
        if (document.body) {
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['value', 'data-time-field']
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startObserving);
    } else {
        startObserving();
    }

})();