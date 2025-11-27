/**
 * Script de autenticación para la plataforma de streaming
 * Maneja la interacción del usuario en las páginas de inicio de sesión, registro y recuperación de contraseña
 */

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes
    initPasswordToggles();
    initPasswordStrengthMeter();
    initPasswordMatchChecker();
    initFormValidations();
});

/**
 * Inicializa los botones para mostrar/ocultar contraseña
 */
function initPasswordToggles() {
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                this.setAttribute('aria-label', 'Ocultar contraseña');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                this.setAttribute('aria-label', 'Mostrar contraseña');
            }
        });
    });
}

/**
 * Inicializa el medidor de fortaleza de contraseña
 */
function initPasswordStrengthMeter() {
    const passwordInput = document.getElementById('password');
    if (!passwordInput) return;
    
    const strengthMeter = document.querySelector('.strength-meter');
    const strengthText = document.querySelector('.strength-text span');
    
    if (!strengthMeter || !strengthText) return;
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = calculatePasswordStrength(password);
        
        // Actualizar la barra de fortaleza
        strengthMeter.style.width = strength.percentage + '%';
        strengthMeter.style.backgroundColor = strength.color;
        
        // Actualizar el texto
        strengthText.textContent = strength.text;
        strengthText.style.color = strength.color;
    });
}

/**
 * Calcula la fortaleza de una contraseña
 * @param {string} password - La contraseña a evaluar
 * @returns {Object} - Objeto con la información de fortaleza
 */
function calculatePasswordStrength(password) {
    let strength = 0;
    let messages = [];
    
    // Longitud mínima
    if (password.length >= 8) strength += 1;
    
    // Contiene letras mayúsculas y minúsculas
    if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1;
    
    // Contiene números
    if (password.match(/([0-9])/)) strength += 1;
    
    // Contiene caracteres especiales
    if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) strength += 1;
    
    // Longitud mayor a 12 caracteres
    if (password.length > 12) strength += 1;
    
    // Determinar el nivel de fortaleza
    let result = {
        percentage: 0,
        color: '#e74c3c', // Rojo por defecto
        text: 'Débil'
    };
    
    if (strength <= 2) {
        result.percentage = 25;
        result.color = '#e74c3c'; // Rojo
        result.text = 'Débil';
    } else if (strength === 3) {
        result.percentage = 50;
        result.color = '#f39c12'; // Naranja
        result.text = 'Moderada';
    } else if (strength === 4) {
        result.percentage = 75;
        result.color = '#3498db'; // Azul
        result.text = 'Fuerte';
    } else {
        result.percentage = 100;
        result.color = '#2ecc71'; // Verde
        result.text = 'Muy fuerte';
    }
    
    return result;
}

/**
 * Inicializa la verificación de coincidencia de contraseñas
 */
function initPasswordMatchChecker() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (!passwordInput || !confirmPasswordInput) return;
    
    const passwordMatch = document.querySelector('.password-match');
    
    function checkPasswords() {
        if (!passwordInput.value && !confirmPasswordInput.value) {
            passwordMatch.style.opacity = '0';
            return;
        }
        
        if (passwordInput.value === confirmPasswordInput.value && passwordInput.value.length >= 8) {
            passwordMatch.style.opacity = '1';
            passwordMatch.querySelector('i').className = 'fas fa-check-circle';
            passwordMatch.querySelector('i').style.color = '#2ecc71';
            passwordMatch.querySelector('span').textContent = 'Las contraseñas coinciden';
            passwordMatch.querySelector('span').style.color = '#2ecc71';
        } else {
            passwordMatch.style.opacity = '1';
            passwordMatch.querySelector('i').className = 'fas fa-times-circle';
            passwordMatch.querySelector('i').style.color = '#e74c3c';
            passwordMatch.querySelector('span').textContent = 'Las contraseñas no coinciden';
            passwordMatch.querySelector('span').style.color = '#e74c3c';
        }
    }
    
    passwordInput.addEventListener('input', checkPasswords);
    confirmPasswordInput.addEventListener('input', checkPasswords);
    
    // Inicializar el estado
    checkPasswords();
}

