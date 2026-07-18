<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Bookmark, CheckCircle2, Eye, Grid3X3, Heart, MessageCircle, Play, UserPlus, X } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const props = defineProps<{ athlete: any; videos: any[]; seo: {title:string;description:string;image:string} }>();
const currentUser = computed(() => (usePage().props.auth as any)?.user);
const following = ref(props.athlete.is_following);
const saved = ref(props.athlete.is_saved);
const followersCount = ref(props.athlete.followers);
const busy = ref(false);
const followError = ref('');
const activeVideo = ref<any | null>(null);
const viewedVideos = new Set<string>();
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
const compact = (value: number) => new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value ?? 0);

const follow = async () => {
    if (!currentUser.value) { window.location.href = '/login'; return; }
    busy.value = true;
    followError.value = '';
    const response = await fetch(`/athletes/${props.athlete.id}/follow`, { method: following.value ? 'DELETE' : 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
    if (response.ok) {
        const payload = await response.json();
        following.value = payload.data.following;
        followersCount.value = payload.data.followers_count;
        window.dispatchEvent(new CustomEvent('following-count-changed', { detail: payload.data.viewer_following_count }));
    } else {
        const payload = await response.json().catch(() => ({}));
        followError.value = payload.message ?? 'Unable to update follow status. Please refresh and try again.';
    }
    busy.value = false;
};
const saveProfile = async () => {
    if (!currentUser.value) { window.location.href = '/login'; return; }
    const response = await fetch(`/api/v1/saved-profiles/${props.athlete.id}`, { method: saved.value ? 'DELETE' : 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: '{}' });
    if (response.ok) saved.value = !saved.value;
};
const recordVideoView = async (video: HTMLVideoElement) => {
    if (!currentUser.value || !activeVideo.value || viewedVideos.has(activeVideo.value.id) || video.currentTime < 3) return;
    viewedVideos.add(activeVideo.value.id);
    const response = await fetch(`/api/v1/videos/${activeVideo.value.id}/views`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ watched_ms: Math.round(video.currentTime * 1000), completed: video.duration > 0 && video.currentTime / video.duration >= .9 }) });
    if (response.ok) { const payload = await response.json(); activeVideo.value.views = payload.data.views_count; }
};
onMounted(() => {
    if (currentUser.value && currentUser.value.id !== props.athlete.id) fetch(`/api/v1/profiles/${props.athlete.slug}/views`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ source: 'profile' }) });
});
</script>

