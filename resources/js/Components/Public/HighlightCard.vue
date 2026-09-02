<script setup lang="ts">
import { Eye, Heart, MapPin, Play } from '@lucide/vue';
defineProps<{ item: any; large?: boolean }>();
const compact = (value:number) => new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value ?? 0);
</script>
<template>
    <a class="highlight-card" :class="{ large }" :href="`/feed#${item.id}`">
        <div class="highlight-media"><img v-if="item.cover" :src="item.cover" :alt="`${item.athlete.name} sports highlight`" loading="lazy"/><video v-else-if="item.video" :src="item.video" muted preload="metadata" playsinline aria-hidden="true"/><div v-else class="highlight-placeholder"><span>{{ item.sport?.slice(0, 1) || 'S' }}</span></div><span class="highlight-play"><Play fill="currentColor"/></span><span v-if="item.sport" class="highlight-sport">{{ item.sport }}</span><div class="highlight-gradient"/></div>
        <div class="highlight-copy"><div class="highlight-athlete"><span class="public-avatar"><img v-if="item.athlete.image" :src="item.athlete.image" alt="" loading="lazy"/><span v-else>{{ item.athlete.name.slice(0,2).toUpperCase() }}</span></span><div><strong>{{ item.athlete.name }}</strong><small>{{ [item.athlete.position, item.location].filter(Boolean).join(' · ') }}</small></div></div><p>{{ item.caption || 'Athlete highlight on SportsUniverse' }}</p><div class="highlight-metrics"><span><Eye/>{{ compact(item.views) }}</span><span><Heart/>{{ compact(item.likes) }}</span><span v-if="item.location"><MapPin/>{{ item.location }}</span></div></div>
    </a>
</template>
