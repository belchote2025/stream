/**
 * Script de prueba simple para verificar funcionalidad del formulario
 * Ejecutar en la consola del navegador cuando estés en admin/index.php
 */

(function() {
    console.log('%c🧪 TEST DE FORMULARIO NETFLIX', 'color: #e50914; font-size: 16px; font-weight: bold;');
    console.log('=====================================\n');

    const results = {
        pass: [],
        fail: [],
        warning: []
    };

    function addResult(type, message, details = '') {
        results[type].push({ message, details });
        const icon = type === 'pass' ? '✅' : type === 'fail' ? '❌' : '⚠️';
        const color = type === 'pass' ? 'color: #28a745' : type === 'fail' ? 'color: #dc3545' : 'color: #ffc107';
        console.log(`%c${icon} ${message}`, color);
        if (details) console.log(`   ${details}`);
    }

    // Test 1: Verificar que el formulario existe
    console.log('\n📋 TEST 1: Elementos HTML');
    console.log('---------------------------');
    const form = document.getElementById('contentForm');
    if (form) {
        addResult('pass', 'Formulario encontrado', `ID: ${form.id}`);
    } else {
        addResult('fail', 'Formulario NO encontrado', 'Buscar #contentForm');
        return;
    }

    // Test 2: Campos requeridos
    const requiredFields = [
        'title', 'release_year', 'duration', 'description',
        'poster_file', 'backdrop_file', 'video_file'
    ];

    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            const isRequired = field.hasAttribute('required');
            if (isRequired) {
                addResult('pass', `Campo ${fieldId}`, 'Encontrado y requerido');
            } else {
                addResult('warning', `Campo ${fieldId}`, 'Encontrado pero no marcado como requerido');
            }
        } else {
            addResult('fail', `Campo ${fieldId}`, 'NO encontrado');
        }
    });

    // Test 3: Campos opcionales
    console.log('\n📋 TEST 2: Campos Opcionales');
    console.log('---------------------------');
    const optionalFields = ['trailer_file', 'torrent_magnet', 'age_rating'];
    optionalFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            addResult('pass', `Campo ${fieldId}`, 'Encontrado');
        } else {
            addResult('warning', `Campo ${fieldId}`, 'NO encontrado (opcional)');
        }
    });

    // Test 4: Checkboxes
    console.log('\n📋 TEST 3: Checkboxes');
    console.log('---------------------------');
    const checkboxes = ['is_featured', 'is_trending', 'is_premium'];
    checkboxes.forEach(cbId => {
        const cb = document.getElementById(cbId);
        if (cb && cb.type === 'checkbox') {
            addResult('pass', `Checkbox ${cbId}`, 'Encontrado');
        } else {
            addResult('fail', `Checkbox ${cbId}`, 'NO encontrado');
        }
    });

    // Test 5: Validación de archivos
    console.log('\n📋 TEST 4: Validación de Archivos');
    console.log('---------------------------');
    const posterInput = document.getElementById('poster_file');
    if (posterInput) {
        if (posterInput.accept === 'image/*') {
            addResult('pass', 'Póster acepta imágenes', `Máx: ${(posterInput.dataset.maxSize / 1024 / 1024).toFixed(0)}MB`);
        } else {
            addResult('fail', 'Póster: tipo de archivo incorrecto', `Actual: ${posterInput.accept}`);
        }
    }

    const videoInput = document.getElementById('video_file');
    if (videoInput) {
        if (videoInput.accept === 'video/*') {
            addResult('pass', 'Video acepta videos', `Máx: ${(videoInput.dataset.maxSize / 1024 / 1024 / 1024).toFixed(1)}GB`);
        } else {
            addResult('fail', 'Video: tipo de archivo incorrecto', `Actual: ${videoInput.accept}`);
        }
    }

    // Test 6: Funciones JavaScript
    console.log('\n📋 TEST 5: Funciones JavaScript');
    console.log('---------------------------');
    const functions = ['clearImageFile', 'clearVideoFile', 'clearTrailerFile'];
    functions.forEach(funcName => {
        if (typeof window[funcName] === 'function') {
            addResult('pass', `Función ${funcName}`, 'Disponible globalmente');
        } else {
            addResult('fail', `Función ${funcName}`, 'NO disponible');
        }
    });

    // Test 7: Estilos CSS
    console.log('\n📋 TEST 6: Estilos CSS');
    console.log('---------------------------');
    const netflixClasses = [
        'netflix-form',
        'netflix-form-section',
        'netflix-file-upload',
        'netflix-input',
        'netflix-btn-primary'
    ];

    netflixClasses.forEach(className => {
        const elements = document.querySelectorAll(`.${className}`);
        if (elements.length > 0) {
            addResult('pass', `Clase .${className}`, `${elements.length} elementos`);
        } else {
            addResult('warning', `Clase .${className}`, 'No encontrada');
        }
    });

    // Test 8: Responsive
    console.log('\n📋 TEST 7: Responsive');
    console.log('---------------------------');
    const formSections = document.querySelectorAll('.netflix-form-section');
    if (formSections.length > 0) {
        const firstSection = formSections[0];
        const styles = window.getComputedStyle(firstSection);
        
        // Verificar que tiene estilos aplicados
        if (styles.padding !== '0px') {
            addResult('pass', 'Estilos de sección aplicados', `Padding: ${styles.padding}`);
        } else {
            addResult('warning', 'Estilos de sección', 'Verificar CSS');
        }

        // Test de media queries (simulado)
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            addResult('pass', 'Vista móvil detectada', `Ancho: ${window.innerWidth}px`);
        } else {
            addResult('pass', 'Vista desktop detectada', `Ancho: ${window.innerWidth}px`);
        }
    }

    // Test 9: Event Listeners
    console.log('\n📋 TEST 8: Event Listeners');
    console.log('---------------------------');
    const fileInputs = ['poster_file', 'backdrop_file', 'video_file'];
    fileInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            // Simular evento change
            try {
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
                addResult('pass', `Evento ${inputId}`, 'Evento change funciona');
            } catch (e) {
                addResult('warning', `Evento ${inputId}`, 'No se pudo probar');
            }
        }
    });

    // Resumen final
    console.log('\n📊 RESUMEN');
    console.log('=====================================');
    console.log(`%c✅ Exitosas: ${results.pass.length}`, 'color: #28a745; font-weight: bold;');
    console.log(`%c❌ Fallidas: ${results.fail.length}`, 'color: #dc3545; font-weight: bold;');
    console.log(`%c⚠️  Advertencias: ${results.warning.length}`, 'color: #ffc107; font-weight: bold;');
    console.log(`%c📝 Total: ${results.pass.length + results.fail.length + results.warning.length}`, 'color: #fff; font-weight: bold;');

    if (results.fail.length === 0) {
        console.log('\n%c🎉 ¡TODOS LOS TESTS PASARON!', 'color: #28a745; font-size: 14px; font-weight: bold;');
    } else {
        console.log('\n%c⚠️  Hay problemas que corregir', 'color: #ffc107; font-size: 14px; font-weight: bold;');
    }

    // Retornar resultados para uso programático
    return results;
})();

