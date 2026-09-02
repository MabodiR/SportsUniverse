<script setup lang="ts">
import { ArrowUpRight, Play } from '@lucide/vue';
defineProps<{ athlete: any }>();
</script>
<template>
    <a class="athlete-card" :href="`/@${athlete.slug}`">
        <div class="athlete-portrait"><img v-if="athlete.image" :src="athlete.image" :alt="athlete.name" loading="lazy"/><div v-else class="athlete-monogram">{{ athlete.name.slice(0,2).toUpperCase() }}</div><span class="athlete-index">ATHLETE</span></div>
        <div class="athlete-card-copy"><div><small>{{ athlete.sport }}</small><h3>{{ athlete.name }}</h3><p>{{ [athlete.position, athlete.location].filter(Boolean).join(' · ') }}</p></div><ArrowUpRight/><div class="athlete-stats"><span><strong>{{ athlete.highlights }}</strong> highlights</span><span><strong>{{ athlete.followers }}</strong> followers</span><Play/></div></div>
    </a>
</template>
