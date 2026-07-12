<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, nextTick, onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const props = withDefaults(defineProps<{ initialTab?: 'messages' | 'requests' }>(), { initialTab: 'messages' });
const page = usePage();
const user = page.props.auth?.user as any;
const conversations = ref<any[]>([]);
const requests = ref<any[]>([]);
const tab = ref<'messages' | 'requests'>(props.initialTab);
const selectedRequest = ref<any>(null);
const actionLoading = ref('');
const active = ref<any>(null);
const messages = ref<any[]>([]);
const draft = ref('');
const loading = ref(true);
const sending = ref(false);
const thread = ref<HTMLElement | null>(null);
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
const other = (conversation: any) => conversation?.participants?.find((person: any) => person.id !== user?.id) ?? conversation?.participants?.[0];
const initials = (name = '') => name.split(/\s+/).map((word: string) => word[0]).join('').slice(0, 2).toUpperCase();
const palette = ['blue', 'orange', 'green', 'pink'];
const activePerson = computed(() => other(active.value));

const api = async (url: string, options: RequestInit = {}) => {
    const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json', ...(options.headers ?? {}) } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message ?? 'Unable to load messages.');
    return payload;
};
const scrollBottom = async () => { await nextTick(); if (thread.value) thread.value.scrollTop = thread.value.scrollHeight; };
const selectConversation = async (conversation: any) => {
    active.value = conversation;
    const payload = await api(`/api/v1/conversations/${conversation.id}/messages`);
    messages.value = [...(payload.data ?? [])].reverse();
    scrollBottom();
};
const load = async () => {
    try {
        const [conversationPayload, requestPayload] = await Promise.all([api('/api/v1/conversations'), api('/api/v1/message-requests?status=pending')]);
        conversations.value = conversationPayload.data ?? []; requests.value = requestPayload.data ?? [];
        if (tab.value === 'requests' && requests.value[0]) selectedRequest.value = requests.value[0];
        if (conversations.value[0]) await selectConversation(conversations.value[0]);
    }
    finally { loading.value = false; }
};
const respond = async (request: any, action: 'accept' | 'decline') => {
    actionLoading.value = `${request.id}:${action}`;
    try {
        await api(`/api/v1/message-requests/${request.id}/${action}`, { method: 'POST', body: '{}' });
        requests.value = requests.value.filter(item => item.id !== request.id); selectedRequest.value = null;
        if (action === 'accept') { const payload = await api('/api/v1/conversations'); conversations.value = payload.data ?? []; tab.value = 'messages'; if (conversations.value[0]) await selectConversation(conversations.value[0]); }
    } finally { actionLoading.value = ''; }
};
const block = async (request: any) => {
    actionLoading.value = `${request.id}:block`;
    try {
        await api(`/api/v1/profiles/${request.sender.id}/block`, { method: 'POST', body: JSON.stringify({ reason: 'Blocked from message requests' }) });
        requests.value = requests.value.filter(item => item.id !== request.id); selectedRequest.value = null;
    } finally { actionLoading.value = ''; }
};
const send = async () => {
    if (!draft.value.trim() || !active.value || sending.value) return;
    sending.value = true;
    try { const payload = await api(`/api/v1/conversations/${active.value.id}/messages`, { method: 'POST', body: JSON.stringify({ body: draft.value.trim() }) }); messages.value.push(payload.data ?? payload); draft.value = ''; scrollBottom(); }
    finally { sending.value = false; }
};
onMounted(load);
</script>

<template>
    <Head :title="props.initialTab === 'requests' ? 'Message requests' : 'Messages'" />
    <AppShell>
        <main class="messages-page">
            <header class="messages-heading"><h1>{{ props.initialTab === 'requests' ? 'Message requests' : 'Messages' }}</h1><p>{{ props.initialTab === 'requests' ? 'Review who can start a private conversation with you.' : 'Chat-style communication between users, clubs and scouts.' }}</p></header>
            <div class="messages-layout">
                <aside class="conversation-panel">
                    <div class="message-tabs"><button :class="{ active: tab === 'messages' }" @click="tab = 'messages'">Messages</button><button :class="{ active: tab === 'requests' }" @click="tab = 'requests'">Message requests <span v-if="requests.length">{{ requests.length }}</span></button></div>
                    <h2>{{ tab === 'messages' ? 'Conversations' : 'Message requests' }}</h2>
                    <p v-if="loading" class="messages-empty">Loading…</p>
                    <p v-else-if="tab === 'messages' && !conversations.length" class="messages-empty">No conversations yet.</p>
                    <p v-else-if="tab === 'requests' && !requests.length" class="messages-empty">No pending message requests.</p>
                    <button v-for="(conversation, index) in (tab === 'messages' ? conversations : [])" :key="conversation.id" class="conversation-item" :class="{ active: active?.id === conversation.id }" @click="selectConversation(conversation)">
                        <span class="conversation-avatar" :class="palette[index % palette.length]">{{ initials(other(conversation)?.name) }}</span>
                        <span class="conversation-copy"><strong>{{ other(conversation)?.name ?? 'Conversation' }}</strong><small>{{ conversation.last_message?.body ?? 'Start a conversation' }}</small></span>
                    </button>
                    <button v-for="(request, index) in (tab === 'requests' ? requests : [])" :key="request.id" class="conversation-item request-item" :class="{ active: selectedRequest?.id === request.id }" @click="selectedRequest = request">
                        <span class="conversation-avatar" :class="palette[index % palette.length]">{{ initials(request.sender?.name) }}</span>
                        <span class="conversation-copy"><strong>{{ request.sender?.name }}</strong><small>{{ request.message }}</small></span><small class="view-label">View</small>
                    </button>
                </aside>
                <section class="chat-panel">
                    <template v-if="tab === 'requests' && selectedRequest">
                        <header class="chat-header"><h2>{{ selectedRequest.sender?.name }}</h2><p>Message request</p></header>
                        <div class="request-preview"><span class="conversation-avatar pink">{{ initials(selectedRequest.sender?.name) }}</span><h3>{{ selectedRequest.sender?.name }}</h3><p>{{ selectedRequest.message }}</p><div class="request-actions"><button class="approve" :disabled="actionLoading" @click="respond(selectedRequest, 'accept')">Approve</button><button :disabled="actionLoading" @click="respond(selectedRequest, 'decline')">Decline</button><button class="block" :disabled="actionLoading" @click="block(selectedRequest)">Block</button></div></div>
                    </template>
                    <template v-else-if="tab === 'messages' && active">
                        <header class="chat-header"><h2>{{ activePerson?.name }}</h2><p>SportUniverse member <span>• Online now</span></p></header>
                        <div ref="thread" class="message-thread">
                            <div v-for="message in messages" :key="message.id" class="message-bubble" :class="{ mine: message.sender?.id === user?.id }">{{ message.body }}</div>
                        </div>
                        <form class="message-composer" @submit.prevent="send"><input v-model="draft" aria-label="Message" placeholder="Type message..." maxlength="2000" /><button :disabled="sending || !draft.trim()">{{ sending ? 'Sending…' : 'Send' }}</button></form>
                    </template>
                    <div v-else class="chat-placeholder"><Search :size="28" /><p>{{ tab === 'requests' ? 'Select a request to view it.' : 'Select a conversation to start messaging.' }}</p></div>
                </section>
            </div>
        </main>
    </AppShell>
</template>
