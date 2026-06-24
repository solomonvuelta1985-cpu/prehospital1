/**
 * Reports & Analytics — Charts & Interactivity
 * All Chart.js initialization, filtering, export, and drill-down logic
 * CSP-compliant: no inline event handlers
 * Part of RESQ-link Pre-Hospital Care System
 */
(function () {
    'use strict';

    // ===== COLOR PALETTE (Indigo Design System) =====
    const COLORS = {
        indigo: '#4f46e5',
        indigoLight: '#818cf8',
        indigoBg: '#eef2ff',
        emerald: '#059669',
        emeraldLight: '#34d399',
        emeraldBg: '#ecfdf5',
        amber: '#d97706',
        amberLight: '#fbbf24',
        amberBg: '#fffbeb',
        purple: '#7c3aed',
        purpleLight: '#a78bfa',
        purpleBg: '#f5f3ff',
        teal: '#0d9488',
        tealLight: '#2dd4bf',
        tealBg: '#f0fdfa',
        rose: '#e11d48',
        roseLight: '#fb7185',
        roseBg: '#fff1f2',
        slate: '#64748b',
        gray100: '#f1f5f9',
        gray200: '#e5e7eb',
        gray700: '#334155',
    };

    // ===== CHART.JS DEFAULTS =====
    if (typeof Chart !== 'undefined') {
        Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif";
        Chart.defaults.font.size = 11;
        Chart.defaults.color = '#64748b';
        Chart.defaults.plugins.tooltip.backgroundColor = '#1e293b';
        Chart.defaults.plugins.tooltip.padding = 12;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.tooltip.titleFont = { size: 12, weight: '600' };
        Chart.defaults.plugins.tooltip.bodyFont = { size: 11, weight: '400' };
    }

    // ===== SHARED CHART OPTIONS =====
    function getSharedOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
            },
        };
    }

    function getGridScales() {
        return {
            y: {
                beginAtZero: true,
                ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' },
                grid: { color: '#f1f5f9', drawBorder: false },
                border: { display: false },
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 10 }, color: '#94a3b8' },
                border: { display: false },
            },
        };
    }

    // ===== UTILITY: Format Complaint Labels =====
    function formatComplaintLabel(key) {
        const map = {
            chestPain: 'Chest Pain',
            headache: 'Headache',
            blurredVision: 'Blurred Vision',
            difficultyBreathing: 'Difficulty Breathing',
            dizziness: 'Dizziness',
            bodyMalaise: 'Body Malaise',
        };
        return map[key] || key.replace(/([A-Z])/g, ' $1').replace(/^./, s => s.toUpperCase()).trim();
    }

    // ===== 1. MAIN TREND CHART (Line — Multi-Series) =====
    function initTrendChart() {
        const canvas = document.getElementById('rptTrendChart');
        if (!canvas) return;
        const rawData = window.__rptTrendData || [];
        const prevData = window.__rptPrevTrendData || [];

        if (rawData.length === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-graph-up"></i></div><div class="rpt-empty-title">No trend data available</div><div class="rpt-empty-desc">Try adjusting your date range or filters to see form trends over time.</div></div>';
            return;
        }

        // Comparison overlay: align previous-period totals by day index (day 1..N)
        var prevSeries = null;
        if (prevData.length) {
            prevSeries = rawData.map(function (_, i) {
                return prevData[i] ? Number(prevData[i].count) || 0 : 0;
            });
        }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: rawData.map(d => {
                    const dt = new Date(d.date + 'T00:00:00');
                    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'Total',
                        data: rawData.map(d => d.count),
                        borderColor: COLORS.indigo,
                        backgroundColor: 'rgba(79,70,229,0.08)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: COLORS.indigo,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        order: 0,
                    },
                    ...(prevSeries ? [{
                        label: 'Previous period',
                        data: prevSeries,
                        borderColor: COLORS.slate,
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        fill: false,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: COLORS.slate,
                        order: 0,
                    }] : []),
                    {
                        label: 'Medical',
                        data: rawData.map(d => Number(d.medical) || 0),
                        borderColor: COLORS.emerald,
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        borderDash: [5, 3],
                        fill: false,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: COLORS.emerald,
                        order: 1,
                    },
                    {
                        label: 'Trauma',
                        data: rawData.map(d => Number(d.trauma) || 0),
                        borderColor: COLORS.rose,
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        borderDash: [5, 3],
                        fill: false,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: COLORS.rose,
                        order: 1,
                    },
                    {
                        label: 'Obstetric',
                        data: rawData.map(d => Number(d.obstetric) || 0),
                        borderColor: COLORS.purple,
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        borderDash: [5, 3],
                        fill: false,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: COLORS.purple,
                        order: 1,
                    },
                    {
                        label: 'General',
                        data: rawData.map(d => Number(d.general) || 0),
                        borderColor: COLORS.amber,
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        borderDash: [5, 3],
                        fill: false,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointBackgroundColor: COLORS.amber,
                        order: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyleWidth: 8,
                            font: { size: 10, weight: '500' },
                            color: '#64748b',
                        },
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 14,
                        cornerRadius: 8,
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 11, weight: '400' },
                        callbacks: {
                            title: function (items) {
                                var idx = items[0].dataIndex;
                                return new Date(rawData[idx].date + 'T00:00:00').toLocaleDateString('en-US', {
                                    weekday: 'short', month: 'long', day: 'numeric', year: 'numeric',
                                });
                            },
                        },
                    },
                },
                scales: getGridScales(),
            },
        });
    }

    // ===== 2. STATUS DISTRIBUTION (Doughnut with Center Text) =====
    function initStatusChart() {
        const canvas = document.getElementById('rptStatusChart');
        if (!canvas) return;
        const completed = parseInt(window.__rptCompleted || 0);
        const draft = parseInt(window.__rptDraft || 0);
        const archived = parseInt(window.__rptArchived || 0);
        const total = completed + draft + archived;

        if (total === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-pie-chart"></i></div><div class="rpt-empty-title">No status data</div><div class="rpt-empty-desc">No forms match the current filters.</div></div>';
            return;
        }

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Archived'],
                datasets: [
                    {
                        data: [completed, draft, archived],
                        backgroundColor: [COLORS.emerald, COLORS.amber, COLORS.slate],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 5,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 10, weight: '500' },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
            plugins: [
                {
                    id: 'centerText',
                    afterDraw: function (chart) {
                        const { ctx, chartArea: { top, bottom, left, right } } = chart;
                        const centerX = (left + right) / 2;
                        const centerY = (top + bottom) / 2;
                        ctx.save();
                        ctx.font = 'bold 22px Inter, sans-serif';
                        ctx.fillStyle = '#0f172a';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(total, centerX, centerY - 6);
                        ctx.font = '10px Inter, sans-serif';
                        ctx.fillStyle = '#94a3b8';
                        ctx.fillText('TOTAL', centerX, centerY + 14);
                        ctx.restore();
                    },
                },
            ],
        });
    }

    // ===== 3. RECORDS BY TYPE (Horizontal Bar, incl. Vehicular Accident) =====
    function initEmergencyChart() {
        const canvas = document.getElementById('rptEmergencyChart');
        if (!canvas) return;
        // Prefer the type arrays (which split Trauma into VA + non-VA); fall back to legacy fields.
        let labels = window.__rptTypeLabels || [];
        let data = (window.__rptTypeData || []).map(v => parseInt(v) || 0);
        let colors = window.__rptTypeColors || [];
        if (!labels.length) {
            labels = ['Medical', 'Trauma', 'Obstetric', 'General'];
            data = [parseInt(window.__rptMedical || 0), parseInt(window.__rptTrauma || 0), parseInt(window.__rptObstetric || 0), parseInt(window.__rptGeneral || 0)];
            colors = [COLORS.emerald, COLORS.rose, COLORS.purple, COLORS.amber];
        }
        const total = data.reduce((a, b) => a + b, 0);

        if (total === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-bar-chart"></i></div><div class="rpt-empty-title">No emergency type data</div></div>';
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        data: data,
                        backgroundColor: colors,
                        borderRadius: 6,
                        barThickness: 28,
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const pct = total > 0 ? ((ctx.parsed.x / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed.x + ' (' + pct + '%)';
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' },
                        grid: { color: '#f1f5f9', drawBorder: false },
                        border: { display: false },
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: '600' }, color: '#475569' },
                        border: { display: false },
                    },
                },
            },
        });
    }

    // ===== 4. AGE DISTRIBUTION (Vertical Bar) =====
    function initAgeChart() {
        const canvas = document.getElementById('rptAgeChart');
        if (!canvas) return;
        const ageData = window.__rptAgeData || [];

        if (ageData.length === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-people"></i></div><div class="rpt-empty-title">No demographic data</div></div>';
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: ageData.map(a => a.age_group),
                datasets: [
                    {
                        label: 'Patients',
                        data: ageData.map(a => a.count),
                        backgroundColor: COLORS.teal,
                        borderRadius: 6,
                        barThickness: 40,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: getGridScales(),
            },
        });
    }

    // ===== 5. GENDER DISTRIBUTION (Doughnut) =====
    function initGenderChart() {
        const canvas = document.getElementById('rptGenderChart');
        if (!canvas) return;
        const genderData = window.__rptGenderData || [];
        if (genderData.length === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-gender-ambiguous"></i></div><div class="rpt-empty-title">No gender data</div></div>';
            return;
        }
        const labels = genderData.map(g => g.gender ? g.gender.charAt(0).toUpperCase() + g.gender.slice(1) : 'Unknown');
        const counts = genderData.map(g => parseInt(g.count) || 0);
        const total = counts.reduce((a, b) => a + b, 0);

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [
                    {
                        data: counts,
                        backgroundColor: [COLORS.indigo, COLORS.roseLight, COLORS.slate],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: { padding: 14, usePointStyle: true, pointStyle: 'circle', font: { size: 10, weight: '500' } },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });
    }

    // ===== 6. VEHICLE USAGE (Horizontal Bar) =====
    function initVehicleChart() {
        const canvas = document.getElementById('rptVehicleChart');
        if (!canvas) return;
        const vehicleData = window.__rptVehicleData || [];
        if (vehicleData.length === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-truck"></i></div><div class="rpt-empty-title">No vehicle data</div></div>';
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: vehicleData.map(v => v.vehicle_used || 'Unspecified'),
                datasets: [
                    {
                        label: 'Forms',
                        data: vehicleData.map(v => v.count),
                        backgroundColor: COLORS.indigo,
                        borderRadius: 4,
                        barThickness: 32,
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' },
                        grid: { color: '#f1f5f9', drawBorder: false },
                        border: { display: false },
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: '500' }, color: '#475569' },
                        border: { display: false },
                    },
                },
            },
        });
    }

    // ===== 7. INJURY TYPES (Horizontal Bar) =====
    function initInjuryChart() {
        const canvas = document.getElementById('rptInjuryChart');
        if (!canvas) return;
        const injuryData = window.__rptInjuryData || [];
        if (injuryData.length === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-heart-pulse"></i></div><div class="rpt-empty-title">No injury data</div></div>';
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: injuryData.map(i => i.injury_type || 'Unspecified'),
                datasets: [
                    {
                        label: 'Cases',
                        data: injuryData.map(i => i.count),
                        backgroundColor: injuryData.map((_, idx) => {
                            const palette = [COLORS.rose, COLORS.amber, COLORS.indigo, COLORS.purple, COLORS.teal, COLORS.slate];
                            return palette[idx % palette.length];
                        }),
                        borderRadius: 4,
                        barThickness: 28,
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' },
                        grid: { color: '#f1f5f9', drawBorder: false },
                        border: { display: false },
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: '500' }, color: '#475569' },
                        border: { display: false },
                    },
                },
            },
        });
    }

    // ===== 8. PEAK HOURS HEATMAP (Bar chart by hour) =====
    function initPeakHoursChart() {
        const canvas = document.getElementById('rptPeakHoursChart');
        if (!canvas) return;
        const hourData = window.__rptHourData || [];
        if (hourData.length === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-clock"></i></div><div class="rpt-empty-title">No time-of-day data</div><div class="rpt-empty-desc">No call time data available for the selected period.</div></div>';
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: hourData.map(h => {
                    const hr = parseInt(h.hour_of_day);
                    const ampm = hr >= 12 ? 'PM' : 'AM';
                    const display = hr === 0 ? 12 : hr > 12 ? hr - 12 : hr;
                    return display + ' ' + ampm;
                }),
                datasets: [
                    {
                        label: 'Calls',
                        data: hourData.map(h => h.count),
                        backgroundColor: hourData.map(h => {
                            const max = Math.max(...hourData.map(x => parseInt(x.count) || 0), 1);
                            const ratio = (parseInt(h.count) || 0) / max;
                            if (ratio > 0.66) return COLORS.indigo;
                            if (ratio > 0.33) return COLORS.indigoLight;
                            return COLORS.indigoBg;
                        }),
                        borderRadius: 4,
                        barThickness: 'flex',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 9 }, color: '#94a3b8' },
                        grid: { color: '#f1f5f9', drawBorder: false },
                        border: { display: false },
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9, weight: '500' }, color: '#64748b', maxRotation: 45 },
                        border: { display: false },
                    },
                },
            },
        });
    }

    // ===== INITIALIZE ALL CHARTS =====
    // ===== 9. RECORDS BY BARANGAY (Horizontal Bar) =====
    function initBarangayChart() {
        const canvas = document.getElementById('rptBarangayChart');
        if (!canvas) return;
        const labels = window.__rptBarangayLabels || [];
        const data = window.__rptBarangayData || [];
        if (!data.length) return;
        const palette = ['#4f46e5', '#6366f1', '#818cf8', '#a5b4fc', '#c7d2fe', '#ddd6fe', '#e0e7ff', '#eef2ff'];
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Records',
                    data: data,
                    backgroundColor: data.map((_, i) => palette[i % palette.length]),
                    borderWidth: 0,
                    borderRadius: 6,
                    barThickness: 18,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ctx.parsed.x + ' record' + (ctx.parsed.x !== 1 ? 's' : '') } },
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' }, grid: { color: '#f1f5f9', drawBorder: false }, border: { display: false } },
                    y: { grid: { display: false }, ticks: { font: { size: 11, weight: '500' }, color: '#475569' }, border: { display: false } },
                },
            },
        });
    }

    // ===== 10. OUTCOMES (Doughnut) =====
    function initOutcomeChart() {
        const canvas = document.getElementById('rptOutcomeChart');
        if (!canvas) return;
        const labels = window.__rptOutcomeLabels || [];
        const data = (window.__rptOutcomeData || []).map(v => parseInt(v) || 0);
        const colors = window.__rptOutcomeColors || [COLORS.emerald, COLORS.amber, COLORS.slate];
        const total = data.reduce((a, b) => a + b, 0);
        if (total === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-clipboard2-check"></i></div><div class="rpt-empty-title">No outcome data</div><div class="rpt-empty-desc">No records match the current filters.</div></div>';
            return;
        }
        new Chart(canvas, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 5 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed + ' (' + ((ctx.parsed / total) * 100).toFixed(1) + '%)' } },
                },
            },
            plugins: [{
                id: 'outcomeCenter',
                afterDraw: function (chart) {
                    const { ctx, chartArea: { top, bottom, left, right } } = chart;
                    const cx = (left + right) / 2, cy = (top + bottom) / 2;
                    ctx.save();
                    ctx.font = 'bold 20px Inter, sans-serif'; ctx.fillStyle = '#0f172a';
                    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                    ctx.fillText(total, cx, cy - 5);
                    ctx.font = '10px Inter, sans-serif'; ctx.fillStyle = '#94a3b8';
                    ctx.fillText('TOTAL', cx, cy + 13);
                    ctx.restore();
                },
            }],
        });
    }

    // ===== 11. VEHICLE USED (Doughnut: Ambulance / Fire Truck / Others) =====
    function initVehicleUsedChart() {
        const canvas = document.getElementById('rptVehicleUsedChart');
        if (!canvas) return;
        const labels = window.__rptVehicleUsedLabels || [];
        const data = (window.__rptVehicleUsedData || []).map(v => parseInt(v) || 0);
        const colors = window.__rptVehicleUsedColors || [];
        const total = data.reduce((a, b) => a + b, 0);
        if (total === 0) {
            canvas.parentElement.innerHTML = '<div class="rpt-empty-state"><div class="rpt-empty-icon"><i class="bi bi-truck"></i></div><div class="rpt-empty-title">No vehicle data</div></div>';
            return;
        }
        new Chart(canvas, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 5 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed + ' (' + ((ctx.parsed / total) * 100).toFixed(1) + '%)' } },
                },
            },
        });
    }

    function initAllCharts() {
        initTrendChart();
        initStatusChart();
        initEmergencyChart();
        initAgeChart();
        initGenderChart();
        initVehicleChart();
        initInjuryChart();
        initPeakHoursChart();
        initBarangayChart();
        initOutcomeChart();
        initVehicleUsedChart();
    }

    // ===== CLINICAL PANEL TOGGLES =====
    function initClinicalPanels() {
        document.querySelectorAll('.rpt-clinical-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var panel = this.closest('.rpt-clinical-panel');
                if (panel) {
                    panel.classList.toggle('open');
                }
            });
        });
    }

    // ===== DATE PRESET PILLS =====
    function initPresetPills() {
        var pills = document.querySelectorAll('.rpt-preset-pill');
        var dateFrom = document.getElementById('date_from');
        var dateTo = document.getElementById('date_to');

        if (!pills.length || !dateFrom || !dateTo) return;

        function setPreset(days, pillEl) {
            var to = new Date();
            var from = new Date();
            from.setDate(from.getDate() - days);
            dateFrom.value = from.toISOString().split('T')[0];
            dateTo.value = to.toISOString().split('T')[0];

            pills.forEach(function (p) { p.classList.remove('active'); });
            if (pillEl) pillEl.classList.add('active');
        }

        pills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                var range = this.getAttribute('data-range');
                switch (range) {
                    case 'today':
                        var today = new Date().toISOString().split('T')[0];
                        dateFrom.value = today;
                        dateTo.value = today;
                        break;
                    case 'week':
                        setPreset(7, this);
                        break;
                    case 'month':
                        setPreset(30, this);
                        break;
                    case 'lastMonth':
                        var now = new Date();
                        var firstDayLastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                        var lastDayLastMonth = new Date(now.getFullYear(), now.getMonth(), 0);
                        dateFrom.value = firstDayLastMonth.toISOString().split('T')[0];
                        dateTo.value = lastDayLastMonth.toISOString().split('T')[0];
                        pills.forEach(function (p) { p.classList.remove('active'); });
                        this.classList.add('active');
                        break;
                    case 'quarter':
                        setPreset(90, this);
                        break;
                    case 'custom':
                        pills.forEach(function (p) { p.classList.remove('active'); });
                        this.classList.add('active');
                        dateFrom.focus();
                        break;
                }
            });
        });

        // Mark active pill based on current date inputs
        if (dateFrom.value && dateTo.value) {
            pills.forEach(function (p) { p.classList.remove('active'); });
            var customPill = document.querySelector('.rpt-preset-pill[data-range="custom"]');
            if (customPill) customPill.classList.add('active');
        }
    }

    // ===== COMPARISON TOGGLE =====
    function initCompareToggle() {
        var toggle = document.getElementById('rptCompareToggle');
        if (!toggle) return;
        toggle.addEventListener('click', function () {
            this.classList.toggle('active');
            var isActive = this.classList.contains('active');
            // Trigger comparison mode — reload with compare=1 param
            var url = new URL(window.location.href);
            if (isActive) {
                url.searchParams.set('compare', '1');
            } else {
                url.searchParams.delete('compare');
            }
            window.location.href = url.toString();
        });
    }

    // ===== EXPORT FUNCTIONS =====
    function exportCSV() {
        var params = new URLSearchParams(window.location.search);
        params.set('export', 'csv');
        window.location.href = '../api/export_reports.php?' + params.toString();
    }

    function exportPDF() {
        // Open print dialog with optimized print stylesheet
        window.print();
    }

    // ===== FILTER PERSISTENCE (remember last-used range) =====
    var RPT_FILTER_KEY = 'rptLastFilters';
    function initFilterPersistence() {
        var form = document.getElementById('rptFilterForm');
        var search = window.location.search;

        // On submit, remember the chosen filters for next visit.
        if (form) {
            form.addEventListener('submit', function () {
                try {
                    var data = new URLSearchParams(new FormData(form)).toString();
                    localStorage.setItem(RPT_FILTER_KEY, data);
                } catch (e) { /* storage unavailable — ignore */ }
            });
        }

        // On a bare visit (no query params), restore the last-used filters.
        if (!search || search === '?') {
            try {
                var saved = localStorage.getItem(RPT_FILTER_KEY);
                if (saved) {
                    window.location.replace('reports.php?' + saved);
                }
            } catch (e) { /* ignore */ }
        }
    }

    // ===== CSP-COMPLIANT EVENT DELEGATION =====
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize charts
        if (typeof Chart !== 'undefined') {
            initAllCharts();
        } else {
            // Show skeleton state fallback
            console.warn('Chart.js not loaded — reports will display without charts.');
        }

        // Initialize interactive components
        initClinicalPanels();
        initPresetPills();
        initCompareToggle();
        initFilterPersistence();

        // Event delegation for all interactive elements
        document.addEventListener('click', function (e) {
            var el = e.target;

            // Export buttons
            var exportBtn = el.closest('[data-action]');
            if (exportBtn) {
                var action = exportBtn.getAttribute('data-action');
                switch (action) {
                    case 'print':
                        window.print();
                        break;
                    case 'exportToCSV':
                        exportCSV();
                        break;
                    case 'exportToPDF':
                        exportPDF();
                        break;
                    case 'resetFilters':
                        try { localStorage.removeItem(RPT_FILTER_KEY); } catch (e) { /* ignore */ }
                        window.location.href = 'reports.php?reset=1';
                        break;
                }
            }

            // Filter chip dismiss
            var chipDismiss = el.closest('.rpt-filter-chip-dismiss');
            if (chipDismiss) {
                var chip = chipDismiss.closest('.rpt-filter-chip');
                var filterParam = chip ? chip.getAttribute('data-filter-param') : null;
                if (filterParam) {
                    var url = new URL(window.location.href);
                    url.searchParams.delete(filterParam);
                    window.location.href = url.toString();
                }
            }
        });
    });
})();