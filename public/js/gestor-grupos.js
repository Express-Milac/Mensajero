/* Confirmación expresa antes de cambiar la visibilidad de un grupo. */
(() => {
    'use strict';

    document.querySelectorAll('form[data-confirmar-gestion]').forEach((formulario) => {
        formulario.addEventListener('submit', (evento) => {
            const aviso = formulario.dataset.confirmarGestion;
            if (aviso && !window.confirm(aviso)) {
                evento.preventDefault();
                return;
            }

            const boton = formulario.querySelector('button[type="submit"]');
            if (boton) {
                boton.disabled = true;
                boton.textContent = 'Guardando...';
            }
        });
    });
})();
