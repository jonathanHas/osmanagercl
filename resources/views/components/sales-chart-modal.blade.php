{{-- Sales Chart Modal Component --}}
{{-- Usage: <x-sales-chart-modal /> --}}
{{-- Trigger: window.showSalesChartModal(productId, productName, initialWeeks) --}}

<!-- Sales Chart Popup Modal -->
<div id="sales-chart-modal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
        <!-- Modal Header -->
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            <h3 id="modal-product-name" class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate pr-4">Product Sales History</h3>
            <button id="close-sales-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors p-1 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Date Range Controls -->
        <div class="flex items-center justify-center gap-3 px-6 py-4 bg-gray-100 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
            <!-- Daily view controls (hidden by default) -->
            <div id="modal-daily-controls" class="hidden flex items-center gap-3">
                <button id="modal-back-weekly" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 border border-indigo-600 rounded-lg transition-colors" title="Back to weekly view">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Weekly
                </button>
                <button id="modal-prev-week" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" title="Previous week">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Prev
                </button>
                <div class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg min-w-[140px] text-center">
                    <span id="modal-week-label">Week of 02 Dec</span>
                </div>
                <button id="modal-next-week" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" title="Next week">
                    Next
                    <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <!-- Transactions view controls (hidden by default) -->
            <div id="modal-transactions-controls" class="hidden flex items-center gap-3">
                <button id="modal-back-daily" class="px-3 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 border border-indigo-600 rounded-lg transition-colors" title="Back to daily view">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Daily
                </button>
                <button id="modal-prev-day" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Previous day with sales">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Prev
                </button>
                <div class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg min-w-[140px] text-center">
                    <span id="modal-date-label">Tuesday 03 Dec</span>
                </div>
                <button id="modal-next-day" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Next day with sales">
                    Next
                    <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <!-- Weekly view controls (shown by default) -->
            <div id="modal-weekly-controls" class="flex items-center justify-center gap-3">
                <button id="modal-expand-8" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" title="Expand by 2 months">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                    2 months
                </button>
                <button id="modal-expand-4" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" title="Expand by 1 month">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    1 month
                </button>
                <div class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg min-w-[140px] text-center">
                    <span id="modal-weeks-display">8</span> weeks
                </div>
                <button id="modal-contract-4" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" title="Contract by 1 month">
                    1 month
                    <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <button id="modal-contract-8" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors" title="Contract by 2 months">
                    2 months
                    <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7m-8-14l7 7-7 7"/>
                    </svg>
                </button>
            </div>

        </div>

        <!-- Chart Container -->
        <div class="p-6 relative">
            <div id="modal-chart-container" class="relative" style="height: 400px;">
                <canvas id="modal-sales-chart"></canvas>
            </div>

            <!-- Transactions List (hidden by default) -->
            <div id="modal-transactions-list" class="hidden overflow-auto" style="height: 400px;">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Time</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Employee</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody id="transactions-tbody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <!-- Transactions will be inserted here -->
                    </tbody>
                </table>
                <div id="no-transactions-message" class="hidden py-12 text-center text-gray-500 dark:text-gray-400">
                    No transaction details available for this date.
                </div>
            </div>

            <div id="modal-loading" class="hidden absolute inset-0 flex items-center justify-center bg-white dark:bg-gray-800 bg-opacity-80 dark:bg-opacity-80">
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Loading...
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="grid grid-cols-4 gap-4 px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="text-center">
                <div id="modal-stat-total-label" class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Sales</div>
                <div id="modal-stat-total" class="text-lg font-semibold text-gray-900 dark:text-gray-100">-</div>
            </div>
            <div class="text-center">
                <div id="modal-stat-peak-label" class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Peak Week</div>
                <div id="modal-stat-peak" class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">-</div>
            </div>
            <div class="text-center">
                <div id="modal-stat-avg-label" class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Average</div>
                <div id="modal-stat-avg" class="text-lg font-semibold text-gray-900 dark:text-gray-100">-</div>
            </div>
            <div class="text-center">
                <div id="modal-stat-active-label" class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Active Weeks</div>
                <div id="modal-stat-active" class="text-lg font-semibold text-gray-900 dark:text-gray-100">-</div>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const modal = document.getElementById('sales-chart-modal');
    const closeBtn = document.getElementById('close-sales-modal');
    const productNameEl = document.getElementById('modal-product-name');
    const weeksDisplayEl = document.getElementById('modal-weeks-display');
    const chartCanvas = document.getElementById('modal-sales-chart');
    const chartContainerEl = document.getElementById('modal-chart-container');
    const transactionsListEl = document.getElementById('modal-transactions-list');
    const transactionsTbodyEl = document.getElementById('transactions-tbody');
    const noTransactionsEl = document.getElementById('no-transactions-message');
    const loadingEl = document.getElementById('modal-loading');
    const expandBtn8 = document.getElementById('modal-expand-8');
    const expandBtn4 = document.getElementById('modal-expand-4');
    const contractBtn4 = document.getElementById('modal-contract-4');
    const contractBtn8 = document.getElementById('modal-contract-8');
    const statTotalEl = document.getElementById('modal-stat-total');
    const statPeakEl = document.getElementById('modal-stat-peak');
    const statAvgEl = document.getElementById('modal-stat-avg');
    const statActiveEl = document.getElementById('modal-stat-active');
    const statTotalLabelEl = document.getElementById('modal-stat-total-label');
    const statPeakLabelEl = document.getElementById('modal-stat-peak-label');
    const statAvgLabelEl = document.getElementById('modal-stat-avg-label');
    const statActiveLabelEl = document.getElementById('modal-stat-active-label');
    const weeklyControlsEl = document.getElementById('modal-weekly-controls');
    const dailyControlsEl = document.getElementById('modal-daily-controls');
    const transactionsControlsEl = document.getElementById('modal-transactions-controls');
    const backWeeklyBtn = document.getElementById('modal-back-weekly');
    const backDailyBtn = document.getElementById('modal-back-daily');
    const prevWeekBtn = document.getElementById('modal-prev-week');
    const nextWeekBtn = document.getElementById('modal-next-week');
    const prevDayBtn = document.getElementById('modal-prev-day');
    const nextDayBtn = document.getElementById('modal-next-day');
    const weekLabelEl = document.getElementById('modal-week-label');
    const dateLabelEl = document.getElementById('modal-date-label');

    if (!modal || !chartCanvas) return;

    let modalChart = null;
    let currentProductId = null;
    let currentProductName = '';
    let currentWeeks = 8;
    const minWeeks = 4;
    const maxWeeks = 104; // 2 years

    // State for drill-down navigation
    let viewMode = 'weekly'; // 'weekly', 'daily', or 'transactions'
    let currentWeekStart = null;
    let currentWeekIndex = null;
    let currentDate = null;
    let currentDayIndex = null;
    let weeklyDataCache = null;
    let weeklyStatsCache = null;
    let dailyDataCache = null;
    let dailyStatsCache = null;

    function showModal() {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function hideModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        if (modalChart) {
            modalChart.destroy();
            modalChart = null;
        }
        currentProductId = null;
        currentProductName = '';
        currentWeeks = 8;
        viewMode = 'weekly';
        currentWeekStart = null;
        currentWeekIndex = null;
        currentDate = null;
        currentDayIndex = null;
        weeklyDataCache = null;
        weeklyStatsCache = null;
        dailyDataCache = null;
        dailyStatsCache = null;
        updateViewMode();
    }

    function updateWeeksDisplay() {
        weeksDisplayEl.textContent = currentWeeks;
        expandBtn8.disabled = currentWeeks + 8 > maxWeeks;
        expandBtn4.disabled = currentWeeks + 4 > maxWeeks;
        contractBtn4.disabled = currentWeeks - 4 < minWeeks;
        contractBtn8.disabled = currentWeeks - 8 < minWeeks;

        [expandBtn8, expandBtn4, contractBtn4, contractBtn8].forEach(btn => {
            if (btn.disabled) {
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    }

    function updateWeekNavigation() {
        // Disable prev if at the oldest week, disable next if at the newest week
        if (prevWeekBtn && weeklyDataCache) {
            prevWeekBtn.disabled = currentWeekIndex <= 0;
            prevWeekBtn.classList.toggle('opacity-50', prevWeekBtn.disabled);
            prevWeekBtn.classList.toggle('cursor-not-allowed', prevWeekBtn.disabled);
        }
        if (nextWeekBtn && weeklyDataCache) {
            nextWeekBtn.disabled = currentWeekIndex >= weeklyDataCache.length - 1;
            nextWeekBtn.classList.toggle('opacity-50', nextWeekBtn.disabled);
            nextWeekBtn.classList.toggle('cursor-not-allowed', nextWeekBtn.disabled);
        }
    }

    function updateDayNavigation() {
        // Find previous and next days with sales
        if (!dailyDataCache) return;

        const daysWithSales = dailyDataCache.map((d, i) => ({ index: i, ...d })).filter(d => parseFloat(d.units) > 0);
        const currentPos = daysWithSales.findIndex(d => d.date === currentDate);

        if (prevDayBtn) {
            prevDayBtn.disabled = currentPos <= 0;
            prevDayBtn.classList.toggle('opacity-50', prevDayBtn.disabled);
            prevDayBtn.classList.toggle('cursor-not-allowed', prevDayBtn.disabled);
        }
        if (nextDayBtn) {
            nextDayBtn.disabled = currentPos >= daysWithSales.length - 1;
            nextDayBtn.classList.toggle('opacity-50', nextDayBtn.disabled);
            nextDayBtn.classList.toggle('cursor-not-allowed', nextDayBtn.disabled);
        }
    }

    function updateViewMode() {
        // Hide all control groups first
        weeklyControlsEl.classList.add('hidden');
        dailyControlsEl.classList.add('hidden');
        transactionsControlsEl.classList.add('hidden');
        chartContainerEl.classList.add('hidden');
        transactionsListEl.classList.add('hidden');

        if (viewMode === 'weekly') {
            weeklyControlsEl.classList.remove('hidden');
            chartContainerEl.classList.remove('hidden');

            statTotalLabelEl.textContent = 'Total Sales';
            statPeakLabelEl.textContent = 'Peak Week';
            statAvgLabelEl.textContent = 'Average';
            statActiveLabelEl.textContent = 'Active Weeks';

            productNameEl.textContent = currentProductName + ' - Sales History';
        } else if (viewMode === 'daily') {
            dailyControlsEl.classList.remove('hidden');
            chartContainerEl.classList.remove('hidden');

            statTotalLabelEl.textContent = 'Total Sales';
            statPeakLabelEl.textContent = 'Peak Day';
            statAvgLabelEl.textContent = 'Average';
            statActiveLabelEl.textContent = 'Active Days';

            productNameEl.textContent = currentProductName + ' - Daily Sales';
            updateWeekNavigation();
        } else if (viewMode === 'transactions') {
            transactionsControlsEl.classList.remove('hidden');
            transactionsListEl.classList.remove('hidden');

            statTotalLabelEl.textContent = 'Total Units';
            statPeakLabelEl.textContent = 'Transactions';
            statAvgLabelEl.textContent = 'Avg/Transaction';
            statActiveLabelEl.textContent = 'Total Value';

            productNameEl.textContent = currentProductName + ' - Transactions';
            updateDayNavigation();
        }
    }

    function showLoading() {
        loadingEl.classList.remove('hidden');
    }

    function hideLoading() {
        loadingEl.classList.add('hidden');
    }

    function updateStats(stats, mode = 'weekly') {
        if (mode === 'weekly') {
            statTotalEl.textContent = stats?.total?.toLocaleString() ?? '-';
            statPeakEl.textContent = stats?.peak?.toLocaleString() ?? '-';
            statAvgEl.textContent = stats?.average?.toLocaleString(undefined, {maximumFractionDigits: 1}) ?? '-';
            statActiveEl.textContent = stats?.weeksWithSales ?? '-';
        } else if (mode === 'daily') {
            statTotalEl.textContent = stats?.total?.toLocaleString() ?? '-';
            statPeakEl.textContent = stats?.peakDay ? `${stats?.peak?.toLocaleString() ?? '-'} (${stats.peakDay})` : (stats?.peak?.toLocaleString() ?? '-');
            statAvgEl.textContent = stats?.average?.toLocaleString(undefined, {maximumFractionDigits: 1}) ?? '-';
            statActiveEl.textContent = stats?.daysWithSales ?? '-';
        } else if (mode === 'transactions') {
            statTotalEl.textContent = stats?.totalUnits?.toLocaleString() ?? '-';
            statPeakEl.textContent = stats?.transactionCount ?? '-';
            statAvgEl.textContent = stats?.avgPerTransaction?.toLocaleString(undefined, {maximumFractionDigits: 1}) ?? '-';
            statActiveEl.textContent = stats?.totalValue ? `€${stats.totalValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : '-';
        }
    }

    async function fetchAndRenderChart(productId, weeks) {
        showLoading();

        try {
            const response = await fetch(`/products/${productId}/weekly-sales?weeks=${weeks}`);
            if (!response.ok) {
                throw new Error('Failed to fetch sales data');
            }

            const data = await response.json();

            weeklyDataCache = data.weeklySales;
            weeklyStatsCache = data.stats;

            renderWeeklyChart(data.weeklySales);
            updateStats(data.stats, 'weekly');
            currentWeeks = data.weeks;
            updateWeeksDisplay();
        } catch (error) {
            console.error('Failed to load sales data:', error);
            alert('Failed to load sales data. Please try again.');
        } finally {
            hideLoading();
        }
    }

    async function fetchAndRenderDailyChart(productId, weekStart) {
        showLoading();

        try {
            const response = await fetch(`/products/${productId}/daily-sales?week_start=${weekStart}`);
            if (!response.ok) {
                throw new Error('Failed to fetch daily sales data');
            }

            const data = await response.json();

            dailyDataCache = data.dailySales;
            dailyStatsCache = data.stats;

            if (weekLabelEl) weekLabelEl.textContent = data.weekLabel;

            renderDailyChart(data.dailySales);
            updateStats(data.stats, 'daily');
        } catch (error) {
            console.error('Failed to load daily sales data:', error);
            alert('Failed to load daily sales data. Please try again.');
            backToWeeklyView();
        } finally {
            hideLoading();
        }
    }

    async function fetchAndRenderTransactions(productId, date) {
        showLoading();

        try {
            const response = await fetch(`/products/${productId}/transaction-details?date=${date}`);
            if (!response.ok) {
                throw new Error('Failed to fetch transaction details');
            }

            const data = await response.json();

            if (dateLabelEl) dateLabelEl.textContent = data.dateLabel;

            renderTransactionsList(data.transactions);
            updateStats(data.stats, 'transactions');
        } catch (error) {
            console.error('Failed to load transaction details:', error);
            alert('Failed to load transaction details. Please try again.');
            backToDailyView();
        } finally {
            hideLoading();
        }
    }

    function renderWeeklyChart(weeklySales) {
        const labels = weeklySales.map(w => w.label);
        const dataPoints = weeklySales.map(w => parseFloat(w.units) || 0);
        const maxSales = Math.max(...dataPoints, 1);

        if (modalChart) {
            modalChart.destroy();
        }

        const ctx = chartCanvas.getContext('2d');
        modalChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Weekly Sales',
                    data: dataPoints,
                    borderColor: 'rgb(79, 70, 229)',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointBackgroundColor: 'rgb(79, 70, 229)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                onClick: (event, elements) => {
                    if (elements.length > 0 && viewMode === 'weekly' && weeklyDataCache) {
                        const index = elements[0].index;
                        const weekData = weeklyDataCache[index];
                        if (weekData && weekData.week_start) {
                            showDailyView(weekData.week_start, weekData.label, index);
                        }
                    }
                },
                onHover: (event, elements) => {
                    if (viewMode === 'weekly') {
                        chartCanvas.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return `Sales: ${context.parsed.y.toFixed(1)} units`;
                            },
                            afterLabel: function(context) {
                                return 'Click to see daily breakdown';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11 },
                            color: 'rgb(100, 116, 139)',
                            maxRotation: 45,
                            minRotation: 0
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: maxSales * 1.1,
                        grid: { color: 'rgba(148, 163, 184, 0.2)' },
                        ticks: {
                            font: { size: 11 },
                            color: 'rgb(71, 85, 105)',
                            precision: 0
                        },
                        title: {
                            display: true,
                            text: 'Units Sold',
                            font: { size: 12, weight: '500' },
                            color: 'rgb(71, 85, 105)'
                        }
                    }
                }
            }
        });
    }

    function renderDailyChart(dailySales) {
        const labels = dailySales.map(d => d.label);
        const dataPoints = dailySales.map(d => parseFloat(d.units) || 0);
        const maxSales = Math.max(...dataPoints, 1);

        if (modalChart) {
            modalChart.destroy();
        }

        const ctx = chartCanvas.getContext('2d');
        modalChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Sales',
                    data: dataPoints,
                    backgroundColor: dataPoints.map(v => v > 0 ? 'rgba(79, 70, 229, 0.8)' : 'rgba(156, 163, 175, 0.3)'),
                    borderColor: dataPoints.map(v => v > 0 ? 'rgb(79, 70, 229)' : 'rgb(156, 163, 175)'),
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                onClick: (event, elements) => {
                    if (elements.length > 0 && viewMode === 'daily' && dailyDataCache) {
                        const index = elements[0].index;
                        const dayData = dailyDataCache[index];
                        if (dayData && dayData.date && parseFloat(dayData.units) > 0) {
                            showTransactionsView(dayData.date, dayData.dayName);
                        }
                    }
                },
                onHover: (event, elements) => {
                    if (viewMode === 'daily' && elements.length > 0) {
                        const index = elements[0].index;
                        const dayData = dailyDataCache?.[index];
                        chartCanvas.style.cursor = (dayData && parseFloat(dayData.units) > 0) ? 'pointer' : 'default';
                    } else {
                        chartCanvas.style.cursor = 'default';
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        callbacks: {
                            title: function(context) {
                                const index = context[0].dataIndex;
                                return dailySales[index]?.dayName || context[0].label;
                            },
                            label: function(context) {
                                return `Sales: ${context.parsed.y.toFixed(1)} units`;
                            },
                            afterLabel: function(context) {
                                if (context.parsed.y > 0) {
                                    return 'Click to see transactions';
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 12, weight: '500' },
                            color: 'rgb(71, 85, 105)'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: maxSales * 1.1,
                        grid: { color: 'rgba(148, 163, 184, 0.2)' },
                        ticks: {
                            font: { size: 11 },
                            color: 'rgb(71, 85, 105)',
                            precision: 0
                        },
                        title: {
                            display: true,
                            text: 'Units Sold',
                            font: { size: 12, weight: '500' },
                            color: 'rgb(71, 85, 105)'
                        }
                    }
                }
            }
        });
    }

    function renderTransactionsList(transactions) {
        transactionsTbodyEl.innerHTML = '';

        if (!transactions || transactions.length === 0) {
            noTransactionsEl.classList.remove('hidden');
            return;
        }

        noTransactionsEl.classList.add('hidden');

        transactions.forEach(t => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
            row.innerHTML = `
                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 font-medium">${t.time}</td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">${t.employee}</td>
                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 text-right">${t.units.toFixed(1)}</td>
                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 text-right">€${t.price.toFixed(2)}</td>
                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 font-medium text-right">€${t.total.toFixed(2)}</td>
            `;
            transactionsTbodyEl.appendChild(row);
        });
    }

    function showDailyView(weekStart, weekLabel, weekIndex = null) {
        viewMode = 'daily';
        currentWeekStart = weekStart;
        currentWeekIndex = weekIndex !== null ? weekIndex : findWeekIndex(weekStart);
        dailyDataCache = null;
        dailyStatsCache = null;
        updateViewMode();
        fetchAndRenderDailyChart(currentProductId, weekStart);
    }

    function findWeekIndex(weekStart) {
        if (!weeklyDataCache) return null;
        return weeklyDataCache.findIndex(w => w.week_start === weekStart);
    }

    function showTransactionsView(date, dayName) {
        viewMode = 'transactions';
        currentDate = date;
        currentDayIndex = findDayIndex(date);
        updateViewMode();
        fetchAndRenderTransactions(currentProductId, date);
    }

    function findDayIndex(date) {
        if (!dailyDataCache) return null;
        return dailyDataCache.findIndex(d => d.date === date);
    }

    function navigateToPrevWeek() {
        if (!weeklyDataCache || currentWeekIndex <= 0) return;
        currentWeekIndex--;
        const weekData = weeklyDataCache[currentWeekIndex];
        if (weekData && weekData.week_start) {
            currentWeekStart = weekData.week_start;
            dailyDataCache = null;
            dailyStatsCache = null;
            fetchAndRenderDailyChart(currentProductId, weekData.week_start);
            updateWeekNavigation();
        }
    }

    function navigateToNextWeek() {
        if (!weeklyDataCache || currentWeekIndex >= weeklyDataCache.length - 1) return;
        currentWeekIndex++;
        const weekData = weeklyDataCache[currentWeekIndex];
        if (weekData && weekData.week_start) {
            currentWeekStart = weekData.week_start;
            dailyDataCache = null;
            dailyStatsCache = null;
            fetchAndRenderDailyChart(currentProductId, weekData.week_start);
            updateWeekNavigation();
        }
    }

    function navigateToPrevDay() {
        if (!dailyDataCache) return;
        const daysWithSales = dailyDataCache.map((d, i) => ({ index: i, ...d })).filter(d => parseFloat(d.units) > 0);
        const currentPos = daysWithSales.findIndex(d => d.date === currentDate);
        if (currentPos > 0) {
            const prevDay = daysWithSales[currentPos - 1];
            currentDate = prevDay.date;
            currentDayIndex = prevDay.index;
            fetchAndRenderTransactions(currentProductId, prevDay.date);
            updateDayNavigation();
        }
    }

    function navigateToNextDay() {
        if (!dailyDataCache) return;
        const daysWithSales = dailyDataCache.map((d, i) => ({ index: i, ...d })).filter(d => parseFloat(d.units) > 0);
        const currentPos = daysWithSales.findIndex(d => d.date === currentDate);
        if (currentPos < daysWithSales.length - 1) {
            const nextDay = daysWithSales[currentPos + 1];
            currentDate = nextDay.date;
            currentDayIndex = nextDay.index;
            fetchAndRenderTransactions(currentProductId, nextDay.date);
            updateDayNavigation();
        }
    }

    function backToWeeklyView() {
        viewMode = 'weekly';
        currentWeekStart = null;
        currentWeekIndex = null;
        currentDate = null;
        currentDayIndex = null;
        dailyDataCache = null;
        dailyStatsCache = null;
        updateViewMode();

        if (weeklyDataCache && weeklyStatsCache) {
            renderWeeklyChart(weeklyDataCache);
            updateStats(weeklyStatsCache, 'weekly');
        } else {
            fetchAndRenderChart(currentProductId, currentWeeks);
        }
    }

    function backToDailyView() {
        viewMode = 'daily';
        currentDate = null;
        currentDayIndex = null;
        updateViewMode();

        if (dailyDataCache && dailyStatsCache) {
            renderDailyChart(dailyDataCache);
            updateStats(dailyStatsCache, 'daily');
        } else if (currentWeekStart) {
            fetchAndRenderDailyChart(currentProductId, currentWeekStart);
        }
    }

    // Close modal handlers
    closeBtn?.addEventListener('click', hideModal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            hideModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            if (viewMode === 'transactions') {
                backToDailyView();
            } else if (viewMode === 'daily') {
                backToWeeklyView();
            } else {
                hideModal();
            }
        }
    });

    // Back button handlers
    backWeeklyBtn?.addEventListener('click', backToWeeklyView);
    backDailyBtn?.addEventListener('click', backToDailyView);

    // Week navigation handlers
    prevWeekBtn?.addEventListener('click', navigateToPrevWeek);
    nextWeekBtn?.addEventListener('click', navigateToNextWeek);

    // Day navigation handlers
    prevDayBtn?.addEventListener('click', navigateToPrevDay);
    nextDayBtn?.addEventListener('click', navigateToNextDay);

    // Date range controls
    expandBtn8?.addEventListener('click', function() {
        if (currentProductId && currentWeeks + 8 <= maxWeeks) {
            currentWeeks += 8;
            fetchAndRenderChart(currentProductId, currentWeeks);
        }
    });

    expandBtn4?.addEventListener('click', function() {
        if (currentProductId && currentWeeks + 4 <= maxWeeks) {
            currentWeeks += 4;
            fetchAndRenderChart(currentProductId, currentWeeks);
        }
    });

    contractBtn4?.addEventListener('click', function() {
        if (currentProductId && currentWeeks - 4 >= minWeeks) {
            currentWeeks -= 4;
            fetchAndRenderChart(currentProductId, currentWeeks);
        }
    });

    contractBtn8?.addEventListener('click', function() {
        if (currentProductId && currentWeeks - 8 >= minWeeks) {
            currentWeeks -= 8;
            fetchAndRenderChart(currentProductId, currentWeeks);
        }
    });

    // Global function to open the modal from anywhere
    window.showSalesChartModal = function(productId, productName, initialWeeks = 8) {
        if (!productId) return;

        currentProductId = productId;
        currentProductName = productName;
        currentWeeks = Math.max(minWeeks, Math.min(maxWeeks, initialWeeks));
        viewMode = 'weekly';
        currentWeekStart = null;
        currentWeekIndex = null;
        currentDate = null;
        currentDayIndex = null;
        weeklyDataCache = null;
        weeklyStatsCache = null;
        dailyDataCache = null;
        dailyStatsCache = null;

        productNameEl.textContent = productName + ' - Sales History';
        updateWeeksDisplay();
        updateViewMode();
        showModal();
        fetchAndRenderChart(productId, currentWeeks);
    };
})();
</script>
@endpush
@endonce
