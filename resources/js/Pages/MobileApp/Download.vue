<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Apple, CheckCircle2, Download, MessageCircle, Play, ShieldCheck, Smartphone, Sparkles, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const props = defineProps<{ downloads: { ios?: string | null; android?: string | null; direct?: string | null } }>();
const available = computed(() => Boolean(props.downloads.ios || props.downloads.android || props.downloads.direct));
const androidDownload = computed(() => props.downloads.android || props.downloads.direct || null);
</script>

<template>
    <Head title="Mobile app" />
    <AppShell>
        <main class="mobile-download-page">
            <section class="download-hero">
                <div class="hero-copy">
                    <span class="eyebrow"><Smartphone /> SPORTSUNIVERSE MOBILE</span>
                    <h1>Your sporting world,<br><em>wherever you go.</em></h1>
                    <p>Use the completed SportsUniverse features from your phone: discover athletes, share highlights, follow opportunities and stay connected.</p>
                    <div class="platform-heading"><strong>Choose your device</strong><span>One account works across web and mobile.</span></div>
                    <div class="platform-downloads">
                        <article class="platform-card android"><span class="platform-icon"><Play fill="currentColor"/></span><div><small>ANDROID · DEVELOPMENT BUILD</small><h2>SportsUniverse for Android</h2><p>Download the current APK directly to your Android phone. Allow installs from your browser when Android asks for permission.</p></div><a v-if="androidDownload" :href="androidDownload" class="platform-button" target="_blank" rel="noopener"><Download/>{{downloads.android?'Get it on Google Play':'Download Android APK'}}</a><span v-else class="platform-button unavailable" aria-disabled="true">Coming soon</span><p v-if="androidDownload" class="build-note"><CheckCircle2/>Version 1.0.0 · Build 3 · Internal testing</p></article>
                        <article class="platform-card ios"><span class="platform-icon"><Apple fill="currentColor"/></span><div><small>IPHONE & IPAD</small><h2>SportsUniverse for iOS</h2><p>Download the official iPhone and iPad app from the Apple App Store.</p></div><a v-if="downloads.ios" :href="downloads.ios" class="platform-button" target="_blank" rel="noopener"><Apple/>View on App Store</a><span v-else class="platform-button unavailable" aria-disabled="true">Coming soon</span></article>
                    </div>
                    <div v-if="!available" class="release-note" role="status"><Sparkles /><div><strong>Native app release coming soon</strong><p>The store builds are not public yet. Keep using the mobile-friendly web app—your account and content will carry over.</p></div></div>
                    <div class="web-actions"><Link href="/feed" class="primary-link">Continue on web</Link><Link href="/login" class="secondary-link">Sign in</Link></div>
                </div>
                <div class="phone-preview" aria-label="SportsUniverse mobile app preview">
                    <div class="phone-frame">
                        <div class="phone-top"><img :src="'/images/logo/sportuniverse-icon.svg'" alt=""><b>SportsUniverse</b></div>
                        <div class="preview-card"><span>FOR YOU</span><h2>Discover the next<br>generation of talent.</h2><div class="preview-player"><span>SU</span><div><strong>Featured athlete</strong><small>Football · Johannesburg</small></div></div></div>
                        <div class="preview-stats"><div><Users/><b>Discover</b></div><div><MessageCircle/><b>Connect</b></div><div><ShieldCheck/><b>Safe</b></div></div>
                    </div>
                </div>
            </section>
            <section class="feature-strip"><article><CheckCircle2/><div><strong>Profiles & portfolios</strong><span>Showcase sporting achievements</span></div></article><article><CheckCircle2/><div><strong>Posts & highlights</strong><span>Upload, engage and save</span></div></article><article><CheckCircle2/><div><strong>Opportunities</strong><span>Discover and apply on the go</span></div></article></section>
        </main>
    </AppShell>
</template>

