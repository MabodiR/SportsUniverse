<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { BarChart3, Bell, Bookmark, BriefcaseBusiness, ChevronDown, ClipboardList, Compass, Download, FileBarChart, Flag, FolderKanban, Heart, Home, LogOut, Menu, MessageCircle, Radio, Search, Settings, Shield, Smartphone, Sparkles, Tags, Upload, UserRound, Users, X } from '@lucide/vue';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import BrandLogo from '../Components/BrandLogo.vue';

const page = usePage();
const user = page.props.auth?.user as any;
const userItems = [
    { label: 'For You', href: '/feed', icon: Home },
    { label: 'Following', href: '/following', icon: Users },
    { label: 'Discover Talent', href: '/explore', icon: Compass },
    { label: 'Women in Sports', href: '/women-in-sports', icon: Heart },
    { label: 'Upload', href: '/upload', icon: Upload },
    { label: 'Live', href: '/live', icon: Radio },
    { label: 'Opportunities', href: '/opportunities', icon: BriefcaseBusiness },
    { label: 'Club & Scout Tools', href: '/club-tools', icon: ClipboardList, clubOnly: true },
    { label: 'Messages', href: '/messages', icon: MessageCircle },
    { label: 'Notifications', href: '/notifications', icon: Bell },
    { label: 'Profile', href: '/profile', icon: UserRound },
];
const moreItems = [
    { label: 'Metrics & Analytics', href: '/analytics', icon: BarChart3 },
    { label: 'Saved posts', href: '/saved', icon: Bookmark },
    { label: 'Promote', href: '/sponsorship', icon: Sparkles },
    { label: 'Devices & sessions', href: '/settings/devices', icon: Smartphone },
    { label: 'Profile settings', href: '/profile/edit', icon: Settings },
];
const adminItems = [
    { label: 'Dashboard', href: '/admin', icon: Shield },
    { label: 'Users', href: '/admin/users', icon: Users },
    { label: 'Moderation', href: '/management/comments', icon: Flag },
    { label: 'Reports', href: '/management/reports', icon: FileBarChart },
    { label: 'Campaigns', href: '/management/campaigns', icon: FolderKanban },
    { label: 'Taxonomy', href: '/management/taxonomy', icon: Tags },
    { label: 'Settings', href: '/management/settings', icon: Settings },
];
const isAdmin = user?.roles?.some((role: any) => role.name === 'admin');
const canUseClubTools = user?.roles?.some((role: any) => ['club', 'academy', 'scout', 'agent', 'admin'].includes(role.name));
const followingCount = ref(user?.following_count ?? 0);
const navCounts = page.props.nav_counts as Record<string, number> ?? {};
const realtimeCounts=ref({...navCounts});
const badgeFor = (item: any) => ({ '/feed': realtimeCounts.value.feed, '/following': realtimeCounts.value.following, '/opportunities': realtimeCounts.value.opportunities, '/messages': realtimeCounts.value.messages, '/notifications': realtimeCounts.value.notifications }[item.href] ?? 0);
const searchQuery = ref('');
const menuOpen = ref(false);
const moreOpen = ref(page.url.startsWith('/analytics') || page.url.startsWith('/settings') || page.url === '/saved' || page.url === '/sponsorship');
const installPrompt = ref<any>(null);
const searchResults = ref<any[]>([]);
const searchOpen = ref(false);
const searchLoading = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
let searchController: AbortController | undefined;
const runSearch = async () => {
    const query = searchQuery.value.trim();
    if (!query || !user) { searchResults.value = []; searchOpen.value = !!query; return; }
    searchController?.abort(); searchController = new AbortController(); searchLoading.value = true; searchOpen.value = true;
    try {
        const response = await fetch(`/api/v1/search/profiles?q=${encodeURIComponent(query)}&per_page=8`, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: searchController.signal });
        if (!response.ok) throw new Error('Search failed');
        const payload = await response.json(); searchResults.value = payload.data ?? [];
    } catch (error: any) { if (error.name !== 'AbortError') searchResults.value = []; }
    finally { searchLoading.value = false; }
};
watch(searchQuery, () => { clearTimeout(searchTimer); searchTimer = setTimeout(runSearch, 250); });
const submitSearch = () => { if (!user) return router.visit('/login'); if (searchResults.value[0]) router.visit(`/@${searchResults.value[0].slug}`); else router.visit(`/explore?q=${encodeURIComponent(searchQuery.value.trim())}`); searchOpen.value = false; };
const closeSearch = (event: MouseEvent) => { if (!(event.target as Element).closest('.global-search')) searchOpen.value = false; };
const updateFollowingCount = (event: Event) => { followingCount.value = (event as CustomEvent<number>).detail; };
const onRealtimeNotification=(event:Event)=>{const data=(event as CustomEvent<any>).detail;realtimeCounts.value.notifications=(realtimeCounts.value.notifications??0)+1;if(data.event==='new_message'||data.event==='message_request_received')realtimeCounts.value.messages=(realtimeCounts.value.messages??0)+1};
const captureInstall=(event:Event)=>{event.preventDefault();installPrompt.value=event};const installApp=async()=>{await installPrompt.value?.prompt();installPrompt.value=null};
onMounted(() => { window.addEventListener('following-count-changed', updateFollowingCount);window.addEventListener('sportuniverse:notification',onRealtimeNotification);window.addEventListener('beforeinstallprompt',captureInstall); document.addEventListener('click', closeSearch); });
onUnmounted(() => { window.removeEventListener('following-count-changed', updateFollowingCount);window.removeEventListener('sportuniverse:notification',onRealtimeNotification);window.removeEventListener('beforeinstallprompt',captureInstall); document.removeEventListener('click', closeSearch); clearTimeout(searchTimer); searchController?.abort(); });

