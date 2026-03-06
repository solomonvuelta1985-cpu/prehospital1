// ============================================
// UTILITY FUNCTIONS
// Shared helpers used across all modules
// ============================================

// XSS Prevention: Escape HTML entities in user input before inserting into DOM
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

// Safe helper to get escaped form field value
function safeVal(id, fallback) {
    const el = document.getElementById(id);
    return escapeHtml(el ? el.value : '') || (fallback || 'Not specified');
}

// Mobile device detection for fullscreen camera behavior
function isMobileDevice() {
    return window.innerWidth <= 768;
}

// Format date/time for GPS overlay display
function formatGPSDateTime(date) {
    var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    var dayName = days[date.getDay()];

    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    var year = date.getFullYear();

    var hours = date.getHours();
    var minutes = String(date.getMinutes()).padStart(2, '0');
    var ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    if (hours === 0) hours = 12;
    var hoursStr = String(hours).padStart(2, '0');

    // Get timezone offset
    var offset = -date.getTimezoneOffset();
    var offsetSign = offset >= 0 ? '+' : '-';
    var offsetHours = String(Math.floor(Math.abs(offset) / 60)).padStart(2, '0');
    var offsetMins = String(Math.abs(offset) % 60).padStart(2, '0');
    var tz = 'GMT' + offsetSign + offsetHours + ':' + offsetMins;

    return dayName + ', ' + month + '/' + day + '/' + year + ' ' + hoursStr + ':' + minutes + ' ' + ampm + ' ' + tz;
}

// Generate random plate number for ambulance list
function generatePlateNumber() {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const numbers = '0123456789';

    let plate = '';
    for (let i = 0; i < 3; i++) {
        plate += letters.charAt(Math.floor(Math.random() * letters.length));
    }
    for (let i = 0; i < 4; i++) {
        plate += numbers.charAt(Math.floor(Math.random() * numbers.length));
    }

    return plate;
}
