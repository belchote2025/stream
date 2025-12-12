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
    return new Promise((resolve, reject) => {
        if (typeof Chart !== 'undefined') {
            resolve();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        script.onload = () => {
            console.log('Chart.js cargado correctamente');
            resolve();
        };
        script.onerror = () => {
            console.error('Error al cargar Chart.js');
            reject(new Error('No se pudo cargar Chart.js'));
        };
        document.head.appendChild(script);
    });
}

// Almacenar instancias de gráficos para poder destruirlos
const chartInstances = {};

/**
 * Crear gráfico de tendencias de vistas
 */
function createViewsTrendChart(data) {
    const ctx = document.getElementById('viewsTrendChart');
    if (!ctx || typeof Chart === 'undefined') return;

    // Destruir gráfico existente si existe
    if (chartInstances.viewsTrend) {
        chartInstances.viewsTrend.destroy();
    }

    // Preparar datos
    const labels = data.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' });
    });
    const values = data.map(item => item.views);

    chartInstances.viewsTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Vistas',
                data: values,
                borderColor: '#e50914',
                backgroundColor: 'rgba(229, 9, 20, 0.15)',
                borderWidth: 3,
                pointBackgroundColor: '#e50914',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
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
                    backgroundColor: '#1a1a1a',
                    padding: 12,
                    titleColor: '#e50914',
                    bodyColor: '#ffffff',
                    borderColor: '#2a2a2a',
                    borderWidth: 1,
                    titleFont: {
                        weight: 'bold'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#b3b3b3',
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.08)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b3b3b3',
                        font: {
                            size: 11
                        }
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

    // Destruir gráfico existente si existe
    if (chartInstances.usersTrend) {
        chartInstances.usersTrend.destroy();
    }

    const labels = data.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('es-ES', { month: 'short', day: 'numeric' });
    });
    const values = data.map(item => item.users);

    chartInstances.usersTrend = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nuevos Usuarios',
                data: values,
                backgroundColor: '#e50914',
                borderColor: '#b20710',
                borderWidth: 2,
                borderRadius: 6
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
                    backgroundColor: 'rgba(0, 0, 0, 0.9)',
                    padding: 12,
                    titleColor: '#ff6b6b',
                    bodyColor: '#fff',
                    borderColor: '#ff6b6b',
                    borderWidth: 1
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#ffffff',
                        stepSize: 1,
                        font: {
                            size: 12
                        }
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.15)'
                    }
                },
                x: {
                    ticks: {
                        color: '#ffffff',
                        font: {
                            size: 12
                        }
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

    // Destruir gráfico existente si existe
    if (chartInstances.usersDistribution) {
        chartInstances.usersDistribution.destroy();
    }

    const labels = Object.keys(data).map(role => {
        const roleNames = {
            'user': 'Usuarios',
            'premium': 'Premium',
            'admin': 'Administradores'
        };
        return roleNames[role] || role;
    });
    const values = Object.values(data);

    chartInstances.usersDistribution = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#e50914',
                    '#b20710',
                    '#8b0000',
                    '#6b0000',
                    '#4a0000'
                ],
                borderColor: '#1a1a1a',
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#b3b3b3',
                        padding: 15,
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#1a1a1a',
                    padding: 12,
                    titleColor: '#e50914',
                    bodyColor: '#ffffff',
                    borderColor: '#2a2a2a',
                    borderWidth: 1,
                    titleFont: {
                        weight: 'bold'
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

    // Destruir gráfico existente si existe
    if (chartInstances.topContent) {
        chartInstances.topContent.destroy();
    }

    const labels = data.map(item => item.title.substring(0, 20) + (item.title.length > 20 ? '...' : ''));
    const values = data.map(item => item.views);
    const colors = data.map(item => item.type === 'movie' ? '#e50914' : '#b20710');

    chartInstances.topContent = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Vistas',
                data: values,
                backgroundColor: colors,
                borderColor: colors.map(c => c),
                borderWidth: 2,
                borderRadius: 6
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
                    backgroundColor: 'rgba(0, 0, 0, 0.9)',
                    padding: 12,
                    titleColor: '#00d4ff',
                    bodyColor: '#fff',
                    borderColor: '#00d4ff',
                    borderWidth: 1,
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
                        color: '#b3b3b3',
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.08)'
                    }
                },
                y: {
                    ticks: {
                        color: '#b3b3b3',
                        font: {
                            size: 11
                        }
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
async function updateDashboardCharts(stats) {
    if (!stats) {
        console.warn('No hay estadísticas para mostrar gráficos');
        return;
    }

    // Asegurar que Chart.js esté cargado
    if (typeof Chart === 'undefined') {
        try {
            await loadChartJS();
            // Esperar un poco más para que Chart.js se inicialice completamente
            await new Promise(resolve => setTimeout(resolve, 100));
        } catch (error) {
            console.error('Error al cargar Chart.js:', error);
            return;
        }
    }

    try {
        // Gráfico de tendencias de vistas
        if (stats.viewsTrend && Array.isArray(stats.viewsTrend) && stats.viewsTrend.length > 0) {
            createViewsTrendChart(stats.viewsTrend);
        } else {
            // Datos por defecto si no hay datos
            createViewsTrendChart([{ date: new Date().toISOString().split('T')[0], views: 0 }]);
        }

        // Gráfico de nuevos usuarios
        if (stats.usersTrend && Array.isArray(stats.usersTrend) && stats.usersTrend.length > 0) {
            createUsersTrendChart(stats.usersTrend);
        } else {
            // Datos por defecto si no hay datos
            createUsersTrendChart([{ date: new Date().toISOString().split('T')[0], users: 0 }]);
        }

        // Gráfico de distribución de usuarios
        if (stats.usersByRole && Object.keys(stats.usersByRole).length > 0) {
            createUsersDistributionChart(stats.usersByRole);
        } else {
            // Datos por defecto si no hay datos
            createUsersDistributionChart({ user: 0, premium: 0, admin: 0 });
        }

        // Gráfico de contenido más visto
        if (stats.topContent && Array.isArray(stats.topContent) && stats.topContent.length > 0) {
            createTopContentChart(stats.topContent);
        } else {
            // No mostrar gráfico si no hay datos
            const ctx = document.getElementById('topContentChart');
            if (ctx) {
                ctx.parentElement.innerHTML = '<p style="color: #999; text-align: center; padding: 2rem;">No hay datos disponibles</p>';
            }
        }
    } catch (error) {
        console.error('Error al crear gráficos:', error);
    }
}

// Exportar funciones globalmente
window.updateDashboardCharts = updateDashboardCharts;
window.createViewsTrendChart = createViewsTrendChart;
window.createUsersTrendChart = createUsersTrendChart;
window.createUsersDistributionChart = createUsersDistributionChart;
window.createTopContentChart = createTopContentChart;
