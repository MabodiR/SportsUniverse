<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, CloudUpload, FileVideo, Info, X } from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const file = ref<File | null>(null);
const images = ref<File[]>([]);
const imagePreviews = ref<string[]>([]);
const coverIndex = ref(0);
const preview = ref('');
const caption = ref('');
const visibility = ref('Everyone');
const comments = ref(true);
const saving = ref(false);
const notice = ref('');
const error = ref('');
const dragging = ref(false);
const input = ref<HTMLInputElement | null>(null);
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
const responsePayload = async (response: Response) => {
    const type = response.headers.get('content-type') ?? '';
    if (type.includes('application/json')) return response.json();
    const body = await response.text();
    throw new Error(response.status === 413 ? 'This video is larger than the server upload limit.' : `The server could not complete the upload (${response.status}). ${body ? 'Please try again.' : ''}`.trim());
};
const fileSize = computed(() => file.value ? `${(file.value.size / 1024 / 1024).toFixed(1)} MB` : '');
const select = (selected?: File) => {
    if (!selected) return;
    if (!['video/mp4', 'video/quicktime', 'video/webm'].includes(selected.type)) { error.value = 'Choose an MP4, MOV, or WebM video.'; return; }
    if (preview.value) URL.revokeObjectURL(preview.value);
    file.value = selected; preview.value = URL.createObjectURL(selected); error.value = ''; notice.value = '';
};
const selectImages = (selected?: FileList | null) => {
    imagePreviews.value.forEach(URL.revokeObjectURL);
    images.value = Array.from(selected ?? []).slice(0, 2);
    imagePreviews.value = images.value.map(URL.createObjectURL);
    coverIndex.value = 0;
    error.value = selected && selected.length > 2 ? 'Only the first two pictures were selected.' : '';
};
const drop = (event: DragEvent) => { dragging.value = false; select(event.dataTransfer?.files?.[0]); };
const discard = () => { if (preview.value) URL.revokeObjectURL(preview.value); imagePreviews.value.forEach(URL.revokeObjectURL); file.value = null; images.value = []; imagePreviews.value = []; preview.value = ''; caption.value = ''; notice.value = ''; error.value = ''; };
const uploadMedia = async (uploadFile: File, kind: 'video' | 'image') => {
    const form = new FormData(); form.append('file', uploadFile); form.append('kind', kind); form.append('collection', 'uploads');
    const response = await fetch('/api/v1/media', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: form });
    const payload = await responsePayload(response);
    if (!response.ok) throw new Error(payload.message ?? Object.values(payload.errors ?? {}).flat().join(' '));
    let processed = payload.data;
    for (let attempt = 0; attempt < 90 && ['pending', 'processing'].includes(processed.processing_status); attempt++) {
        await new Promise(resolve => setTimeout(resolve, 2000));
        const statusResponse = await fetch(`/api/v1/media/${processed.id}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const statusPayload = await responsePayload(statusResponse); processed = statusPayload.data ?? statusPayload;
    }
    if (processed.processing_status !== 'ready') throw new Error(processed.processing_error || `${kind === 'video' ? 'Video' : 'Picture'} processing did not complete.`);
    return processed;
};
const upload = async () => {
    if (!file.value) { error.value = 'Select a video to upload first.'; return; }
    saving.value = true; error.value = ''; notice.value = '';
    try {
        notice.value = 'Upload complete. Processing your video…';
        const media = await uploadMedia(file.value, 'video');
        const uploadedImages = [];
        for (const image of images.value) uploadedImages.push(await uploadMedia(image, 'image'));
        const publishResponse = await fetch('/api/v1/videos', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ media_id: media.id, image_media_ids: uploadedImages.map(image => image.id), cover_media_id: uploadedImages[coverIndex.value]?.id, caption: caption.value, visibility: visibility.value === 'Everyone' ? 'public' : visibility.value === 'Followers' ? 'followers' : 'private', publish: true }) });
        const publishPayload = await responsePayload(publishResponse);
        if (!publishResponse.ok) throw new Error(publishPayload.message ?? Object.values(publishPayload.errors ?? {}).flat().join(' '));
        notice.value = 'Your video is live on SportUniverse.';
    } catch (e: any) { error.value = e.message ?? 'Upload failed.'; }
    finally { saving.value = false; }
};
onUnmounted(() => { if (preview.value) URL.revokeObjectURL(preview.value); imagePreviews.value.forEach(URL.revokeObjectURL); });
</script>

<template>
    <Head title="Upload" />
    <AppShell>
        <main class="upload-studio">
            <header class="upload-heading"><div><h1>Upload video</h1><p>Post a video to your SportUniverse profile.</p></div><span><CloudUpload :size="18" /> Upload</span></header>
            <section class="upload-card">
                <div class="upload-media-column">
                    <div v-if="!file" class="upload-dropzone" :class="{ dragging }" @click="input?.click()" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop">
                        <span class="upload-cloud"><CloudUpload :size="38" /></span><h2>Select video to upload</h2><p>Or drag and drop a file</p>
                        <div class="upload-requirements"><span>MP4, MOV or WebM</span><span>720×1280 resolution or higher</span><span>Up to the configured upload limit</span></div>
                        <button type="button">Select file</button><input ref="input" hidden type="file" accept="video/mp4,video/quicktime,video/webm" @change="select(($event.target as HTMLInputElement).files?.[0])" />
                    </div>
                    <div v-else class="upload-preview"><video :src="preview" controls /><div class="selected-file"><FileVideo :size="20" /><span><strong>{{ file.name }}</strong><small>{{ fileSize }}</small></span><button aria-label="Remove video" @click="discard"><X :size="17" /></button></div></div>
                </div>
                <form class="upload-details" @submit.prevent="upload">
                    <h2>Video details</h2>
                    <label><span class="upload-label">Pictures <small>Optional · up to 2</small></span><input class="upload-picture-input" type="file" accept="image/jpeg,image/png,image/webp" multiple @change="selectImages(($event.target as HTMLInputElement).files)" /></label>
                    <div v-if="imagePreviews.length" class="upload-picture-grid"><button v-for="(image, index) in imagePreviews" :key="image" type="button" :class="{ selected: coverIndex === index }" @click="coverIndex = index"><img :src="image" alt="Selected post picture" /><span>{{ coverIndex === index ? 'Main picture' : 'Set as main' }}</span></button></div>
                    <label><span class="upload-label">Caption <small>{{ caption.length }} / 2200</small></span><textarea v-model="caption" maxlength="2200" placeholder="Tell viewers about your video..." /></label>
                    <label><span class="upload-label">Who can watch this video</span><select v-model="visibility"><option>Everyone</option><option>Followers</option><option>Only me</option></select></label>
                    <div><span class="upload-label">Allow users to</span><label class="upload-check"><input v-model="comments" type="checkbox" /> Comments</label></div>
                    <div class="upload-note"><Info :size="17" /><p>Your video will be reviewed and processed after upload. Keep this page open until the upload completes.</p></div>
                    <p v-if="error" class="upload-feedback error">{{ error }}</p><p v-if="notice" class="upload-feedback success"><CheckCircle2 :size="16" />{{ notice }}</p>
                    <div class="upload-actions"><button type="button" class="discard" @click="discard">Discard</button><button class="post" :disabled="saving || !file">{{ saving ? 'Uploading…' : 'Post' }}</button></div>
                </form>
            </section>
        </main>
    </AppShell>
</template>
