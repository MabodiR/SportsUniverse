<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Award, BarChart3, BriefcaseBusiness, Plus, Trash2 } from '@lucide/vue';
import { computed, nextTick, onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const props = withDefaults(defineProps<{ initialTab?: string }>(), { initialTab: 'history' });
const tab = ref(props.initialTab);
const data = ref<any>({ history: [], achievements: [], statistics: [] });
const busy = ref(false);
const error = ref('');
const fieldErrors = ref<Record<string, string[]>>({});
const activeForm = ref<HTMLFormElement | null>(null);
const sports = ref<any[]>([]);
const levelOptions = ['School', 'Academy', 'Community / grassroots', 'Amateur', 'Club', 'District', 'Regional', 'Provincial', 'Varsity / university', 'Semi-professional', 'Professional', 'National', 'International'];
const emptyHistory = () => ({ team_name: '', sport_id: '', position_id: '', level: '', started_on: '', ended_on: '', is_current: false, description: '' });
const history = ref(emptyHistory());
const positionOptions = computed(() => sports.value.find((sport) => String(sport.id) === String(history.value.sport_id))?.positions ?? []);
const achievement = ref({ title: '', issuer: '', achieved_on: '', description: '' });
const statistic = ref({ season: '', competition: '', name: '', value: '', unit: '' });
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const api = async (url: string, options: RequestInit = {}) => {
    const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() } });
    const payload = await response.json();
    if (!response.ok) {
        const errors = Object.fromEntries(Object.entries(payload.errors ?? {}).map(([field, messages]) => [field, Array.isArray(messages) ? messages.map(String) : [String(messages)]]));
        const message = Object.values(errors).flat()[0] ?? payload.message ?? 'Something went wrong. Please try again.';
        throw Object.assign(new Error(message), { fieldErrors: errors });
    }
    return payload.data ?? payload;
};
const load = async () => { data.value = await api('/api/v1/profile/career'); };
const loadSports = async () => { sports.value = await api('/api/v1/sports'); };
const selectSport = () => { history.value.position_id = ''; clearFieldError('sport_id'); clearFieldError('position_id'); };
const clearFieldError = (field: string) => { delete fieldErrors.value[field]; };
const focusFirstInvalidField = async () => {
    await nextTick();
    const firstField = Object.keys(fieldErrors.value)[0];
    if (!firstField) return;
    activeForm.value?.querySelector<HTMLElement>(`[data-field="${CSS.escape(firstField)}"]`)?.focus();
};
const add = async (type: string, form: any) => {
    busy.value = true; error.value = ''; fieldErrors.value = {};
    try {
        await api(`/api/v1/profile/career/${type}`, { method: 'POST', body: JSON.stringify(form) });
        if (type === 'history') history.value = emptyHistory();
        if (type === 'achievements') achievement.value = { title: '', issuer: '', achieved_on: '', description: '' };
        if (type === 'statistics') statistic.value = { season: '', competition: '', name: '', value: '', unit: '' };
        await load();
    } catch (exception: any) {
        fieldErrors.value = type === 'history' ? (exception.fieldErrors ?? {}) : {};
        if (Object.keys(fieldErrors.value).length) await focusFirstInvalidField();
        else error.value = exception.message;
    }
    finally { busy.value = false; }
};
const remove = async (type: string, id: number) => {
    if (!confirm('Remove this career record?')) return;
    await api(`/api/v1/profile/career/${type}/${id}`, { method: 'DELETE' });
    await load();
};
const date = (value?: string) => value ? new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString(undefined, { year: 'numeric', month: 'short' }) : '';
onMounted(async () => { await Promise.all([load(), loadSports()]); });
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
                <form ref="activeForm" novalidate @submit.prevent="add('history', history)">
                    <h2>Add team or career entry</h2>
                    <label :class="{ 'has-error': fieldErrors.team_name }">Team, club, school or academy<input v-model="history.team_name" data-field="team_name" :aria-invalid="!!fieldErrors.team_name" @input="clearFieldError('team_name')" /><small v-if="fieldErrors.team_name" class="field-error">{{ fieldErrors.team_name[0] }}</small></label>
                    <div>
                        <label :class="{ 'has-error': fieldErrors.sport_id }">Sport<select v-model="history.sport_id" data-field="sport_id" :aria-invalid="!!fieldErrors.sport_id" @change="selectSport"><option value="">Select a sport</option><option v-for="sport in sports" :key="sport.id" :value="sport.id">{{ sport.name }}</option></select><small v-if="fieldErrors.sport_id" class="field-error">{{ fieldErrors.sport_id[0] }}</small></label>
                        <label :class="{ 'has-error': fieldErrors.position_id }">Position / role<select v-model="history.position_id" data-field="position_id" :disabled="!history.sport_id" :aria-invalid="!!fieldErrors.position_id" @change="clearFieldError('position_id')"><option value="">{{ history.sport_id ? 'Select a position or role' : 'Select a sport first' }}</option><option v-for="position in positionOptions" :key="position.id" :value="position.id">{{ position.name }}</option></select><small v-if="fieldErrors.position_id" class="field-error">{{ fieldErrors.position_id[0] }}</small></label>
                    </div>
                    <label :class="{ 'has-error': fieldErrors.level }">Level<select v-model="history.level" data-field="level" :aria-invalid="!!fieldErrors.level" @change="clearFieldError('level')"><option value="">Select a playing level</option><option v-for="level in levelOptions" :key="level" :value="level">{{ level }}</option></select><small v-if="fieldErrors.level" class="field-error">{{ fieldErrors.level[0] }}</small></label>
                    <div><label :class="{ 'has-error': fieldErrors.started_on }">Started<input v-model="history.started_on" data-field="started_on" type="date" :aria-invalid="!!fieldErrors.started_on" @input="clearFieldError('started_on')" /><small v-if="fieldErrors.started_on" class="field-error">{{ fieldErrors.started_on[0] }}</small></label><label :class="{ 'has-error': fieldErrors.ended_on }">Ended<input v-model="history.ended_on" data-field="ended_on" type="date" :disabled="history.is_current" :aria-invalid="!!fieldErrors.ended_on" @input="clearFieldError('ended_on')" /><small v-if="fieldErrors.ended_on" class="field-error">{{ fieldErrors.ended_on[0] }}</small></label></div>
                    <label class="career-check" :class="{ 'has-error': fieldErrors.is_current }"><input v-model="history.is_current" data-field="is_current" type="checkbox" :aria-invalid="!!fieldErrors.is_current" @change="clearFieldError('is_current'); if (history.is_current) { history.ended_on = ''; clearFieldError('ended_on') }" /> I currently play here<small v-if="fieldErrors.is_current" class="field-error">{{ fieldErrors.is_current[0] }}</small></label>
                    <label :class="{ 'has-error': fieldErrors.description }">Description<textarea v-model="history.description" data-field="description" maxlength="2000" :aria-invalid="!!fieldErrors.description" @input="clearFieldError('description')" /><small v-if="fieldErrors.description" class="field-error">{{ fieldErrors.description[0] }}</small></label>
                    <button :disabled="busy"><Plus />Add entry</button>
                </form>
                <section><h2>Your history</h2><article v-for="item in data.history" :key="item.id"><div><strong>{{ item.team_name }}</strong><span>{{ [item.sport?.name, item.position?.name || item.role, item.level].filter(Boolean).join(' · ') }}</span><small>{{ date(item.started_on) }} — {{ item.is_current ? 'Present' : date(item.ended_on) || 'Unspecified' }}</small><p v-if="item.description">{{ item.description }}</p></div><button @click="remove('history', item.id)"><Trash2 /></button></article><p v-if="!data.history.length" class="career-empty">No career history added yet.</p></section>
            </div>

            <div v-if="tab === 'achievements'" class="career-layout">
                <form ref="activeForm" novalidate @submit.prevent="add('achievements', achievement)">
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
                <form ref="activeForm" novalidate @submit.prevent="add('statistics', statistic)">
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
