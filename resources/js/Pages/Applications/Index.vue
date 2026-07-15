<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, BriefcaseBusiness, CalendarDays, CheckCircle2, Clock3, FileText, MapPin, UserRound, XCircle } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const auth = (usePage().props.auth as any)?.user;
const roleNames = (auth?.roles ?? []).map((role: any) => typeof role === 'string' ? role : role.name);
const isOrganiser = roleNames.some((role: string) => ['club', 'academy', 'business', 'sponsor', 'admin'].includes(role));
const applications = ref<any[]>([]);
const opportunities = ref<any[]>([]);
const selectedOpportunity = ref<any>();
const loading = ref(true);
const filter = ref('all');
const notes = ref<Record<string, string>>({});
const busy = ref('');
const error = ref('');
const statuses = ['submitted', 'reviewing', 'shortlisted', 'accepted', 'rejected', 'withdrawn'];
const filtered = computed(() => filter.value === 'all' ? applications.value : applications.value.filter((application) => application.status === filter.value));
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const label = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
const date = (value: string) => new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
const api = async (url: string, options: RequestInit = {}) => {
    const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message ?? 'Request failed.');
    return payload.data ?? payload;
};
const loadApplicants = async (opportunity: any) => {
    selectedOpportunity.value = opportunity;
    loading.value = true;
    applications.value = await api(`/api/v1/opportunities/${opportunity.id}/applications`);
    loading.value = false;
};
const review = async (application: any, status: string) => {
    busy.value = application.id; error.value = '';
    try {
        const updated = await api(`/api/v1/applications/${application.id}`, { method: 'PATCH', body: JSON.stringify({ status, reviewer_notes: notes.value[application.id] || null }) });
        Object.assign(application, updated);
    } catch (exception: any) { error.value = exception.message; }
    finally { busy.value = ''; }
};
const withdraw = async (application: any) => {
    if (!confirm('Withdraw this application? The organiser will see it as withdrawn.')) return;
    const updated = await api(`/api/v1/applications/${application.id}/withdraw`, { method: 'POST', body: '{}' });
    Object.assign(application, updated);
};
const timeline = (application: any) => application.timeline?.length ? application.timeline : [{ status: 'submitted', created_at: application.created_at }, ...(application.status !== 'submitted' ? [{ status: application.status, created_at: application.reviewed_at ?? application.created_at, notes: application.reviewer_notes }] : [])];
onMounted(async () => {
    try {
        if (isOrganiser) {
            opportunities.value = await api('/api/v1/opportunities/mine');
            if (opportunities.value.length) await loadApplicants(opportunities.value[0]);
        } else applications.value = await api('/api/v1/applications/mine');
    } catch (exception: any) { error.value = exception.message; }
    finally { loading.value = false; }
});
</script>

<template>
    <Head :title="isOrganiser?'Manage Applicants':'My Applications'" />
    <AppShell>
        <main class="applications-page">
            <header><div><span><BriefcaseBusiness /> OPPORTUNITIES</span><h1>{{isOrganiser?'Applicant management':'My applications'}}</h1><p>{{isOrganiser?'Review applicants and communicate decisions clearly.':'Track every application from submission to final decision.'}}</p></div><Link href="/opportunities">Browse opportunities <ArrowRight /></Link></header>
            <p v-if="error" class="edit-message error">{{error}}</p>

            <div v-if="isOrganiser" class="organiser-opportunities">
                <button v-for="opportunity in opportunities" :key="opportunity.id" :class="{active:selectedOpportunity?.id===opportunity.id}" @click="loadApplicants(opportunity)"><strong>{{opportunity.title}}</strong><span>{{opportunity.applications_count}} applicants</span></button>
                <p v-if="!opportunities.length&&!loading">You have not posted any opportunities yet.</p>
            </div>

            <nav class="application-filters"><button v-for="status in ['all',...statuses]" :class="{active:filter===status}" @click="filter=status">{{label(status)}} <b>{{status==='all'?applications.length:applications.filter(item=>item.status===status).length}}</b></button></nav>
            <p v-if="loading" class="applications-empty">Loading applications…</p>

            <section v-else-if="filtered.length" class="application-list">
                <article v-for="application in filtered" :key="application.id">
                    <header>
                        <div v-if="isOrganiser" class="application-applicant"><span><img v-if="application.applicant.image" :src="application.applicant.image" :alt="`${application.applicant.name} profile photo`"/><UserRound v-else/></span><div><Link :href="`/@${application.applicant.slug}`">{{application.applicant.name}}</Link><small>Applied {{date(application.created_at)}}</small></div></div>
                        <div v-else><span>{{label(application.opportunity.type)}}</span><h2>{{application.opportunity.title}}</h2><strong>{{application.opportunity.poster.name}}</strong></div>
                        <i :class="`status-${application.status}`">{{label(application.status)}}</i>
                    </header>
                    <div v-if="!isOrganiser" class="application-meta"><span><MapPin/>{{application.opportunity.location.is_remote?'Remote':[application.opportunity.location.city,application.opportunity.location.province].filter(Boolean).join(', ')}}</span><span><CalendarDays/>Applied {{date(application.created_at)}}</span></div>
                    <p v-if="application.cover_letter" class="application-letter"><FileText/>{{application.cover_letter}}</p>
                    <a v-if="isOrganiser&&application.resume" :href="application.resume.download_url" class="application-resume"><FileText/>Download attached résumé</a>

                    <ol v-if="!isOrganiser" class="application-timeline"><li v-for="event in timeline(application)" :class="`status-${event.status}`"><span><CheckCircle2 v-if="['accepted','shortlisted'].includes(event.status)"/><XCircle v-else-if="['rejected','withdrawn'].includes(event.status)"/><Clock3 v-else/></span><div><strong>{{label(event.status)}}</strong><small>{{date(event.created_at)}}</small><p v-if="event.notes">{{event.notes}}</p></div></li></ol>

                    <div v-if="isOrganiser&&!['accepted','rejected','withdrawn'].includes(application.status)" class="review-controls"><textarea v-model="notes[application.id]" placeholder="Add a note for the applicant (optional)"/><div><button v-for="status in ['reviewing','shortlisted','accepted','rejected']" :disabled="busy===application.id" :class="status" @click="review(application,status)">{{label(status)}}</button></div></div>
                    <button v-if="!isOrganiser&&!['accepted','rejected','withdrawn'].includes(application.status)" class="withdraw-application" @click="withdraw(application)">Withdraw application</button>
                </article>
            </section>
            <section v-else-if="!loading" class="applications-empty"><FileText/><h2>No {{filter==='all'?'':label(filter).toLowerCase()}} applications</h2><p>{{isOrganiser?'Applicants will appear here when people apply.':'Applications you submit will appear here with live status updates.'}}</p><Link v-if="!isOrganiser" href="/opportunities">Find opportunities</Link></section>
        </main>
    </AppShell>
</template>
