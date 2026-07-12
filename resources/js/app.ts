import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

createInertiaApp({
    title: (title) => title ? `${title} · SportUniverse` : 'SportUniverse',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true }) as Record<string, { default: unknown }>;
        return pages[`./Pages/${name}.vue`].default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: '#1B63F3' },
});
