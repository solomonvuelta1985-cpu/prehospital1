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

    try {
        var monthlyLabels = JSON.parse(el.getAttribute('data-monthly-labels') || '[]');
        var monthlyData = JSON.parse(el.getAttribute('data-monthly-data') || '[]');
        var emergencyLabels = JSON.parse(el.getAttribute('data-emergency-labels') || '[]');
        var emergencyData = JSON.parse(el.getAttribute('data-emergency-data') || '[]');
        var dailyLabels = JSON.parse(el.getAttribute('data-daily-labels') || '[]');
        var dailyData = JSON.parse(el.getAttribute('data-daily-data') || '[]');
    } catch (e) {
        console.error('admin-dashboard-charts.js: Failed to parse chart data', e);
        return;
    }

    // Global Chart.js config
    Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
    Chart.defaults.color = '#6c757d';
    Chart.defaults.font.size = 11;

    // Monthly Performance Chart (Line)
    var monthlyCanvas = document.getElementById('monthlyChart');
    if (monthlyCanvas) {
        try {
            new Chart(monthlyCanvas, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Forms Created',
                        data: monthlyData,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#0d6efd',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#212529',
                            padding: 10,
                            cornerRadius: 4,
                            displayColors: false,
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.parsed.y + ' form' + (ctx.parsed.y !== 1 ? 's' : '');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 },
                            grid: { color: '#f0f0f0' },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { maxRotation: 45, minRotation: 45, font: { size: 10 } }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('admin-dashboard-charts.js: monthlyChart error', e);
        }
    }

    // Emergency Types Chart (Doughnut)
    var emergencyCanvas = document.getElementById('emergencyChart');
    if (emergencyCanvas) {
        try {
            new Chart(emergencyCanvas, {
                type: 'doughnut',
                data: {
                    labels: emergencyLabels,
                    datasets: [{
                        data: emergencyData,
                        backgroundColor: ['#0d6efd', '#dc3545', '#fd7e14', '#198754'],
                        borderWidth: 0,
                        spacing: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 12,
                                font: { size: 11, weight: '500' },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#212529',
                            padding: 10,
                            cornerRadius: 4,
                            callbacks: {
                                label: function(ctx) {
                                    var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                                    return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('admin-dashboard-charts.js: emergencyChart error', e);
        }
    }

    // Daily Activity Chart (Bar)
    var dailyCanvas = document.getElementById('dailyChart');
    if (dailyCanvas) {
        try {
            new Chart(dailyCanvas, {
                type: 'bar',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: 'Forms Created',
                        data: dailyData,
                        backgroundColor: '#0d6efd',
                        borderWidth: 0,
                        borderRadius: 4,
                        barThickness: 36
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#212529',
                            padding: 10,
                            cornerRadius: 4,
                            displayColors: false,
                            callbacks: {
                                label: function(ctx) {
                                    return ctx.parsed.y + ' form' + (ctx.parsed.y !== 1 ? 's' : '');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 },
                            grid: { color: '#f0f0f0' },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false },
                            border: { display: false }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('admin-dashboard-charts.js: dailyChart error', e);
        }
    }
})();
