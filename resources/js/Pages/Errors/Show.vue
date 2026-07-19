<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BrandLogo from '../../Components/BrandLogo.vue';

const props = defineProps<{ status: number }>();
const pages: Record<number, any> = {
    401: { title: 'Sign in required', label: 'Authentication required', heading: 'Please sign in to continue.', message: 'Your account is not currently authenticated. Sign in securely to return to SportsUniverse.', action: 'Sign in', href: '/login' },
    403: { title: 'Access denied', label: 'Restricted area', heading: 'You don’t have access to this.', message: 'This content may be private, restricted to another account, or unavailable with your current permissions.', action: 'Return to the feed', href: '/feed' },
    404: { title: 'Page not found', label: 'Off the field', heading: 'This page is out of play.', message: 'The link may be outdated, the post may have been removed, or the page may have moved somewhere new.', action: 'Return to the feed', href: '/feed' },
    419: { title: 'Session expired', label: 'Session timeout', heading: 'Your session has expired.', message: 'For your security, inactive sessions are closed automatically. Sign in again and you can continue where you left off.', action: 'Sign in again', href: '/login' },
    429: { title: 'Too many requests', label: 'Take a quick timeout', heading: 'A few too many plays at once.', message: 'We temporarily slowed this action to keep SportsUniverse safe and responsive. Please wait a moment before trying again.', action: 'Return to the feed', href: '/feed' },
    500: { title: 'Something went wrong', label: 'Unexpected error', heading: 'We dropped the ball.', message: 'Something unexpected happened while completing your request. Our system has recorded the error so it can be investigated.', action: 'Return to the feed', href: '/feed' },
    503: { title: 'Temporarily unavailable', label: 'Brief maintenance', heading: 'We’ll be back in the game shortly.', message: 'SportsUniverse is temporarily unavailable while we improve the experience or restore a service.', action: 'Try again', href: '/' },
};
const page = pages[props.status] ?? pages[500];
const goBack = () => window.history.back();
</script>

<template>
    <Head :title="page.title" />
    <main class="error-screen">
        <article>
            <section class="error-art" aria-hidden="true">
                <BrandLogo />
                <div class="error-orbit"><i/><strong>{{ status }}</strong></div>
                <small>Talent · Opportunity · Community</small>
            </section>
            <section class="error-copy">
                <span>{{ page.label }}</span><h1>{{ page.heading }}</h1><p>{{ page.message }}</p>
                <div><Link :href="page.href">{{ page.action }}</Link><button v-if="status !== 419" @click="goBack">Go back</button></div>
                <small v-if="status >= 500">Your account and saved content remain safe. If this continues, contact <a href="mailto:support@sportsuniverse.co.za">SportsUniverse support</a>.</small>
            </section>
        </article>
    </main>
</template>

<style scoped>
.error-screen{display:grid;min-height:100dvh;padding:28px;place-items:center;overflow:hidden;background:radial-gradient(circle at 90% 10%,rgba(37,99,235,.13),transparent 32%),radial-gradient(circle at 10% 90%,rgba(236,61,145,.11),transparent 30%),#f4f7fb}.error-screen>article{display:grid;width:min(100%,1050px);min-height:600px;grid-template-columns:.9fr 1.1fr;overflow:hidden;border:1px solid #dbe3ee;border-radius:30px;background:#fff;box-shadow:0 32px 90px rgba(19,38,71,.14)}.error-art{position:relative;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden;padding:42px;color:#fff;background:radial-gradient(circle at 22% 22%,rgba(37,99,235,.6),transparent 34%),radial-gradient(circle at 84% 82%,rgba(236,61,145,.42),transparent 35%),linear-gradient(150deg,#1B212D,#050915 76%)}.error-art :deep(.brand-logo-image){width:225px}.error-orbit{position:relative;display:grid;width:min(100%,360px);aspect-ratio:1;margin:auto;place-items:center}.error-orbit:before,.error-orbit:after{position:absolute;border:1px solid rgba(255,255,255,.17);border-radius:50%;content:''}.error-orbit:before{inset:2%}.error-orbit:after{inset:19%}.error-orbit i{position:absolute;top:3%;width:13px;height:13px;border-radius:50%;background:#ec3d91;box-shadow:0 0 24px #ec3d91}.error-orbit strong{font-size:clamp(5rem,12vw,8.5rem);font-weight:900;letter-spacing:-.09em}.error-art>small{color:#b8c2d2;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.error-copy{display:flex;flex-direction:column;justify-content:center;padding:clamp(36px,7vw,76px)}.error-copy>span{margin-bottom:22px;color:#476FEA;font-size:.7rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.error-copy h1{margin:0;font-size:clamp(2.1rem,4.5vw,3.5rem);line-height:1.04;letter-spacing:-.055em}.error-copy>p{margin:20px 0 30px;color:#65748a;line-height:1.7}.error-copy>div{display:flex;gap:12px}.error-copy a,.error-copy button{display:inline-flex;min-height:48px;align-items:center;justify-content:center;padding:0 20px;border:1px solid #dfe6f0;border-radius:13px;color:#172033;background:#fff;font:inherit;font-size:.78rem;font-weight:800;text-decoration:none;cursor:pointer}.error-copy>div a{color:#fff;border-color:transparent;background:#476FEA;box-shadow:0 12px 28px rgba(37,99,235,.22)}.error-copy>small{margin-top:34px;padding-top:20px;color:#8a96a8;border-top:1px solid #edf1f6;line-height:1.6}.error-copy>small a{display:inline;min-height:0;padding:0;color:#476FEA;border:0;font-size:inherit}.error-copy button:hover,.error-copy>div a:hover{transform:translateY(-2px)}@media(max-width:760px){.error-screen{padding:0}.error-screen>article{min-height:100dvh;grid-template-columns:1fr;border:0;border-radius:0}.error-art{min-height:300px;padding:28px}.error-art :deep(.brand-logo-image){width:190px}.error-orbit{position:absolute;right:-28px;bottom:-68px;width:260px}.error-orbit strong{font-size:5.5rem}.error-copy{justify-content:flex-start;padding:38px 25px}.error-copy h1{font-size:2.35rem}.error-copy>div{display:grid}.error-copy a,.error-copy button{width:100%}}
</style>
