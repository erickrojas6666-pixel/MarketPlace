import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, Fragment, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PwaStatus from './Components/PwaStatus.vue';
import { iniciarPwa } from './composables/usePwa';
import { alSincronizar, iniciarSync, setUsuario } from './offline/sync';
import { desuscribirPush, refrescarEstadoPush, sincronizarSuscripcion } from './composables/usePush';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        iniciarPwa();

        // Datos locales (IndexedDB): ligados al usuario y borrados al cerrar sesión.
        let ultimoUsuario = null;
        const alCambiarUsuario = (id) => {
            setUsuario(id);
            if (id !== null && id !== ultimoUsuario) sincronizarSuscripcion(); // asocia este dispositivo a la cuenta
            ultimoUsuario = id;
        };
        alCambiarUsuario(props.initialPage.props.auth?.user?.id ?? null);
        refrescarEstadoPush();
        router.on('navigate', (event) => alCambiarUsuario(event.detail.page.props.auth?.user?.id ?? null));

        // Al cerrar sesión, primero se da de baja el push de este dispositivo
        // (con la sesión aún válida) para que el siguiente usuario no reciba avisos ajenos.
        let saliendo = false;
        router.on('before', (event) => {
            const visita = event.detail.visit;
            if (saliendo || visita.method !== 'post' || visita.url.pathname !== '/logout') return;

            event.preventDefault();
            saliendo = true;
            desuscribirPush().finally(() => router.post(route('logout')));
        });
        router.on('finish', () => (saliendo = false));
        iniciarSync();
        alSincronizar(() => router.reload({ preserveScroll: true }));

        return createApp({ render: () => h(Fragment, [h(App, props), h(PwaStatus)]) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