<template>
    <Head><title>{{seo.title}}</title><meta name="description" :content="seo.description"/><meta property="og:title" :content="seo.title"/><meta property="og:description" :content="seo.description"/><meta property="og:image" :content="seo.image"/><meta name="twitter:title" :content="seo.title"/><meta name="twitter:description" :content="seo.description"/><meta name="twitter:image" :content="seo.image"/></Head>
    <AppShell>
        <main class="public-profile-page">
            <section class="public-profile-header">
                <div class="profile-cover"><img v-if="athlete.cover" :src="athlete.cover" :alt="`${athlete.name} cover photo`" /></div>
                <div class="public-profile-info">
                    <div class="public-avatar"><img v-if="athlete.image" :src="athlete.image" :alt="`${athlete.name} profile photo`" /><span v-else>{{ athlete.name.slice(0,2).toUpperCase() }}</span></div>
                    <div class="profile-identity"><h1>{{ athlete.name }} <CheckCircle2 /></h1><strong>@{{ athlete.slug }}</strong><p>{{ athlete.sport || 'Athlete' }}<template v-if="athlete.position"> · {{ athlete.position }}</template><template v-if="athlete.city"> · {{ athlete.city }}</template></p></div>
                    <div class="profile-actions"><button class="su-btn su-btn-primary" :disabled="busy" @click="follow"><UserPlus :size="16" /> {{ busy ? 'Updating…' : following ? 'Following' : 'Follow' }}</button><button class="su-btn su-btn-ghost" @click="saveProfile"><Bookmark :size="16" :fill="saved?'currentColor':'none'" /> {{ saved ? 'Saved' : 'Save' }}</button><Link href="/message-requests" class="su-btn su-btn-ghost"><MessageCircle :size="16" /> Message</Link><small v-if="followError" class="follow-error">{{ followError }}</small></div>
                </div>
                <div class="public-stats"><div><strong>{{ compact(athlete.following) }}</strong><span>Following</span></div><div><strong>{{ compact(followersCount) }}</strong><span>Followers</span></div><div><strong>{{ compact(videos.reduce((sum, video) => sum + video.likes, 0)) }}</strong><span>Likes</span></div><div><strong>{{ athlete.videos_count }}</strong><span>Videos</span></div><div><strong>{{ compact(athlete.profile_views) }}</strong><span>Profile views</span></div></div>
                <p class="public-bio">{{ athlete.bio || 'Sporting talent, progress and highlights.' }}</p>
                <div class="athlete-detail-chips"><span v-if="athlete.club">{{ athlete.club }}</span><span v-if="athlete.level">{{ athlete.level }}</span><span v-if="athlete.dominant_side">{{ athlete.dominant_side }} side</span><span v-if="athlete.height_cm">{{ athlete.height_cm }} cm</span><span v-if="athlete.weight_kg">{{ athlete.weight_kg }} kg</span><span v-if="athlete.available" class="available">Open to opportunities</span></div>
            </section>

            <section v-if="athlete.statistics?.length || athlete.achievements?.length || athlete.career_history?.length" class="public-career">
                <div v-if="athlete.statistics?.length"><h2>Performance statistics</h2><div class="public-stat-grid"><article v-for="item in athlete.statistics" :key="`${item.season}-${item.name}`"><strong>{{ Number(item.value).toLocaleString() }} {{ item.unit }}</strong><span>{{ item.name }}</span><small>{{ item.season }}<template v-if="item.competition"> · {{ item.competition }}</template></small></article></div></div>
                <div v-if="athlete.achievements?.length"><h2>Achievements</h2><article v-for="item in athlete.achievements" :key="`${item.title}-${item.achieved_on}`"><strong>{{ item.title }}</strong><span>{{ [item.issuer, item.achieved_on?.slice(0, 4)].filter(Boolean).join(' · ') }}</span><p v-if="item.description">{{ item.description }}</p></article></div>
                <div v-if="athlete.career_history?.length"><h2>Career history</h2><article v-for="item in athlete.career_history" :key="`${item.team_name}-${item.started_on}`"><strong>{{ item.team_name }}</strong><span>{{ [item.role, item.level].filter(Boolean).join(' · ') }}</span><small>{{ item.started_on?.slice(0, 4) || 'Start unknown' }} — {{ item.is_current ? 'Present' : item.ended_on?.slice(0, 4) || 'End unknown' }}</small></article></div>
            </section>

            <div class="profile-tabs"><button class="active"><Grid3X3 :size="17" /> Videos</button><button><Heart :size="17" /> Liked</button></div>
            <section v-if="videos.length" class="profile-video-grid">
                <button v-for="(video,index) in videos" :key="video.id" class="profile-video-tile" :class="`tile-${(index%3)+1}`" @click="activeVideo = video">
                    <Play class="tile-play" /><div class="tile-copy"><p>{{ video.caption }}</p><span><Eye :size="13" /> {{ compact(video.views) }}</span></div>
                </button>
            </section>
            <section v-else class="profile-empty"><Grid3X3 :size="34" /><h2>No videos yet</h2><p>Published athlete highlights will appear here.</p></section>
            <div v-if="activeVideo" class="profile-player-modal" @click.self="activeVideo = null">
                <button class="player-close" aria-label="Close video" @click="activeVideo = null"><X /></button>
                <div class="profile-player-shell">
                    <video v-if="activeVideo.url" :src="activeVideo.url" autoplay controls playsinline loop @click.stop @timeupdate="recordVideoView($event.currentTarget as HTMLVideoElement)" />
                    <div v-else class="missing-video"><Play /><p>This video file is still processing.</p></div>
                    <div class="player-caption"><strong>{{ athlete.name }}</strong><p>{{ activeVideo.caption }}</p></div>
                </div>
            </div>
        </main>
    </AppShell>
</template>
