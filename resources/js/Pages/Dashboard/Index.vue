<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Bell, BriefcaseBusiness, Eye, FileText, Medal, Play, Target, TrendingUp, Upload, UserRound, Users } from '@lucide/vue';
import AppShell from '../../Layouts/AppShell.vue';

const props = defineProps<{ dashboard: any }>();
const number = (value: number) => new Intl.NumberFormat(undefined, { notation: value >= 10000 ? 'compact' : 'standard' }).format(value);
const actionIcon = (kind: string) => ({ profile: UserRound, career: Medal, upload: Upload, opportunity: BriefcaseBusiness }[kind] ?? Target);
const greeting = () => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
};
</script>

<template>
    <Head title="Dashboard" />
    <AppShell>
        <main class="dynamic-dashboard">
            <header class="dashboard-welcome">
                <div><span>YOUR OVERVIEW</span><h1>{{ greeting() }}, {{ dashboard.user.name.split(' ')[0] }}</h1><p>Here’s what is happening with your SportUniverse profile.</p></div>
                <Link href="/profile" class="su-btn su-btn-primary">View profile <ArrowRight /></Link>
            </header>

            <section class="dashboard-metrics" aria-label="Account summary">
                <article><i><TrendingUp /></i><div><small>Profile complete</small><strong>{{ dashboard.metrics.profile_completion }}%</strong><progress :value="dashboard.metrics.profile_completion" max="100" /></div></article>
                <article><i><Eye /></i><div><small>Profile views</small><strong>{{ number(dashboard.metrics.profile_views) }}</strong><span>All time</span></div></article>
                <article><i><Users /></i><div><small>Followers</small><strong>{{ number(dashboard.metrics.followers) }}</strong><span>Your community</span></div></article>
                <article><i><Play /></i><div><small>Posts</small><strong>{{ number(dashboard.metrics.posts) }}</strong><span>Published and processing</span></div></article>
            </section>

            <div class="dashboard-columns">
                <section class="dashboard-panel dashboard-actions">
                    <header><div><small>RECOMMENDED</small><h2>What to do next</h2></div></header>
                    <div class="dashboard-action-grid">
                        <Link v-for="action in dashboard.actions" :key="action.title" :href="action.href">
                            <i><component :is="actionIcon(action.kind)" /></i><div><strong>{{ action.title }}</strong><span>{{ action.description }}</span></div><ArrowRight />
                        </Link>
                    </div>
                </section>

                <aside class="dashboard-panel dashboard-status">
                    <header><small>AT A GLANCE</small><h2>Your activity</h2></header>
                    <dl>
                        <div><dt><BriefcaseBusiness />Open opportunities</dt><dd>{{ number(dashboard.open_opportunities) }}</dd></div>
                        <div><dt><FileText />Applications</dt><dd>{{ number(dashboard.metrics.applications) }}</dd></div>
                        <div><dt><Bell />Unread notifications</dt><dd>{{ number(dashboard.metrics.unread_notifications) }}</dd></div>
                    </dl>
                    <Link href="/opportunities">Explore opportunities <ArrowRight /></Link>
                </aside>

                <section class="dashboard-panel dashboard-recent">
                    <header><div><small>RECENT</small><h2>Your latest updates</h2></div><Link href="/profile">See profile</Link></header>
                    <div v-if="dashboard.activities.length" class="dashboard-activity-list">
                        <Link v-for="(activity, index) in dashboard.activities" :key="`${activity.type}-${index}`" :href="activity.href"><i><Play v-if="activity.type === 'post'" /><Medal v-else /></i><span><strong>{{ activity.title }}</strong><small>{{ activity.meta }}</small></span><ArrowRight /></Link>
                    </div>
                    <div v-else class="dashboard-empty"><Target /><h3>No activity yet</h3><p>Complete your profile or publish your first highlight to get started.</p><Link href="/upload" class="su-btn su-btn-primary">Upload highlight</Link></div>
                </section>
            </div>
        </main>
    </AppShell>
</template>
