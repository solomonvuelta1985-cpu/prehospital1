/**
 * User Dashboard Charts
 * Reads chart data from #dashboard-chart-data element's data attributes
 * and initializes Chart.js charts.
 * NOTE: This script must be loaded AFTER Chart.js and at the bottom of <body>.
 */
(function() {
    'use strict';

    var el = document.getElementById('dashboard-chart-data');
    if (!el) {
        console.warn('dashboard-charts.js: #dashboard-chart-data element not found');
        return;
    }

    if (typeof Chart === 'undefined') {
        console.error('dashboard-charts.js: Chart.js is not loaded');
        return;
    }

    try {
        var weeklyLabels = JSON.parse(el.getAttribute('data-weekly-labels') || '[]');
        var weeklyData = JSON.parse(el.getAttribute('data-weekly-data') || '[]');
        var monthlyLabels = JSON.parse(el.getAttribute('data-monthly-labels') || '[]');
        var monthlyData = JSON.parse(el.getAttribute('data-monthly-data') || '[]');
        var completed = parseInt(el.getAttribute('data-completed') || '0', 10);
        var drafts = parseInt(el.getAttribute('data-drafts') || '0', 10);
        var archived = parseInt(el.getAttribute('data-archived') || '0', 10);
    } catch (e) {
        console.error('dashboard-charts.js: Failed to parse chart data', e);
        return;
    }

    // Global Chart.js config
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", system-ui, sans-serif';
    Chart.defaults.color = '#71717a';
    Chart.defaults.font.size = 11;

    // Weekly Activity Chart (Bar)
    var weeklyCanvas = document.getElementById('weeklyChart');
    if (weeklyCanvas) {
        try {
            new Chart(weeklyCanvas, {
                type: 'bar',
                data: {
                    labels: weeklyLabels,
                    datasets: [{
                        label: 'Forms Created',
                        data: weeklyData,
                        backgroundColor: '#2563eb',
                        borderWidth: 0,
                        borderRadius: 4,
                        barThickness: 32
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#18181b',
                            padding: 10,
                            borderColor: '#27272a',
                            borderWidth: 1,
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
                            grid: { color: '#f4f4f5' },
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
            console.error('dashboard-charts.js: weeklyChart error', e);
        }
    }

    // Status Distribution Chart (Doughnut)
    var statusCanvas = document.getElementById('statusChart');
    if (statusCanvas) {
        try {
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Draft', 'Archived'],
                    datasets: [{
                        data: [completed, drafts, archived],
                        backgroundColor: ['#16a34a', '#ca8a04', '#a1a1aa'],
                        borderWidth: 0,
                        spacing: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
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
                            backgroundColor: '#18181b',
                            padding: 10,
                            cornerRadius: 4,
                            callbacks: {
                                label: function(ctx) {
                                    var value = ctx.parsed || 0;
                                    var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                    var pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return ctx.label + ': ' + value + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('dashboard-charts.js: statusChart error', e);
        }
    }

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
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.04)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#18181b',
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
                            grid: { color: '#f4f4f5' },
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
            console.error('dashboard-charts.js: monthlyChart error', e);
        }
    }
})();