/**
 * Inicializa las validaciones de formulario
 */
function initFormValidations() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Validación personalizada para el formulario de registro
        if (form.id === 'registerForm') {
            form.addEventListener('submit', function(e) {
                const termsCheckbox = document.getElementById('terms');
                const passwordInput = document.getElementById('password');
                const confirmPasswordInput = document.getElementById('confirm_password');
                
                // Validar términos y condiciones
                if (!termsCheckbox.checked) {
                    e.preventDefault();
                    showAlert('Debes aceptar los términos y condiciones para continuar.', 'error');
                    termsCheckbox.focus();
                    return false;
                }
                
                // Validar coincidencia de contraseñas
                if (passwordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    showAlert('Las contraseñas no coinciden. Por favor, verifica e intenta de nuevo.', 'error');
                    confirmPasswordInput.focus();
                    return false;
                }
                
                // Validar fortaleza de contraseña
                const strength = calculatePasswordStrength(passwordInput.value);
                if (strength.text === 'Débil') {
                    const confirmMessage = 'La contraseña es débil. ¿Estás seguro de que deseas continuar?';
                    if (!confirm(confirmMessage)) {
                        e.preventDefault();
                        passwordInput.focus();
                        return false;
                    }
                }
                
                return true;
            });
        }
        
        // Validación para el formulario de inicio de sesión
        if (form.id === 'loginForm') {
            form.addEventListener('submit', function(e) {
                const emailInput = form.querySelector('input[type="email"]');
                const passwordInput = form.querySelector('input[type="password"]');
                
                if (!emailInput.value || !passwordInput.value) {
                    e.preventDefault();
                    showAlert('Por favor, completa todos los campos obligatorios.', 'error');
                    return false;
                }
                
                return true;
            });
        }
    });
}

/**
 * Muestra un mensaje de alerta
 * @param {string} message - El mensaje a mostrar
 * @param {string} type - El tipo de alerta (success, error, warning, info)
 */
function showAlert(message, type = 'info') {
    // Crear elemento de alerta
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    
    // Icono según el tipo de alerta
    let iconClass = 'fa-info-circle';
    if (type === 'success') iconClass = 'fa-check-circle';
    else if (type === 'error') iconClass = 'fa-exclamation-circle';
    else if (type === 'warning') iconClass = 'fa-exclamation-triangle';
    
    alertDiv.innerHTML = `
        <i class="fas ${iconClass}"></i>
        <span>${message}</span>
    `;
    
    // Insertar antes del primer elemento del formulario
    const form = document.querySelector('form');
    if (form) {
        form.insertBefore(alertDiv, form.firstChild);
        
        // Eliminar la alerta después de 5 segundos
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            setTimeout(() => {
                alertDiv.remove();
            }, 300);
        }, 5000);
    }
}

/**
 * Maneja los errores de formulario
 * @param {string} fieldId - El ID del campo con error
 * @param {string} message - El mensaje de error
 */
function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    // Eliminar mensajes de error existentes
    const existingError = field.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Agregar clase de error al campo
    field.classList.add('is-invalid');
    
    // Crear y agregar mensaje de error
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    errorDiv.style.color = '#e74c3c';
    errorDiv.style.fontSize = '0.8rem';
    errorDiv.style.marginTop = '0.25rem';
    
    field.parentNode.appendChild(errorDiv);
    
    // Enfocar el campo con error
    field.focus();
}

/**
 * Limpia los errores de un campo
 * @param {string} fieldId - El ID del campo
 */
function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    field.classList.remove('is-invalid');
    
    const errorMessage = field.parentNode.querySelector('.error-message');
    if (errorMessage) {
        errorMessage.remove();
    }
}

// Hacer las funciones accesibles globalmente si es necesario
window.showAlert = showAlert;
window.showFieldError = showFieldError;
window.clearFieldError = clearFieldError;
