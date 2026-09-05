<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CirclePlay, Pause, Play, Sparkles, Volume2, VolumeX } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicNavbar from '../../Components/Public/PublicNavbar.vue';
import PublicFooter from '../../Components/Public/PublicFooter.vue';

const props = defineProps<{ highlights: any[]; athletes: any[]; sports: any[]; opportunities: any[]; stats: Record<string, number> }>();
const active = ref(0); const paused = ref(false); const muted = ref(true);
const heroVideo = ref<HTMLVideoElement | null>(null);
const pointer = ref({ x: 50, y: 42, rx: 0, ry: 0 });
const touchStart = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;
const slides = computed(() => props.highlights.filter((item) => item.video || item.cover).slice(0, 5));
const current = computed(() => slides.value[active.value]);
const featured = computed(() => props.highlights.slice(0, 3));
const compact = (value: number) => new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value || 0);
const goTo = (index: number) => { active.value = index; window.setTimeout(() => heroVideo.value?.play().catch(() => undefined), 80); };
const next = () => slides.value.length && goTo((active.value + 1) % slides.value.length);
const startTimer = () => { window.clearInterval(timer); if (slides.value.length > 1 && !paused.value) timer = window.setInterval(next, 6500); };
const togglePlayback = () => { paused.value = !paused.value; paused.value ? heroVideo.value?.pause() : heroVideo.value?.play().catch(() => undefined); startTimer(); };
const moveScene = (event: PointerEvent) => { if (event.pointerType === 'touch') return; const box = (event.currentTarget as HTMLElement).getBoundingClientRect(); const x = (event.clientX - box.left) / box.width; const y = (event.clientY - box.top) / box.height; pointer.value = { x: x * 100, y: y * 100, rx: (0.5 - y) * 2.5, ry: (x - 0.5) * 3.5 }; };
const resetScene = () => pointer.value = { x: 50, y: 42, rx: 0, ry: 0 };
const beginSwipe = (event: TouchEvent) => touchStart.value = event.changedTouches[0]?.clientX || 0;
const endSwipe = (event: TouchEvent) => { const distance = (event.changedTouches[0]?.clientX || 0) - touchStart.value; if (Math.abs(distance) > 45 && slides.value.length > 1) goTo(distance < 0 ? (active.value + 1) % slides.value.length : (active.value - 1 + slides.value.length) % slides.value.length); };
const sceneStyle = computed(() => ({ '--mouse-x': `${pointer.value.x}%`, '--mouse-y': `${pointer.value.y}%`, '--scene-rx': `${pointer.value.rx}deg`, '--scene-ry': `${pointer.value.ry}deg` }));
const playPreview = (event: MouseEvent) => (event.currentTarget as HTMLElement).querySelector('video')?.play().catch(() => undefined);
const stopPreview = (event: MouseEvent) => { const video = (event.currentTarget as HTMLElement).querySelector('video'); if (video) { video.pause(); video.currentTime = 0; } };
onMounted(startTimer); onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
<Head title="Where Sports Talent Meets Opportunity"><meta name="description" content="Share your sporting moments, build your profile and get discovered on SportsUniverse."/></Head>
<div class="public-home kinetic-home"><PublicNavbar/><main id="main-content">
<section class="kinetic-hero" :style="sceneStyle" @pointermove="moveScene" @pointerleave="resetScene" @touchstart.passive="beginSwipe" @touchend.passive="endSwipe">
<div class="kinetic-media" :class="{ 'is-paused': paused }"><video v-if="current?.video" :key="current.id" ref="heroVideo" :src="current.video" :poster="current.cover" autoplay :muted="muted" loop playsinline preload="auto"/><img v-else :src="current?.cover || '/images/homepage-hero.png'" alt="Athletes competing across multiple sports"/></div><div class="kinetic-wash"/><div class="kinetic-light" aria-hidden="true"/><div class="kinetic-orbit orbit-one" aria-hidden="true"/><div class="kinetic-orbit orbit-two" aria-hidden="true"/>
<div class="kinetic-hero-inner public-container"><div class="kinetic-copy"><span class="kinetic-kicker"><Sparkles/> Your moment starts here</span><h1>Play loud.<br/><em>Get seen.</em></h1><p>One profile. Every highlight. A world of opportunity for the next generation of athletes.</p><div class="kinetic-actions"><Link href="/register" class="kinetic-primary">Create your profile <ArrowRight/></Link><Link href="/feed" class="kinetic-ghost"><CirclePlay/> Watch athletes</Link></div></div><div v-if="slides.length" class="kinetic-player-meta"><span>Now playing</span><strong>{{ current?.athlete?.name }}</strong><small>{{ [current?.sport, current?.location].filter(Boolean).join(' · ') }}</small></div></div>
<div v-if="slides.length" class="kinetic-controls public-container"><button class="kinetic-play" :aria-label="paused ? 'Play video' : 'Pause video'" @click="togglePlayback"><Play v-if="paused"/><Pause v-else/></button><button class="kinetic-sound" :aria-label="muted ? 'Unmute video' : 'Mute video'" @click="muted=!muted; if(heroVideo) heroVideo.muted=muted"><VolumeX v-if="muted"/><Volume2 v-else/></button><span class="kinetic-swipe">Swipe highlights</span><div class="kinetic-dots"><button v-for="(item,index) in slides" :key="item.id" :class="{ active:index===active }" :aria-label="`Show ${item.athlete?.name || 'highlight'}`" @click="goTo(index)"><i/></button></div><span class="kinetic-count">0{{ active + 1 }} / 0{{ slides.length }}</span></div>
</section>
<section id="highlights" class="kinetic-reel public-container"><header class="kinetic-section-title"><div><span>Fresh from the field</span><h2>Moments worth watching.</h2></div><Link href="/feed">Explore the feed <ArrowRight/></Link></header><div class="kinetic-grid"><a v-for="(item,index) in featured" :key="item.id" :href="`/feed#${item.id}`" class="kinetic-card" @mouseenter="playPreview" @mouseleave="stopPreview"><video v-if="item.video" :src="item.video" :poster="item.cover" muted loop playsinline preload="metadata"/><img v-else-if="item.cover" :src="item.cover" :alt="`${item.athlete.name} highlight`" loading="lazy"/><div v-else class="kinetic-card-empty">{{ item.sport?.slice(0,1) }}</div><div class="kinetic-card-shade"/><span class="kinetic-card-number">0{{ index + 1 }}</span><CirclePlay class="kinetic-card-play"/><div class="kinetic-card-copy"><strong>{{ item.athlete.name }}</strong><span>{{ item.sport }} · {{ compact(item.views) }} views</span></div></a><div v-if="!featured.length" class="kinetic-empty"><CirclePlay/><strong>The next great moment starts with you.</strong><Link href="/register">Upload a highlight</Link></div></div></section>
<section class="kinetic-join public-container"><span class="kinetic-join-label">ATHLETES · SCOUTS · CLUBS</span><h2>Talent is everywhere.<br/><em>Opportunity should be too.</em></h2><p>Build your sporting identity or discover the athletes shaping what comes next.</p><div class="kinetic-actions"><Link href="/register" class="kinetic-primary">Join SportsUniverse <ArrowRight/></Link><Link href="/about" class="kinetic-ghost">Our story</Link></div></section>
</main><PublicFooter/></div>
</template>
