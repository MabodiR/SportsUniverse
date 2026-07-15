<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Award, BarChart3, BriefcaseBusiness, Plus, Trash2 } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const props = withDefaults(defineProps<{ initialTab?: string }>(), { initialTab: 'history' });
const tab = ref(props.initialTab);
const data = ref<any>({ history: [], achievements: [], statistics: [] });
const busy = ref(false);
const error = ref('');
const history = ref({ team_name: '', role: '', level: '', started_on: '', ended_on: '', is_current: false, description: '' });
const achievement = ref({ title: '', issuer: '', achieved_on: '', description: '' });
const statistic = ref({ season: '', competition: '', name: '', value: '', unit: '' });
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const api = async (url: string, options: RequestInit = {}) => {
    const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message ?? Object.values(payload.errors ?? {}).flat().join(' '));
    return payload.data ?? payload;
};
const load = async () => { data.value = await api('/api/v1/profile/career'); };
const add = async (type: string, form: any) => {
    busy.value = true; error.value = '';
    try {
        await api(`/api/v1/profile/career/${type}`, { method: 'POST', body: JSON.stringify(form.value) });
        if (type === 'history') form.value = { team_name: '', role: '', level: '', started_on: '', ended_on: '', is_current: false, description: '' };
        if (type === 'achievements') form.value = { title: '', issuer: '', achieved_on: '', description: '' };
        if (type === 'statistics') form.value = { season: '', competition: '', name: '', value: '', unit: '' };
        await load();
    } catch (exception: any) { error.value = exception.message; }
    finally { busy.value = false; }
};
const remove = async (type: string, id: number) => {
    if (!confirm('Remove this career record?')) return;
    await api(`/api/v1/profile/career/${type}/${id}`, { method: 'DELETE' });
    await load();
};
const date = (value?: string) => value ? new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString(undefined, { year: 'numeric', month: 'short' }) : '';
onMounted(load);
</script>

<template>
    <Head title="Athlete Career" />
    <AppShell>
        <main class="career-page">
            <header><Link href="/profile"><ArrowLeft />Back to profile</Link><h1>Career & performance</h1><p>Build a verified-looking sporting résumé for clubs, scouts, and sponsors.</p></header>
            <nav>
                <button :class="{ active: tab === 'history' }" @click="tab = 'history'"><BriefcaseBusiness />Career history</button>
                <button :class="{ active: tab === 'achievements' }" @click="tab = 'achievements'"><Award />Achievements</button>
                <button :class="{ active: tab === 'statistics' }" @click="tab = 'statistics'"><BarChart3 />Statistics</button>
            </nav>
            <p v-if="error" class="edit-message error">{{ error }}</p>

            <div v-if="tab === 'history'" class="career-layout">
                <form @submit.prevent="add('history', history)">
                    <h2>Add team or career entry</h2>
                    <label>Team, club, school or academy<input v-model="history.team_name" required /></label>
                    <div><label>Position / role<input v-model="history.role" /></label><label>Level<input v-model="history.level" placeholder="Provincial, varsity…" /></label></div>
                    <div><label>Started<input v-model="history.started_on" type="date" /></label><label>Ended<input v-model="history.ended_on" type="date" :disabled="history.is_current" /></label></div>
                    <label class="career-check"><input v-model="history.is_current" type="checkbox" /> I currently play here</label>
                    <label>Description<textarea v-model="history.description" maxlength="2000" /></label>
                    <button :disabled="busy"><Plus />Add entry</button>
                </form>
                <section><h2>Your history</h2><article v-for="item in data.history" :key="item.id"><div><strong>{{ item.team_name }}</strong><span>{{ [item.role, item.level].filter(Boolean).join(' · ') }}</span><small>{{ date(item.started_on) }} — {{ item.is_current ? 'Present' : date(item.ended_on) || 'Unspecified' }}</small><p v-if="item.description">{{ item.description }}</p></div><button @click="remove('history', item.id)"><Trash2 /></button></article><p v-if="!data.history.length" class="career-empty">No career history added yet.</p></section>
            </div>

            <div v-if="tab === 'achievements'" class="career-layout">
                <form @submit.prevent="add('achievements', achievement)">
                    <h2>Add achievement</h2>
                    <label>Achievement title<input v-model="achievement.title" required placeholder="Player of the Tournament" /></label>
                    <label>Awarded by<input v-model="achievement.issuer" /></label>
                    <label>Date achieved<input v-model="achievement.achieved_on" type="date" /></label>
                    <label>Description<textarea v-model="achievement.description" maxlength="2000" /></label>
                    <button :disabled="busy"><Plus />Add achievement</button>
                </form>
                <section><h2>Your achievements</h2><article v-for="item in data.achievements" :key="item.id"><Award /><div><strong>{{ item.title }}</strong><span>{{ item.issuer }}</span><small>{{ date(item.achieved_on) }}</small><p v-if="item.description">{{ item.description }}</p></div><button @click="remove('achievements', item.id)"><Trash2 /></button></article><p v-if="!data.achievements.length" class="career-empty">No achievements added yet.</p></section>
            </div>

            <div v-if="tab === 'statistics'" class="career-layout">
                <form @submit.prevent="add('statistics', statistic)">
                    <h2>Add performance statistic</h2>
                    <div><label>Season<input v-model="statistic.season" required placeholder="2025/26" /></label><label>Competition<input v-model="statistic.competition" /></label></div>
                    <label>Statistic<input v-model="statistic.name" required placeholder="Goals, assists, 100m time…" /></label>
                    <div><label>Value<input v-model="statistic.value" required type="number" step=".01" /></label><label>Unit<input v-model="statistic.unit" placeholder="goals, seconds, %…" /></label></div>
                    <button :disabled="busy"><Plus />Add statistic</button>
                </form>
                <section><h2>Your statistics</h2><article v-for="item in data.statistics" :key="item.id"><div><strong>{{ item.name }}</strong><span>{{ item.season }}<template v-if="item.competition"> · {{ item.competition }}</template></span></div><b>{{ Number(item.value).toLocaleString() }} {{ item.unit }}</b><button @click="remove('statistics', item.id)"><Trash2 /></button></article><p v-if="!data.statistics.length" class="career-empty">No performance statistics added yet.</p></section>
            </div>
        </main>
    </AppShell>
</template>
