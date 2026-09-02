<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

const open = ref(false), scrolled = ref(false);
const onScroll = () => scrolled.value = window.scrollY > 24;
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onUnmounted(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <header class="public-nav" :class="{ scrolled }">
        <div class="public-container public-nav-inner">
            <Link href="/" class="public-brand" aria-label="SportsUniverse homepage"><img :src="'/images/logo/sportuniverse-logo-horizontal-transparent.png'" alt="SportsUniverse" /></Link>
            <nav class="public-links" aria-label="Public navigation">
                <a href="#highlights">Highlights</a><a href="#athletes">Athletes</a><a href="#sports">Sports</a><a href="#opportunities">Opportunities</a>
            </nav>
            <div class="public-nav-actions"><Link href="/login" class="public-login">Sign in</Link><Link href="/register" class="public-button public-button-primary">Join now</Link></div>
            <button class="public-menu-button" type="button" :aria-expanded="open" aria-controls="public-mobile-menu" aria-label="Toggle navigation" @click="open = !open"><X v-if="open"/><Menu v-else/></button>
        </div>
        <nav v-if="open" id="public-mobile-menu" class="public-mobile-menu" aria-label="Mobile navigation">
            <a href="#highlights" @click="open=false">Highlights</a><a href="#athletes" @click="open=false">Athletes</a><a href="#sports" @click="open=false">Sports</a><a href="#opportunities" @click="open=false">Opportunities</a>
            <Link href="/login">Sign in</Link><Link href="/register" class="public-button public-button-primary">Join SportsUniverse</Link>
        </nav>
    </header>
</template>
