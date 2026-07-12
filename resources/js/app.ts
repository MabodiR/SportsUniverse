import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
declare global { interface Window { Echo?: Echo<any>; Pusher?: typeof Pusher } }

createInertiaApp({
    title: (title) => title ? `${title} · SportUniverse` : 'SportUniverse',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true }) as Record<string, { default: unknown }>;
        return pages[`./Pages/${name}.vue`].default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
        const pageProps=props.initialPage.props as any,user=pageProps.auth?.user,key=pageProps.realtime?.key;
        if(user&&key){window.Pusher=Pusher;window.Echo=new Echo({broadcaster:'reverb',key,wsHost:location.hostname,wsPort:Number(location.port||80),wssPort:Number(location.port||443),forceTLS:location.protocol==='https:',enabledTransports:['ws','wss'],authEndpoint:'/broadcasting/auth'});window.Echo.private('App.Models.User.'+user.id).notification(async(notification:any)=>{window.dispatchEvent(new CustomEvent('sportuniverse:notification',{detail:notification}));if(Notification.permission==='granted'){const registration=await navigator.serviceWorker?.ready;registration?.showNotification(notification.sender_name||notification.actor_name||'SportUniverse',{body:notification.preview||'You have a new notification',icon:'/images/logo/favicon-192x192.png',data:{url:notification.conversation_id?'/messages':'/notifications'}})}});}
        if('serviceWorker' in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>undefined);
    },
    progress: { color: '#1B63F3' },
});
