/**
 * Funcionalidades mejoradas para el panel de administración
 * Incluye: búsqueda en tiempo real, filtros, paginación, exportación
 */

// ============================================
// BÚSQUEDA EN TIEMPO REAL
// ============================================

/**
 * Inicializar búsqueda en tiempo real para tablas
 */
function initTableSearch() {
    const searchInputs = document.querySelectorAll('[id$="-search"]');

    searchInputs.forEach(input => {
        let debounceTimer;

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const query = this.value.toLowerCase().trim();
            const clearBtn = document.getElementById(this.id + '-clear');

            // Mostrar/ocultar botón de limpiar
            if (clearBtn) {
                clearBtn.style.display = query ? 'block' : 'none';
            }

            debounceTimer = setTimeout(() => {
                filterTable(query, this.closest('.content-header, .user-filters')?.nextElementSibling);
            }, 300);
        });

        // Botón de limpiar búsqueda
        const clearBtn = document.getElementById(input.id + '-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                input.dispatchEvent(new Event('input'));
                input.focus();
            });
        }
    });
}

/**
 * Filtrar filas de tabla según búsqueda
 */
function filterTable(query, tableContainer) {
    if (!tableContainer) return;

    const table = tableContainer.querySelector('table');
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const matches = !query || text.includes(query);

        row.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
    });

    // Mostrar mensaje si no hay resultados
    updateNoResultsMessage(table, visibleCount, query);
}

/**
 * Actualizar mensaje de "no hay resultados"
 */
