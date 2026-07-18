<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import BrandLogo from '../../Components/BrandLogo.vue';
defineProps<{ email: string; status?: string; mailError?: string }>();
</script>

<template>
    <Head title="Verify your email" />
    <main class="simple-auth-page"><section><BrandLogo/><MailCheck class="auth-feature-icon"/><h1>Check your email</h1><p>We sent a verification link to <strong>{{ email }}</strong>. Your account will be created only after you open that link.</p><div v-if="status" class="auth-success">{{ status }}</div><div v-if="mailError" class="auth-error">{{ mailError }}</div><button class="su-btn su-btn-primary" @click="router.post('/register/resend-verification')">Resend verification email</button><Link href="/register" class="su-btn su-btn-ghost">Use a different email</Link><small>The link expires after 60 minutes.</small></section></main>
</template>
