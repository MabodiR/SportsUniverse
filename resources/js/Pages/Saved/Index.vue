<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Bookmark, Eye, Heart, MessageCircle, Play, Search, UserRound, X } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const videos = ref<any[]>([]);
const profiles = ref<any[]>([]);
const loading = ref(true);
const query = ref('');
const tab = ref('profiles');
const active = ref<any>();
const busy = ref('');
const filteredVideos = computed(() => videos.value.filter((video) => !query.value || `${video.caption} ${video.creator?.name} ${video.sport?.name}`.toLowerCase().includes(query.value.toLowerCase())));
const filteredProfiles = computed(() => profiles.value.filter((profile) => !query.value || `${profile.name} ${profile.bio} ${profile.athlete?.sport?.name} ${profile.location?.city}`.toLowerCase().includes(query.value.toLowerCase())));
const compact = (value: number) => new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value ?? 0);
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const load = async () => {
    const [videoResponse, profileResponse] = await Promise.all([
        fetch('/api/v1/feed/saved?per_page=50', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
        fetch('/api/v1/saved-profiles', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
    ]);
    videos.value = (await videoResponse.json()).data ?? [];
    profiles.value = (await profileResponse.json()).data ?? [];
    loading.value = false;
};
const unsaveVideo = async (video: any) => {
    busy.value = `video-${video.id}`;
    const response = await fetch(`/api/v1/videos/${video.id}/save`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: '{}' });
    if (response.ok) videos.value = videos.value.filter((item) => item.id !== video.id);
    busy.value = '';
};
const unsaveProfile = async (profile: any) => {
    busy.value = `profile-${profile.id}`;
    const response = await fetch(`/api/v1/saved-profiles/${profile.id}`, { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
    if (response.ok) profiles.value = profiles.value.filter((item) => item.id !== profile.id);
    busy.value = '';
};
onMounted(load);
</script>

<template>
    <Head title="Saved" />
    <AppShell>
        <main class="saved-page">
            <header class="saved-heading"><div><h1>Saved</h1><p>Your private collection of talent profiles and sporting highlights.</p></div><div><Search /><input v-model="query" placeholder="Search your saved collection..." /></div></header>
            <nav class="saved-tabs"><button :class="{active:tab==='profiles'}" @click="tab='profiles'"><UserRound />Profiles <b>{{profiles.length}}</b></button><button :class="{active:tab==='videos'}" @click="tab='videos'"><Play />Videos <b>{{videos.length}}</b></button></nav>
            <div class="saved-summary"><h2>{{ loading ? 'Loading…' : `${tab==='profiles'?filteredProfiles.length:filteredVideos.length} saved ${tab}` }}</h2><Link href="/explore">Discover more</Link></div>

            <section v-if="!loading&&tab==='profiles'&&filteredProfiles.length" class="saved-profile-grid">
                <article v-for="profile in filteredProfiles" :key="profile.id"><div class="saved-profile-avatar"><img v-if="profile.images?.profile" :src="profile.images.profile" /><span v-else>{{profile.name.slice(0,2).toUpperCase()}}</span></div><div><Link :href="`/@${profile.slug}`">{{profile.name}}</Link><small>{{[profile.athlete?.sport?.name,profile.athlete?.position?.name,profile.location?.city].filter(Boolean).join(' · ')||profile.roles?.join(', ')}}</small><p>{{profile.bio||'SportsUniverse member'}}</p></div><button :disabled="busy===`profile-${profile.id}`" @click="unsaveProfile(profile)"><Bookmark fill="currentColor"/>{{busy===`profile-${profile.id}`?'Removing…':'Saved'}}</button></article>
            </section>

            <section v-if="!loading&&tab==='videos'&&filteredVideos.length" class="saved-grid"><article v-for="video in filteredVideos" :key="video.id" class="saved-card"><button class="saved-preview" @click="active=video"><video :src="video.media?.download_url" muted preload="metadata"/><span><Play fill="white"/></span><small><Eye/>{{compact(video.counts?.views)}}</small></button><div class="saved-card-body"><div class="saved-creator"><span>{{video.creator?.name?.slice(0,2).toUpperCase()}}</span><div><Link :href="`/@${video.creator?.slug}`">{{video.creator?.name}}</Link><small>{{video.sport?.name||'SportsUniverse athlete'}}</small></div></div><p>{{video.caption||'SportsUniverse video'}}</p><footer><span><Heart/>{{compact(video.counts?.likes)}}</span><span><MessageCircle/>{{compact(video.counts?.comments)}}</span><button :disabled="busy===`video-${video.id}`" @click="unsaveVideo(video)"><Bookmark fill="currentColor"/>{{busy===`video-${video.id}`?'Removing…':'Saved'}}</button></footer></div></article></section>

            <section v-if="!loading&&!(tab==='profiles'?filteredProfiles.length:filteredVideos.length)" class="saved-empty"><Bookmark/><h2>{{query?'No matching saved items':`No saved ${tab} yet`}}</h2><p>Save {{tab}} while exploring SportsUniverse and they will appear here.</p><Link href="/explore">Explore talent</Link></section>
        </main>
        <div v-if="active" class="saved-player" @click.self="active=null"><section><button @click="active=null"><X/></button><video :src="active.media?.download_url" autoplay controls playsinline/><div><strong>{{active.creator?.name}}</strong><p>{{active.caption}}</p></div></section></div>
    </AppShell>
</template>