const logout = () => user ? router.post('/logout') : router.visit('/login');
</script>

<template>
    <div class="su-app app-shell">
        <button v-if="menuOpen" class="mobile-nav-backdrop" aria-label="Close navigation" @click="menuOpen = false" />
        <aside class="sidebar" :class="{ 'mobile-open': menuOpen }">
            <div class="mobile-sidebar-head"><Link class="mobile-sidebar-brand" href="/feed" aria-label="SportUniverse home" @click="menuOpen = false"><img :src="'/images/logo/sportuniverse-logo-horizontal-dark.png'" alt="SportUniverse" /></Link><button aria-label="Close navigation" @click="menuOpen = false"><X /></button></div>
            <Link class="desktop-sidebar-brand" href="/feed"><BrandLogo /></Link>
            <nav class="nav-list" aria-label="Primary navigation">
                <Link v-for="item in userItems.filter(item => !item.clubOnly || canUseClubTools)" :key="item.label" :href="item.href" class="nav-item" :class="{ active: page.url === item.href }" @click="menuOpen = false">
                    <component :is="item.icon" class="nav-icon" />
                    <span>{{ item.label }}</span>
                    <small v-if="badgeFor(item)" class="nav-count">{{ badgeFor(item) > 99 ? '99+' : badgeFor(item) }}</small>
                </Link>
                <button class="nav-item more-toggle" :class="{ active: moreOpen }" type="button" :aria-expanded="moreOpen" @click="moreOpen = !moreOpen"><Menu class="nav-icon"/><span>More</span><ChevronDown class="more-chevron" :class="{ open: moreOpen }"/></button>
                <div v-if="moreOpen" class="more-menu"><Link v-for="item in moreItems" :key="item.href" :href="item.href" class="more-menu-item" :class="{ active: page.url.startsWith(item.href) }" @click="menuOpen=false"><component :is="item.icon"/><span>{{ item.label }}</span></Link></div>
                <template v-if="isAdmin">
                    <div class="nav-section-label">Admin</div>
                    <Link v-for="item in adminItems" :key="item.label" :href="item.href" class="nav-item" :class="{ active: page.url === item.href }">
                        <component :is="item.icon" class="nav-icon" />
                        <span>{{ item.label }}</span>
                    </Link>
                </template>
            </nav>
            <button class="sidebar-logout nav-item" type="button" @click="logout">
                <LogOut :size="16" />
                <span>{{ user ? 'Logout' : 'Sign in' }}</span>
            </button>
        </aside>
        <div id="main-content" class="shell-main" role="main" tabindex="-1">
            <header class="topbar">
                <button class="mobile-menu-button" aria-label="Open navigation" @click="menuOpen = true"><Menu /></button>
                <Link class="mobile-topbar-brand" href="/feed" aria-label="SportUniverse home"><img :src="'/images/logo/sportuniverse-logo-horizontal-transparent-black.png'" alt="SportUniverse" /></Link>
                <form class="search global-search" role="search" @submit.prevent="submitSearch">
                    <Search />
                    <input v-model="searchQuery" class="su-input" placeholder="Search players, clubs, trials, coaches..." aria-label="Search SportUniverse" autocomplete="off" @focus="searchOpen = !!searchQuery" @keydown.esc="searchOpen = false" />
                    <div v-if="searchOpen" class="global-search-results">
                        <p v-if="searchLoading" class="global-search-status">Searching…</p>
                        <template v-else-if="searchResults.length"><Link v-for="result in searchResults" :key="result.id" :href="`/@${result.slug}`" class="global-search-result" @click="searchOpen = false"><span class="search-result-avatar"><img v-if="result.images?.profile" :src="result.images.profile" alt="" /><span v-else>{{ result.name?.slice(0,2).toUpperCase() }}</span></span><span><strong>{{ result.name }}</strong><small>{{ [result.athlete?.sport?.name, result.athlete?.position?.name, result.location?.city].filter(Boolean).join(' • ') || result.roles?.join(', ') }}</small></span></Link></template>
                        <p v-else class="global-search-status">No matching profiles found.</p>
                        <button v-if="searchQuery.trim()" type="submit" class="global-search-all">View all results for “{{ searchQuery.trim() }}”</button>
                    </div>
                </form>
                <span class="top-spacer" />
                <template v-if="!user"><Link href="/login" class="su-btn su-btn-ghost" style="min-height:40px">Sign in</Link><Link href="/register" class="su-btn su-btn-primary" style="min-height:40px">Join now</Link></template>
                <Link href="/mobile-app" class="icon-button mobile-app-link" aria-label="Get the SportUniverse mobile app" title="Get the mobile app"><Smartphone :size="18" /></Link>
                <Link :href="user ? '/notifications' : '/login'" class="icon-button" aria-label="Notifications"><Bell :size="19" /></Link>
                <button v-if="installPrompt" class="install-app-button" type="button" @click="installApp"><Download/><span>Install</span></button>
            </header>
            <slot />
        </div>
        <nav v-if="user" class="mobile-bottom-nav" aria-label="Mobile navigation">
            <Link href="/feed" :class="{ active: page.url.startsWith('/feed') }"><Home/><span>Home</span></Link>
            <Link href="/explore" :class="{ active: page.url.startsWith('/explore') }"><Search/><span>Discover</span></Link>
            <Link href="/upload?camera=1" class="mobile-create" :class="{ active: page.url.startsWith('/upload') }"><Upload/><span>Create</span></Link>
            <Link href="/messages" :class="{ active: page.url.startsWith('/messages') }"><MessageCircle/><i v-if="realtimeCounts.messages">{{ realtimeCounts.messages > 9 ? '9+' : realtimeCounts.messages }}</i><span>Inbox</span></Link>
            <Link href="/profile" :class="{ active: page.url.startsWith('/profile') }"><UserRound/><span>Profile</span></Link>
        </nav>
    </div>
</template>
