<script setup lang="ts">
defineProps<{ compact?: boolean }>();
</script>

<template>
    <div class="brand brand-official">
        <img v-if="compact" class="brand-icon-image" :src="'/images/logo/sportuniverse-icon.svg'" alt="SportsUniverse" />
        <img v-else class="brand-logo-image" :src="'/images/logo/sportuniverse-logo-horizontal-transparent.png'" alt="SportsUniverse — Talent. Opportunity. Community." />
    </div>
</template>
