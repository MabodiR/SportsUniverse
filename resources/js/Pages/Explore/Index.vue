<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { MapPin, Search, SlidersHorizontal, UserPlus, X } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const user = (usePage().props.auth as any)?.user;
const query = ref(new URLSearchParams(location.search).get('q') ?? '');
const role = ref('athlete'); const sport = ref(''); const position = ref(''); const city = ref(''); const available = ref(false);
const minAge = ref(''); const maxAge = ref(''); const results = ref<any[]>([]); const sports = ref<any[]>([]);
const loading = ref(true); const filtersOpen = ref(false); const following = ref(new Set<number>()); let timer: ReturnType<typeof setTimeout>;
const positions = computed(() => sports.value.find(item => String(item.id) === sport.value)?.positions ?? []);
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
const initials = (name = '') => name.split(/\s+/).map((part: string) => part[0]).join('').slice(0,2).toUpperCase();
const search = async () => {
    loading.value = true; const params = new URLSearchParams({ per_page: '30' });
    if (query.value.trim()) params.set('q', query.value.trim()); if (role.value) params.set('role', role.value); if (sport.value) params.set('sport_id', sport.value); if (position.value) params.set('position_id', position.value); if (city.value.trim()) params.set('city', city.value.trim()); if (available.value) params.set('available', '1'); if (minAge.value) params.set('min_age', minAge.value); if (maxAge.value) params.set('max_age', maxAge.value);
    const response = await fetch(`/api/v1/search/profiles?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); const payload = await response.json(); results.value = payload.data ?? []; loading.value = false;
};
const follow = async (person: any) => { const response = await fetch(`/athletes/${person.id}/follow`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } }); if (response.ok) following.value = new Set([...following.value, person.id]); };
const clear = () => { query.value = ''; role.value = 'athlete'; sport.value = ''; position.value = ''; city.value = ''; available.value = false; minAge.value = ''; maxAge.value = ''; search(); };
watch([query, role, sport, position, city, available, minAge, maxAge], () => { clearTimeout(timer); timer = setTimeout(search, 300); });
watch(sport, () => position.value = '');
onMounted(async () => { const response = await fetch('/api/v1/sports', { headers: { Accept: 'application/json' } }); sports.value = (await response.json()).data ?? []; await search(); });
</script>

<template><Head title="Discover Talent" /><AppShell><main class="discover-page">
    <header class="discover-heading"><div><h1>Discover Talent</h1><p>Search and filter profiles by location, role, age, sport and position.</p></div><form class="discover-search" @submit.prevent="search"><Search :size="16" /><input v-model="query" placeholder="Search athletes, sports, clubs..." /><button type="button" @click="filtersOpen = !filtersOpen"><SlidersHorizontal :size="16" /> Filters</button></form></header>
    <section class="sport-chips"><button :class="{ active: !sport }" @click="sport = ''">All sports</button><button v-for="item in sports.slice(0,8)" :key="item.id" :class="{ active: sport === String(item.id) }" @click="sport = String(item.id)">{{ item.name }}</button></section>
    <section v-if="filtersOpen" class="discover-filters"><label>Role<select v-model="role"><option value="">All roles</option><option v-for="item in ['athlete','coach','scout','agent','club','academy']" :key="item" :value="item">{{ item }}</option></select></label><label>Sport<select v-model="sport"><option value="">All sports</option><option v-for="item in sports" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select></label><label>Position<select v-model="position" :disabled="!sport"><option value="">All positions</option><option v-for="item in positions" :key="item.id" :value="String(item.id)">{{ item.name }}</option></select></label><label>City<input v-model="city" placeholder="e.g. Johannesburg" /></label><label>Minimum age<input v-model="minAge" type="number" min="5" max="100" /></label><label>Maximum age<input v-model="maxAge" type="number" min="5" max="100" /></label><label class="available-filter"><input v-model="available" type="checkbox" /> Available for opportunities</label><button class="clear-filters" @click="clear"><X :size="15" /> Clear filters</button></section>
    <div class="discover-summary"><h2>{{ loading ? 'Searching…' : `${results.length} talent profiles` }}</h2><span>Profiles matching your filters</span></div>
    <section v-if="!loading && results.length" class="talent-grid"><article v-for="person in results" :key="person.id" class="talent-card"><div class="talent-cover" :style="person.images?.cover ? { backgroundImage: `url(${person.images.cover})` } : {}" /><div class="talent-body"><div class="talent-avatar"><img v-if="person.images?.profile" :src="person.images.profile" alt="" /><span v-else>{{ initials(person.name) }}</span></div><span v-if="person.is_available" class="available-badge">Available</span><h3>{{ person.name }}</h3><p class="talent-role">{{ person.athlete?.sport?.name || person.roles?.[0] || 'Sport professional' }}<template v-if="person.athlete?.position?.name"> · {{ person.athlete.position.name }}</template></p><p class="talent-location"><MapPin :size="13" />{{ [person.location?.city, person.location?.province].filter(Boolean).join(', ') || 'South Africa' }}</p><p class="talent-bio">{{ person.bio || 'Building a sporting profile and connecting with opportunities.' }}</p><div class="talent-actions"><Link :href="`/@${person.slug}`">View profile</Link><button v-if="person.id !== user?.id && !following.has(person.id)" @click="follow(person)"><UserPlus :size="14" /> Follow</button><span v-else-if="following.has(person.id)">Following</span></div></div></article></section>
    <section v-else-if="!loading" class="discover-empty"><Search :size="34" /><h2>No talent found</h2><p>Try broadening your filters or searching another location.</p><button @click="clear">Clear all filters</button></section>
</main></AppShell></template>
