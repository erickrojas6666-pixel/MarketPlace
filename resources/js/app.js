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
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Inicializar Service Worker
        iniciarPwa();

        // Datos locales (IndexedDB): ligados al usuario y borrados al cerrar sesión.
        let ultimoUsuario = null;
        const alCambiarUsuario = (id) => {
            setUsuario(id);
            if (id !== null && id !== ultimoUsuario) {
                sincronizarSuscripcion(); // Asocia este dispositivo a la cuenta
            }
            ultimoUsuario = id;
        };

        alCambiarUsuario(props.initialPage.props.auth?.user?.id ?? null);
        refrescarEstadoPush();

        router.on('navigate', (event) => {
            alCambiarUsuario(event.detail.page.props.auth?.user?.id ?? null);
        });

        // Interceptación de Logout para cancelar Push Notifications antes de perder la sesión
        let saliendo = false;
        router.on('before', (event) => {
            const visita = event.detail.visit;
            const esLogout = visita.method === 'post' && (visita.url.pathname === '/logout' || visita.url.href.includes('/logout'));

            if (!esLogout || saliendo) return;

            event.preventDefault();
            saliendo = true;

            desuscribirPush().finally(() => {
                router.post(route('logout'), {}, {
                    onFinish: () => {
                        saliendo = false;
                    }
                });
            });
        });

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