<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, CloudUpload, FileVideo, Info, X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const file = ref<File | null>(null);
const images = ref<File[]>([]);
const imagePreviews = ref<string[]>([]);
const coverIndex = ref(0);
const preview = ref('');
const caption = ref('');
const visibility = ref('Everyone');
const comments = ref(true);
const hashtags = ref('');
const sportId = ref('');
const locationName = ref('');
const sports = ref<any[]>([]);
const progress = ref(0);
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
    images.value = Array.from(selected ?? []).slice(0, 10);
    imagePreviews.value = images.value.map(URL.createObjectURL);
    coverIndex.value = 0;
    error.value = selected && selected.length > 10 ? 'Only the first 10 pictures were selected.' : '';
};
const drop = (event: DragEvent) => { dragging.value = false; select(event.dataTransfer?.files?.[0]); };
const discard = () => { if (preview.value) URL.revokeObjectURL(preview.value); imagePreviews.value.forEach(URL.revokeObjectURL); file.value = null; images.value = []; imagePreviews.value = []; preview.value = ''; caption.value = ''; notice.value = ''; error.value = ''; };
const uploadMedia = async (uploadFile: File, kind: 'video' | 'image', index: number, total: number) => {
    const form = new FormData(); form.append('file', uploadFile); form.append('kind', kind); form.append('collection', 'uploads');
    const processedUpload = await new Promise<any>((resolve, reject) => {
        const xhr = new XMLHttpRequest(); xhr.open('POST', '/api/v1/media'); xhr.responseType = 'json'; xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.upload.onprogress = event => { if (event.lengthComputable) progress.value = Math.round(((index + event.loaded / event.total * .7) / total) * 100); };
        xhr.onerror = () => reject(new Error('Network error during upload.'));
        xhr.onload = () => xhr.status >= 200 && xhr.status < 300 ? resolve(xhr.response.data) : reject(new Error(xhr.response?.message ?? 'Upload failed.'));
        xhr.send(form);
    });
    let processed = processedUpload;
    for (let attempt = 0; attempt < 900 && ['pending', 'processing'].includes(processed.processing_status); attempt++) {
        await new Promise(resolve => setTimeout(resolve, 2000));
        const statusResponse = await fetch(`/api/v1/media/${processed.id}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const statusPayload = await responsePayload(statusResponse); processed = statusPayload.data ?? statusPayload; progress.value = Math.max(progress.value, Math.round(((index + .85) / total) * 100));
    }
    if (processed.processing_status !== 'ready') throw new Error(processed.processing_error || `${kind === 'video' ? 'Video' : 'Picture'} processing did not complete.`);
    return processed;
};
const upload = async () => {
    if (!file.value && !images.value.length) { error.value = 'Select a video or at least one picture.'; return; }
    saving.value = true; error.value = ''; notice.value = '';
    try {
        const total = images.value.length + (file.value ? 1 : 0); let cursor = 0;
        notice.value = 'Uploading and processing your media…';
        const media = file.value ? await uploadMedia(file.value, 'video', cursor++, total) : null;
        const uploadedImages = [];
        for (const image of images.value) uploadedImages.push(await uploadMedia(image, 'image', cursor++, total));
        const publishResponse = await fetch('/api/v1/videos', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ media_id: media?.id, image_media_ids: uploadedImages.map(image => image.id), cover_media_id: uploadedImages[coverIndex.value]?.id, caption: caption.value, hashtags: hashtags.value.split(/[\s,]+/).map(tag => tag.replace(/^#/, '')).filter(Boolean), sport_id: sportId.value || null, location_name: locationName.value || null, comments_enabled: comments.value, visibility: visibility.value === 'Everyone' ? 'public' : visibility.value === 'Followers' ? 'followers' : 'private', publish: true }) });
        const publishPayload = await responsePayload(publishResponse);
        if (!publishResponse.ok) throw new Error(publishPayload.message ?? Object.values(publishPayload.errors ?? {}).flat().join(' '));
        progress.value = 100; notice.value = 'Your post is live on SportUniverse.';
    } catch (e: any) { error.value = e.message ?? 'Upload failed.'; }
    finally { saving.value = false; }
};
onMounted(async () => { const response = await fetch('/api/v1/sports', { headers: { Accept: 'application/json' } }); sports.value = (await response.json()).data ?? []; });
onUnmounted(() => { if (preview.value) URL.revokeObjectURL(preview.value); imagePreviews.value.forEach(URL.revokeObjectURL); });
</script>

<template>
    <Head title="Upload" />
    <AppShell>
        <main class="upload-studio">
            <header class="upload-heading"><div><h1>Create post</h1><p>Share a video, pictures, or a swipeable carousel.</p></div><span><CloudUpload :size="18" /> Upload</span></header>
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
                    <h2>Post details</h2>
                    <label><span class="upload-label">Pictures <small>Up to 10</small></span><input class="upload-picture-input" type="file" accept="image/jpeg,image/png,image/webp" multiple @change="selectImages(($event.target as HTMLInputElement).files)" /></label>
                    <div v-if="imagePreviews.length" class="upload-picture-grid"><button v-for="(image, index) in imagePreviews" :key="image" type="button" :class="{ selected: coverIndex === index }" @click="coverIndex = index"><img :src="image" alt="Selected post picture" /><span>{{ coverIndex === index ? 'Main picture' : 'Set as main' }}</span></button></div>
                    <label><span class="upload-label">Caption <small>{{ caption.length }} / 2200</small></span><textarea v-model="caption" maxlength="2200" placeholder="Tell viewers about your video..." /></label>
                    <label><span class="upload-label">Hashtags</span><input v-model="hashtags" class="su-input" placeholder="football talent training" /></label>
                    <label><span class="upload-label">Sport</span><select v-model="sportId"><option value="">Select sport</option><option v-for="sport in sports" :key="sport.id" :value="sport.id">{{ sport.name }}</option></select></label>
                    <label><span class="upload-label">Location</span><input v-model="locationName" class="su-input" placeholder="City, venue, suburb or township" /></label>
                    <label><span class="upload-label">Who can watch this video</span><select v-model="visibility"><option>Everyone</option><option>Followers</option><option>Only me</option></select></label>
                    <div><span class="upload-label">Allow users to</span><label class="upload-check"><input v-model="comments" type="checkbox" /> Comments</label></div>
                    <div class="upload-note"><Info :size="17" /><p>Your video will be reviewed and processed after upload. Keep this page open until the upload completes.</p></div>
                    <div v-if="saving" class="upload-progress"><span :style="{ width: progress + '%' }" /><strong>{{ progress }}%</strong></div>
                    <p v-if="error" class="upload-feedback error">{{ error }}</p><p v-if="notice" class="upload-feedback success"><CheckCircle2 :size="16" />{{ notice }}</p>
                    <div class="upload-actions"><button type="button" class="discard" @click="discard">Discard</button><button class="post" :disabled="saving || (!file && !images.length)">{{ saving ? 'Uploading…' : 'Post' }}</button></div>
                </form>
            </section>
        </main>
    </AppShell>
</template>
