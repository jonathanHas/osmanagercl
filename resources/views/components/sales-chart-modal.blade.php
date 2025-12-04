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

        <!-- Chart Container -->
        <div class="p-6 relative">
            <div class="relative" style="height: 400px;">
                <canvas id="modal-sales-chart"></canvas>
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
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Sales</div>
                <div id="modal-stat-total" class="text-lg font-semibold text-gray-900 dark:text-gray-100">-</div>
            </div>
            <div class="text-center">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Peak Week</div>
                <div id="modal-stat-peak" class="text-lg font-semibold text-indigo-600 dark:text-indigo-400">-</div>
            </div>
            <div class="text-center">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Average</div>
                <div id="modal-stat-avg" class="text-lg font-semibold text-gray-900 dark:text-gray-100">-</div>
            </div>
            <div class="text-center">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Active Weeks</div>
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
    const loadingEl = document.getElementById('modal-loading');
    const expandBtn8 = document.getElementById('modal-expand-8');
    const expandBtn4 = document.getElementById('modal-expand-4');
    const contractBtn4 = document.getElementById('modal-contract-4');
    const contractBtn8 = document.getElementById('modal-contract-8');
    const statTotalEl = document.getElementById('modal-stat-total');
    const statPeakEl = document.getElementById('modal-stat-peak');
    const statAvgEl = document.getElementById('modal-stat-avg');
    const statActiveEl = document.getElementById('modal-stat-active');

    if (!modal || !chartCanvas) return;

    let modalChart = null;
    let currentProductId = null;
    let currentWeeks = 8;
    const minWeeks = 4;
    const maxWeeks = 104; // 2 years

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
        currentWeeks = 8;
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

    function showLoading() {
        loadingEl.classList.remove('hidden');
    }

    function hideLoading() {
        loadingEl.classList.add('hidden');
    }

    function updateStats(stats) {
        if (statTotalEl) statTotalEl.textContent = stats?.total?.toLocaleString() ?? '-';
        if (statPeakEl) statPeakEl.textContent = stats?.peak?.toLocaleString() ?? '-';
        if (statAvgEl) statAvgEl.textContent = stats?.average?.toLocaleString(undefined, {maximumFractionDigits: 1}) ?? '-';
        if (statActiveEl) statActiveEl.textContent = stats?.weeksWithSales ?? '-';
    }

    async function fetchAndRenderChart(productId, weeks) {
        showLoading();

        try {
            const response = await fetch(`/products/${productId}/weekly-sales?weeks=${weeks}`);
            if (!response.ok) {
                throw new Error('Failed to fetch sales data');
            }

            const data = await response.json();
            renderChart(data.weeklySales);
            updateStats(data.stats);
            currentWeeks = data.weeks;
            updateWeeksDisplay();
        } catch (error) {
            console.error('Failed to load sales data:', error);
            alert('Failed to load sales data. Please try again.');
        } finally {
            hideLoading();
        }
    }

    function renderChart(weeklySales) {
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
                    pointRadius: 4,
                    pointHoverRadius: 6,
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
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
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
                        grid: {
                            color: 'rgba(148, 163, 184, 0.2)'
                        },
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

    // Close modal handlers
    closeBtn?.addEventListener('click', hideModal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            hideModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            hideModal();
        }
    });

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
        currentWeeks = Math.max(minWeeks, Math.min(maxWeeks, initialWeeks));
        productNameEl.textContent = productName + ' - Sales History';
        updateWeeksDisplay();
        showModal();
        fetchAndRenderChart(productId, currentWeeks);
    };
})();
</script>
@endpush
@endonce
