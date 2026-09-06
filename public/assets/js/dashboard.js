/**
 * Dashboard Page Logic
 * Initializes charts and dashboard interactive elements
 */

document.addEventListener('DOMContentLoaded', () => {
    if (!window.DASHBOARD_DATA || !window.FinanceCharts) return;

    const { chartData, expenseCategories } = window.DASHBOARD_DATA;

    // 1. Initialize Cash Flow Chart
    if (document.getElementById('cashFlowChart') && chartData) {
        window.cashFlowChartInstance = window.FinanceCharts.createCashFlowChart('cashFlowChart', chartData);
    }

    // 2. Initialize Expense Donut Chart
    if (document.getElementById('expenseDonutChart') && expenseCategories) {
        window.expenseDonutChartInstance = window.FinanceCharts.createExpenseDonutChart('expenseDonutChart', expenseCategories);
    }
});