<style scoped>
.mobile-download-page{min-height:calc(100vh - 64px);padding:clamp(24px,5vw,70px);background:radial-gradient(circle at 82% 20%,#dcecff 0,transparent 31%),#f4f6fa}.download-hero{display:grid;max-width:1200px;margin:auto;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:clamp(35px,5vw,70px);align-items:center}.eyebrow{display:flex;gap:8px;align-items:center;color:#476FEA;font-size:13px;font-weight:850;letter-spacing:.09em}.eyebrow svg{width:18px}.hero-copy h1{margin:14px 0;font-size:clamp(42px,6vw,72px);line-height:.98;letter-spacing:-.05em}.hero-copy h1 em{color:#476FEA;font-style:normal}.hero-copy>p{max-width:680px;color:#66758a;font-size:18px;line-height:1.65}.platform-heading{display:grid;gap:3px;margin-top:25px}.platform-heading strong{font-size:16px}.platform-heading span{color:#7a8798;font-size:13px}.platform-downloads{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}.platform-card{display:flex;min-width:0;gap:12px;align-items:flex-start;flex-wrap:wrap;padding:18px;border:1px solid #d9e2ec;border-radius:17px;background:#fff;box-shadow:0 8px 24px rgba(24,48,80,.06)}.platform-card>div{flex:1;min-width:150px}.platform-icon{display:grid;width:45px;height:45px;flex:none;place-items:center;border-radius:13px}.platform-icon svg{width:23px}.platform-card.android .platform-icon{color:#168f5d;background:#e9f8f1}.platform-card.ios .platform-icon{color:#fff;background:#111827}.platform-card small{color:#476FEA;font-size:10px;font-weight:900;letter-spacing:.09em}.platform-card h2{margin:4px 0;font-size:16px}.platform-card p{margin:0;color:#718096;font-size:12px;line-height:1.5}.platform-button{display:flex;width:100%;min-height:43px;gap:7px;align-items:center;justify-content:center;color:#fff;border-radius:10px;background:#476FEA;text-decoration:none;font-size:13px;font-weight:850}.platform-button svg{width:17px}.platform-button.unavailable{color:#748195;background:#edf1f5}.web-actions{display:flex;gap:11px;flex-wrap:wrap;margin-top:20px}.primary-link,.secondary-link{display:flex;min-height:48px;gap:10px;align-items:center;justify-content:center;padding:9px 16px;border-radius:12px;text-decoration:none;font-weight:800}.primary-link{color:white;background:#476FEA}.secondary-link{color:#24364b;border:1px solid #cdd8e5;background:white}.release-note{display:flex;max-width:680px;gap:13px;margin-top:15px;padding:14px;color:#174a38;border:1px solid #bce5d5;border-radius:14px;background:#edfaf5}.release-note svg{flex:none}.release-note p{margin:4px 0 0;color:#527066;line-height:1.5}.phone-preview{display:flex;justify-content:center}.phone-frame{width:min(100%,340px);padding:18px;border:9px solid #101827;border-radius:42px;background:#f7f9fc;box-shadow:0 35px 80px rgba(20,43,77,.23)}.phone-top{display:flex;gap:8px;align-items:center;padding:3px 3px 18px}.phone-top img{width:29px;height:29px}.preview-card{min-height:360px;padding:26px 22px;border-radius:25px;background:linear-gradient(155deg,#081727,#143c67 55%,#476FEA);color:white}.preview-card>span{font-size:11px;font-weight:850;letter-spacing:.12em;color:#79aaff}.preview-card h2{margin:18px 0;font-size:29px;line-height:1.08}.preview-player{display:flex;gap:11px;align-items:center;margin-top:155px}.preview-player>span{display:grid;width:42px;height:42px;place-items:center;border-radius:50%;background:linear-gradient(135deg,#476FEA,#B96B9D);font-weight:900}.preview-player strong,.preview-player small{display:block}.preview-player small{margin-top:3px;color:#b8c9dd}.preview-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:17px 2px 3px}.preview-stats div{display:flex;gap:4px;align-items:center;justify-content:center;flex-direction:column;color:#627086;font-size:11px}.preview-stats svg{width:19px;color:#476FEA}.feature-strip{display:grid;max-width:1200px;margin:65px auto 0;grid-template-columns:repeat(3,1fr);gap:13px}.feature-strip article{display:flex;gap:11px;padding:18px;border:1px solid #dce4ed;border-radius:15px;background:rgba(255,255,255,.8)}.feature-strip svg{flex:none;color:#17a673}.feature-strip strong,.feature-strip span{display:block}.feature-strip span{margin-top:4px;color:#738195;font-size:13px}
.build-note{display:flex!important;width:100%;gap:6px;align-items:center!important;margin:0!important;color:#168f5d!important;font-size:11px!important;font-weight:750}.build-note svg{width:15px;height:15px}
@media(max-width:920px){.mobile-download-page{padding:28px 18px 105px}.download-hero{grid-template-columns:1fr}.hero-copy h1{font-size:44px}.phone-preview{order:2}.feature-strip{grid-template-columns:1fr;margin-top:36px}.web-actions>*{flex:1}}@media(max-width:620px){.platform-downloads{grid-template-columns:1fr}}
</style>
