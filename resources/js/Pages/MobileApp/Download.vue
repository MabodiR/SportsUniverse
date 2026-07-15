<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Apple, CheckCircle2, Download, MessageCircle, Play, ShieldCheck, Smartphone, Sparkles, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const props = defineProps<{ downloads: { ios?: string | null; android?: string | null; direct?: string | null } }>();
const available = computed(() => Boolean(props.downloads.ios || props.downloads.android || props.downloads.direct));
</script>

<template>
    <Head title="Mobile app" />
    <AppShell>
        <main class="mobile-download-page">
            <section class="download-hero">
                <div class="hero-copy">
                    <span class="eyebrow"><Smartphone /> SPORTUNIVERSE MOBILE</span>
                    <h1>Your sporting world,<br><em>wherever you go.</em></h1>
                    <p>Use the completed SportUniverse features from your phone: discover athletes, share highlights, follow opportunities and stay connected.</p>
                    <div v-if="available" class="store-actions">
                        <a v-if="downloads.ios" :href="downloads.ios" class="store-button" target="_blank" rel="noopener"><Apple /><span><small>Download on the</small>App Store</span></a>
                        <a v-if="downloads.android" :href="downloads.android" class="store-button" target="_blank" rel="noopener"><Play /><span><small>Get it on</small>Google Play</span></a>
                        <a v-if="downloads.direct" :href="downloads.direct" class="direct-button"><Download /> Download test build</a>
                    </div>
                    <div v-else class="release-note" role="status"><Sparkles /><div><strong>Native app release coming soon</strong><p>The store builds are not public yet. Keep using the mobile-friendly web app—your account and content will carry over.</p></div></div>
                    <div class="web-actions"><Link href="/feed" class="primary-link">Continue on web</Link><Link href="/login" class="secondary-link">Sign in</Link></div>
                </div>
                <div class="phone-preview" aria-label="SportUniverse mobile app preview">
                    <div class="phone-frame">
                        <div class="phone-top"><img :src="'/images/logo/sportuniverse-icon.svg'" alt=""><b>SportUniverse</b></div>
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
.mobile-download-page{min-height:calc(100vh - 64px);padding:clamp(24px,5vw,70px);background:radial-gradient(circle at 82% 20%,#dcecff 0,transparent 31%),#f4f6fa}.download-hero{display:grid;max-width:1120px;margin:auto;grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr);gap:clamp(35px,7vw,90px);align-items:center}.eyebrow{display:flex;gap:8px;align-items:center;color:#1769ed;font-size:13px;font-weight:850;letter-spacing:.09em}.eyebrow svg{width:18px}.hero-copy h1{margin:14px 0;font-size:clamp(42px,6vw,72px);line-height:.98;letter-spacing:-.05em}.hero-copy h1 em{color:#1769ed;font-style:normal}.hero-copy>p{max-width:630px;color:#66758a;font-size:18px;line-height:1.65}.store-actions,.web-actions{display:flex;gap:11px;flex-wrap:wrap;margin-top:24px}.store-button,.direct-button,.primary-link,.secondary-link{display:flex;min-height:48px;gap:10px;align-items:center;justify-content:center;padding:9px 16px;border-radius:12px;text-decoration:none;font-weight:800}.store-button{color:white;background:#111827}.store-button svg{width:25px}.store-button span,.store-button small{display:block}.store-button small{font-size:10px;font-weight:500}.direct-button,.primary-link{color:white;background:#1769ed}.direct-button svg{width:18px}.secondary-link{color:#24364b;border:1px solid #cdd8e5;background:white}.release-note{display:flex;max-width:620px;gap:13px;margin-top:24px;padding:16px;color:#174a38;border:1px solid #bce5d5;border-radius:14px;background:#edfaf5}.release-note svg{flex:none}.release-note p{margin:4px 0 0;color:#527066;line-height:1.5}.phone-preview{display:flex;justify-content:center}.phone-frame{width:min(100%,340px);padding:18px;border:9px solid #101827;border-radius:42px;background:#f7f9fc;box-shadow:0 35px 80px rgba(20,43,77,.23)}.phone-top{display:flex;gap:8px;align-items:center;padding:3px 3px 18px}.phone-top img{width:29px;height:29px}.preview-card{min-height:360px;padding:26px 22px;border-radius:25px;background:linear-gradient(155deg,#081727,#143c67 55%,#1769ed);color:white}.preview-card>span{font-size:11px;font-weight:850;letter-spacing:.12em;color:#79aaff}.preview-card h2{margin:18px 0;font-size:29px;line-height:1.08}.preview-player{display:flex;gap:11px;align-items:center;margin-top:155px}.preview-player>span{display:grid;width:42px;height:42px;place-items:center;border-radius:50%;background:linear-gradient(135deg,#1769ed,#e646a2);font-weight:900}.preview-player strong,.preview-player small{display:block}.preview-player small{margin-top:3px;color:#b8c9dd}.preview-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:17px 2px 3px}.preview-stats div{display:flex;gap:4px;align-items:center;justify-content:center;flex-direction:column;color:#627086;font-size:11px}.preview-stats svg{width:19px;color:#1769ed}.feature-strip{display:grid;max-width:1120px;margin:65px auto 0;grid-template-columns:repeat(3,1fr);gap:13px}.feature-strip article{display:flex;gap:11px;padding:18px;border:1px solid #dce4ed;border-radius:15px;background:rgba(255,255,255,.8)}.feature-strip svg{flex:none;color:#17a673}.feature-strip strong,.feature-strip span{display:block}.feature-strip span{margin-top:4px;color:#738195;font-size:13px}
@media(max-width:820px){.mobile-download-page{padding:28px 18px 105px}.download-hero{grid-template-columns:1fr}.hero-copy h1{font-size:44px}.phone-preview{order:2}.feature-strip{grid-template-columns:1fr;margin-top:36px}.web-actions>*{flex:1}}
</style>
