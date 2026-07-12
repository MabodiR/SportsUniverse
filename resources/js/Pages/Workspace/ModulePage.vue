<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Check, ChevronRight, Circle, Play, Plus, Search, ShieldCheck, Sparkles } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

type PageDefinition = {
    key: string;
    group: string;
    title: string;
    description: string;
    accent: 'blue' | 'pink' | 'orange' | 'green';
    metrics: Record<string, string>;
    sections: Record<string, string[]>;
    steps: string[];
};

const props = defineProps<{ page: PageDefinition }>();
const loading = ref(false);
const saving = ref(false);
const notice = ref('');
const error = ref('');
const liveItems = ref<any[]>([]);
const title = ref('');
const category = ref('');
const notes = ref('');
const file = ref<File | null>(null);
const quickForm = ref<HTMLFormElement | null>(null);

const endpoints: Record<string, string> = {
    profile: '/api/v1/profile', 'profile-edit': '/api/v1/profile', completeness: '/api/v1/profile',
    gallery: '/api/v1/media?collection=gallery', highlights: '/api/v1/media?collection=highlights', upload: '/api/v1/media', 'upload-status': '/api/v1/media',
    explore: '/api/v1/search/profiles', messages: '/api/v1/conversations', 'message-requests': '/api/v1/message-requests',
    opportunities: '/api/v1/opportunities', applications: '/api/v1/applications/mine', 'application-tracking': '/api/v1/applications/mine',
    notifications: '/api/v1/notifications',
};
const endpoint = computed(() => endpoints[props.page.key]);
const canUpload = computed(() => ['upload', 'gallery', 'highlights'].includes(props.page.key));
const canCreateOpportunity = computed(() => props.page.key === 'opportunity-create');
const canEditProfile = computed(() => ['profile-edit', 'onboarding', 'athlete-onboarding', 'fan-onboarding'].includes(props.page.key));
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const request = async (url: string, options: RequestInit = {}) => {
    const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }), ...(options.headers ?? {}) } });
    const payload = response.status === 204 ? {} : await response.json();
    if (!response.ok) throw new Error(payload.message ?? Object.values(payload.errors ?? {}).flat().join(' ') ?? 'Something went wrong.');
    return payload;
};

const itemLabel = (item: any) => item.title ?? item.name ?? item.original_name ?? item.participants?.map((person: any) => person.name).join(', ') ?? item.status ?? 'SportUniverse item';
const itemMeta = (item: any) => item.description ?? item.bio ?? item.processing_status ?? item.type ?? item.last_message?.body ?? item.city ?? 'View and manage details';
const start = () => quickForm.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });

const load = async () => {
    if (!endpoint.value) return;
    loading.value = true; error.value = '';
    try {
        const query = props.page.key === 'explore' && title.value ? `?q=${encodeURIComponent(title.value)}` : '';
        const payload = await request(endpoint.value + query);
        const data = payload.data ?? payload;
        liveItems.value = Array.isArray(data) ? data : data ? [data] : [];
        if (props.page.key === 'profile-edit' && data) {
            title.value = data.name ?? ''; notes.value = data.bio ?? ''; category.value = data.location?.city ?? '';
        }
    } catch (e: any) { error.value = e.message; }
    finally { loading.value = false; }
};

const submit = async () => {
    saving.value = true; notice.value = ''; error.value = '';
    try {
        if (canUpload.value) {
            if (!file.value) throw new Error('Choose a file before uploading.');
            const form = new FormData(); form.append('file', file.value); form.append('kind', file.value.type.startsWith('video/') ? 'video' : 'image'); form.append('collection', props.page.key === 'upload' ? 'uploads' : props.page.key);
            await request('/api/v1/media', { method: 'POST', body: form });
        } else if (canCreateOpportunity.value) {
            await request('/api/v1/opportunities', { method: 'POST', body: JSON.stringify({ title: title.value, type: category.value || 'trial', description: notes.value, publish: false }) });
        } else if (canEditProfile.value) {
            await request('/api/v1/profile', { method: 'PATCH', body: JSON.stringify({ name: title.value, city: category.value, bio: notes.value }) });
        } else if (props.page.key === 'explore') {
            await load(); notice.value = `${liveItems.value.length} matching profiles loaded.`; return;
        } else {
            localStorage.setItem(`sportuniverse:${props.page.key}:draft`, JSON.stringify({ title: title.value, category: category.value, notes: notes.value }));
        }
        notice.value = 'Changes saved successfully.'; await load();
    } catch (e: any) { error.value = e.message; }
    finally { saving.value = false; }
};

onMounted(load);
</script>

