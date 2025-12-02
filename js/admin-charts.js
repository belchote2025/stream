/**
 * Gráficos y visualizaciones para el dashboard de administración
 */

// Inicializar gráficos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js no está cargado. Cargando desde CDN...');
        loadChartJS();
    }
});

// Cargar Chart.js si no está disponible
function loadChartJS() {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
    script.onload = () => {
        console.log('Chart.js cargado correctamente');
    };
    document.head.appendChild(script);
}

/**
 * Crear gráfico de tendencias de vistas
 */
function createViewsTrendChart(data) {
    const ctx = document.getElementById('viewsTrendChart');
    if (!ctx || typeof Chart === 'undefined') return;

    // Preparar datos
    const labels = data.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' });
    });
    const values = data.map(item => item.views);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Vistas',
                data: values,
                borderColor: '#e50914',
                backgroundColor: 'rgba(229, 9, 20, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleColor: '#fff',
                    bodyColor: '#fff'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#999'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#999'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/**
 * Crear gráfico de nuevos usuarios
 */
function createUsersTrendChart(data) {
    const ctx = document.getElementById('usersTrendChart');
    if (!ctx || typeof Chart === 'undefined') return;

    const labels = data.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' });
    });
    const values = data.map(item => item.users);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nuevos Usuarios',
                data: values,
                backgroundColor: '#667eea',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#999',
                        stepSize: 1
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#999'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/**
 * Crear gráfico de distribución de usuarios por rol
 */
function createUsersDistributionChart(data) {
    const ctx = document.getElementById('usersDistributionChart');
    if (!ctx || typeof Chart === 'undefined') return;

    const labels = Object.keys(data).map(role => {
        const roleNames = {
            'user': 'Usuarios',
            'premium': 'Premium',
            'admin': 'Administradores'
        };
        return roleNames[role] || role;
    });
    const values = Object.values(data);

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#667eea',
                    '#f093fb',
                    '#4facfe'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#fff',
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
}

/**
 * Crear gráfico de contenido más visto
 */
function createTopContentChart(data) {
    const ctx = document.getElementById('topContentChart');
    if (!ctx || typeof Chart === 'undefined') return;

    const labels = data.map(item => item.title.substring(0, 20) + (item.title.length > 20 ? '...' : ''));
    const values = data.map(item => item.views);
    const colors = data.map(item => item.type === 'movie' ? '#f093fb' : '#4facfe');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Vistas',
                data: values,
                backgroundColor: colors,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        title: function (context) {
                            return data[context[0].dataIndex].title;
                        },
                        label: function (context) {
                            const item = data[context.dataIndex];
                            const type = item.type === 'movie' ? 'Película' : 'Serie';
                            return `${type}: ${context.parsed.x} vistas`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        color: '#999'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                y: {
                    ticks: {
                        color: '#999'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/**
 * Actualizar todos los gráficos con nuevos datos
 */
function updateDashboardCharts(stats) {
    if (!stats) return;

    // Gráfico de tendencias de vistas
    if (stats.viewsTrend && stats.viewsTrend.length > 0) {
        createViewsTrendChart(stats.viewsTrend);
    }

    // Gráfico de nuevos usuarios
    if (stats.usersTrend && stats.usersTrend.length > 0) {
        createUsersTrendChart(stats.usersTrend);
    }

    // Gráfico de distribución de usuarios
    if (stats.usersByRole) {
        createUsersDistributionChart(stats.usersByRole);
    }

    // Gráfico de contenido más visto
    if (stats.topContent && stats.topContent.length > 0) {
        createTopContentChart(stats.topContent);
    }
}

// Exportar funciones globalmente
window.updateDashboardCharts = updateDashboardCharts;
window.createViewsTrendChart = createViewsTrendChart;
window.createUsersTrendChart = createUsersTrendChart;
window.createUsersDistributionChart = createUsersDistributionChart;
window.createTopContentChart = createTopContentChart;
