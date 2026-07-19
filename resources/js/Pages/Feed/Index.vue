<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Bookmark, Cast, ChevronLeft, ChevronRight, Download, Ellipsis, Eye, Flag, Heart, LockKeyhole, MessageCircle, Megaphone, Pencil, Radio, Repeat2, Send, Share2, Sparkles, Trash2, UserPlus, Users, Volume2, VolumeX, X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const props = defineProps<{ videos: any[]; suggestions: any[]; location?: string | null; sportFilter?: string | null; positionFilter?: string | null; mode?: 'for-you' | 'following' }>();
const page = usePage();
const authenticated = computed(() => Boolean((page.props.auth as any)?.user));
const gateVisible = ref(false);
const videoElements = new Map<string, HTMLVideoElement>();
const soundEnabled = ref(false);
const videoVolume = ref(.75);
const audioControlsPost = ref<string | null>(null);
const liveStreams = ref<any[]>([]);
const activeMedia = ref<Record<string, number>>({});
let touchX = 0;
const recordedViews = new Set<string>();
let observer: IntersectionObserver | null = null;
let sponsoredObserver: IntersectionObserver | null = null;
const sponsoredElements = new Map<Element, any>();
const sponsoredTimers = new Map<Element, ReturnType<typeof setTimeout>>();
const recordedSponsored = new Set<string>();
const demos = [
    { id: 'demo-1', creator: { name: 'Thabo Mokoena', sport: 'Football', position: 'Midfielder', city: 'Johannesburg', completeness: 70 }, caption: 'Turning pressure into possibility. One touch, one chance, one goal.', hashtags: ['Football', 'RisingTalent', 'SouthAfrica'], counts: { views: 12840, likes: 2840, comments: 196, shares: 84, saves: 310 } },
    { id: 'demo-2', creator: { name: 'Naledi Dlamini', sport: 'Netball', position: 'Goal Attack', city: 'Pretoria', completeness: 82 }, caption: 'Speed, vision and the courage to take the shot.', hashtags: ['Netball', 'WomenInSport', 'NextGeneration'], counts: { views: 9200, likes: 1840, comments: 122, shares: 64, saves: 205 } },
    { id: 'demo-3', creator: { name: 'Lwazi Khumalo', sport: 'Athletics', position: 'Sprinter', city: 'Durban', completeness: 76 }, caption: 'The work nobody sees creates the result everybody remembers.', hashtags: ['Sprinting', 'Training', 'RoadToGold'], counts: { views: 7400, likes: 1220, comments: 88, shares: 42, saves: 174 } },
];
const feed = computed(() => props.videos ?? []);
const featured = computed(() => feed.value[0]);
const trending = computed(() => (props.suggestions ?? []).slice(0, 4));
const compact = (value: number) => new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value ?? 0);
const slides = (item: any) => {
    const pictures = [...(item.images ?? [])].sort((a:any,b:any) => Number(b.is_cover)-Number(a.is_cover)).map((image:any) => ({ type: 'image', url: image.url }));
    return [...pictures, ...(item.url ? [{ type: 'video', url: item.url }] : [])];
};
const mediaIndex = (item:any) => activeMedia.value[item.id] ?? 0;
const changeMedia = (item:any, direction:number) => {
    const total=slides(item).length;if(total<2)return;
    activeMedia.value[item.id]=(mediaIndex(item)+direction+total)%total;
    const video=videoElements.get(item.id);if(video) direction ? video.pause() : undefined;
};
const swipeStart=(event:TouchEvent)=>{touchX=event.touches[0].clientX};
const swipeEnd=(item:any,event:TouchEvent)=>{const delta=event.changedTouches[0].clientX-touchX;if(Math.abs(delta)>45)changeMedia(item,delta<0?1:-1)};
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
const actionBusy = ref<string | null>(null);
const commentsPost = ref<any | null>(null);
const comments = ref<any[]>([]);
const commentBody = ref('');
const replyingTo = ref<any | null>(null);
const commentsLoading = ref(false);
const sharePost = ref<any | null>(null);
const controlsPost = ref<any | null>(null);
const notInterestedTarget = ref<any | null>(null);
const preferenceScope = ref('post'), preferenceReason = ref('irrelevant'), preferenceDetails = ref('');
const reportTarget = ref<any | null>(null);
const reportReason = ref('spam'), reportDetails = ref('');
const feedbackBusy = ref(false);
const editingPost = ref<any|null>(null);
const editCaption = ref(''), editHashtags = ref(''), editLocation = ref(''), editVisibility = ref('public'), editBusy = ref(false);
const messaging = ref<any | null>(null);
const messageBody = ref('');
const messagingLoading = ref(false);
const messagingError = ref('');
const request = async (url: string, body: Record<string, unknown> = {}) => {
    const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body) });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message ?? 'Unable to complete this action.');
    return payload.data;
};
const interact = async (item: any, action: 'like' | 'save') => {
    if (!authenticated.value) return showGate();
    actionBusy.value = `${item.id}:${action}`;
    try {
        const data = await request(`/posts/${item.id}/${action}`);
        item.viewer ??= {};
        if (action === 'like') { item.viewer.liked = data.liked; item.counts.likes = data.likes_count; }
        else { item.viewer.saved = data.saved; item.counts.saves = data.saves_count; }
    } finally { actionBusy.value = null; }
};
const openComments = async (item: any) => {
    commentsPost.value = item; commentsLoading.value = true; comments.value = [];
    const response = await fetch(`/posts/${item.id}/comments`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const payload = await response.json(); comments.value = payload.data ?? []; commentsLoading.value = false;
};
const closeComments = () => { commentsPost.value = null; replyingTo.value = null; commentBody.value = ''; };
const submitComment = async () => {
    if (!authenticated.value) return showGate();
    if (!commentBody.value.trim() || !commentsPost.value) return;
    const data = await request(`/posts/${commentsPost.value.id}/comments`, { body: commentBody.value.trim(), ...(replyingTo.value ? { parent_id: replyingTo.value.id } : {}) });
    if (replyingTo.value) {
        const parent = comments.value.find(comment => comment.id === replyingTo.value.id); if (parent) (parent.replies ??= []).push(data);
    } else comments.value.unshift(data);
    commentsPost.value.counts.comments++; commentBody.value = ''; replyingTo.value = null;
};
const likeComment = async (comment: any) => {
    if (!authenticated.value) return showGate();
    const data = await request(`/comments/${comment.id}/like`); comment.liked = data.liked; comment.likes_count = data.likes_count;
};
const share = async (item: any, channel = 'copy_link') => {
    if (!authenticated.value) return showGate();
    const data = await request(`/posts/${item.id}/share`, { channel: ['whatsapp','facebook','repost'].includes(channel) ? channel : 'other' });
    item.counts.shares = data.shares_count;
    if (channel === 'repost') { item.viewer ??= {}; item.viewer.reposted = data.reposted; sharePost.value = null; return; }
    const url = `${window.location.origin}/feed#${item.id}`;
    const encoded = encodeURIComponent(url); const text = encodeURIComponent(item.caption ?? 'SportsUniverse highlight');
    if (channel === 'whatsapp' || channel === 'status') window.open(`https://wa.me/?text=${text}%20${encoded}`, '_blank');
    else if (channel === 'facebook') window.open(`https://www.facebook.com/sharer/sharer.php?u=${encoded}`, '_blank');
    else if (channel === 'telegram') window.open(`https://t.me/share/url?url=${encoded}&text=${text}`, '_blank');
    else if (navigator.share) await navigator.share({ title: item.creator.name, text: item.caption, url }).catch(() => undefined);
    else await navigator.clipboard.writeText(url).catch(() => undefined);
    sharePost.value = null;
};
const hidePost = (item: any) => { const index = props.videos.indexOf(item); if (index >= 0) props.videos.splice(index, 1); controlsPost.value = null; };
const startEdit=(item:any)=>{editingPost.value=item;editCaption.value=item.caption??'';editHashtags.value=(item.hashtags??[]).join(' ');editLocation.value=item.location?.name??'';editVisibility.value=item.visibility??'public';controlsPost.value=null};
const saveEdit=async()=>{if(!editingPost.value)return;editBusy.value=true;try{const response=await fetch('/api/v1/videos/'+editingPost.value.id,{method:'PATCH',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({caption:editCaption.value,hashtags:editHashtags.value.split(/[\s,]+/).filter(Boolean),location_name:editLocation.value||null,visibility:editVisibility.value})});const body=await response.json();if(!response.ok)throw new Error(body.message??'Unable to update post.');Object.assign(editingPost.value,{caption:editCaption.value,hashtags:body.data.hashtags,location:body.data.location,visibility:body.data.visibility});editingPost.value=null}finally{editBusy.value=false}};
const deletePost=async(item:any)=>{if(!confirm('Delete this post permanently?'))return;await fetch('/api/v1/videos/'+item.id,{method:'DELETE',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf()}});hidePost(item)};
const openNotInterested = (item: any) => { notInterestedTarget.value = item; preferenceScope.value = 'post'; preferenceReason.value = 'irrelevant'; preferenceDetails.value = ''; controlsPost.value = null; };
const submitNotInterested = async () => { if (!notInterestedTarget.value) return; feedbackBusy.value = true; try { await request(`/posts/${notInterestedTarget.value.id}/not-interested`, { scope: preferenceScope.value, reason: preferenceReason.value, details: preferenceDetails.value || null }); hidePost(notInterestedTarget.value); notInterestedTarget.value = null; } finally { feedbackBusy.value = false; } };
const openReport = (item: any) => { if (!authenticated.value) return showGate(); reportTarget.value = item; reportReason.value = 'spam'; reportDetails.value = ''; controlsPost.value = null; };
const submitReport = async () => { if (!reportTarget.value) return; feedbackBusy.value = true; try { await request('/post-reports', { type: 'video', id: reportTarget.value.id, reason: reportReason.value, details: reportDetails.value || null }); reportTarget.value = null; } finally { feedbackBusy.value = false; } };
const castPost = async (item: any) => { const video = videoElements.get(item.id) as any; if (video?.remote?.prompt) await video.remote.prompt().catch(() => undefined); else alert('Casting is not available in this browser.'); controlsPost.value = null; };
const messageRequest = async (item: any) => {
    if (!authenticated.value) return showGate();
    messagingLoading.value = true; messagingError.value = ''; messageBody.value = '';
    const response = await fetch(`/athletes/${item.creator.id}/messaging-context`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const payload = await response.json();
    if (response.ok) messaging.value = payload.data; else messagingError.value = payload.message ?? 'Unable to open messaging.';
    messagingLoading.value = false;
};
const sendMessage = async () => {
    if (!messageBody.value.trim() || !messaging.value) return;
    messagingError.value = '';
    try {
        if (messaging.value.mode === 'request') {
            await request('/athlete-message-requests', { recipient_id: messaging.value.recipient.id, message: messageBody.value.trim() });
            messaging.value.requestSent = true;
        } else {
            const data = await request(`/conversations/${messaging.value.conversation.id}/messages`, { body: messageBody.value.trim() });
            messaging.value.conversation.messages.push({ ...data, mine: true, sender: 'You' });
        }
        messageBody.value = '';
    } catch (error: any) { messagingError.value = error.message; }
};
const followSuggestion = async (person: any) => {
    actionBusy.value = `follow:${person.id}`;
    try {
        const data = await request(`/athletes/${person.id}/follow`);
        window.dispatchEvent(new CustomEvent('following-count-changed', { detail: data.viewer_following_count }));
        router.reload({ only: ['videos', 'suggestions'] });
    } finally { actionBusy.value = null; }
};
const followCreator = async (item: any) => {
    if (!authenticated.value) return showGate();
    actionBusy.value = `follow:${item.creator.id}`;
    try {
        const data = await request(`/athletes/${item.creator.id}/follow`);
        feed.value.filter(video => video.creator.id === item.creator.id).forEach(video => { video.viewer ??= {}; video.viewer.following_creator = true; });
        if (item.sponsored?.delivery_id) request(`/api/v1/campaign-deliveries/${item.sponsored.delivery_id}/conversion`, { event: 'follow' }).catch(() => undefined);
        window.dispatchEvent(new CustomEvent('following-count-changed', { detail: data.viewer_following_count }));
    } finally { actionBusy.value = null; }
};
const recordView = async (item: any, video: HTMLVideoElement) => {
    if (!authenticated.value || recordedViews.has(item.id) || video.currentTime < 3) return;
    recordedViews.add(item.id);
    try { const data = await request(`/api/v1/videos/${item.id}/views`, { watched_ms: Math.round(video.currentTime * 1000), completed: video.duration > 0 && video.currentTime / video.duration >= .9 }); item.counts.views = data.views_count; if (item.sponsored?.delivery_id) request(`/api/v1/campaign-deliveries/${item.sponsored.delivery_id}/conversion`, { event: 'video_view' }).catch(() => undefined); }
    catch { recordedViews.delete(item.id); }
};

const showGate = () => {
    if (authenticated.value || gateVisible.value) return;
    gateVisible.value = true;
    document.body.style.overflow = 'hidden';
};
const onScroll = () => {
    if (!authenticated.value && window.scrollY > Math.max(260, window.innerHeight * 0.42)) showGate();
};
const closeGate = () => {
    gateVisible.value = false;
    document.body.style.overflow = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const requireAuth = () => { if (!authenticated.value) showGate(); };
const registerVideo = (element: HTMLVideoElement | null, id: string) => {
    if (!element) return;
    element.volume = videoVolume.value;
    element.muted = !soundEnabled.value || videoVolume.value === 0;
    videoElements.set(id, element);
    observer?.observe(element);
};
const registerSponsored = (element: Element | null, item: any) => {
    if (!element || !item.sponsored?.delivery_id) return;
    sponsoredElements.set(element, item);
    sponsoredObserver?.observe(element);
};
const openSponsored = async (item: any) => {
    const sponsored = item.sponsored;
    if (!sponsored?.delivery_id) return;
    await request(`/api/v1/campaign-deliveries/${sponsored.delivery_id}/click`).catch(() => undefined);
    if (sponsored.goal === 'followers') await request(`/api/v1/campaign-deliveries/${sponsored.delivery_id}/conversion`, { event: 'profile_visit' }).catch(() => undefined);
    if (sponsored.destination_url) window.open(sponsored.destination_url, '_blank', 'noopener,noreferrer');
};
const toggleSound = async (video: HTMLVideoElement) => {
    if (!soundEnabled.value && videoVolume.value === 0) videoVolume.value = .5;
    soundEnabled.value = !soundEnabled.value;
    videoElements.forEach(element => {
        element.volume = videoVolume.value;
        element.muted = !soundEnabled.value || videoVolume.value === 0;
    });
    if (soundEnabled.value && video.paused) await video.play().catch(() => undefined);
};
const changeVolume = (event: Event, video: HTMLVideoElement) => {
    const nextVolume = Number((event.target as HTMLInputElement).value);
    videoVolume.value = Math.min(1, Math.max(0, nextVolume));
    soundEnabled.value = videoVolume.value > 0;
    videoElements.forEach(element => {
        element.volume = videoVolume.value;
        element.muted = videoVolume.value === 0;
    });
    localStorage.setItem('sportuniverse-video-volume', String(videoVolume.value));
    if (videoVolume.value > 0 && video.paused) video.play().catch(() => undefined);
};
const toggleVideo = (event: Event) => {
    const video = event.currentTarget as HTMLVideoElement;
    video.paused ? video.play().catch(() => undefined) : video.pause();
};

onMounted(() => {
    const savedVolume = Number(localStorage.getItem('sportuniverse-video-volume'));
    if (Number.isFinite(savedVolume) && savedVolume >= 0 && savedVolume <= 1) videoVolume.value = savedVolume;
    fetch('/api/v1/live', { headers: { Accept: 'application/json' } }).then(response => response.json()).then(payload => { liveStreams.value = payload.data ?? []; }).catch(() => undefined);
    window.addEventListener('scroll', onScroll, { passive: true });
    observer = new IntersectionObserver((entries) => entries.forEach(entry => {
        const video = entry.target as HTMLVideoElement;
        if (entry.isIntersecting && entry.intersectionRatio >= .65) {
            videoElements.forEach(other => { if (other !== video) other.pause(); });
            if (video.poster && video.currentTime === 0) return;
            video.play().catch(() => undefined);
        } else video.pause();
    }), { threshold: [.25, .65, .9] });
    sponsoredObserver = new IntersectionObserver(entries => entries.forEach(entry => {
        const item = sponsoredElements.get(entry.target);
        const delivery = item?.sponsored?.delivery_id;
        if (!delivery || recordedSponsored.has(delivery)) return;
        if (entry.isIntersecting && entry.intersectionRatio >= .6) {
            if (!sponsoredTimers.has(entry.target)) sponsoredTimers.set(entry.target, setTimeout(() => {
                recordedSponsored.add(delivery); sponsoredTimers.delete(entry.target);
                request(`/api/v1/campaign-deliveries/${delivery}/impression`).catch(() => recordedSponsored.delete(delivery));
            }, 1000));
        } else if (sponsoredTimers.has(entry.target)) {
            clearTimeout(sponsoredTimers.get(entry.target)); sponsoredTimers.delete(entry.target);
        }
    }), { threshold: [.25, .6, .9] });
    requestAnimationFrame(() => videoElements.forEach(video => observer?.observe(video)));
});
onUnmounted(() => { window.removeEventListener('scroll', onScroll); observer?.disconnect(); sponsoredObserver?.disconnect(); sponsoredTimers.forEach(timer => clearTimeout(timer)); videoElements.forEach(video => video.pause()); document.body.style.overflow = ''; });
</script>

<template>
    <Head title="For You" />
    <AppShell>
        <div class="for-you-page">
            <header class="for-you-heading"><div><h1>{{ mode === 'following' ? 'Following' : sportFilter ? `${sportFilter} videos` : positionFilter ? `${positionFilter} highlights` : location ? `Sports in ${location}` : 'For You Sports Feed' }}</h1><p>{{ mode === 'following' ? 'New posts from athletes and accounts you follow.' : sportFilter ? `Athlete posts and highlights tagged ${sportFilter}.` : positionFilter ? `Videos shared by athletes playing ${positionFilter}.` : location ? `Videos posted by athletes in ${location}.` : 'Discover athlete highlights selected for you.' }}</p></div></header>
            <section v-if="liveStreams.length" class="feed-live-now"><header><div><span><Radio/> LIVE NOW</span><h2>People broadcasting now</h2></div><Link href="/live">See everyone</Link></header><div><Link v-for="stream in liveStreams.slice(0,6)" :key="stream.id" :href="`/live/${stream.id}`"><span class="feed-live-avatar"><img v-if="stream.image" :src="stream.image" alt=""/><Radio v-else/><b>LIVE</b></span><strong>{{stream.host_name}}</strong><small>{{stream.title}}</small></Link></div></section>
            <section v-if="mode === 'following' && !feed.length" class="following-empty">
                <div class="following-empty-copy"><Users :size="34" /><h2>Start following accounts</h2><p>Follow athletes you like and their latest posts will appear here.</p></div>
                <div class="top-accounts"><h2>Top accounts</h2><article v-for="person in suggestions" :key="person.id"><Link :href="person.slug ? `/@${person.slug}` : '/explore'" class="top-account-avatar">{{ person.name.slice(0,2).toUpperCase() }}</Link><div><Link :href="person.slug ? `/@${person.slug}` : '/explore'"><strong>{{ person.name }}</strong></Link><p>{{ person.sport }} · {{ compact(person.followers) }} followers</p></div><button class="su-btn su-btn-primary" :disabled="actionBusy === `follow:${person.id}`" @click="followSuggestion(person)">{{ actionBusy === `follow:${person.id}` ? 'Following…' : 'Follow' }}</button></article></div>
            </section>
            <section v-else-if="!feed.length" class="following-empty"><div class="following-empty-copy"><Sparkles :size="34" /><h2>No videos match this feed yet</h2><p>Try another sport, position, or location while new highlights are added.</p><Link href="/feed" class="su-btn su-btn-primary">View all videos</Link></div></section>
            <div class="for-you-layout">
                <template v-if="feed.length">
                <div class="feed-stream">
                    <div v-for="(item, index) in feed" :id="item.id" :key="`${item.id}:${item.sponsored?.delivery_id ?? 'organic'}`" :ref="element => registerSponsored(element as Element,item)" class="featured-video-wrap" :class="{ 'sponsored-post': item.sponsored }">
                        <article class="featured-video-card" :class="`feed-card-${(index % 3) + 1}`">
                            <div class="post-carousel" @touchstart.passive="swipeStart" @touchend.passive="swipeEnd(item,$event)">
                                <template v-for="(slide,slideIndex) in slides(item)" :key="slide.url"><img v-if="slide.type==='image' && mediaIndex(item)===slideIndex" :src="slide.url" :alt="item.caption||'SportsUniverse post image'"/><video v-else-if="slide.type==='video' && mediaIndex(item)===slideIndex" :ref="element => registerVideo(element as HTMLVideoElement,item.id)" class="feed-video" :src="slide.url" :muted="!soundEnabled" loop playsinline preload="metadata" @click="toggleVideo" @timeupdate="recordView(item,$event.currentTarget as HTMLVideoElement)"/></template>
                                <template v-if="slides(item).length>1"><button class="carousel-arrow previous" @click="changeMedia(item,-1)"><ChevronLeft/></button><button class="carousel-arrow next" @click="changeMedia(item,1)"><ChevronRight/></button><div class="carousel-dots"><i v-for="(_,dot) in slides(item)" :class="{active:mediaIndex(item)===dot}"/></div></template>
                            </div>
                            <div v-if="item.url && slides(item)[mediaIndex(item)]?.type==='video'" class="video-audio-control" @click.stop>
                                <button class="mobile-video-audio-menu" aria-label="Video sound controls" :aria-expanded="audioControlsPost === item.id" @click="audioControlsPost = audioControlsPost === item.id ? null : item.id"><Ellipsis /></button>
                                <div class="video-audio-options" :class="{ open: audioControlsPost === item.id }">
                                    <button class="video-sound-toggle" :aria-label="soundEnabled ? 'Mute video' : 'Turn on sound'" @click="toggleSound(videoElements.get(item.id)!)"><Volume2 v-if="soundEnabled && videoVolume > 0" /><VolumeX v-else /><span>{{ soundEnabled && videoVolume > 0 ? 'Sound on' : 'Sound off' }}</span></button>
                                    <label class="video-volume-control"><Volume2 aria-hidden="true" /><span class="sr-only">Video volume</span><input type="range" min="0" max="1" step="0.05" :value="videoVolume" aria-label="Video volume" @input="changeVolume($event, videoElements.get(item.id)!)"/><output>{{ Math.round(videoVolume * 100) }}%</output></label>
                                </div>
                            </div>
                            <div class="featured-video-label"><span v-if="item.sponsored" class="sponsored-label"><Megaphone/> Sponsored</span><strong>{{ item.caption || 'SportsUniverse post' }}</strong><small>{{ slides(item)[mediaIndex(item)]?.type === 'video' ? `${index === 0 ? '00:15' : `00:${22 + index * 7}`} · HD` : slides(item).length > 1 ? `Photo ${mediaIndex(item) + 1} of ${slides(item).length}` : 'Photo' }}</small></div>
                            <div class="featured-athlete"><div class="feed-creator-line"><Link class="feed-creator-identity" :href="item.creator.slug ? `/@${item.creator.slug}` : '/explore'"><span class="feed-creator-avatar"><img v-if="item.creator.profile_image" :src="item.creator.profile_image" :alt="`${item.creator.name} profile picture`"/><span v-else>{{ item.creator.name.slice(0, 2).toUpperCase() }}</span></span><h2>{{ item.creator.name }}</h2></Link><button v-if="authenticated && item.creator.id !== (page.props.auth as any)?.user?.id && !item.viewer?.following_creator" :disabled="actionBusy === `follow:${item.creator.id}`" @click="followCreator(item)"><UserPlus :size="14" />{{ actionBusy === `follow:${item.creator.id}` ? 'Following…' : 'Follow' }}</button></div><small><Link v-if="item.creator.sport" :href="`/feed/sport/${encodeURIComponent(item.creator.sport)}`" class="metadata-link">{{ item.creator.sport }}</Link><template v-else>Sport</template> · <Link v-if="item.creator.position" :href="`/feed/position/${encodeURIComponent(item.creator.position)}`" class="metadata-link">{{ item.creator.position }}</Link><template v-else>Athlete</template><template v-if="item.location?.name"> · <Link :href="`/feed/location/${encodeURIComponent(item.location.name)}`" class="metadata-link">{{ item.location.name }}</Link></template></small><p class="feed-caption">{{ item.caption }}</p><div v-if="item.hashtags?.length" class="feed-post-tags"><Link v-for="tag in item.hashtags" :key="tag" :href="`/explore?q=${encodeURIComponent(`#${String(tag).replace(/^#/, '')}`)}`">#{{ String(tag).replace(/^#/, '') }}</Link></div><button v-if="item.sponsored" class="sponsored-cta" @click="openSponsored(item)">{{ item.sponsored.cta }}<ChevronRight/></button><span /></div>
                        </article>
                        <div class="featured-actions">
                            <button class="view-count" data-tooltip="Views" aria-label="Views" disabled><span><Eye /></span><small>{{ compact(item.counts.views) }}</small></button>
                            <button class="feed-like-action" :class="{ active: item.viewer?.liked }" :data-tooltip="item.viewer?.liked ? 'Unlike post' : 'Like post'" :aria-label="item.viewer?.liked ? 'Unlike post' : 'Like post'" @click="interact(item,'like')"><span><Heart :fill="item.viewer?.liked ? 'currentColor' : 'none'" /></span><small>{{ compact(item.counts.likes) }}</small></button>
                            <button :disabled="!item.comments_enabled" :data-tooltip="item.comments_enabled ? 'Comments' : 'Comments disabled'" :aria-label="item.comments_enabled ? 'Comments' : 'Comments disabled'" @click="item.comments_enabled&&openComments(item)"><span><MessageCircle /></span><small>{{ item.comments_enabled ? compact(item.counts.comments) : 'Off' }}</small></button>
                            <button data-tooltip="Share or repost" aria-label="Share or repost" @click="sharePost = item"><span><Send /></span><small>{{ compact(item.counts.shares) }}</small></button>
                            <button data-tooltip="Send message request" aria-label="Send message request" @click="messageRequest(item)"><span class="request"><UserPlus /></span><small>Request</small></button>
                            <button class="mobile-overflow-action" :class="{ active: item.viewer?.saved }" :data-tooltip="item.viewer?.saved ? 'Remove from saved' : 'Save post'" :aria-label="item.viewer?.saved ? 'Remove from saved' : 'Save post'" @click="interact(item,'save')"><span><Bookmark /></span><small>{{ compact(item.counts.saves) }}</small></button>
                            <button data-tooltip="More actions" aria-label="More actions" @click="controlsPost = item"><span><Ellipsis /></span><small>More</small></button>
                        </div>
                    </div>
                </div>
                <aside class="trending-athletes">
                    <h3>Trending athletes</h3>
                    <div v-for="(person, index) in trending" :key="person.id ?? person.name" class="trending-person">
                        <span class="avatar" :class="`trend-${index+1}`">{{ person.name.slice(0,2).toUpperCase() }}</span>
                        <div><strong>{{ person.name }}</strong><small>{{ person.sport }} · {{ compact(person.trend_score ?? 0) }} trend points</small></div>
                        <Link :href="person.slug ? `/@${person.slug}` : '/explore'">View</Link>
                    </div>
                </aside>
                </template>
            </div>
            <p class="snap-note">Scroll snap feed: users scroll through athlete videos one by one, like TikTok but for sports.</p>
        </div>

        <Transition name="gate">
            <div v-if="commentsPost" class="comments-overlay" @click.self="closeComments">
                <section class="comments-drawer">
                    <header><div><h2>Comments</h2><span>{{ commentsPost.counts.comments }}</span></div><button @click="closeComments"><X /></button></header>
                    <div class="comments-list">
                        <p v-if="commentsLoading" class="empty-comments">Loading comments…</p>
                        <p v-else-if="!comments.length" class="empty-comments">Be the first to comment.</p>
                        <article v-for="commentItem in comments" :key="commentItem.id" class="comment-thread">
                            <div class="comment-row"><Link :href="commentItem.user.slug ? `/@${commentItem.user.slug}` : '/explore'" class="comment-avatar">{{ commentItem.user.name.slice(0,2).toUpperCase() }}</Link><div class="comment-copy"><strong>{{ commentItem.user.name }}</strong><p>{{ commentItem.body }}</p><div><small>{{ new Date(commentItem.created_at).toLocaleDateString() }}</small><button @click="replyingTo = commentItem">Reply</button></div></div><button class="comment-like" :class="{ liked: commentItem.liked }" @click="likeComment(commentItem)"><Heart /> <small>{{ compact(commentItem.likes_count) }}</small></button></div>
                            <div v-for="reply in commentItem.replies" :key="reply.id" class="comment-row comment-reply"><Link :href="reply.user.slug ? `/@${reply.user.slug}` : '/explore'" class="comment-avatar">{{ reply.user.name.slice(0,2).toUpperCase() }}</Link><div class="comment-copy"><strong>{{ reply.user.name }}</strong><p>{{ reply.body }}</p><div><small>{{ new Date(reply.created_at).toLocaleDateString() }}</small><button @click="replyingTo = commentItem">Reply</button></div></div><button class="comment-like" :class="{ liked: reply.liked }" @click="likeComment(reply)"><Heart /> <small>{{ compact(reply.likes_count) }}</small></button></div>
                        </article>
                    </div>
                    <form class="comment-composer" @submit.prevent="submitComment"><div v-if="replyingTo" class="replying-label">Replying to {{ replyingTo.user.name }} <button type="button" @click="replyingTo = null">Cancel</button></div><div><input v-model="commentBody" class="su-input" :placeholder="authenticated ? 'Add a comment…' : 'Log in to comment'" :disabled="!authenticated" /><button class="su-btn su-btn-primary" :disabled="!authenticated || !commentBody.trim()">Post</button></div><Link v-if="!authenticated" href="/login" class="login-comment">Log in to comment</Link></form>
                </section>
            </div>
        </Transition>

        <Transition name="gate">
            <div v-if="sharePost" class="action-sheet-overlay" @click.self="sharePost = null"><section class="feed-action-sheet"><header><h2>Share options</h2><button @click="sharePost = null"><X /></button></header><div class="sheet-grid"><button :class="{ active: sharePost.viewer?.reposted }" @click="share(sharePost,'repost')"><Repeat2 />{{ sharePost.viewer?.reposted ? 'Undo repost' : 'Repost' }}</button><button @click="share(sharePost,'whatsapp')"><MessageCircle />WhatsApp</button><button @click="share(sharePost,'copy_link')"><Bookmark />Copy link</button><button @click="share(sharePost,'status')"><Sparkles />Status</button><button @click="share(sharePost,'facebook')"><Share2 />Facebook</button><button @click="share(sharePost,'telegram')"><Send />Telegram</button></div></section></div>
        </Transition>
        <Transition name="gate">
            <div v-if="controlsPost" class="action-sheet-overlay" @click.self="controlsPost = null"><section class="feed-action-sheet"><header><div><h2>{{ controlsPost.viewer?.is_owner ? 'Your post controls' : 'Post controls' }}</h2><p v-if="controlsPost.viewer?.is_owner">Manage your published post.</p></div><button @click="controlsPost = null"><X /></button></header><div class="sheet-list"><template v-if="controlsPost.viewer?.can_manage"><button @click="startEdit(controlsPost)"><Pencil />Edit post</button><button class="danger" @click="deletePost(controlsPost)"><Trash2 />Delete post</button></template><button class="mobile-sheet-action" @click="interact(controlsPost,'save'); controlsPost = null"><Bookmark :fill="controlsPost.viewer?.saved ? 'currentColor' : 'none'" />{{ controlsPost.viewer?.saved ? 'Remove from saved' : 'Save' }} · {{ compact(controlsPost.counts.saves) }}</button><template v-if="!controlsPost.viewer?.is_owner"><button @click="openReport(controlsPost)"><Flag />Report</button><button @click="openNotInterested(controlsPost)"><X />Not interested</button></template><a v-if="controlsPost.url" :href="controlsPost.url" download><Download />Download</a><Link v-if="controlsPost.viewer?.is_owner" href="/sponsorship"><Megaphone />Promote</Link><button @click="castPost(controlsPost)"><Cast />Cast</button><button class="desktop-sheet-save" @click="interact(controlsPost,'save'); controlsPost = null"><Bookmark />{{ controlsPost.viewer?.saved ? 'Remove from saved' : 'Save' }}</button></div></section></div>
        </Transition>
        <Transition name="gate">
            <div v-if="notInterestedTarget" class="action-sheet-overlay" @click.self="notInterestedTarget = null"><form class="post-feedback-sheet" @submit.prevent="submitNotInterested"><header><div><h2>Why aren’t you interested?</h2><p>We’ll use this to improve your feed.</p></div><button type="button" @click="notInterestedTarget = null"><X /></button></header><label>What should we show less of?<select v-model="preferenceScope"><option value="post">Only this post</option><option value="creator">Posts from {{ notInterestedTarget.creator.name }}</option><option v-if="notInterestedTarget.sport?.name || notInterestedTarget.creator.sport" value="sport">{{ notInterestedTarget.sport?.name || notInterestedTarget.creator.sport }} content</option><option value="similar">Similar content and hashtags</option></select></label><label>Reason<select v-model="preferenceReason"><option value="irrelevant">Not relevant to me</option><option value="repetitive">I see too much of this</option><option value="low_quality">Low-quality content</option><option value="not_my_sport">Not interested in this sport</option><option value="other">Something else</option></select></label><label>More details (optional)<textarea v-model="preferenceDetails" maxlength="1000" placeholder="Tell us more…" /></label><footer><button type="button" @click="notInterestedTarget = null">Cancel</button><button class="primary" :disabled="feedbackBusy">{{ feedbackBusy ? 'Saving…' : 'Improve my feed' }}</button></footer></form></div>
        </Transition>
        <Transition name="gate">
            <div v-if="reportTarget" class="action-sheet-overlay" @click.self="reportTarget = null"><form class="post-feedback-sheet" @submit.prevent="submitReport"><header><div><h2>Report this post</h2><p>Your report will be sent to the moderation team.</p></div><button type="button" @click="reportTarget = null"><X /></button></header><label>Reason<select v-model="reportReason"><option value="spam">Spam</option><option value="harassment">Harassment or bullying</option><option value="hate">Hate speech</option><option value="nudity">Nudity or sexual content</option><option value="violence">Violence or dangerous activity</option><option value="fraud">Fraud or scam</option><option value="impersonation">Impersonation</option><option value="copyright">Copyright violation</option><option value="other">Other</option></select></label><label>Details (optional)<textarea v-model="reportDetails" maxlength="5000" placeholder="Help our moderators understand the issue…" /></label><footer><button type="button" @click="reportTarget = null">Cancel</button><button class="danger" :disabled="feedbackBusy">{{ feedbackBusy ? 'Submitting…' : 'Submit report' }}</button></footer></form></div>
        </Transition>
        <Transition name="gate"><div v-if="editingPost" class="action-sheet-overlay" @click.self="editingPost=null"><form class="post-edit-sheet" @submit.prevent="saveEdit"><header><h2>Edit post</h2><button type="button" @click="editingPost=null"><X/></button></header><label>Caption<textarea v-model="editCaption" maxlength="2200"/></label><label>Hashtags<input v-model="editHashtags" placeholder="football talent training"/></label><label>Location<input v-model="editLocation" placeholder="City, venue or suburb"/></label><label>Visibility<select v-model="editVisibility"><option value="public">Everyone</option><option value="followers">Followers</option><option value="private">Only me</option></select></label><button class="su-btn su-btn-primary" :disabled="editBusy">{{editBusy?'Saving…':'Save changes'}}</button></form></div></Transition>
        <Transition name="gate">
            <div v-if="messaging || messagingLoading" class="comments-overlay" @click.self="messaging = null; messagingLoading = false"><section class="message-drawer"><header><div><h2>{{ messaging?.mode === 'conversation' ? messaging.recipient.name : 'Message request' }}</h2><span>{{ messaging?.mode === 'conversation' ? 'Conversation' : 'Introduce yourself before messaging' }}</span></div><button @click="messaging = null"><X /></button></header><div v-if="messagingLoading" class="message-loading">Opening messaging…</div><template v-else-if="messaging"><div v-if="messaging.mode === 'conversation'" class="drawer-messages"><div v-if="!messaging.conversation.messages.length" class="message-loading">You follow each other. Start the conversation.</div><div v-for="message in messaging.conversation.messages" :key="message.id" class="drawer-message" :class="{ mine: message.mine }"><small>{{ message.mine ? 'You' : message.sender }}</small><p>{{ message.body }}</p></div></div><div v-else class="request-intro"><div class="comment-avatar">{{ messaging.recipient.name.slice(0,2).toUpperCase() }}</div><h3>{{ messaging.recipient.name }}</h3><p v-if="!messaging.requestSent">You are not mutual connections yet. Your first message will be sent as a request.</p><p v-else class="request-success">Message request sent successfully.</p></div><p v-if="messagingError" class="form-message error">{{ messagingError }}</p><form v-if="!messaging.requestSent" class="drawer-message-composer" @submit.prevent="sendMessage"><textarea v-model="messageBody" class="su-input" :placeholder="messaging.mode === 'conversation' ? 'Type a message…' : 'Write your message request…'" maxlength="2000" /><button class="su-btn su-btn-primary" :disabled="!messageBody.trim()">{{ messaging.mode === 'conversation' ? 'Send' : 'Send request' }}</button></form></template></section></div>
        </Transition>

        <Transition name="gate">
            <div v-if="gateVisible" class="auth-gate" role="dialog" aria-modal="true" aria-labelledby="auth-gate-title">
                <div class="auth-gate-backdrop" />
                <section class="auth-gate-card su-card">
                    <button class="gate-close" aria-label="Return to preview" @click="closeGate"><X :size="19" /></button>
                    <span class="gate-icon"><LockKeyhole :size="25" /></span>
                    <div class="gate-eyebrow"><Sparkles :size="14" /> You’ve seen a glimpse</div>
                    <h2 id="auth-gate-title">The next play starts with your profile.</h2>
                    <p>Sign in to keep scrolling, follow athletes, save highlights and connect with the people shaping sport.</p>
                    <div class="gate-actions"><Link href="/register" class="su-btn su-btn-primary">Create free account</Link><Link href="/login" class="su-btn su-btn-ghost">Sign in</Link></div>
                    <small>No subscription required · Join athletes, fans, scouts and clubs</small>
                </section>
            </div>
        </Transition>
    </AppShell>
</template>
