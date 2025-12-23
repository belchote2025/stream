// Script de prueba para el formulario de películas
// Uso: Abre la consola del navegador y copia/pega este código

async function testMovieUpload() {
    // URL base de la API
    const baseUrl = window.location.origin;
    
    // Datos de prueba para la película
    const movieData = {
        title: 'Película de Prueba ' + new Date().getTime(),
        description: 'Esta es una película de prueba creada automáticamente',
        release_year: new Date().getFullYear(),
        duration: 120,
        age_rating: 'PG-13',
        is_featured: 1,
        is_trending: 0,
        is_premium: 1,
        genres: [1, 2, 3] // IDs de géneros existentes
    };

    console.log('Iniciando prueba de carga de película...');
    console.log('Datos de prueba:', movieData);

    try {
        // 1. Crear la película
        console.log('\n1. Creando película...');
        const createResponse = await fetch(`${baseUrl}/api/movies/index.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(movieData)
        });

        const createResult = await createResponse.json();
        
        if (!createResponse.ok) {
            throw new Error(createResult.error || 'Error al crear la película');
        }

        console.log('✅ Película creada exitosamente:', createResult);
        const movieId = createResult.data?.id;
        
        if (!movieId) {
            throw new Error('No se recibió el ID de la película');
        }

        // 2. Obtener la película recién creada
        console.log('\n2. Obteniendo detalles de la película...');
        const getResponse = await fetch(`${baseUrl}/api/movies/index.php?id=${movieId}`);
        const movieDetails = await getResponse.json();
        
        if (!getResponse.ok) {
            throw new Error(movieDetails.error || 'Error al obtener los detalles de la película');
        }

        console.log('✅ Detalles de la película:', movieDetails);

        // 3. Actualizar la película
        console.log('\n3. Actualizando la película...');
        const updateData = {
            id: movieId,
            title: `[Actualizado] ${movieData.title}`,
            description: `${movieData.description} (actualizada)`,
            is_featured: 0
        };

        const updateResponse = await fetch(`${baseUrl}/api/movies/index.php?id=${movieId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(updateData)
        });

        const updateResult = await updateResponse.json();
        
        if (!updateResponse.ok) {
            throw new Error(updateResult.error || 'Error al actualizar la película');
        }

        console.log('✅ Película actualizada exitosamente:', updateResult);

        // 4. Listar todas las películas
        console.log('\n4. Listando películas...');
        const listResponse = await fetch(`${baseUrl}/api/movies/index.php`);
        const moviesList = await listResponse.json();
        
        if (!listResponse.ok) {
            throw new Error(moviesList.error || 'Error al listar las películas');
        }

        console.log(`✅ Se encontraron ${moviesList.data?.length || 0} películas`);
        
        // 5. Opcional: Eliminar la película de prueba
        const confirmDelete = confirm('¿Deseas eliminar la película de prueba?');
        if (confirmDelete) {
            console.log('\n5. Eliminando película de prueba...');
            const deleteResponse = await fetch(`${baseUrl}/api/movies/index.php?id=${movieId}`, {
                method: 'DELETE'
            });
            
            const deleteResult = await deleteResponse.json();
            
            if (!deleteResponse.ok) {
                throw new Error(deleteResult.error || 'Error al eliminar la película');
            }
            
            console.log('✅ Película eliminada exitosamente:', deleteResult);
        }

    } catch (error) {
        console.error('❌ Error en la prueba:', error);
        alert(`Error en la prueba: ${error.message}`);
    }
}

// Ejecutar la prueba al cargar la página
console.log('Script de prueba cargado. Ejecuta testMovieUpload() para comenzar.');

// Hacer la función accesible desde la consola
window.testMovieUpload = testMovieUpload;