<template>
    <Head :title="page.title" />
    <AppShell>
        <div class="module-page" :class="[`accent-${page.accent}`, `page-${page.key}`]">
            <section class="module-hero su-card">
                <div class="hero-orbit"><Sparkles :size="28" /></div>
                <div class="module-eyebrow"><span />{{ page.group }}</div>
                <h1>{{ page.title }}</h1>
                <p>{{ page.description }}</p>
                <div class="hero-actions">
                    <button class="su-btn su-btn-primary" @click="start">Get started <ArrowRight :size="17" /></button>
                    <Link href="/feed" class="su-btn su-btn-ghost">Back to feed</Link>
                </div>
            </section>

            <div class="metric-grid">
                <article v-for="(value, label) in page.metrics" :key="label" class="metric-card su-card">
                    <small>{{ label }}</small><strong>{{ value }}</strong><span><Check :size="12" /> Updated</span>
                </article>
            </div>

            <div class="module-layout">
                <main class="module-content">
                    <section v-if="endpoint" class="workspace-section su-card live-data">
                        <header><div><small>LIVE DATA</small><h2>Connected to SportUniverse</h2></div><span class="status-pill">{{ loading ? 'Loading' : `${liveItems.length} loaded` }}</span></header>
                        <p v-if="error" class="form-message error">{{ error }}</p>
                        <p v-else-if="!loading && !liveItems.length" class="empty-state">No records yet. Use the form below to add your first one.</p>
                        <div v-else class="workspace-rows">
                            <button v-for="item in liveItems.slice(0, 8)" :key="item.id ?? item.slug" class="workspace-row" @click="start">
                                <span class="row-icon"><Check :size="15" /></span><span><strong>{{ itemLabel(item) }}</strong><small>{{ itemMeta(item) }}</small></span><ChevronRight :size="17" />
                            </button>
                        </div>
                    </section>
                    <section v-for="(items, title, sectionIndex) in page.sections" :key="title" class="workspace-section su-card">
                        <header><div><small>0{{ Number(sectionIndex) + 1 }}</small><h2>{{ title }}</h2></div><button class="icon-button" @click="start"><Plus :size="18" /></button></header>
                        <div class="workspace-rows">
                            <button v-for="(item, index) in items" :key="item" class="workspace-row" @click="start">
                                <span class="row-icon"><Play v-if="page.key === 'video' && index === 0" :size="16" /><Circle v-else :size="11" /></span>
                                <span><strong>{{ item }}</strong><small>{{ index === items.length - 1 ? 'Ready for your input' : 'View and manage details' }}</small></span>
                                <ChevronRight :size="17" />
                            </button>
                        </div>
                    </section>

                    <form ref="quickForm" class="workspace-section su-card quick-form" @submit.prevent="submit">
                        <header><div><small>QUICK ACTION</small><h2>Update details</h2></div><ShieldCheck :size="22" /></header>
                        <div class="field-grid">
                            <label><span class="su-label">{{ page.key === 'explore' ? 'Search talent' : 'Title or name' }}</span><input v-model="title" class="su-input" :placeholder="`Add ${page.title.toLowerCase()} detail`" /></label>
                            <label><span class="su-label">{{ canCreateOpportunity ? 'Opportunity type' : 'Category or city' }}</span><select v-if="canCreateOpportunity" v-model="category" class="su-input"><option value="trial">Trial</option><option value="job">Job</option><option value="training_camp">Training camp</option><option value="sponsorship">Sponsorship</option><option value="scout_day">Scout day</option></select><input v-else v-model="category" class="su-input" placeholder="Optional" /></label>
                        </div>
                        <label v-if="canUpload"><span class="su-label">Media file</span><input class="su-input file-input" type="file" accept="image/*,video/*" @change="file = ($event.target as HTMLInputElement).files?.[0] ?? null" /></label>
                        <label v-else><span class="su-label">Notes or description</span><textarea v-model="notes" class="su-input module-textarea" placeholder="Add useful context…" /></label>
                        <p v-if="notice" class="form-message success">{{ notice }}</p><p v-if="error" class="form-message error">{{ error }}</p>
                        <div class="form-actions"><button type="button" class="su-btn su-btn-ghost" @click="title = ''; category = ''; notes = ''">Clear</button><button class="su-btn su-btn-primary" :disabled="saving">{{ saving ? 'Saving…' : page.key === 'explore' ? 'Search' : canUpload ? 'Upload file' : 'Save changes' }}</button></div>
                    </form>
                </main>

                <aside class="module-sidebar">
                    <section class="workflow-card su-card">
                        <span class="module-eyebrow"><span /> Workflow</span><h3>Your progress</h3>
                        <div class="workflow-steps">
                            <div v-for="(step, index) in page.steps" :key="step" class="workflow-step" :class="{ done: index === 0, current: index === 1 }">
                                <span>{{ index === 0 ? '✓' : index + 1 }}</span><div><strong>{{ step }}</strong><small>{{ index === 0 ? 'Complete' : index === 1 ? 'In progress' : 'Up next' }}</small></div>
                            </div>
                        </div>
                    </section>
                    <section class="tip-card su-card"><Search :size="22" /><div><strong>Built for discovery</strong><p>Clear, complete information helps the right people find and understand you.</p></div></section>
                </aside>
            </div>
        </div>
    </AppShell>
</template>
