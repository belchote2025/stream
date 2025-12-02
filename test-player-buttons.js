/**
 * Script de diagnóstico para probar los botones del reproductor
 * Ejecutar en la consola del navegador o incluir en la página
 */

(function() {
    'use strict';

    console.log('%c🔍 DIAGNÓSTICO DE BOTONES DEL REPRODUCTOR', 'color: #e50914; font-size: 16px; font-weight: bold;');
    console.log('================================================');

    // Función para probar los botones
    function testPlayerButtons() {
        const results = {
            totalButtons: 0,
            buttonsWithListeners: 0,
            buttonsWithoutListeners: 0,
            buttonsWithDataAction: 0,
            buttonsWithDataId: 0,
            contentCards: 0,
            issues: []
        };

        // Buscar todos los botones de reproducción
        const playButtons = document.querySelectorAll('[data-action="play"]');
        const actionButtons = document.querySelectorAll('.action-btn[data-action="play"]');
        const btnPlayButtons = document.querySelectorAll('.btn-play[data-action="play"]');
        
        // Combinar todos los botones encontrados
        const allButtons = new Set();
        playButtons.forEach(btn => allButtons.add(btn));
        actionButtons.forEach(btn => allButtons.add(btn));
        btnPlayButtons.forEach(btn => allButtons.add(btn));

        results.totalButtons = allButtons.size;
        console.log(`\n📊 Total de botones encontrados: ${results.totalButtons}`);

        // Buscar todas las fichas de contenido
        const contentCards = document.querySelectorAll('.content-card');
        results.contentCards = contentCards.length;
        console.log(`📋 Total de fichas de contenido: ${results.contentCards}`);

        // Verificar cada botón
        allButtons.forEach((button, index) => {
            const buttonInfo = {
                index: index + 1,
                element: button,
                hasDataAction: button.hasAttribute('data-action'),
                hasDataId: button.hasAttribute('data-id'),
                dataId: button.getAttribute('data-id'),
                dataType: button.getAttribute('data-type'),
                className: button.className,
                parentCard: button.closest('.content-card'),
                hasListener: false,
                issues: []
            };

            // Verificar atributos
            if (buttonInfo.hasDataAction) results.buttonsWithDataAction++;
            if (buttonInfo.hasDataId) results.buttonsWithDataId++;

            // Verificar si tiene event listener (método aproximado)
            // Nota: No podemos detectar listeners directamente, pero podemos verificar si hay listeners globales
            const hasGlobalListener = document.addEventListener.toString().includes('click');
            buttonInfo.hasListener = hasGlobalListener;

            // Verificar problemas
            if (!buttonInfo.hasDataId) {
                buttonInfo.issues.push('❌ Falta atributo data-id');
                results.issues.push(`Botón ${index + 1}: Falta data-id`);
            }

            if (!buttonInfo.parentCard) {
                buttonInfo.issues.push('⚠️ No está dentro de un .content-card');
            }

            // Verificar si el botón está visible
            const style = window.getComputedStyle(button);
            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                buttonInfo.issues.push('⚠️ Botón no visible');
            }

            // Mostrar información del botón
            if (buttonInfo.issues.length > 0 || index < 5) {
                console.log(`\n🔘 Botón ${index + 1}:`, {
                    'data-id': buttonInfo.dataId || 'NO DEFINIDO',
                    'data-type': buttonInfo.dataType || 'NO DEFINIDO',
                    'clase': buttonInfo.className,
                    'dentro de .content-card': buttonInfo.parentCard ? '✅' : '❌',
                    'problemas': buttonInfo.issues.length > 0 ? buttonInfo.issues : ['✅ Sin problemas']
                });
            }
        });

        // Verificar event listeners globales
        console.log('\n📡 Verificando event listeners globales...');
        
        // Verificar si hay listeners en document
        const hasDocumentClickListener = document.onclick !== null || 
            (document.addEventListener && typeof document.addEventListener === 'function');
        
        console.log('  - Listener en document:', hasDocumentClickListener ? '✅' : '❌');

        // Verificar funciones globales
        const globalFunctions = {
            'playContent': typeof window.playContent === 'function',
            'handlePlayContent': typeof window.handlePlayContent === 'function',
            'initEventListeners': typeof window.initEventListeners === 'function'
        };

        console.log('\n🔧 Funciones globales disponibles:');
        Object.entries(globalFunctions).forEach(([name, exists]) => {
            console.log(`  - ${name}:`, exists ? '✅' : '❌');
        });

        // Probar hacer clic en un botón (simulado)
        console.log('\n🧪 Prueba de clic simulado...');
        if (allButtons.size > 0) {
            const firstButton = Array.from(allButtons)[0];
            const contentId = firstButton.getAttribute('data-id');
            const contentType = firstButton.getAttribute('data-type') || firstButton.closest('.content-card')?.getAttribute('data-type') || 'movie';
            
            console.log('  - Botón seleccionado:', {
                'data-id': contentId,
                'data-type': contentType,
                'clase': firstButton.className
            });
            
            // Verificar si existe la función playContent
            if (typeof window.playContent === 'function') {
                console.log('  - ✅ Función playContent encontrada');
            } else {
                console.warn('  - ⚠️ Función playContent no encontrada globalmente');
            }
            
            // Verificar si existe el modal
            const videoModal = document.getElementById('videoPlayerModal');
            const videoPlayer = document.querySelector('.video-player');
            console.log('  - Modal videoPlayerModal:', videoModal ? '✅ Encontrado' : '❌ No encontrado');
            console.log('  - .video-player:', videoPlayer ? '✅ Encontrado' : '❌ No encontrado');
            
            // NO hacer clic automático - solo verificar que el botón existe
            console.log('  - ✅ Botón encontrado y listo para pruebas manuales');
            console.log('  - 💡 Para probar el clic, ejecuta: testRealClick(0) en la consola');
        }

        // Verificar estructura HTML
        console.log('\n🏗️ Verificando estructura HTML...');
        if (contentCards.length > 0) {
            const firstCard = contentCards[0];
            const cardStructure = {
                'Tiene .content-card': true,
                'Tiene botón play': firstCard.querySelector('[data-action="play"]') ? '✅' : '❌',
                'Tiene .content-actions': firstCard.querySelector('.content-actions') ? '✅' : '❌',
                'Tiene .content-overlay': firstCard.querySelector('.content-overlay') ? '✅' : '❌',
                'Tiene data-id': firstCard.hasAttribute('data-id') ? '✅' : '❌',
                'Tiene data-type': firstCard.hasAttribute('data-type') ? '✅' : '❌'
            };
            console.log('  Estructura de la primera ficha:', cardStructure);
        }

        // Resumen
        console.log('\n📊 RESUMEN:');
        console.log('================================================');
        console.log(`Total botones: ${results.totalButtons}`);
        console.log(`Botones con data-action: ${results.buttonsWithDataAction}`);
        console.log(`Botones con data-id: ${results.buttonsWithDataId}`);
        console.log(`Fichas de contenido: ${results.contentCards}`);
        console.log(`Problemas encontrados: ${results.issues.length}`);
        
        if (results.issues.length > 0) {
            console.log('\n❌ PROBLEMAS DETECTADOS:');
            results.issues.forEach(issue => console.log(`  - ${issue}`));
        } else {
            console.log('\n✅ No se encontraron problemas obvios en los botones');
        }

        // Recomendaciones
        console.log('\n💡 RECOMENDACIONES:');
        if (results.totalButtons === 0) {
            console.log('  ⚠️ No se encontraron botones. Verificar:');
            console.log('    1. Si las fichas se están cargando dinámicamente');
            console.log('    2. Si los selectores CSS son correctos');
            console.log('    3. Si hay errores en la consola');
        } else if (results.buttonsWithDataId === 0) {
            console.log('  ⚠️ Los botones no tienen data-id. Verificar:');
            console.log('    1. La función createContentCard()');
            console.log('    2. Los atributos data-* en el HTML generado');
        } else if (!globalFunctions.playContent) {
            console.log('  ⚠️ La función playContent() no está disponible. Verificar:');
            console.log('    1. Si el archivo main.js se está cargando');
            console.log('    2. Si hay errores de JavaScript');
        } else {
            console.log('  ✅ Estructura básica correcta');
            console.log('  🔍 Verificar event listeners y delegación de eventos');
        }

        return results;
    }

    // Función para probar un clic real
    function testRealClick(buttonIndex = 0) {
        const buttons = document.querySelectorAll('[data-action="play"]');
        if (buttons.length === 0) {
            console.error('❌ No se encontraron botones para probar');
            return;
        }

        const button = buttons[buttonIndex];
        if (!button) {
            console.error(`❌ No existe el botón en el índice ${buttonIndex}`);
            return;
        }

        console.log(`\n🖱️ Probando clic real en botón ${buttonIndex + 1}...`);
        console.log('  - Botón:', button);
        console.log('  - data-id:', button.getAttribute('data-id'));
        console.log('  - data-type:', button.getAttribute('data-type'));
        
        // Hacer scroll hasta el botón
        button.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Esperar un momento y hacer clic
        setTimeout(() => {
            console.log('  - Disparando clic...');
            button.click();
            console.log('  - ✅ Clic disparado');
            
            // Verificar si se abrió el reproductor
            setTimeout(() => {
                const videoModal = document.getElementById('videoPlayerModal');
                const videoPlayer = document.querySelector('.video-player');
                const modalPlayer = document.getElementById('contentPlayer');
                
                console.log('\n📺 Verificando si se abrió el reproductor...');
                console.log('  - Modal videoPlayerModal:', videoModal ? '✅ Encontrado' : '❌ No encontrado');
                console.log('  - .video-player:', videoPlayer ? '✅ Encontrado' : '❌ No encontrado');
                console.log('  - #contentPlayer:', modalPlayer ? '✅ Encontrado' : '❌ No encontrado');
                
                if (videoModal) {
                    const modalInstance = bootstrap?.Modal?.getInstance(videoModal);
                    console.log('  - Modal visible:', modalInstance?.isShown ? '✅' : '❌');
                }
                
                if (videoPlayer) {
                    console.log('  - .video-player activo:', videoPlayer.classList.contains('active') ? '✅' : '❌');
                }
            }, 500);
        }, 500);
    }

    // Función para monitorear eventos de clic
    function monitorClicks() {
        console.log('\n👂 Monitoreando eventos de clic...');
        
        document.addEventListener('click', function(e) {
            const button = e.target.closest('[data-action="play"]');
            if (button) {
                console.log('\n🖱️ CLIC DETECTADO EN BOTÓN PLAY:');
                console.log('  - Botón:', button);
                console.log('  - data-id:', button.getAttribute('data-id'));
                console.log('  - data-type:', button.getAttribute('data-type'));
                console.log('  - Elemento original:', e.target);
                console.log('  - Timestamp:', new Date().toISOString());
                
                // Verificar si hay un preventDefault y si se abrió el modal
                setTimeout(() => {
                    console.log('  - Verificando después del clic...');
                    const videoModal = document.getElementById('videoPlayerModal');
                    const videoPlayer = document.querySelector('.video-player');
                    
                    if (videoModal) {
                        const modalInstance = bootstrap?.Modal?.getInstance(videoModal);
                        const isShown = modalInstance?.isShown || videoModal.classList.contains('show');
                        console.log('    - Modal videoPlayerModal abierto:', isShown ? '✅ SÍ' : '❌ NO');
                        
                        if (!isShown) {
                            console.warn('    - ⚠️ PROBLEMA DETECTADO: El modal no se abrió');
                            console.warn('    - Verificando posibles causas...');
                            
                            // Verificar si hay errores en la consola
                            console.warn('    - Revisa la consola por errores de JavaScript');
                            
                            // Verificar si la función playContent existe
                            if (typeof window.playContent !== 'function') {
                                console.error('    - ❌ ERROR: window.playContent no es una función');
                            } else {
                                console.log('    - ✅ window.playContent existe');
                            }
                            
                            // Verificar si hay elementos del modal
                            const modalPlayer = document.getElementById('contentPlayer');
                            const videoTitle = document.getElementById('videoPlayerTitle');
                            console.log('    - #contentPlayer:', modalPlayer ? '✅' : '❌');
                            console.log('    - #videoPlayerTitle:', videoTitle ? '✅' : '❌');
                        }
                    } else {
                        console.error('    - ❌ ERROR: No se encontró el modal videoPlayerModal');
                    }
                    
                    if (videoPlayer) {
                        const isActive = videoPlayer.classList.contains('active');
                        console.log('    - .video-player activo:', isActive ? '✅ SÍ' : '❌ NO');
                    }
                }, 500);
            }
        }, true); // Usar capture phase
        
        console.log('  ✅ Monitor activado. Haz clic en cualquier botón de reproducción.');
    }

    // Exponer funciones globalmente
    window.testPlayerButtons = testPlayerButtons;
    window.testRealClick = testRealClick;
    window.monitorClicks = monitorClicks;

    // Ejecutar diagnóstico automáticamente después de que se cargue el contenido dinámico
    function autoRunDiagnostics() {
        // Esperar a que se carguen las fichas dinámicas
        const checkInterval = setInterval(() => {
            const contentCards = document.querySelectorAll('.content-card');
            const playButtons = document.querySelectorAll('[data-action="play"]');
            
            // Si hay fichas o botones, ejecutar diagnóstico
            if (contentCards.length > 0 || playButtons.length > 0) {
                clearInterval(checkInterval);
                console.log('\n⏳ Esperando a que se cargue el contenido dinámico...');
                
                // Esperar un poco más para que se complete la carga
                setTimeout(() => {
                    console.log('\n🚀 Ejecutando diagnóstico automático...\n');
                    testPlayerButtons();
                    
                    // También activar el monitor de clics automáticamente
                    console.log('\n👂 Activando monitor de clics automáticamente...');
                    monitorClicks();
                }, 1500);
            }
        }, 500);
        
        // Timeout de seguridad después de 10 segundos
        setTimeout(() => {
            clearInterval(checkInterval);
            const contentCards = document.querySelectorAll('.content-card');
            if (contentCards.length === 0) {
                console.log('\n⚠️ No se encontraron fichas de contenido después de 10 segundos.');
                console.log('💡 Puede que el contenido se cargue más tarde. Ejecuta manualmente: testPlayerButtons()');
            }
        }, 10000);
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(autoRunDiagnostics, 1000);
        });
    } else {
        setTimeout(autoRunDiagnostics, 1000);
    }

    console.log('\n✅ Script de diagnóstico cargado y ejecutándose automáticamente');
    console.log('💡 Funciones disponibles en la consola:');
    console.log('   - testPlayerButtons() - Ejecutar diagnóstico completo');
    console.log('   - testRealClick(0) - Probar clic en el primer botón');
    console.log('   - monitorClicks() - Monitorear todos los clics');
    console.log('\n');

})();

