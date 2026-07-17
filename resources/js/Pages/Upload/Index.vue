<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Camera, CheckCircle2, CloudUpload, FileVideo, ImagePlus, Info, X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const file = ref<File | null>(null);
const images = ref<File[]>([]);
const imagePreviews = ref<string[]>([]);
const coverIndex = ref(0);
const preview = ref('');
const videoDuration = ref(0);
const trimStart = ref(0);
const trimLength = ref(60);
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
const cameraInput = ref<HTMLInputElement | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);
const DRAFT_KEY = 'current-upload';
const openDrafts = () => new Promise<IDBDatabase>((resolve, reject) => { const request = indexedDB.open('sportuniverse-uploads', 1); request.onupgradeneeded = () => request.result.createObjectStore('drafts'); request.onsuccess = () => resolve(request.result); request.onerror = () => reject(request.error); });
const saveDraft = async () => { const db = await openDrafts(); const transaction = db.transaction('drafts', 'readwrite'); transaction.objectStore('drafts').put({ video: file.value, images: images.value, caption: caption.value, hashtags: hashtags.value, sportId: sportId.value, locationName: locationName.value, coverIndex: coverIndex.value, trimStart: trimStart.value, trimLength: trimLength.value, savedAt: Date.now() }, DRAFT_KEY); };
const clearDraft = async () => { const db = await openDrafts(); db.transaction('drafts', 'readwrite').objectStore('drafts').delete(DRAFT_KEY); };
const restoreDraft = async () => { const db = await openDrafts(); const request = db.transaction('drafts').objectStore('drafts').get(DRAFT_KEY); request.onsuccess = () => { const draft = request.result; if (!draft) return; if (draft.video) select(draft.video, false); if (draft.images?.length) selectImages(draft.images, false); caption.value=draft.caption??'';hashtags.value=draft.hashtags??'';sportId.value=draft.sportId??'';locationName.value=draft.locationName??'';coverIndex.value=draft.coverIndex??0;trimStart.value=draft.trimStart??0;trimLength.value=draft.trimLength??60;notice.value='Recovered your unfinished upload.'; }; };
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
const responsePayload = async (response: Response) => {
    const type = response.headers.get('content-type') ?? '';
    if (type.includes('application/json')) return response.json();
    const body = await response.text();
    throw new Error(response.status === 413 ? 'This video is larger than the server upload limit.' : `The server could not complete the upload (${response.status}). ${body ? 'Please try again.' : ''}`.trim());
};
const fileSize = computed(() => file.value ? `${(file.value.size / 1024 / 1024).toFixed(1)} MB` : '');
const selectedSport = computed(() => sports.value.find(sport => String(sport.id) === String(sportId.value))?.name ?? 'Sport');
const trimEnd = computed(() => Math.min(videoDuration.value, trimStart.value + trimLength.value));
const formatTime = (seconds: number) => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
const loadedMetadata = (event: Event) => { videoDuration.value = (event.target as HTMLVideoElement).duration || 0; trimLength.value = Math.min(60, videoDuration.value); trimStart.value = Math.min(trimStart.value, Math.max(0, videoDuration.value - 1)); };
const sportUploadSymbol = computed(() => {
    const name = selectedSport.value.toLowerCase();
    if (name.includes('football') || name.includes('soccer')) return '⚽';
    if (name.includes('cricket')) return '🏏';
    if (name.includes('rugby')) return '🏉';
    if (name.includes('basketball')) return '🏀';
    if (name.includes('tennis')) return '🎾';
    if (name.includes('volleyball')) return '🏐';
    if (name.includes('baseball') || name.includes('softball')) return '⚾';
    if (name.includes('hockey')) return '🏑';
    if (name.includes('golf')) return '⛳';
    if (name.includes('boxing')) return '🥊';
    if (name.includes('swimming')) return '🏊';
    if (name.includes('cycling')) return '🚴';
    if (name.includes('running') || name.includes('athletics')) return '🏃';
    return '🏅';
});
const select = (selected?: File, persist = true) => {
    if (!selected) return;
    if (!['video/mp4', 'video/quicktime', 'video/webm'].includes(selected.type)) { error.value = 'Choose an MP4, MOV, or WebM video.'; return; }
    if (selected.size > 512 * 1024 * 1024) { error.value = 'Videos must be 512 MB or smaller.'; return; }
    if (preview.value) URL.revokeObjectURL(preview.value);
    file.value = selected; preview.value = URL.createObjectURL(selected); videoDuration.value = 0; trimStart.value = 0; trimLength.value = 60; error.value = ''; notice.value = ''; if (persist) saveDraft();
};
const selectImages = (selected?: FileList | File[] | null, persist = true) => {
    imagePreviews.value.forEach(URL.revokeObjectURL);
    const candidates = Array.from(selected ?? []).slice(0, 10);
    const invalid = candidates.find(image => !['image/jpeg','image/png','image/webp'].includes(image.type) || image.size > 10 * 1024 * 1024);
    if (invalid) { error.value = `${invalid.name} must be a JPG, PNG, or WebP image no larger than 10 MB.`; return; }
    images.value = candidates;
    imagePreviews.value = images.value.map(URL.createObjectURL);
    coverIndex.value = 0;
    error.value = selected && selected.length > 10 ? 'Only the first 10 pictures were selected.' : ''; if (persist) saveDraft();
};
const drop = (event: DragEvent) => { dragging.value = false; select(event.dataTransfer?.files?.[0]); };
const discard = () => { if (preview.value) URL.revokeObjectURL(preview.value); imagePreviews.value.forEach(URL.revokeObjectURL); file.value = null; images.value = []; imagePreviews.value = []; preview.value = ''; caption.value = ''; notice.value = ''; error.value = ''; clearDraft(); };
const uploadMedia = async (uploadFile: File, kind: 'video' | 'image', index: number, total: number) => {
    const form = new FormData(); form.append('file', uploadFile); form.append('kind', kind); form.append('collection', 'uploads');
    if (kind === 'video' && videoDuration.value > 0) { form.append('trim_start_ms', String(Math.round(trimStart.value * 1000))); form.append('trim_end_ms', String(Math.round(trimEnd.value * 1000))); }
    const processedUpload = await new Promise<any>((resolve, reject) => {
        const xhr = new XMLHttpRequest(); xhr.open('POST', '/api/v1/media'); xhr.responseType = 'json'; xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.upload.onprogress = event => { if (event.lengthComputable) progress.value = Math.round(((index + event.loaded / event.total * .7) / total) * 100); };
        xhr.onerror = () => reject(new Error('Network error during upload.'));
        xhr.ontimeout = () => reject(new Error('The upload timed out. Your draft is safe; please retry.'));
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) return resolve(xhr.response.data);
            if (xhr.status === 413) return reject(new Error('This video is larger than the PHP upload limit. Restart the local app with composer run dev to enable uploads up to 512 MB.'));
            reject(new Error(xhr.response?.message ?? `Upload failed (${xhr.status}).`));
        };
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
    if (!navigator.onLine) { await saveDraft(); notice.value = 'Saved on this device. Upload will resume when you are online.'; navigator.serviceWorker?.ready.then((registration:any)=>registration.sync?.register('sportuniverse-upload')); return; }
    saving.value = true; error.value = ''; notice.value = ''; await saveDraft();
    try {
        const total = images.value.length + (file.value ? 1 : 0); let cursor = 0;
        notice.value = 'Uploading and processing your media…';
        const media = file.value ? await uploadMedia(file.value, 'video', cursor++, total) : null;
        const uploadedImages = [];
        for (const image of images.value) uploadedImages.push(await uploadMedia(image, 'image', cursor++, total));
        const publishResponse = await fetch('/api/v1/videos', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ media_id: media?.id, image_media_ids: uploadedImages.map(image => image.id), cover_media_id: uploadedImages[coverIndex.value]?.id, caption: caption.value, hashtags: hashtags.value.split(/[\s,]+/).map(tag => tag.replace(/^#/, '')).filter(Boolean), sport_id: sportId.value || null, location_name: locationName.value || null, comments_enabled: comments.value, visibility: visibility.value === 'Everyone' ? 'public' : visibility.value === 'Followers' ? 'followers' : 'private', publish: true }) });
        const publishPayload = await responsePayload(publishResponse);
        if (!publishResponse.ok) throw new Error(publishPayload.message ?? Object.values(publishPayload.errors ?? {}).flat().join(' '));
        progress.value = 100; notice.value = 'Your post is live on SportUniverse.'; await clearDraft();
    } catch (e: any) { error.value = `${e.message ?? 'Upload failed.'} Your draft is saved and can be retried.`; navigator.serviceWorker?.ready.then((registration:any)=>registration.sync?.register('sportuniverse-upload')); }
    finally { saving.value = false; }
};
const resume = () => { if ((file.value || images.value.length) && !saving.value && navigator.onLine) { notice.value='Connection restored. Tap Post to resume your saved upload.'; } };
const serviceMessage = (event:MessageEvent) => { if(event.data?.type==='RESUME_UPLOAD') resume(); };
let draftTimer:ReturnType<typeof setTimeout>;watch([caption,hashtags,sportId,locationName,coverIndex],()=>{if(file.value||images.value.length){clearTimeout(draftTimer);draftTimer=setTimeout(saveDraft,350)}});
onMounted(async () => { await restoreDraft(); const response = await fetch('/api/v1/sports', { headers: { Accept: 'application/json' } }); sports.value = (await response.json()).data ?? []; window.addEventListener('online',resume);navigator.serviceWorker?.addEventListener('message',serviceMessage);if(new URLSearchParams(location.search).has('camera'))setTimeout(()=>cameraInput.value?.click(),250); });
onUnmounted(() => { if (preview.value) URL.revokeObjectURL(preview.value); imagePreviews.value.forEach(URL.revokeObjectURL);window.removeEventListener('online',resume);navigator.serviceWorker?.removeEventListener('message',serviceMessage);clearTimeout(draftTimer); });
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
                        <div class="camera-actions"><button type="button" @click.stop="cameraInput?.click()"><Camera/>Record video</button><button type="button" @click.stop="photoInput?.click()"><ImagePlus/>Take photos</button></div>
                        <input ref="cameraInput" hidden type="file" accept="video/*" capture="environment" @change="select(($event.target as HTMLInputElement).files?.[0])" />
                        <input ref="photoInput" hidden type="file" accept="image/*" capture="environment" multiple @change="selectImages(($event.target as HTMLInputElement).files)" />
                    </div>
                    <div v-else class="upload-preview"><video :src="preview" controls @loadedmetadata="loadedMetadata" /><div class="selected-file"><FileVideo :size="20" /><span><strong>{{ file.name }}</strong><small>{{ fileSize }}</small></span><button aria-label="Remove video" @click="discard"><X :size="17" /></button></div><div v-if="videoDuration" class="trim-editor"><div><strong>Trim video</strong><span>{{ formatTime(trimStart) }} – {{ formatTime(trimEnd) }} · {{ Math.round(trimEnd - trimStart) }}s</span></div><label>Start point<input v-model.number="trimStart" type="range" min="0" :max="Math.max(0, videoDuration - 1)" step="0.1" /></label><label>Clip length<input v-model.number="trimLength" type="range" min="1" :max="Math.min(60, videoDuration - trimStart)" step="0.1" /></label><small>Choose any segment up to 60 seconds. The selected clip is created during processing.</small></div></div>
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
                    <div v-if="saving" class="sport-upload-status" role="status" aria-live="polite">
                        <span class="sport-upload-spinner" aria-hidden="true">{{ sportUploadSymbol }}</span>
                        <div><strong>{{ selectedSport }} upload in progress</strong><small>{{ progress < 75 ? 'Uploading your media…' : progress < 100 ? 'Processing your post…' : 'Finishing up…' }}</small></div>
                    </div>
                    <div v-if="saving" class="upload-progress"><span :style="{ width: progress + '%' }" /><strong>{{ progress }}%</strong></div>
                    <p v-if="error" class="upload-feedback error">{{ error }}</p><p v-if="notice" class="upload-feedback success"><CheckCircle2 :size="16" />{{ notice }}</p>
                    <div class="upload-actions"><button type="button" class="discard" @click="discard">Discard</button><button class="post" :disabled="saving || (!file && !images.length)">{{ saving ? 'Uploading…' : 'Post' }}</button></div>
                </form>
            </section>
        </main>
    </AppShell>
</template>

<style scoped>
.sport-upload-status { display: flex; align-items: center; gap: .85rem; padding: .8rem 1rem; color: #172033; border: 1px solid #e2e8f1; border-radius: 13px; background: #f7f9fc; }
.sport-upload-spinner { display: grid; flex: 0 0 42px; width: 42px; height: 42px; place-items: center; border-radius: 50%; background: #fff; box-shadow: 0 5px 14px rgba(30,46,72,.12); font-size: 1.65rem; animation: sport-ball-spin .9s linear infinite; }
.sport-upload-status div { display: grid; gap: .15rem; }.sport-upload-status strong { font-size: .75rem; }.sport-upload-status small { color: #718096; font-size: .65rem; }
.trim-editor { display: grid; gap: .75rem; padding: 1rem; border-top: 1px solid #e2e8f1; background: #fff; }.trim-editor > div { display: flex; justify-content: space-between; gap: 1rem; font-size: .78rem; }.trim-editor label { display: grid; gap: .35rem; color: #56657a; font-size: .68rem; font-weight: 700; }.trim-editor input { width: 100%; accent-color: #2563eb; }.trim-editor small { color: #718096; font-size: .66rem; }
@keyframes sport-ball-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .sport-upload-spinner { animation: sport-ball-pulse 1.2s ease-in-out infinite; } @keyframes sport-ball-pulse { 50% { transform: scale(.9); opacity: .7; } } }
</style>
