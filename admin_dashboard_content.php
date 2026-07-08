<?php
// admin_dashboard_content.php - Clean Dashboard without Icons
// This file is included in admin.php when section=dashboard
?>
<style>
    .dash-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 10px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e5e7eb;
    }
    .dash-header h2 {
        margin: 0;
        color: #1a1a2e;
        font-size: 24px;
    }
    .dash-header h2 small {
        font-size: 14px;
        font-weight: 400;
        color: #94a3b8;
        margin-left: 8px;
    }
    .dash-header .dash-info {
        color: #94a3b8;
        font-size: 14px;
    }
    
    /* Stats Cards - No Icons */
    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .dashboard-stat-card {
        background: white;
        padding: 24px 28px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border-top: 4px solid #0f172a;
        transition: 0.2s;
        text-align: center;
    }
    .dashboard-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    }
    .dashboard-stat-card .stat-number {
        font-size: 34px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.5px;
    }
    .dashboard-stat-card .stat-label {
        color: #94a3b8;
        font-size: 14px;
        margin-top: 6px;
        font-weight: 500;
    }
    .dashboard-stat-card.pending { border-top-color: #f59e0b; }
    .dashboard-stat-card.revenue { border-top-color: #8b5cf6; }
    .dashboard-stat-card.submissions { border-top-color: #0ea5e9; }
    
    /* Charts Grid */
    .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }
    .chart-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        transition: 0.2s;
    }
    .chart-box:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    }
    .chart-box h3 {
        margin-bottom: 15px;
        color: #333;
        font-size: 15px;
        border-left: 4px solid #0f172a;
        padding-left: 12px;
        margin-top: 0;
        font-weight: 600;
    }
    .chart-box canvas {
        max-height: 250px;
        width: 100% !important;
    }
    .chart-box .center-stats {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 200px;
        text-align: center;
    }
    .chart-box .center-stats .big-number {
        font-size: 48px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -2px;
    }
    .chart-box .center-stats .stat-label {
        color: #94a3b8;
        font-size: 14px;
        margin-top: 4px;
    }
    .chart-box .center-stats .stat-row {
        display: flex;
        gap: 24px;
        margin-top: 15px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .chart-box .center-stats .stat-row .item {
        font-size: 14px;
        color: #475569;
    }
    .chart-box .center-stats .stat-row .item .num {
        font-weight: 700;
        color: #0f172a;
    }
    .dot-green { color: #10b981; }
    .dot-yellow { color: #f59e0b; }
    .dot-red { color: #ef4444; }
    
    /* Recent Table */
    .recent-table-wrap {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        overflow-x: auto;
    }
    .recent-table-wrap h3 {
        margin-bottom: 15px;
        color: #333;
        border-left: 4px solid #0f172a;
        padding-left: 12px;
        margin-top: 0;
        font-size: 16px;
        font-weight: 600;
    }
    .recent-table-wrap table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .recent-table-wrap table th {
        background: #f8fafc;
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        border-bottom: 2px solid #e5e7eb;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .recent-table-wrap table td {
        padding: 10px 14px;
        border-bottom: 1px solid #e5e7eb;
        color: #334155;
    }
    .recent-table-wrap table tr:hover td {
        background: #fafbfc;
    }
    
    .status-badge {
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: inline-block;
    }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-approved { background: #d1fae5; color: #065f46; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-completed { background: #dbeafe; color: #1e40af; }
    .status-flagged { background: #fce7f3; color: #db2777; }
    
    .loading-dash {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    .loading-dash .spinner {
        width: 40px;
        height: 40px;
        border: 3px solid #e5e7eb;
        border-top: 3px solid #0f172a;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 15px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    .error-dash {
        background: #fee2e2;
        color: #991b1b;
        padding: 20px 30px;
        border-radius: 12px;
        text-align: center;
        border: 1px solid #fecaca;
    }
    .error-dash .retry-btn {
        margin-top: 12px;
        padding: 8px 24px;
        background: #0f172a;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    
    .text-muted { color: #94a3b8; }
    
    @media (max-width: 768px) {
        .chart-grid { grid-template-columns: 1fr; }
        .dashboard-stats { grid-template-columns: 1fr 1fr; }
        .dash-header { flex-direction: column; align-items: flex-start; }
        .dashboard-stat-card .stat-number { font-size: 26px; }
        .dashboard-stat-card { padding: 18px 16px; }
    }
    @media (max-width: 420px) {
        .dashboard-stats { grid-template-columns: 1fr; }
    }
</style>

<!-- Dashboard Header -->
<div class="dash-header">
    <h2>
        Analytics Dashboard
        <small>Real-time Statistics</small>
    </h2>
    <div>
        <span class="dash-info">
            <?php echo date('F j, Y - h:i A'); ?>
        </span>
    </div>
</div>

<!-- Dashboard Container -->
<div id="dash-root">
    <div class="loading-dash">
        <div class="spinner"></div>
        <p>Loading dashboard data...</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ============================================================
// DASHBOARD - Descriptive Statistics from Database
// ============================================================

let dashboardCharts = {};

function loadDashboardData() {
    const container = document.getElementById('dash-root');
    if (!container) return;
    
    container.innerHTML = `
        <div class="loading-dash">
            <div class="spinner"></div>
            <p>Fetching latest metrics...</p>
        </div>
    `;
    
    fetch('get_dashboard_stats.php')
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.error) {
                container.innerHTML = `<div class="error-dash">⚠️ ${data.error}</div>`;
                return;
            }
            renderDashboard(data);
        })
        .catch(error => {
            console.error('Dashboard error:', error);
            container.innerHTML = `
                <div class="error-dash">
                    <p><strong>Failed to load dashboard</strong></p>
                    <p style="font-size:14px;color:#7f1d1d;">${error.message}</p>
                    <button class="retry-btn" onclick="loadDashboardData()">Try Again</button>
                </div>
            `;
        });
}

function renderDashboard(data) {
    const container = document.getElementById('dash-root');
    
    const totalSub = data.total_submissions || 0;
    const pendingSub = data.pending_submissions || 0;
    const totalVal = data.total_value || 0;
    const avgPrice = data.avg_price || 0;
    const statusCounts = data.status_counts || [0,0,0,0];
    const deviceLabels = data.device_labels || [];
    const deviceCounts = data.device_counts || [];
    const repairLabels = data.repair_labels || ['Pending', 'Awaiting', 'In Repair', 'Completed'];
    const repairCounts = data.repair_counts || [0,0,0,0];
    const avgUser = data.avg_user_price || 0;
    const avgClerk = data.avg_clerk_price || 0;
    const maxPrice = data.max_price || 0;
    const minPrice = data.min_price || 0;
    const monthLabels = data.month_labels || [];
    const monthCounts = data.month_counts || [];
    const recent = data.recent_submissions || [];
    
    const approvalRate = totalSub > 0 ? Math.round((statusCounts[1] / totalSub) * 100) : 0;
    
    // ===== STATS CARDS - NO ICONS =====
    const html = `
        <!-- Stats Cards -->
        <div class="dashboard-stats">
            <div class="dashboard-stat-card">
                <div class="stat-number">${totalSub.toLocaleString()}</div>
                <div class="stat-label">Total Submissions</div>
            </div>
            <div class="dashboard-stat-card pending">
                <div class="stat-number">${pendingSub.toLocaleString()}</div>
                <div class="stat-label">Pending Reviews</div>
            </div>
            <div class="dashboard-stat-card revenue">
                <div class="stat-number">RM ${totalVal.toLocaleString()}</div>
                <div class="stat-label">Total Value</div>
            </div>
            <div class="dashboard-stat-card submissions">
                <div class="stat-number">RM ${avgPrice.toLocaleString()}</div>
                <div class="stat-label">Average Price</div>
            </div>
        </div>
        
        <!-- Charts Row 1 -->
        <div class="chart-grid">
            <div class="chart-box">
                <h3>Submissions by Status</h3>
                <canvas id="statusChart"></canvas>
            </div>
            <div class="chart-box">
                <h3>Device Type Distribution</h3>
                <canvas id="deviceChart"></canvas>
            </div>
        </div>
        
        <!-- Charts Row 2 -->
        <div class="chart-grid">
            <div class="chart-box">
                <h3>Repair Status Overview</h3>
                <canvas id="repairChart"></canvas>
            </div>
            <div class="chart-box">
                <h3>Monthly Submissions Trend</h3>
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        
        <!-- Charts Row 3 -->
        <div class="chart-grid">
            <div class="chart-box">
                <h3>Price Analysis</h3>
                <canvas id="priceChart"></canvas>
            </div>
            <div class="chart-box">
                <div class="center-stats">
                    <div class="big-number">${approvalRate}%</div>
                    <div class="stat-label">Approval Rate</div>
                    <div class="stat-row">
                        <span class="item"><span class="dot-green">●</span> <span class="num">${statusCounts[1]}</span> Approved</span>
                        <span class="item"><span class="dot-yellow">●</span> <span class="num">${statusCounts[0]}</span> Pending</span>
                        <span class="item"><span class="dot-red">●</span> <span class="num">${statusCounts[2]}</span> Rejected</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Submissions -->
        <div class="recent-table-wrap">
            <h3>Recent Submissions</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Device</th>
                        <th>Brand / Model</th>
                        <th>Est. Price</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    ${recent.length > 0 ? recent.map(sub => `
                        <tr>
                            <td><strong>#${sub.id}</strong></td>
                            <td>${sub.device_type || '-'}</td>
                            <td>${sub.brand || '-'} ${sub.model || ''}</td>
                            <td>RM ${parseFloat(sub.estimated_price || 0).toLocaleString()}</td>
                            <td>
                                <span class="status-badge status-${sub.status || 'pending'}">
                                    ${sub.status || 'Pending'}
                                </span>
                            </td>
                            <td>${sub.submitted_at ? new Date(sub.submitted_at).toLocaleDateString() : '-'}</td>
                        </tr>
                    `).join('') : `
                        <tr><td colspan="6" style="padding:30px;text-align:center;color:#94a3b8;">No submissions found</td></tr>
                    `}
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = html;
    
    // Destroy old charts
    Object.values(dashboardCharts).forEach(chart => chart.destroy());
    dashboardCharts = {};
    
    // Create charts after DOM update
    setTimeout(() => createCharts(data), 150);
}

function createCharts(data) {
    // Status Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        dashboardCharts.status = new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: data.status_labels || ['Pending','Approved','Rejected','Completed'],
                datasets: [{
                    label: 'Submissions',
                    data: data.status_counts || [0,0,0,0],
                    backgroundColor: ['#f59e0b','#10b981','#ef4444','#3b82f6'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }
    
    // Device Chart
    const deviceCtx = document.getElementById('deviceChart');
    if (deviceCtx && data.device_labels && data.device_labels.length > 0) {
        const colors = ['#6366f1','#f97316','#0ea5e9','#10b981','#f43f5e','#8b5cf6','#f59e0b'];
        dashboardCharts.device = new Chart(deviceCtx, {
            type: 'pie',
            data: {
                labels: data.device_labels,
                datasets: [{
                    data: data.device_counts,
                    backgroundColor: colors.slice(0, data.device_labels.length),
                    borderWidth: 2,
                    borderColor: 'white'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right', labels: { font: { size: 11 } } }
                }
            }
        });
    }
    
    // Repair Chart
    const repairCtx = document.getElementById('repairChart');
    if (repairCtx && data.repair_labels && data.repair_labels.length > 0) {
        dashboardCharts.repair = new Chart(repairCtx, {
            type: 'doughnut',
            data: {
                labels: data.repair_labels,
                datasets: [{
                    data: data.repair_counts,
                    backgroundColor: ['#f59e0b','#3b82f6','#10b981','#8b5cf6'],
                    borderWidth: 2,
                    borderColor: 'white'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } }
            }
        });
    }
    
    // Trend Chart
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx && data.month_labels && data.month_labels.length > 0) {
        dashboardCharts.trend = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: data.month_labels,
                datasets: [{
                    label: 'Submissions',
                    data: data.month_counts,
                    borderColor: '#0f172a',
                    backgroundColor: 'rgba(15,23,42,0.06)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#0f172a',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }
    
    // Price Chart
    const priceCtx = document.getElementById('priceChart');
    if (priceCtx) {
        dashboardCharts.price = new Chart(priceCtx, {
            type: 'bar',
            data: {
                labels: ['Avg User Est.', 'Avg Clerk Price', 'Highest', 'Lowest'],
                datasets: [{
                    label: 'Price (RM)',
                    data: [
                        data.avg_user_price || 0,
                        data.avg_clerk_price || 0,
                        data.max_price || 0,
                        data.min_price || 0
                    ],
                    backgroundColor: ['#6366f1','#10b981','#f59e0b','#ef4444'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
}

// Auto-refresh every 60 seconds
let refreshInterval;

function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(loadDashboardData, 60000);
}

// Load on page ready
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    startAutoRefresh();
});
</script>