/**
 * formatters.js
 * Utilidades para formateo de campos de moneda en tiempo real
 */

/**
 * Formatea un input de texto para mostrar separadores de miles (puntos)
 * @param {HTMLInputElement} input 
 */
function formatCurrencyInput(input) {
    // Eliminar todo lo que no sea número
    let value = input.value.replace(/\D/g, "");
    
    if (value === "") {
        input.value = "";
        return;
    }

    // Convertir a número y formatear con puntos (es-CO)
    // Usamos el locale es-CO que usa puntos para miles
    let formatted = new Intl.NumberFormat('es-CO').format(value);
    
    input.value = formatted;
}

/**
 * Obtiene el valor numérico limpio de un input formateado
 * @param {HTMLInputElement} input 
 * @returns {number}
 */
function getRawValue(input) {
    if (!input) return 0;
    return parseFloat(input.value.replace(/\D/g, "")) || 0;
}

// Inicializar todos los campos con data-type="currency" si es necesario
// Aunque lo llamaremos manualmente en los eventos oninput para mayor control.
