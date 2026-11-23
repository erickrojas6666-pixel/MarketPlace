import { createApp, ref } from 'vue';

/**
 * Estado de la celebración al instalar la app.
 * `celebrando` vale true unos 4 segundos.
 *
 * La celebración NO empieza al aceptar: espera a que el navegador termine de instalar
 * (evento "appinstalled") y a que el usuario cierre los cuadros del navegador
 * (barra de progreso y "¿abrir la app?"), para que nada tape el mensaje.
 */
export const celebrando = ref(false);

const DURACION_MS = 4000;
const AJUSTE_MS = 700; // pausa final para que el cuadro del navegador termine de desaparecer

let temporizador = null;
let temporizadorAsentar = null;
let iniciado = false;

let instaladaEsperando = false; // el navegador ya avisó que terminó; falta que se cierren sus cuadros
let punteroFuera = false; // el mouse salió de la página (por ejemplo, hacia el cuadro del navegador)

export function celebrarInstalacion() {
    if (celebrando.value) return;
    celebrando.value = true;
    clearTimeout(temporizador);
    temporizador = setTimeout(() => (celebrando.value = false), DURACION_MS);
}

const paginaLista = () => document.visibilityState === 'visible' && document.hasFocus();

// Celebra solo si: ya se instaló, el mouse está de vuelta en la página y la ventana tiene el foco.
const intentarCelebrar = () => {
    if (!instaladaEsperando || punteroFuera || !paginaLista()) return;

    clearTimeout(temporizadorAsentar);
    temporizadorAsentar = setTimeout(() => {
        if (!instaladaEsperando || punteroFuera || !paginaLista()) return;
        instaladaEsperando = false;
        celebrarInstalacion();
    }, AJUSTE_MS);
};

const alInstalarse = () => {
    instaladaEsperando = true;
    intentarCelebrar();
};

// El mouse regresó a la página (o el usuario interactuó con ella).
const alVolverAInteractuar = () => {
    if (punteroFuera) punteroFuera = false;
    intentarCelebrar();
};

/**
 * Escucha la instalación y monta (una sola vez) el mensaje de agradecimiento
 * encima de toda la página. Recibe el componente para no depender de él aquí.
 */
export function iniciarCelebracion(componente) {
    if (iniciado || typeof window === 'undefined') return;
    iniciado = true;

    window.addEventListener('appinstalled', alInstalarse);
    window.addEventListener('focus', intentarCelebrar);
    document.addEventListener('visibilitychange', intentarCelebrar);

    // Seguimiento del mouse: salir de la página suele significar "voy al cuadro del navegador".
    document.addEventListener(
        'mouseout',
        (e) => {
            if (!e.relatedTarget) punteroFuera = true;
        },
        { passive: true },
    );
    document.addEventListener('pointermove', alVolverAInteractuar, { passive: true });
    document.addEventListener('pointerdown', alVolverAInteractuar, { passive: true });
    document.addEventListener('keydown', alVolverAInteractuar, { passive: true });

    const contenedor = document.createElement('div');
    document.body.appendChild(contenedor);
    createApp(componente).mount(contenedor);
}