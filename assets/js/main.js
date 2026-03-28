// ============================================
// GESIFAR - JavaScript Principal
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('GESIFAR Sistema cargado correctamente');
    
    // Animación de las tarjetas
    const cards = document.querySelectorAll('.module-card, .stat-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Función para confirmar eliminaciones
function confirmarEliminacion(mensaje) {
    return confirm(mensaje || '¿Está seguro de eliminar este registro?');
}

// Función para validar formularios
function validarFormulario(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], select[required]');
    let valido = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = 'var(--danger-color)';
            valido = false;
        } else {
            input.style.borderColor = 'var(--border-color)';
        }
    });
    
    return valido;
}