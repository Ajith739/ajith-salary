/**
 * Charts Engine — Chart.js Helper Functions & Configurations
 * Supports Indian Rupee notation, glowing gradients, and dynamic themes
 */

window.FinanceCharts = (function() {
    'use strict';

    // Format currency for Chart.js tooltips
    function formatINR(val) {
        if (typeof val !== 'number') val = parseFloat(val) || 0;
        const neg = val < 0;
        val = Math.abs(Math.round(val));
        const s = val.toString();
        const last3 = s.substring(s.length - 3);
        const other = s.substring(0, s.length - 3);
        const formatted = other !== '' ? other.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3 : last3;
        return (neg ? '-' : '') + '₹' + formatted;
    }

    // Chart.js Theme Colors
    const isDark = () => document.documentElement.getAttribute('data-theme') !== 'light';

    function getGridColor() {
        return isDark() ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.06)';
    }

    function getTextColor() {
        return isDark() ? '#9ca3af' : '#475569';
    }

    // Cash Flow Chart (Income, Expenses, Savings)
    function createCashFlowChart(canvasId, chartData) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !chartData || !chartData.length) return null;

        const ctx = canvas.getContext('2d');
        const labels = chartData.map(d => d.label);
        const incomeData = chartData.map(d => d.income);
        const expenseData = chartData.map(d => d.expenses);
        const savingsData = chartData.map(d => d.savings);

        // Gradients
        const incomeGrad = ctx.createLinearGradient(0, 0, 0, 280);
        incomeGrad.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
        incomeGrad.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

        const expenseGrad = ctx.createLinearGradient(0, 0, 0, 280);
        expenseGrad.addColorStop(0, 'rgba(244, 63, 94, 0.35)');
        expenseGrad.addColorStop(1, 'rgba(244, 63, 94, 0.0)');

        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Income',
                        data: incomeData,
                        borderColor: '#10b981',
                        backgroundColor: incomeGrad,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#10b981',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Expenses',
                        data: expenseData,
                        borderColor: '#f43f5e',
                        backgroundColor: expenseGrad,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2.5,
                        pointBackgroundColor: '#f43f5e',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Savings',
                        data: savingsData,
                        borderColor: '#6366f1',
                        backgroundColor: 'transparent',
                        borderDash: [5, 5],
                        tension: 0.35,
                        borderWidth: 2,
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 3
                    }
                ]
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
                        position: 'top',
                        labels: {
                            color: getTextColor(),
                            font: { family: 'Inter', size: 12, weight: 600 },
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.92)',
                        titleColor: '#f3f4f6',
                        bodyColor: '#e5e7eb',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatINR(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: getGridColor() },
                        ticks: { color: getTextColor(), font: { family: 'Inter', size: 11 } }
                    },
                    y: {
                        grid: { color: getGridColor() },
                        ticks: {
                            color: getTextColor(),
                            font: { family: 'Inter', size: 11 },
                            callback: function(val) {
                                if (val >= 1000) return '₹' + (val / 1000).toFixed(0) + 'k';
                                return '₹' + val;
                            }
                        }
                    }
                }
            }
        });
    }

    // Expense Categories Donut Chart
    function createExpenseDonutChart(canvasId, categories) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !categories || !categories.length) return null;

        const ctx = canvas.getContext('2d');
        const labels = categories.map(c => c.category || c.name);
        const data = categories.map(c => parseFloat(c.total) || 0);

        const defaultPalette = [
            '#f59e0b', '#3b82f6', '#ec4899', '#8b5cf6', '#ef4444', 
            '#14b8a6', '#f43f5e', '#6366f1', '#0ea5e9', '#10b981', '#a855f7'
        ];

        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: defaultPalette.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: isDark() ? '#111827' : '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: getTextColor(),
                            font: { family: 'Inter', size: 11, weight: 500 },
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 10
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.92)',
                        titleColor: '#f3f4f6',
                        bodyColor: '#e5e7eb',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const val = context.raw;
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return ' ' + context.label + ': ' + formatINR(val) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Cumulative Savings Chart (Month-over-Month Wealth Accumulation)
    function createCumulativeSavingsChart(canvasId, cumulativeData) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !cumulativeData || !cumulativeData.length) return null;

        const ctx = canvas.getContext('2d');
        const labels = cumulativeData.map(d => d.label);
        const monthlySavings = cumulativeData.map(d => parseFloat(d.monthly_savings) || 0);
        const cumulativeSavings = cumulativeData.map(d => parseFloat(d.cumulative_savings) || 0);

        // Gradient for cumulative area fill
        const cumGrad = ctx.createLinearGradient(0, 0, 0, 320);
        cumGrad.addColorStop(0, 'rgba(16, 185, 129, 0.45)');
        cumGrad.addColorStop(0.7, 'rgba(16, 185, 129, 0.12)');
        cumGrad.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

        return new Chart(ctx, {
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'line',
                        label: 'Accumulated Savings (Cumulative)',
                        data: cumulativeSavings,
                        borderColor: '#10b981',
                        backgroundColor: cumGrad,
                        fill: true,
                        tension: 0.35,
                        borderWidth: 3,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: isDark() ? '#111827' : '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        order: 1
                    },
                    {
                        type: 'bar',
                        label: 'Monthly Saved',
                        data: monthlySavings,
                        backgroundColor: isDark() ? 'rgba(99, 102, 241, 0.4)' : 'rgba(99, 102, 241, 0.22)',
                        borderColor: '#6366f1',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        order: 2
                    }
                ]
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
                        position: 'top',
                        labels: {
                            color: getTextColor(),
                            font: { family: 'Inter', size: 12, weight: 600 },
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.92)',
                        titleColor: '#f3f4f6',
                        bodyColor: '#e5e7eb',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatINR(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: getGridColor() },
                        ticks: { color: getTextColor(), font: { family: 'Inter', size: 11 } }
                    },
                    y: {
                        grid: { color: getGridColor() },
                        ticks: {
                            color: getTextColor(),
                            font: { family: 'Inter', size: 11 },
                            callback: function(val) {
                                if (Math.abs(val) >= 100000) return '₹' + (val / 100000).toFixed(1) + 'L';
                                if (Math.abs(val) >= 1000) return '₹' + (val / 1000).toFixed(0) + 'k';
                                return '₹' + val;
                            }
                        }
                    }
                }
            }
        });
    }

    return {
        formatINR: formatINR,
        createCashFlowChart: createCashFlowChart,
        createExpenseDonutChart: createExpenseDonutChart,
        createCumulativeSavingsChart: createCumulativeSavingsChart
    };
})();
