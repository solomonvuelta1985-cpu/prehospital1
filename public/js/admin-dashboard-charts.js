/**
 * Admin Dashboard Charts
 * Reads chart data from #admin-chart-data element's data attributes
 * and initializes Chart.js charts.
 * NOTE: This script must be loaded AFTER Chart.js and at the bottom of <body>.
 */
(function() {
    'use strict';

    var el = document.getElementById('admin-chart-data');
    if (!el) {
        console.warn('admin-dashboard-charts.js: #admin-chart-data element not found');
        return;
    }

    if (typeof Chart === 'undefined') {
        console.error('admin-dashboard-charts.js: Chart.js is not loaded');
        return;
    }

    // Parse all data attributes
    try {
        var weeklyLabels = JSON.parse(el.getAttribute('data-weekly-labels') || '[]');
        var weeklyData = JSON.parse(el.getAttribute('data-weekly-data') || '[]');
        var monthlyLabels = JSON.parse(el.getAttribute('data-monthly-labels') || '[]');
        var monthlyData = JSON.parse(el.getAttribute('data-monthly-data') || '[]');
        var completed = parseInt(el.getAttribute('data-completed') || '0', 10);
        var drafts = parseInt(el.getAttribute('data-drafts') || '0', 10);
        var archived = parseInt(el.getAttribute('data-archived') || '0', 10);
        var emergencyMedical = parseInt(el.getAttribute('data-emergency-medical') || '0', 10);
        var emergencyTrauma = parseInt(el.getAttribute('data-emergency-trauma') || '0', 10);
        var emergencyOb = parseInt(el.getAttribute('data-emergency-ob') || '0', 10);
        var emergencyGeneral = parseInt(el.getAttribute('data-emergency-general') || '0', 10);
        var hospitalLabels = JSON.parse(el.getAttribute('data-hospital-labels') || '[]');
        var hospitalData = JSON.parse(el.getAttribute('data-hospital-data') || '[]');
        var hospitalColors = JSON.parse(el.getAttribute('data-hospital-colors') || '[]');
        var zoneLabels = JSON.parse(el.getAttribute('data-zone-labels') || '[]');
        var zoneData = JSON.parse(el.getAttribute('data-zone-data') || '[]');
        var typeLabels = JSON.parse(el.getAttribute('data-type-labels') || '[]');
        var typeData = JSON.parse(el.getAttribute('data-type-data') || '[]');
        var typeColors = JSON.parse(el.getAttribute('data-type-colors') || '[]');
    } catch (e) {
        console.error('admin-dashboard-charts.js: Failed to parse chart data', e);
        return;
    }

    // Global Chart.js config
    Chart.defaults.font.family = '"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif';
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.size = 11;
    Chart.defaults.plugins.tooltip.padding = 10;

    // Gradient helper for canvas
    function createGradient(ctx, color1, color2) {
        var gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, color1);
        gradient.addColorStop(1, color2);
        return gradient;
    }

    // Shared tooltip config for bar/line charts
    function barLineTooltip() {
        return {
            backgroundColor: '#18181b',
            padding: 10,
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 11, weight: '500' },
            borderColor: '#27272a',
            borderWidth: 1,
            cornerRadius: 6,
            displayColors: false,
            callbacks: {
                label: function(ctx) {
                    var val = ctx.parsed.y !== undefined ? ctx.parsed.y : ctx.raw;
                    return val + ' form' + (val !== 1 ? 's' : '');
                }
            }
        };
    }

    // Shared doughnut tooltip
    function doughnutTooltip() {
        return {
            backgroundColor: '#18181b',
            padding: 10,
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 11, weight: '500' },
            borderColor: '#27272a',
            borderWidth: 1,
            cornerRadius: 6,
            callbacks: {
                label: function(ctx) {
                    var value = ctx.parsed || 0;
                    var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                    var pct = total > 0 ? Math.round((value / total) * 100) : 0;
                    return ' ' + ctx.label + ': ' + value + ' (' + pct + '%)';
                }
            }
        };
    }

    // Shared doughnut legend
    function doughnutLegend() {
        return {
            position: 'bottom',
            labels: {
                padding: 14,
                color: '#475569',
                font: { size: 11, weight: '500' },
                usePointStyle: true,
                pointStyle: 'circle',
                pointStyleWidth: 8
            }
        };
    }

    // ===== 1. WEEKLY ACTIVITY BAR CHART =====
    var weeklyCanvas = document.getElementById('weeklyChart');
    if (weeklyCanvas) {
        try {
            var weeklyCtx = weeklyCanvas.getContext('2d');
            var weeklyGradient = createGradient(weeklyCtx, 'rgba(79, 70, 229, 0.85)', 'rgba(99, 102, 241, 0.95)');
            new Chart(weeklyCanvas, {
                type: 'bar',
                data: {
                    labels: weeklyLabels,
                    datasets: [{
                        label: 'Forms Created',
                        data: weeklyData,
                        backgroundColor: weeklyGradient,
                        hoverBackgroundColor: '#4338ca',
                        borderWidth: 0,
                        borderRadius: 8,
                        barThickness: 36
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: barLineTooltip()
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: { size: 11 },
                                color: '#94a3b8',
                                callback: function(val) { return val % 1 === 0 ? val : ''; }
                            },
                            grid: { color: '#f1f5f9', drawBorder: false },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { font: { size: 11, weight: '500' }, color: '#64748b' }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('admin-dashboard-charts.js: weeklyChart error', e);
        }
    }

    // ===== 2. FORM STATUS DOUGHNUT CHART =====
    var statusCanvas = document.getElementById('statusChart');
    if (statusCanvas) {
        try {
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Draft', 'Archived'],
                    datasets: [{
                        data: [completed, drafts, archived],
                        backgroundColor: ['#059669', '#d97706', '#94a3b8'],
                        hoverBackgroundColor: ['#047857', '#b45309', '#64748b'],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        spacing: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: doughnutLegend(),
                        tooltip: doughnutTooltip()
                    }
                },
                plugins: [{
                    id: 'centerText',
                    afterDraw: function(chart) {
                        var width = chart.width,
                            height = chart.height,
                            ctx = chart.ctx;
                        ctx.restore();
                        var fontSize = (height / 180).toFixed(2);
                        ctx.font = '800 ' + (fontSize * 2.2) + 'em "Inter", sans-serif';
                        ctx.fillStyle = '#0f172a';
                        ctx.textBaseline = 'middle';
                        var total = completed + drafts + archived;
                        var text = total.toString();
                        var textX = Math.round((width - ctx.measureText(text).width) / 2);
                        ctx.fillText(text, textX, height / 2 - 8);
                        ctx.font = '500 ' + fontSize + 'em "Inter", sans-serif';
                        ctx.fillStyle = '#94a3b8';
                        var subText = 'TOTAL';
                        var subX = Math.round((width - ctx.measureText(subText).width) / 2);
                        ctx.fillText(subText, subX, height / 2 + 14);
                        ctx.save();
                    }
                }]
            });
        } catch (e) {
            console.error('admin-dashboard-charts.js: statusChart error', e);
        }
    }

    // ===== 3. MONTHLY PERFORMANCE LINE CHART =====
    var monthlyCanvas = document.getElementById('monthlyChart');
    if (monthlyCanvas) {
        try {
            var monthlyCtx = monthlyCanvas.getContext('2d');
            var monthlyGradient = monthlyCtx.createLinearGradient(0, 0, 0, 300);
            monthlyGradient.addColorStop(0, 'rgba(79, 70, 229, 0.12)');
            monthlyGradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

            new Chart(monthlyCanvas, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Forms Created',
                        data: monthlyData,
                        borderColor: '#4f46e5',
                        backgroundColor: monthlyGradient,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4f46e5',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointHoverBackgroundColor: '#4338ca',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: barLineTooltip()
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: { size: 11 },
                                color: '#94a3b8',
                                callback: function(val) { return val % 1 === 0 ? val : ''; }
                            },
                            grid: { color: '#f1f5f9', drawBorder: false },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                font: { size: 10, weight: '500' },
                                color: '#64748b',
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('admin-dashboard-charts.js: monthlyChart error', e);
        }
    }

    // ===== 4. RECORDS BY TYPE DOUGHNUT CHART (incl. Vehicular Accident) =====
    var emergencyCanvas = document.getElementById('emergencyChart');
    if (emergencyCanvas) {
        try {
            // Prefer the generic type arrays (which include the VA slice); fall back to legacy fields.
            var tLabels = typeLabels.length ? typeLabels : ['Medical', 'Trauma', 'OB', 'General'];
            var tData = typeData.length ? typeData : [emergencyMedical, emergencyTrauma, emergencyOb, emergencyGeneral];
            var tColors = typeColors.length ? typeColors : ['#4f46e5', '#dc2626', '#7c3aed', '#d97706'];
            new Chart(emergencyCanvas, {
                type: 'doughnut',
                data: {
                    labels: tLabels,
                    datasets: [{
                        data: tData,
                        backgroundColor: tColors,
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        spacing: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: doughnutLegend(),
                        tooltip: doughnutTooltip()
                    }
                }
            });
        } catch (e) {
            console.error('admin-dashboard-charts.js: emergencyChart error', e);
        }
    }

    // ===== 5. TOP HOSPITALS HORIZONTAL BAR CHART =====
    var hospitalsCanvas = document.getElementById('hospitalsChart');
    if (hospitalsCanvas && hospitalData.length > 0) {
        try {
            new Chart(hospitalsCanvas, {
                type: 'bar',
                data: {
                    labels: hospitalLabels,
                    datasets: [{
                        label: 'Forms',
                        data: hospitalData,
                        backgroundColor: hospitalColors,
                        borderWidth: 0,
                        borderRadius: 6,
                        barThickness: 20
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#18181b',
                            padding: 10,
                            titleFont: { size: 12, weight: '600' },
                            bodyFont: { size: 11, weight: '500' },
                            borderColor: '#27272a',
                            borderWidth: 1,
                            cornerRadius: 6,
                            displayColors: false,
                            callbacks: {
                                label: function(ctx) {
                                    var val = ctx.parsed.x;
                                    return val + ' form' + (val !== 1 ? 's' : '');
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: { size: 11 },
                                color: '#94a3b8',
                                callback: function(val) { return val % 1 === 0 ? val : ''; }
                            },
                            grid: { color: '#f1f5f9', drawBorder: false },
                            border: { display: false }
                        },
                        y: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                font: { size: 11, weight: '500' },
                                color: '#475569',
                                padding: 8
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('admin-dashboard-charts.js: hospitalsChart error', e);
        }
    }

    // ===== 6. RECORDS BY ZONE / BARANGAY HORIZONTAL BAR CHART =====
    var zoneCanvas = document.getElementById('zoneChart');
    if (zoneCanvas && zoneData.length > 0) {
        try {
            var zonePalette = ['#4f46e5', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe', '#e0e7ff'];
            var zoneColors = zoneData.map(function(_, i) { return zonePalette[i % zonePalette.length]; });
            new Chart(zoneCanvas, {
                type: 'bar',
                data: {
                    labels: zoneLabels,
                    datasets: [{
                        label: 'Records',
                        data: zoneData,
                        backgroundColor: zoneColors,
                        borderWidth: 0,
                        borderRadius: 6,
                        barThickness: 20
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#18181b',
                            padding: 10,
                            titleFont: { size: 12, weight: '600' },
                            bodyFont: { size: 11, weight: '500' },
                            borderColor: '#27272a',
                            borderWidth: 1,
                            cornerRadius: 6,
                            displayColors: false,
                            callbacks: {
                                label: function(ctx) {
                                    var val = ctx.parsed.x;
                                    return val + ' record' + (val !== 1 ? 's' : '');
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                font: { size: 11 },
                                color: '#94a3b8',
                                callback: function(val) { return val % 1 === 0 ? val : ''; }
                            },
                            grid: { color: '#f1f5f9', drawBorder: false },
                            border: { display: false }
                        },
                        y: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                font: { size: 11, weight: '500' },
                                color: '#475569',
                                padding: 8
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('admin-dashboard-charts.js: zoneChart error', e);
        }
    }

})();