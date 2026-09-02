import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
declare global { interface Window { Echo?: Echo<any>; Pusher?: typeof Pusher } }

const reportPagePerformance = (kind: 'navigation'|'inertia', path: string, durationMs: number) => {
    if (!Number.isFinite(durationMs) || durationMs < 0) return;
    const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    fetch('/api/v1/page-performance', { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ kind, path: path.slice(0, 500), duration_ms: Math.round(durationMs) }) }).catch(() => undefined);
};

const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming|undefined;
if (navigation) window.addEventListener('load', () => reportPagePerformance('navigation', location.pathname, navigation.duration), { once: true });
let inertiaStartedAt = 0;
router.on('start', () => { inertiaStartedAt = performance.now(); });
router.on('finish', () => { if (inertiaStartedAt) reportPagePerformance('inertia', location.pathname, performance.now() - inertiaStartedAt); inertiaStartedAt = 0; });

createInertiaApp({
    title: (title) => title ? `${title} · SportsUniverse` : 'SportsUniverse',
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
        const pageProps=props.initialPage.props as any,user=pageProps.auth?.user,key=pageProps.realtime?.key;
        if(user&&key){const reverbHost=import.meta.env.VITE_REVERB_HOST||location.hostname,reverbSecure=import.meta.env.VITE_REVERB_SCHEME?import.meta.env.VITE_REVERB_SCHEME==='https':location.protocol==='https:',reverbPort=Number(import.meta.env.VITE_REVERB_PORT||(reverbSecure?443:8080));window.Pusher=Pusher;window.Echo=new Echo({broadcaster:'reverb',key,wsHost:reverbHost,wsPort:reverbPort,wssPort:reverbPort,forceTLS:reverbSecure,enabledTransports:reverbSecure?['wss']:['ws'],authEndpoint:'/broadcasting/auth'});window.Echo.private('App.Models.User.'+user.id).notification(async(notification:any)=>{window.dispatchEvent(new CustomEvent('sportuniverse:notification',{detail:notification}));if(Notification.permission==='granted'){const registration=await navigator.serviceWorker?.ready;registration?.showNotification(notification.sender_name||notification.actor_name||'SportsUniverse',{body:notification.preview||'You have a new notification',icon:'/images/logo/favicon-192x192.png',data:{url:notification.conversation_id?'/messages':'/notifications'}})}});}
        if('serviceWorker' in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>undefined);
    },
    progress: { color: '#476FEA' },
});
