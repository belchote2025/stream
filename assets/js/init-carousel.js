/**
 * Inicialización del carrusel de series recientes
 */
document.addEventListener('DOMContentLoaded', function () {
    // Verificar si el contenedor de series recientes existe
    const recentSeriesContainer = document.querySelector('#recent-series');
    if (!recentSeriesContainer) return;

    // Función para cargar las series
    async function loadRecentSeries() {
        try {
            // Mostrar indicador de carga
            recentSeriesContainer.innerHTML = '<div class="loading-skeleton" style="height: 300px; width: 100%;"></div>';

            // Construir la URL de la API usando el helper global
            const apiUrl = typeof getApiUrl === 'function'
                ? getApiUrl('/api/content/recent?type=series&limit=12')
                : (window.__APP_BASE_URL || '') + '/api/content/recent?type=series&limit=12';
            console.log('Fetching from:', apiUrl);

            const response = await fetch(apiUrl, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Error response:', errorText);
                throw new Error(`Error al cargar las series: ${response.status} ${response.statusText}`);
            }

            const result = await response.json();

            // Verificar si la respuesta tiene el formato esperado
            const data = result.success ? result.data : result;

            if (!data || (Array.isArray(data) && !data.length)) {
                recentSeriesContainer.innerHTML = '<p class="no-results">No se encontraron series recientes.</p>';
                return;
            }

            // Generar el HTML de las series
            const defaultPoster = typeof getAssetUrl === 'function'
                ? getAssetUrl('/assets/img/default-poster.svg')
                : (window.__APP_BASE_URL || '') + '/assets/img/default-poster.svg';

            const seriesHTML = data.map(series => {
                const posterUrl = series.poster_url || defaultPoster;
                return `
                <div class="content-item" data-id="${series.id}">
                    <div class="content-card">
                        <div class="content-poster">
                            <img 
                                src="${posterUrl}" 
                                alt="${series.title}"
                                loading="lazy"
                                onerror="this.onerror=null; this.src='${defaultPoster}'"
                            >
                            <div class="content-overlay">
                                <button class="btn-play" data-action="play" data-id="${series.id}">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button class="btn-info" data-action="info" data-id="${series.id}">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </div>
                        <div class="content-details">
                            <h3 class="content-title">${series.title}</h3>
                            ${series.release_year ? `<span class="content-year">${series.release_year}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
            }).join('');

            // Insertar las series en el contenedor
            recentSeriesContainer.innerHTML = `
                <div class="row-items">
                    ${seriesHTML}
                </div>
            `;

            // Inicializar la navegación del carrusel
            initCarouselNavigation('recent-series');

        } catch (error) {
            console.error('Error al cargar series recientes:', error);
            recentSeriesContainer.innerHTML = `
                <p class="error-message">
                    Error al cargar las series. 
                    <button class="btn-retry" onclick="location.reload()">Reintentar</button>
                </p>
            `;
        }
    }

    // Función para inicializar la navegación del carrusel
    function initCarouselNavigation(rowId) {
        const container = document.getElementById(rowId);
        if (!container) return;

        const row = container.querySelector('.row-items');
        // Buscar botones en el contenedor padre (donde suelen estar en el layout)
        const prevBtn = container.parentElement.querySelector('.prev');
        const nextBtn = container.parentElement.querySelector('.next');

        if (!row || !prevBtn || !nextBtn) return;

        const itemWidth = 220; // Ancho de cada tarjeta aproximado
        let scrollPosition = 0;

        // Función para actualizar estado de botones
        function updateNavButtons() {
            const maxScroll = row.scrollWidth - row.clientWidth;

            prevBtn.style.opacity = scrollPosition > 0 ? '1' : '0.3';
            prevBtn.style.pointerEvents = scrollPosition > 0 ? 'auto' : 'none';

            nextBtn.style.opacity = scrollPosition < maxScroll - 10 ? '1' : '0.3';
            nextBtn.style.pointerEvents = scrollPosition < maxScroll - 10 ? 'auto' : 'none';
        }

        // Navegación Next
        nextBtn.addEventListener('click', () => {
            const maxScroll = row.scrollWidth - row.clientWidth;
            scrollPosition = Math.min(scrollPosition + itemWidth * 3, maxScroll);
            row.style.transform = `translateX(-${scrollPosition}px)`;
            updateNavButtons();
        });

        // Navegación Prev
        prevBtn.addEventListener('click', () => {
            scrollPosition = Math.max(scrollPosition - itemWidth * 3, 0);
            row.style.transform = `translateX(-${scrollPosition}px)`;
            updateNavButtons();
        });

        // Actualizar botones al redimensionar
        window.addEventListener('resize', () => {
            updateNavButtons();
        });

        // Inicializar botones
        // Pequeño delay para asegurar que el DOM se renderizó bien
        setTimeout(updateNavButtons, 100);
    }

    // Cargar las series al iniciar
    loadRecentSeries();
});
