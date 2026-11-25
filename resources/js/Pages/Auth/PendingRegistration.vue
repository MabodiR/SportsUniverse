<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import BrandLogo from '../../Components/BrandLogo.vue';
defineProps<{ email: string; status?: string; mailError?: string; channel?: string }>();
const form = useForm({otp:''});
</script>

<template>
    <Head title="Verify your email" />
    <main class="simple-auth-page"><section><BrandLogo/><MailCheck class="auth-feature-icon"/><h1>Complete verification</h1><p>Your registration for <strong>{{ email }}</strong> is saved. Check your {{channel === 'whatsapp' ? 'WhatsApp messages for a code' : 'email for a verification link'}}. If delivery fails, an administrator can approve your registration.</p><div v-if="status" class="auth-success">{{ status }}</div><div v-if="mailError" class="auth-error">{{ mailError }}</div><form v-if="channel === 'whatsapp'" @submit.prevent="form.post('/register/verify-otp')"><label>WhatsApp code<input v-model="form.otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6"/></label><p>{{form.errors.otp}}</p><button class="su-btn su-btn-primary" :disabled="form.processing">Verify code</button></form><button class="su-btn su-btn-primary" @click="router.post('/register/resend-verification')">Resend verification message</button><Link href="/register" class="su-btn su-btn-ghost">Use a different email</Link><small>Email links expire after 60 minutes. WhatsApp codes expire after 10 minutes.</small></section></main>
</template>
