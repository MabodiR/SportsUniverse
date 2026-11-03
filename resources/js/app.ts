import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
declare global { interface Window { Echo?: Echo<any>; Pusher?: typeof Pusher } }

const reportPagePerformance = (kind: 'navigation'|'inertia', path: string, durationMs: number) => {
    if (['localhost','127.0.0.1','::1'].includes(location.hostname) || !Number.isFinite(durationMs) || durationMs < 0) return;
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
        if(user&&key){
            const configuredHost=String(import.meta.env.VITE_REVERB_HOST||'');
            const localHosts=['localhost','127.0.0.1','::1'];
            const localPage=localHosts.includes(location.hostname);
            const invalidProductionHost=!localPage&&localHosts.includes(configuredHost);
            const reverbHost=invalidProductionHost||!configuredHost?location.hostname:configuredHost;
            const reverbSecure=!localPage&&(invalidProductionHost||location.protocol==='https:'||import.meta.env.VITE_REVERB_SCHEME==='https');
            const reverbPort=invalidProductionHost?443:Number(import.meta.env.VITE_REVERB_PORT||(reverbSecure?443:8080));
            window.Pusher=Pusher;
            const echo=new Echo({broadcaster:'reverb',key,wsHost:reverbHost,wsPort:reverbPort,wssPort:reverbPort,forceTLS:reverbSecure,enabledTransports:reverbSecure?['wss']:['ws'],authEndpoint:'/broadcasting/auth'});
            window.Echo=echo;
            let failures=0;
            const connection=(echo.connector as any)?.pusher?.connection;
            connection?.bind('connected',()=>{failures=0});
            connection?.bind('error',()=>{if(++failures>=3){echo.disconnect();window.Echo=undefined;window.dispatchEvent(new CustomEvent('sportuniverse:realtime-unavailable'))}});
            echo.private('App.Models.User.'+user.id).notification(async(notification:any)=>{window.dispatchEvent(new CustomEvent('sportuniverse:notification',{detail:notification}));if(Notification.permission==='granted'){const registration=await navigator.serviceWorker?.ready;registration?.showNotification(notification.sender_name||notification.actor_name||'SportsUniverse',{body:notification.preview||'You have a new notification',icon:'/images/logo/favicon-192x192.png',data:{url:notification.conversation_id?'/messages':'/notifications'}})}});
        }
        if('serviceWorker' in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>undefined);
    },
    progress: { color: '#476FEA' },
});
