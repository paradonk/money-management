/* Dashboard charts — runs after Chart.js and trendData/debtData/expCatData are defined */
document.addEventListener('DOMContentLoaded', function () {

    /* ── Income vs Expense trend ───────────────────────────────────── */
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx && typeof trendData !== 'undefined') {
        new Chart(trendCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: trendData.map(d => d.label),
                datasets: [
                    {
                        label: 'Income',
                        data: trendData.map(d => d.income),
                        backgroundColor: 'rgba(16,185,129,0.85)',
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'Expenses',
                        data: trendData.map(d => d.expense),
                        backgroundColor: 'rgba(239,68,68,0.85)',
                        borderRadius: 6,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.dataset.label + ': ฿' + ctx.parsed.y.toLocaleString('th-TH', { minimumFractionDigits: 0 })
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => '฿' + (v / 1000).toFixed(0) + 'K' }
                    }
                }
            }
        });
    }

    /* ── Debt pie chart ────────────────────────────────────────────── */
    const debtCtx = document.getElementById('debtPieChart');
    if (debtCtx && typeof debtData !== 'undefined' && debtData.length > 0) {
        const palette = ['#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6','#ec4899','#8b5cf6','#14b8a6'];
        new Chart(debtCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: debtData.map(d => d.name),
                datasets: [{
                    data: debtData.map(d => d.remaining_balance),
                    backgroundColor: palette.slice(0, debtData.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ฿' + ctx.parsed.toLocaleString('th-TH', { minimumFractionDigits: 0 })
                        }
                    }
                }
            }
        });
    }

    /* ── Expense by category ───────────────────────────────────────── */
    const expCatCtx = document.getElementById('expCatChart');
    if (expCatCtx && typeof expCatData !== 'undefined' && expCatData.length > 0) {
        const palette = ['#f59e0b','#3b82f6','#6366f1','#10b981','#94a3b8','#ef4444','#1e293b'];
        new Chart(expCatCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: expCatData.map(d => d.category.charAt(0).toUpperCase() + d.category.slice(1)),
                datasets: [{
                    data: expCatData.map(d => d.total),
                    backgroundColor: palette.slice(0, expCatData.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 4,
                }]
            },
            options: {
                responsive: true,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, font: { size: 12 } } }
                }
            }
        });
    }
});
