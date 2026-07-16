<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BarChart3, Bookmark, Eye, FileEdit, FolderOpen, Grid3X3, Heart, Lock, MapPin, Play, Repeat2, Settings, Share2, Tag, X } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const auth = usePage().props.auth?.user as any;
const profile = ref<any>(null);
const media = ref<any[]>([]);
const drafts = ref<any[]>([]);
const reposts = ref<any[]>([]);
const favourites = ref<any[]>([]);
const liked = ref<any[]>([]);
const loading = ref(true);
const tabLoading = ref(false);
const profileViews = ref(0);
const tab = ref('videos');
const sort = ref('latest');
const previewPost = ref<any | null>(null);
const initials = computed(() => (profile.value?.name ?? auth?.name ?? 'SU').split(/\s+/).map((part: string) => part[0]).join('').slice(0, 2).toUpperCase());
const profileImage = computed(() => profile.value?.images?.profile || auth?.profile?.profile_image_path || '');
const handle = computed(() => profile.value?.slug ? `@${profile.value.slug}` : '@sportuniverse');
const activeMedia = computed(() => tab.value === 'drafts' ? drafts.value : tab.value === 'reposts' ? reposts.value : tab.value === 'favourites' ? favourites.value : tab.value === 'liked' ? liked.value : media.value);
const itemDate = (item: any) => new Date(item.published_at ?? item.updated_at ?? 0).getTime();
const sortedMedia = computed(() => [...activeMedia.value].sort((a, b) => sort.value === 'oldest' ? itemDate(a) - itemDate(b) : sort.value === 'popular' ? (b.counts?.views ?? 0) - (a.counts?.views ?? 0) : itemDate(b) - itemDate(a)));
const api = async (url: string) => { const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); const payload = await response.json(); if (!response.ok) throw new Error(payload.message); return payload; };
const loadedTabs = new Set(['videos']);
const selectTab = async (next: string) => {
    tab.value = next;
    if (loadedTabs.has(next)) return;
    tabLoading.value = true;
    try {
        const payload = await api(next === 'drafts' ? '/api/v1/videos/mine?status=draft' : `/api/v1/videos/mine/${next}`);
        if (next === 'drafts') drafts.value = payload.data ?? [];
        if (next === 'reposts') reposts.value = payload.data ?? [];
        if (next === 'favourites') favourites.value = payload.data ?? [];
        if (next === 'liked') liked.value = payload.data ?? [];
        loadedTabs.add(next);
    } finally { tabLoading.value = false; }
};
onMounted(async () => {
    try { const [p, m] = await Promise.all([api('/api/v1/profile'), api('/api/v1/videos/mine?status=published')]); profile.value = p.data ?? p; media.value = m.data ?? []; }
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
                <button :class="{ active: tab === 'drafts' }" @click="selectTab('drafts')"><FileEdit :size="16" /> Drafts</button>
                <div class="profile-sort"><button v-for="option in ['latest','popular','oldest']" :key="option" :class="{ active: sort === option }" @click="sort = option">{{ option }}</button></div>
            </nav>
            <p v-if="loading || tabLoading" class="profile-empty">Loading {{ tab === 'videos' ? 'your videos' : tab }}…</p>
            <section v-else-if="sortedMedia.length" class="profile-video-grid">
                <article v-for="item in sortedMedia" :key="item.id" class="profile-video profile-post-card" role="button" tabindex="0" :aria-label="`Preview ${item.caption || 'post'}`" @click="previewPost = item" @keydown.enter="previewPost = item">
                    <div class="profile-post-media">
                        <video v-if="item.media?.download_url" :src="item.media.download_url" muted preload="metadata" />
                        <img v-else-if="item.images?.length" :src="item.images.find((image:any) => image.is_cover)?.download_url ?? item.images[0].download_url" alt="Post image" />
                        <div v-else class="profile-post-placeholder"><FileEdit :size="30" /><span>Media processing</span></div>
                        <span class="profile-post-views"><Play :size="13" /> {{ item.counts?.views ?? 0 }}</span>
                        <span v-if="item.status === 'draft'" class="profile-draft-badge">Draft</span>
                        <Lock v-else-if="item.visibility !== 'public'" class="video-lock" :size="14" />
                    </div>
                    <div class="profile-post-details">
                        <p>{{ item.caption || (item.status === 'draft' ? 'Untitled draft' : 'No caption') }}</p>
                        <div v-if="item.hashtags?.length" class="profile-post-tags"><Tag :size="13" /><span v-for="tag in item.hashtags" :key="tag">#{{ String(tag).replace(/^#/, '') }}</span></div>
                        <div class="profile-post-meta">
                            <span v-if="item.sport?.name">{{ item.sport.name }}</span>
                            <span v-if="item.location?.name"><MapPin :size="12" />{{ item.location.name }}</span>
                        </div>
                        <span class="profile-preview-hint"><Eye :size="13" /> Preview post</span>
                    </div>
                </article>
            </section>
            <section v-else class="profile-empty"><component :is="tab === 'liked' ? Heart : tab === 'favourites' ? Bookmark : tab === 'drafts' ? FileEdit : Grid3X3" :size="34" /><h2>{{ tab === 'videos' ? 'Upload your first video' : `No ${tab} yet` }}</h2><p>{{ tab === 'videos' ? 'Your published posts will appear here.' : tab === 'drafts' ? 'Posts saved as drafts will appear here.' : `Your ${tab} content will appear here.` }}</p><Link v-if="tab === 'videos' || tab === 'drafts'" href="/upload">{{ tab === 'drafts' ? 'Create a post' : 'Upload video' }}</Link></section>

            <Transition name="profile-preview">
                <div v-if="previewPost" class="post-preview-overlay" @click.self="previewPost = null">
                    <article class="post-preview-dialog" role="dialog" aria-modal="true" aria-label="Post preview">
                        <header><div><span>{{ previewPost.status === 'draft' ? 'Draft preview' : 'Post preview' }}</span><strong>{{ previewPost.visibility }}</strong></div><button type="button" aria-label="Close preview" @click="previewPost = null"><X :size="22" /></button></header>
                        <div class="post-preview-content">
                            <div class="post-preview-media">
                                <video v-if="previewPost.media?.download_url" :src="previewPost.media.download_url" controls autoplay playsinline preload="metadata" />
                                <div v-else-if="previewPost.images?.length" class="post-preview-images"><img v-for="image in previewPost.images" :key="image.id" :src="image.download_url" alt="Post image" /></div>
                                <div v-else class="profile-post-placeholder"><FileEdit :size="38" /><span>This post’s media is still processing.</span></div>
                            </div>
                            <section class="post-preview-copy">
                                <h2>{{ previewPost.caption || (previewPost.status === 'draft' ? 'Untitled draft' : 'No caption') }}</h2>
                                <div v-if="previewPost.hashtags?.length" class="post-preview-tags"><span v-for="tag in previewPost.hashtags" :key="tag">#{{ String(tag).replace(/^#/, '') }}</span></div>
                                <dl>
                                    <div v-if="previewPost.sport?.name"><dt>Sport</dt><dd>{{ previewPost.sport.name }}</dd></div>
                                    <div v-if="previewPost.location?.name"><dt>Location</dt><dd><MapPin :size="14" />{{ previewPost.location.name }}</dd></div>
                                    <div><dt>Status</dt><dd>{{ previewPost.status }}</dd></div>
                                    <div><dt>Visibility</dt><dd>{{ previewPost.visibility }}</dd></div>
                                </dl>
                                <div class="post-preview-stats"><span><Play :size="14" />{{ previewPost.counts?.views ?? 0 }} views</span><span><Heart :size="14" />{{ previewPost.counts?.likes ?? 0 }} likes</span></div>
                            </section>
                        </div>
                    </article>
                </div>
            </Transition>
        </main>
    </AppShell>
</template>

<style scoped>
.profile-post-card { aspect-ratio: auto; overflow: hidden; color: #172033; border: 1px solid #e1e7ef; border-radius: 12px; background: #fff; }
.profile-post-card { cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
.profile-post-card:hover,.profile-post-card:focus-visible { outline: none; transform: translateY(-2px); box-shadow: 0 12px 28px rgba(20,32,51,.13); }
.profile-post-media { position: relative; aspect-ratio: 3/4; overflow: hidden; background: #111827; }
.profile-post-media video,.profile-post-media img { width: 100%; height: 100%; object-fit: cover; }
.profile-post-placeholder { display: grid; height: 100%; place-content: center; justify-items: center; gap: .5rem; color: #aeb8c7; font-size: .72rem; }
.profile-post-views { position: absolute; bottom: .65rem; left: .65rem; display: flex; align-items: center; gap: .25rem; color: #fff; font-size: .68rem; text-shadow: 0 1px 5px #000; }
.profile-draft-badge { position: absolute; top: .65rem; right: .65rem; padding: .3rem .55rem; color: #fff; border-radius: 999px; background: #e83e95; font-size: .65rem; font-weight: 800; }
.profile-post-details { display: grid; gap: .55rem; padding: .8rem; }
.profile-post-details p { min-height: 2.6em; margin: 0; overflow: hidden; font-size: .78rem; font-weight: 700; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.profile-post-tags { display: flex; align-items: center; gap: .3rem; overflow: hidden; color: #2469f5; font-size: .66rem; white-space: nowrap; }
.profile-post-meta { display: flex; flex-wrap: wrap; gap: .4rem .7rem; color: #748095; font-size: .65rem; }
.profile-post-meta span { display: inline-flex; align-items: center; gap: .2rem; }
.profile-preview-hint { display: flex; align-items: center; gap: .3rem; color: #2469f5; font-size: .65rem; font-weight: 750; }
.post-preview-overlay { position: fixed; z-index: 1000; inset: 0; display: grid; place-items: center; padding: 1.5rem; background: rgba(7,12,22,.76); backdrop-filter: blur(5px); }
.post-preview-dialog { width: min(980px,100%); max-height: calc(100vh - 3rem); overflow: hidden; border-radius: 18px; background: #fff; box-shadow: 0 28px 80px rgba(0,0,0,.35); }
.post-preview-dialog > header { display: flex; align-items: center; justify-content: space-between; padding: .85rem 1rem; border-bottom: 1px solid #e6ebf2; }
.post-preview-dialog > header div { display: flex; align-items: center; gap: .65rem; text-transform: capitalize; }
.post-preview-dialog > header span { font-size: .9rem; font-weight: 800; }.post-preview-dialog > header strong { padding: .25rem .5rem; color: #647187; border-radius: 999px; background: #f0f3f8; font-size: .62rem; }
.post-preview-dialog > header button { display: grid; width: 38px; height: 38px; place-items: center; color: #172033; border: 0; border-radius: 50%; background: #f0f3f8; cursor: pointer; }
.post-preview-content { display: grid; grid-template-columns: minmax(0,1.35fr) minmax(280px,.65fr); max-height: calc(100vh - 8rem); }
.post-preview-media { display: grid; min-height: 560px; overflow: auto; place-items: center; background: #070b12; }
.post-preview-media video { width: 100%; max-height: calc(100vh - 8rem); object-fit: contain; }
.post-preview-images { display: flex; width: 100%; height: 100%; overflow-x: auto; scroll-snap-type: x mandatory; }.post-preview-images img { flex: 0 0 100%; width: 100%; object-fit: contain; scroll-snap-align: start; }
.post-preview-copy { overflow-y: auto; padding: 1.4rem; }.post-preview-copy h2 { margin: 0 0 .8rem; font-size: 1.1rem; line-height: 1.45; }.post-preview-tags { display: flex; flex-wrap: wrap; gap: .4rem; color: #2469f5; font-size: .78rem; }
.post-preview-copy dl { display: grid; gap: .75rem; margin: 1.4rem 0; }.post-preview-copy dl div { padding-bottom: .65rem; border-bottom: 1px solid #edf0f5; }.post-preview-copy dt { color: #7b8798; font-size: .65rem; text-transform: uppercase; }.post-preview-copy dd { display: flex; align-items: center; gap: .3rem; margin: .25rem 0 0; font-size: .82rem; font-weight: 700; text-transform: capitalize; }
.post-preview-stats { display: flex; gap: 1rem; color: #5e6b7e; font-size: .72rem; }.post-preview-stats span { display: flex; align-items: center; gap: .3rem; }
.profile-preview-enter-active,.profile-preview-leave-active { transition: opacity .18s ease; }.profile-preview-enter-from,.profile-preview-leave-to { opacity: 0; }
@media (max-width: 700px) { .profile-post-details { padding: .55rem; }.profile-post-details p { font-size: .7rem; }.profile-post-meta { display: grid; gap: .25rem; } }
@media (max-width: 700px) { .post-preview-overlay { padding: 0; place-items: end center; }.post-preview-dialog { max-height: 94vh; border-radius: 18px 18px 0 0; }.post-preview-content { display: block; max-height: calc(94vh - 60px); overflow-y: auto; }.post-preview-media { min-height: 48vh; }.post-preview-media video { max-height: 58vh; }.post-preview-copy { overflow: visible; } }
</style>
