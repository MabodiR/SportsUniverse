<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BarChart3, Bookmark, FolderOpen, Grid3X3, Heart, Lock, Play, Repeat2, Settings, Share2 } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const auth = usePage().props.auth?.user as any;
const profile = ref<any>(null);
const media = ref<any[]>([]);
const reposts = ref<any[]>([]);
const favourites = ref<any[]>([]);
const liked = ref<any[]>([]);
const loading = ref(true);
const tabLoading = ref(false);
const profileViews = ref(0);
const tab = ref('videos');
const sort = ref('latest');
const initials = computed(() => (profile.value?.name ?? auth?.name ?? 'SU').split(/\s+/).map((part: string) => part[0]).join('').slice(0, 2).toUpperCase());
const profileImage = computed(() => profile.value?.images?.profile || auth?.profile?.profile_image_path || '');
const handle = computed(() => profile.value?.slug ? `@${profile.value.slug}` : '@sportuniverse');
const activeMedia = computed(() => tab.value === 'reposts' ? reposts.value : tab.value === 'favourites' ? favourites.value : tab.value === 'liked' ? liked.value : media.value);
const sortedMedia = computed(() => [...activeMedia.value].sort((a, b) => sort.value === 'oldest' ? +new Date(a.published_at) - +new Date(b.published_at) : sort.value === 'popular' ? (b.counts?.views ?? 0) - (a.counts?.views ?? 0) : +new Date(b.published_at) - +new Date(a.published_at)));
const api = async (url: string) => { const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); const payload = await response.json(); if (!response.ok) throw new Error(payload.message); return payload; };
const loadedTabs = new Set(['videos']);
const selectTab = async (next: string) => {
    tab.value = next;
    if (loadedTabs.has(next)) return;
    tabLoading.value = true;
    try {
        const payload = await api(`/api/v1/videos/mine/${next}`);
        if (next === 'reposts') reposts.value = payload.data ?? [];
        if (next === 'favourites') favourites.value = payload.data ?? [];
        if (next === 'liked') liked.value = payload.data ?? [];
        loadedTabs.add(next);
    } finally { tabLoading.value = false; }
};
onMounted(async () => {
    try { const [p, m] = await Promise.all([api('/api/v1/profile'), api('/api/v1/videos/mine')]); profile.value = p.data ?? p; media.value = m.data ?? []; }
    finally { loading.value = false; }
    api('/api/v1/analytics/me?days=30').then(analytics => { profileViews.value = (analytics.data ?? analytics).totals?.profile_views ?? 0; });
});
</script>

<template>
    <Head title="Profile" />
    <AppShell>
        <main class="self-profile">
            <section class="self-profile-header">
                <div class="self-avatar"><img v-if="profileImage" :src="profileImage" alt="Profile photo" /><span v-else>{{ initials }}</span></div>
                <div class="self-profile-copy">
                    <div class="self-name"><h1>{{ profile?.name ?? auth?.name }}</h1><span>{{ handle }}</span></div>
                    <div class="self-stats"><span><strong>{{ auth?.following_count ?? 0 }}</strong> Following</span><span><strong>{{ auth?.followers_count ?? 0 }}</strong> Followers</span><span><strong>{{ media.reduce((sum, video) => sum + (video.counts?.likes ?? 0), 0) }}</strong> Likes</span><span><strong>{{ profileViews }}</strong> Profile views</span></div>
                    <div class="self-actions"><Link href="/profile/edit">Edit profile</Link><Link v-if="profile?.roles?.includes('athlete')" href="/profile/statistics"><BarChart3 :size="16" /> Career</Link><Link href="/profile/gallery"><FolderOpen :size="16" /> Library</Link><button>Promote post</button><Link class="round" href="/settings/devices" aria-label="Settings"><Settings :size="18" /></Link><button class="round" aria-label="Share profile"><Share2 :size="18" /></button></div>
                    <p>{{ profile?.bio || 'Add a bio to tell the SportUniverse community about yourself.' }}</p>
                </div>
            </section>
            <nav class="profile-tabs">
                <button :class="{ active: tab === 'videos' }" @click="selectTab('videos')"><Grid3X3 :size="16" /> Videos</button>
                <button :class="{ active: tab === 'reposts' }" @click="selectTab('reposts')"><Repeat2 :size="16" /> Reposts</button>
                <button :class="{ active: tab === 'favourites' }" @click="selectTab('favourites')"><Bookmark :size="16" /> Favourites</button>
                <button :class="{ active: tab === 'liked' }" @click="selectTab('liked')"><Heart :size="16" /> Liked</button>
                <div class="profile-sort"><button v-for="option in ['latest','popular','oldest']" :key="option" :class="{ active: sort === option }" @click="sort = option">{{ option }}</button></div>
            </nav>
            <p v-if="loading || tabLoading" class="profile-empty">Loading {{ tab === 'videos' ? 'your videos' : tab }}…</p>
            <section v-else-if="sortedMedia.length" class="profile-video-grid">
                <article v-for="item in sortedMedia" :key="item.id" class="profile-video"><video :src="item.media?.download_url" muted preload="metadata" /><span><Play :size="13" /> {{ item.counts?.views ?? 0 }}</span><Lock v-if="item.visibility !== 'public'" class="video-lock" :size="14" /></article>
            </section>
            <section v-else class="profile-empty"><component :is="tab === 'liked' ? Heart : tab === 'favourites' ? Bookmark : Grid3X3" :size="34" /><h2>{{ tab === 'videos' ? 'Upload your first video' : `No ${tab} yet` }}</h2><p>{{ tab === 'videos' ? 'Your uploaded videos will appear here.' : `Your ${tab} content will appear here.` }}</p><Link v-if="tab === 'videos'" href="/upload">Upload video</Link></section>
        </main>
    </AppShell>
</template>