function updateNoResultsMessage(table, visibleCount, query) {
    const tbody = table.querySelector('tbody');
    let noResultsRow = tbody.querySelector('.no-results-row');

    if (visibleCount === 0 && query) {
        if (!noResultsRow) {
            const colCount = table.querySelectorAll('thead th').length;
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="${colCount}" style="text-align: center; padding: 2rem; color: #999;">
                    <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    No se encontraron resultados para "<strong>${escapeHtml(query)}</strong>"
                </td>
            `;
            tbody.appendChild(noResultsRow);
        }
    } else if (noResultsRow) {
        noResultsRow.remove();
    }
}

// ============================================
// FILTROS AVANZADOS
// ============================================

/**
 * Aplicar filtros combinados a tabla
 */
function applyFilters(filters, tableSelector) {
    const table = document.querySelector(tableSelector);
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr:not(.no-results-row)');
    let visibleCount = 0;

    rows.forEach(row => {
        let matches = true;

        // Aplicar cada filtro
        for (const [key, value] of Object.entries(filters)) {
            if (!value) continue; // Skip empty filters

            const cellValue = row.dataset[key] || row.querySelector(`[data-${key}]`)?.dataset[key] || '';

            if (cellValue.toLowerCase() !== value.toLowerCase()) {
                matches = false;
                break;
            }
        }

        row.style.display = matches ? '' : 'none';
        if (matches) visibleCount++;
    });

    return visibleCount;
}

/**
 * Inicializar filtros de usuarios
 */
function initUserFilters() {
    const applyBtn = document.getElementById('apply-filters-btn');
    if (!applyBtn) return;

    applyBtn.addEventListener('click', function () {
        const filters = {
            status: document.getElementById('filter-status')?.value || '',
            role: document.getElementById('filter-role')?.value || ''
        };

        const visibleCount = applyFilters(filters, '.user-table-container table');

        // Aplicar ordenamiento
        const sortBy = document.getElementById('filter-sort')?.value;
        if (sortBy) {
            sortTable('.user-table-container table', sortBy);
        }

        showNotification(`Filtros aplicados. ${visibleCount} usuarios encontrados.`, 'success');
    });
}

// ============================================
// ORDENAMIENTO DE TABLAS
// ============================================

/**
 * Ordenar tabla por columna
 */
function sortTable(tableSelector, sortBy) {
    const table = document.querySelector(tableSelector);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(.no-results-row)'));

    rows.sort((a, b) => {
        let aValue, bValue;

        switch (sortBy) {
            case 'newest':
                aValue = new Date(a.dataset.created || 0);
                bValue = new Date(b.dataset.created || 0);
                return bValue - aValue;

            case 'oldest':
                aValue = new Date(a.dataset.created || 0);
                bValue = new Date(b.dataset.created || 0);
                return aValue - bValue;

            case 'name':
                aValue = (a.dataset.name || a.querySelector('td:nth-child(2)')?.textContent || '').toLowerCase();
                bValue = (b.dataset.name || b.querySelector('td:nth-child(2)')?.textContent || '').toLowerCase();
                return aValue.localeCompare(bValue);

            case 'email':
                aValue = (a.dataset.email || a.querySelector('td:nth-child(3)')?.textContent || '').toLowerCase();
                bValue = (b.dataset.email || b.querySelector('td:nth-child(3)')?.textContent || '').toLowerCase();
                return aValue.localeCompare(bValue);

            case 'last-login':
                aValue = new Date(a.dataset.lastLogin || 0);
                bValue = new Date(b.dataset.lastLogin || 0);
                return bValue - aValue;

            default:
                return 0;
        }
    });

    // Reordenar filas
    rows.forEach(row => tbody.appendChild(row));
}

// ============================================
// EXPORTACIÓN DE DATOS
// ============================================

/**
 * Exportar usuarios a CSV
 */
function exportUsersToCSV() {
    const table = document.querySelector('.user-table-container table');
    if (!table) {
        showNotification('No hay datos para exportar', 'warning');
        return;
    }

    const rows = table.querySelectorAll('tbody tr:not(.no-results-row)');
    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');

    if (visibleRows.length === 0) {
        showNotification('No hay usuarios visibles para exportar', 'warning');
        return;
    }

    // Crear CSV
    let csv = 'ID,Nombre,Email,Rol,Estado,Fecha de Registro\n';

    visibleRows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const id = row.dataset.id || '';
        const name = cells[1]?.textContent.trim() || '';
        const email = cells[2]?.textContent.trim() || '';
        const role = cells[3]?.textContent.trim() || '';
        const status = cells[4]?.textContent.trim() || '';
        const date = cells[5]?.textContent.trim() || '';

        csv += `"${id}","${name}","${email}","${role}","${status}","${date}"\n`;
    });

    // Descargar archivo
    downloadCSV(csv, `usuarios_${new Date().toISOString().split('T')[0]}.csv`);
    showNotification(`${visibleRows.length} usuarios exportados correctamente`, 'success');
}

/**
 * Exportar contenido a CSV
 */
function exportContentToCSV(type) {
    const table = document.querySelector(`.data-table[data-type="${type}"]`);
    if (!table) {
        showNotification('No hay datos para exportar', 'warning');
        return;
    }

    const rows = table.querySelectorAll('tbody tr');
    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');

    if (visibleRows.length === 0) {
        showNotification('No hay contenido visible para exportar', 'warning');
        return;
    }

    // Crear CSV
    let csv = 'ID,Título,Año,Géneros,IMDb,Premium,Destacado\n';

    visibleRows.forEach(row => {
        const id = row.dataset.id || '';
        const title = row.dataset.title || '';
        const year = row.dataset.year || '';
        const cells = row.querySelectorAll('td');
        const genres = cells[3]?.textContent.trim() || '';
        const imdb = cells[5]?.textContent.trim() || '';
        const premium = cells[6]?.textContent.includes('check') ? 'Sí' : 'No';
        const featured = cells[7]?.textContent.includes('check') ? 'Sí' : 'No';

        csv += `"${id}","${title}","${year}","${genres}","${imdb}","${premium}","${featured}"\n`;
    });

    // Descargar archivo
    const filename = `${type}_${new Date().toISOString().split('T')[0]}.csv`;
    downloadCSV(csv, filename);
    showNotification(`${visibleRows.length} elementos exportados correctamente`, 'success');
}

/**
 * Descargar archivo CSV
 */
function downloadCSV(csvContent, filename) {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');

    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, filename);
    } else {
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// ============================================
// ACCIONES MASIVAS
// ============================================

/**
 * Seleccionar/deseleccionar todas las filas
 */
function initBulkActions() {
    const selectAllCheckboxes = document.querySelectorAll('.select-all-checkbox');

    selectAllCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            const table = this.closest('table');
            const rowCheckboxes = table.querySelectorAll('tbody input[type="checkbox"]');

            rowCheckboxes.forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = this.checked;
                }
            });

            updateBulkActionsBar();
        });
    });
}

/**
 * Actualizar barra de acciones masivas
 */
function updateBulkActionsBar() {
    const selectedCheckboxes = document.querySelectorAll('tbody input[type="checkbox"]:checked');
    const bulkBar = document.getElementById('bulk-actions-bar');

    if (!bulkBar) return;

    if (selectedCheckboxes.length > 0) {
        bulkBar.style.display = 'flex';
        bulkBar.querySelector('.selected-count').textContent = selectedCheckboxes.length;
    } else {
        bulkBar.style.display = 'none';
    }
}

// ============================================
// VALIDACIONES
// ============================================

/**
 * Validar formulario de contenido
 */
function validateContentForm(formData) {
    const errors = [];

    // Título requerido
    if (!formData.get('title')?.trim()) {
        errors.push('El título es obligatorio');
    }

    // Año válido
    const year = parseInt(formData.get('release_year'));
    if (!year || year < 1900 || year > new Date().getFullYear() + 5) {
        errors.push('El año debe estar entre 1900 y ' + (new Date().getFullYear() + 5));
    }

    // Duración válida
    const duration = parseInt(formData.get('duration'));
    if (!duration || duration < 1) {
        errors.push('La duración debe ser mayor a 0');
    }

    // Descripción requerida
    if (!formData.get('description')?.trim()) {
        errors.push('La descripción es obligatoria');
    }

    // Video requerido (URL o archivo)
    const videoSource = formData.get('video_source');
    if (videoSource === 'url' && !formData.get('video_url')?.trim()) {
        errors.push('Debes proporcionar una URL de video');
    } else if (videoSource === 'file' && !formData.get('video_file')) {
        errors.push('Debes seleccionar un archivo de video');
    } else if (!videoSource) {
        errors.push('Debes seleccionar una fuente de video');
    }

    return errors;
}

/**
 * Validar formulario de usuario
 */
function validateUserForm(formData, isEdit = false) {
    const errors = [];

    // Username requerido
    const username = formData.get('username')?.trim();
    if (!username) {
        errors.push('El nombre de usuario es obligatorio');
    } else if (username.length < 3) {
        errors.push('El nombre de usuario debe tener al menos 3 caracteres');
    } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        errors.push('El nombre de usuario solo puede contener letras, números y guiones bajos');
    }

    // Email válido
    const email = formData.get('email')?.trim();
    if (!email) {
        errors.push('El email es obligatorio');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.push('El email no es válido');
    }

    // Contraseña (solo para nuevos usuarios)
    const password = formData.get('password');
    if (!isEdit && (!password || password.length < 8)) {
        errors.push('La contraseña debe tener al menos 8 caracteres');
    }

    // Confirmar contraseña
    if (password && password !== formData.get('password_confirm')) {
        errors.push('Las contraseñas no coinciden');
    }

    return errors;
}

// ============================================
// INICIALIZACIÓN
// ============================================

/**
 * Inicializar todas las funcionalidades mejoradas
 */
function initEnhancedFeatures() {
    initTableSearch();
    initUserFilters();
    initBulkActions();

    // Exportar usuarios
    const exportUsersBtn = document.getElementById('export-users-btn');
    if (exportUsersBtn) {
        exportUsersBtn.addEventListener('click', exportUsersToCSV);
    }

    // Exportar contenido
    const exportContentBtns = document.querySelectorAll('[id^="export-"][id$="-btn"]');
    exportContentBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const type = this.id.replace('export-', '').replace('-btn', '');
            exportContentToCSV(type);
        });
    });
}

// Exportar funciones globalmente
window.initEnhancedFeatures = initEnhancedFeatures;
window.initTableSearch = initTableSearch;
window.exportUsersToCSV = exportUsersToCSV;
window.exportContentToCSV = exportContentToCSV;
window.validateContentForm = validateContentForm;
window.validateUserForm = validateUserForm;
window.applyFilters = applyFilters;
window.sortTable = sortTable;
