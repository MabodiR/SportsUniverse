<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Camera, CheckCircle2, CloudUpload, FileVideo, ImagePlus, Info, RotateCw, SlidersHorizontal, X } from '@lucide/vue';
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
const brightness = ref(0);
const contrast = ref(0);
const saturation = ref(0);
const rotation = ref(0);
const outputWidth = ref(1080);
const quality = ref('balanced');
const caption = ref('');
const postType = ref<'post' | 'story'>('post');
const visibility = ref('Everyone');
const comments = ref(true);
const hashtags = ref('');
const sportId = ref('');
const locationName = ref('');
const countryCode = ref('ZA');
const league = ref('');
const team = ref('');
const competition = ref('');
const contentType = ref('match_highlight');
const language = ref('en');
const skillTags = ref('');
const sports = ref<any[]>([]);
const progress = ref(0);
const saving = ref(false);
const publishedPost = ref<any | null>(null);
const notice = ref('');
const error = ref('');
const dragging = ref(false);
const input = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);
const DRAFT_KEY = 'current-upload';
const openDrafts = () => new Promise<IDBDatabase>((resolve, reject) => { const request = indexedDB.open('sportuniverse-uploads', 1); request.onupgradeneeded = () => request.result.createObjectStore('drafts'); request.onsuccess = () => resolve(request.result); request.onerror = () => reject(request.error); });
const saveDraft = async () => {
    try {
        const db = await openDrafts();
        const transaction = db.transaction('drafts', 'readwrite');
        // IndexedDB cannot clone Vue's reactive array proxy. A new array keeps
        // the File objects intact while removing Vue's proxy wrapper.
        transaction.objectStore('drafts').put({
            video: file.value,
            images: Array.from(images.value),
            caption: caption.value,
            postType: postType.value,
            hashtags: hashtags.value,
            sportId: sportId.value,
            locationName: locationName.value,
            countryCode: countryCode.value, league: league.value, team: team.value, competition: competition.value, contentType: contentType.value, language: language.value, skillTags: skillTags.value,
            coverIndex: coverIndex.value,
            trimStart: trimStart.value,
            trimLength: trimLength.value,
            brightness: brightness.value,
            contrast: contrast.value,
            saturation: saturation.value,
            rotation: rotation.value,
            outputWidth: outputWidth.value,
            quality: quality.value,
            savedAt: Date.now(),
        }, DRAFT_KEY);
    } catch (exception) {
        // Draft persistence is a convenience and must never block publishing.
        console.warn('Unable to save the upload draft.', exception);
    }
};
const clearDraft = async () => { const db = await openDrafts(); db.transaction('drafts', 'readwrite').objectStore('drafts').delete(DRAFT_KEY); };
const restoreDraft = async () => { const db = await openDrafts(); const request = db.transaction('drafts').objectStore('drafts').get(DRAFT_KEY); request.onsuccess = () => { const draft = request.result; if (!draft) return; if (draft.video) select(draft.video, false); if (draft.images?.length) selectImages(draft.images, false); caption.value=draft.caption??'';hashtags.value=draft.hashtags??'';sportId.value=draft.sportId??'';locationName.value=draft.locationName??'';countryCode.value=draft.countryCode??'ZA';league.value=draft.league??'';team.value=draft.team??'';competition.value=draft.competition??'';contentType.value=draft.contentType??'match_highlight';language.value=draft.language??'en';skillTags.value=draft.skillTags??'';coverIndex.value=draft.coverIndex??0;trimStart.value=draft.trimStart??0;trimLength.value=draft.trimLength??60;brightness.value=draft.brightness??0;contrast.value=draft.contrast??0;saturation.value=draft.saturation??0;rotation.value=draft.rotation??0;outputWidth.value=draft.outputWidth??1080;quality.value=draft.quality??'balanced';notice.value='Recovered your unfinished upload.'; }; };
const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
const responsePayload = async (response: Response) => {
    const type = response.headers.get('content-type') ?? '';
    if (type.includes('application/json')) return response.json();
    const body = await response.text();
    throw new Error(response.status === 413 ? 'This file is too large to upload. Your draft is safe—choose a smaller file or trim the video, then try again.' : `We couldn’t complete the upload (${response.status}). ${body ? 'Please try again.' : ''}`.trim());
};
const fileSize = computed(() => file.value ? `${(file.value.size / 1024 / 1024).toFixed(1)} MB` : '');
const selectedSport = computed(() => sports.value.find(sport => String(sport.id) === String(sportId.value))?.name ?? 'Sport');
const trimEnd = computed(() => Math.min(videoDuration.value, trimStart.value + trimLength.value));
const formatBytes = (bytes: number) => bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
const estimatedSizeFor = (seconds: number) => file.value && videoDuration.value ? formatBytes(file.value.size * Math.min(seconds, Math.max(0, videoDuration.value - trimStart.value)) / videoDuration.value) : '—';
const trimmedSize = computed(() => estimatedSizeFor(Math.max(0, trimEnd.value - trimStart.value)));
const mediaPreviewStyle = computed(() => ({ filter: `brightness(${100 + brightness.value}%) contrast(${100 + contrast.value}%) saturate(${100 + saturation.value}%)`, transform: `rotate(${rotation.value}deg)` }));
const resetAdjustments = () => { brightness.value = 0; contrast.value = 0; saturation.value = 0; rotation.value = 0; outputWidth.value = 1080; quality.value = 'balanced'; };
const formatTime = (seconds: number) => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
const loadedMetadata = (event: Event) => { videoDuration.value = (event.target as HTMLVideoElement).duration || 0; trimLength.value = Math.min(postType.value === 'story' ? 30 : 60, videoDuration.value); trimStart.value = Math.min(trimStart.value, Math.max(0, videoDuration.value - 1)); };
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
const discard = () => { if (preview.value) URL.revokeObjectURL(preview.value); imagePreviews.value.forEach(URL.revokeObjectURL); file.value = null; images.value = []; imagePreviews.value = []; preview.value = ''; caption.value = ''; notice.value = ''; error.value = ''; publishedPost.value = null; progress.value = 0; clearDraft(); };
const uploadMedia = async (uploadFile: File, kind: 'video' | 'image', index: number, total: number) => {
    const form = new FormData(); form.append('file', uploadFile); form.append('kind', kind); form.append('collection', 'uploads');
    form.append('brightness', String(brightness.value)); form.append('contrast', String(contrast.value)); form.append('saturation', String(saturation.value)); form.append('rotation', String(rotation.value)); form.append('output_width', String(outputWidth.value)); form.append('quality', quality.value);
    if (kind === 'video' && videoDuration.value > 0) { form.append('trim_start_ms', String(Math.round(trimStart.value * 1000))); form.append('trim_end_ms', String(Math.round(trimEnd.value * 1000))); }
    const acceptedUpload = await new Promise<any>((resolve, reject) => {
        const xhr = new XMLHttpRequest(); xhr.open('POST', '/api/v1/media'); xhr.responseType = 'json'; xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-CSRF-TOKEN', csrf());
        xhr.upload.onprogress = event => { if (event.lengthComputable) progress.value = Math.round(((index + event.loaded / event.total * .7) / total) * 100); };
        xhr.onerror = () => reject(new Error('Network error during upload.'));
        xhr.ontimeout = () => reject(new Error('The upload timed out. Your draft is safe; please retry.'));
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) return resolve(xhr.response.data);
            if (xhr.status === 413) return reject(new Error('This file is too large to upload. Your draft is safe—choose a smaller file or trim the video, then try again.'));
            const validation = xhr.response?.errors ? Object.values(xhr.response.errors).flat()[0] : null;
            reject(new Error(String(validation ?? xhr.response?.message ?? `Upload failed (${xhr.status}).`)));
        };
        xhr.send(form);
    });
    progress.value = Math.max(progress.value, Math.round(((index + 1) / total) * 90));
    return acceptedUpload;
};
const upload = async () => {
    if (saving.value || publishedPost.value) return;
    if (!file.value && !images.value.length) { error.value = 'Select a video or at least one picture.'; return; }
    if (!navigator.onLine) { await saveDraft(); notice.value = 'Saved on this device. Upload will resume when you are online.'; navigator.serviceWorker?.ready.then((registration:any)=>registration.sync?.register('sportuniverse-upload')); return; }
    saving.value = true; error.value = ''; notice.value = ''; await saveDraft();
    try {
        const total = images.value.length + (file.value ? 1 : 0); let cursor = 0;
        notice.value = 'Uploading your media…';
        const media = file.value ? await uploadMedia(file.value, 'video', cursor++, total) : null;
        const uploadedImages = [];
        for (const image of images.value) uploadedImages.push(await uploadMedia(image, 'image', cursor++, total));
        const imageIds = uploadedImages.map(image => image.id);
        const publishResponse = await fetch('/api/v1/videos', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ media_id: media?.id, ...(imageIds.length ? { image_media_ids: imageIds, cover_media_id: imageIds[coverIndex.value] } : {}), caption: caption.value, post_type: postType.value, hashtags: hashtags.value.split(/[\s,]+/).map(tag => tag.replace(/^#/, '')).filter(Boolean), sport_id: sportId.value || null, country_code: countryCode.value || null, league: league.value || null, team: team.value || null, competition: competition.value || null, content_type: contentType.value || null, language: language.value || null, skill_tags: skillTags.value.split(/[\s,]+/).map(tag => tag.replace(/^#/, '')).filter(Boolean), location_name: locationName.value || null, comments_enabled: comments.value, visibility: postType.value === 'story' ? 'followers' : visibility.value === 'Everyone' ? 'public' : visibility.value === 'Followers' ? 'followers' : 'private', publish: true }) });
        const publishPayload = await responsePayload(publishResponse);
        if (!publishResponse.ok) throw new Error(publishPayload.message ?? Object.values(publishPayload.errors ?? {}).flat().join(' '));
        publishedPost.value = publishPayload.data;
        progress.value = 100; notice.value = publishPayload.queued ? 'Upload received. You can leave this page—we’ll notify you when your post is ready.' : 'Your post is live on SportsUniverse.'; await clearDraft();
    } catch (e: any) { const message = e.message ?? 'We couldn’t upload your media.'; error.value = /draft is safe/i.test(message) ? message : `${message} Your draft is safe, so you can try again.`; navigator.serviceWorker?.ready.then((registration:any)=>registration.sync?.register('sportuniverse-upload')); }
    finally { saving.value = false; }
};
const resume = () => { if ((file.value || images.value.length) && !saving.value && navigator.onLine) { notice.value='Connection restored. Tap Post to resume your saved upload.'; } };
const serviceMessage = (event:MessageEvent) => { if(event.data?.type==='RESUME_UPLOAD') resume(); };
let draftTimer:ReturnType<typeof setTimeout>;watch(postType,value=>{if(value==='story'){visibility.value='Followers';trimLength.value=Math.min(30,videoDuration.value||30)}});watch([caption,postType,hashtags,sportId,locationName,countryCode,league,team,competition,contentType,language,skillTags,coverIndex,brightness,contrast,saturation,rotation,outputWidth,quality],()=>{if(file.value||images.value.length){clearTimeout(draftTimer);draftTimer=setTimeout(saveDraft,350)}});
onMounted(async () => { await restoreDraft(); const params=new URLSearchParams(location.search);if(params.get('type')==='story'){postType.value='story';visibility.value='Followers'}const response = await fetch('/api/v1/sports', { headers: { Accept: 'application/json' } }); sports.value = (await response.json()).data ?? []; window.addEventListener('online',resume);navigator.serviceWorker?.addEventListener('message',serviceMessage);if(params.has('camera'))setTimeout(()=>cameraInput.value?.click(),250); });
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
                    <div v-else class="upload-preview"><video :src="preview" :style="mediaPreviewStyle" controls @loadedmetadata="loadedMetadata" /><div class="selected-file"><FileVideo :size="20" /><span><strong>{{ file.name }}</strong><small>{{ fileSize }}</small></span><button aria-label="Remove video" @click="discard"><X :size="17" /></button></div><div v-if="videoDuration" class="trim-editor"><div><strong>Trim video</strong><span>{{ formatTime(trimStart) }} – {{ formatTime(trimEnd) }} · {{ Math.round(trimEnd - trimStart) }}s <b>≈ {{ trimmedSize }}</b></span></div><label>Start point<input v-model.number="trimStart" type="range" min="0" :max="Math.max(0, videoDuration - 1)" step="0.1" /></label><div class="clip-length-options"><span>{{postType==='story'?'Story length':'Post length'}}</span><button type="button" :class="{ active: trimLength === 30 || postType==='story' }" @click="trimLength = Math.min(30, videoDuration - trimStart)"><strong>30 seconds</strong><small>{{postType==='story'?'Maximum Story length':`≈ ${estimatedSizeFor(30)}`}}</small></button><button v-if="postType!=='story'" type="button" :class="{ active: trimLength > 30 }" @click="trimLength = Math.min(60, videoDuration - trimStart)"><strong>60 seconds max</strong><small>≈ {{ estimatedSizeFor(60) }}</small></button></div><small>{{postType==='story'?'Stories are limited to 30 seconds. Choose the best part of your video.':'Estimated from the original file before optimisation. Final size may be smaller depending on the resize and compression settings.'}}</small></div></div>
                </div>
                <form class="upload-details" @submit.prevent="upload">
                    <h2>Post details</h2>
                    <div class="upload-type-picker"><span class="upload-label">Share as</span><div><button type="button" :class="{active:postType==='post'}" @click="postType='post'">Post<small>Stays on your profile</small></button><button type="button" :class="{active:postType==='story'}" @click="postType='story';visibility='Followers'">Story<small>Followers only · 24 hours</small></button></div><p v-if="postType==='story'"><Info :size="16"/>Your Story disappears after 24 hours. Promoting it can show it to more people, but does not extend its lifetime.</p></div>
                    <label><span class="upload-label">Pictures <small>Up to 10</small></span><input class="upload-picture-input" type="file" accept="image/jpeg,image/png,image/webp" multiple @change="selectImages(($event.target as HTMLInputElement).files)" /></label>
                    <div v-if="imagePreviews.length" class="upload-picture-grid"><button v-for="(image, index) in imagePreviews" :key="image" type="button" :class="{ selected: coverIndex === index }" @click="coverIndex = index"><img :src="image" :style="mediaPreviewStyle" alt="Selected post picture" /><span>{{ coverIndex === index ? 'Main picture' : 'Set as main' }}</span></button></div>
                    <section v-if="file || images.length" class="media-editor"><header><div><SlidersHorizontal/><span><strong>Improve & optimise</strong><small>Adjust the look and reduce file size safely</small></span></div><button type="button" @click="resetAdjustments">Reset</button></header><div class="adjustment-grid"><label>Brightness <b>{{brightness>0?'+':''}}{{brightness}}</b><input v-model.number="brightness" type="range" min="-50" max="50" /></label><label>Contrast <b>{{contrast>0?'+':''}}{{contrast}}</b><input v-model.number="contrast" type="range" min="-50" max="50" /></label><label>Colour <b>{{saturation>0?'+':''}}{{saturation}}</b><input v-model.number="saturation" type="range" min="-50" max="50" /></label></div><div class="editor-options"><button type="button" @click="rotation=(rotation+90)%360"><RotateCw/>Rotate {{rotation ? `${rotation}°` : ''}}</button><label>Resize<select v-model.number="outputWidth"><option :value="1080">1080px · Recommended</option><option :value="720">720px · Smaller</option><option :value="480">480px · Data saver</option></select></label><label>Compression<select v-model="quality"><option value="high">High quality</option><option value="balanced">Balanced · Recommended</option><option value="space">Save more space</option></select></label></div><p>Optimisation happens securely after upload. Balanced mode removes unnecessary file weight while keeping strong visual quality.</p></section>
                    <label><span class="upload-label">Caption <small>{{ caption.length }} / 2200</small></span><textarea v-model="caption" maxlength="2200" placeholder="Tell viewers about your video..." /></label>
                    <label><span class="upload-label">Hashtags</span><input v-model="hashtags" class="su-input" placeholder="football talent training" /></label>
                    <label><span class="upload-label">Sport</span><select v-model="sportId"><option value="">Select sport</option><option v-for="sport in sports" :key="sport.id" :value="sport.id">{{ sport.name }}</option></select></label>
                    <div class="content-metadata-grid">
                        <label><span class="upload-label">Country</span><input v-model="countryCode" class="su-input" maxlength="2" placeholder="ZA" /></label>
                        <label><span class="upload-label">League</span><input v-model="league" class="su-input" placeholder="Premier League, PSL…" /></label>
                        <label><span class="upload-label">Team</span><input v-model="team" class="su-input" placeholder="Team or club" /></label>
                        <label><span class="upload-label">Competition</span><input v-model="competition" class="su-input" placeholder="Competition or tournament" /></label>
                        <label><span class="upload-label">Content type</span><select v-model="contentType"><option value="match_highlight">Match highlight</option><option value="training">Training</option><option value="skills">Skills</option><option value="analysis">Analysis</option><option value="interview">Interview</option><option value="news">News</option><option value="behind_the_scenes">Behind the scenes</option><option value="other">Other</option></select></label>
                        <label><span class="upload-label">Language</span><input v-model="language" class="su-input" maxlength="12" placeholder="en" /></label>
                    </div>
                    <label><span class="upload-label">Skills and topics</span><input v-model="skillTags" class="su-input" placeholder="finishing counter-attack goalkeeping" /></label>
                    <label><span class="upload-label">Location</span><input v-model="locationName" class="su-input" placeholder="City, venue, suburb or township" /></label>
                    <label><span class="upload-label">Who can watch this video</span><select v-model="visibility" :disabled="postType==='story'"><option>Everyone</option><option>Followers</option><option>Only me</option></select><small v-if="postType==='story'">Stories are visible only to followers unless promoted.</small></label>
                    <div><span class="upload-label">Allow users to</span><label class="upload-check"><input v-model="comments" type="checkbox" /> Comments</label></div>
                    <div class="upload-note"><Info :size="17" /><p>Keep this page open only while the original file uploads. Processing continues in the background and we’ll notify you when it is ready.</p></div>
                    <div v-if="saving" class="sport-upload-status" role="status" aria-live="polite">
                        <span class="sport-upload-spinner" aria-hidden="true">{{ sportUploadSymbol }}</span>
                        <div><strong>{{ selectedSport }} upload in progress</strong><small>{{ progress < 100 ? 'Safely transferring your media…' : 'Sending it to the processing queue…' }}</small></div>
                    </div>
                    <div v-if="saving" class="upload-progress"><span :style="{ width: progress + '%' }" /><strong>{{ progress }}%</strong></div>
                    <p v-if="error" class="upload-feedback error">{{ error }}</p><p v-if="notice" class="upload-feedback success"><CheckCircle2 :size="16" />{{ notice }}</p>
                    <div class="upload-actions"><button type="button" class="discard" @click="discard">{{ publishedPost ? 'Create another post' : 'Discard' }}</button><button class="post" :disabled="saving || !!publishedPost || (!file && !images.length)">{{ saving ? 'Uploading…' : publishedPost ? (publishedPost.status === 'published' ? 'Posted' : 'Queued') : 'Post' }}</button></div>
                </form>
            </section>
        </main>
    </AppShell>
</template>

<style scoped>
.sport-upload-status { display: flex; align-items: center; gap: .85rem; padding: .8rem 1rem; color: #172033; border: 1px solid #e2e8f1; border-radius: 13px; background: #f7f9fc; }
.sport-upload-spinner { display: grid; flex: 0 0 42px; width: 42px; height: 42px; place-items: center; border-radius: 50%; background: #fff; box-shadow: 0 5px 14px rgba(30,46,72,.12); font-size: 1.65rem; animation: sport-ball-spin .9s linear infinite; }
.sport-upload-status div { display: grid; gap: .15rem; }.sport-upload-status strong { font-size: .75rem; }.sport-upload-status small { color: #718096; font-size: .65rem; }
.trim-editor { display: grid; gap: .75rem; padding: 1rem; border-top: 1px solid #e2e8f1; background: #fff; }.trim-editor > div { display: flex; justify-content: space-between; gap: 1rem; font-size: .78rem; }.trim-editor label { display: grid; gap: .35rem; color: #56657a; font-size: .68rem; font-weight: 700; }.trim-editor input { width: 100%; accent-color: #476FEA; }.trim-editor small { color: #718096; font-size: .66rem; }
.trim-editor .clip-length-options { align-items: center; justify-content: flex-start; gap: .5rem; }.trim-editor>div:first-child span{display:flex;gap:.4rem;align-items:center}.trim-editor>div:first-child b{padding:.2rem .4rem;color:#476FEA;border-radius:6px;background:#edf3ff;font-size:.62rem}.clip-length-options > span { margin-right: auto; color: #56657a; font-size: .68rem; font-weight: 700; }.clip-length-options button { display:grid;gap:.12rem;padding:.45rem .7rem;color:#56657a;border:1px solid #d5dce7;border-radius:9px;background:#fff;font-size:.66rem;font-weight:750;cursor:pointer;text-align:center}.clip-length-options button small{color:#8190a4;font-size:.56rem}.clip-length-options button.active { color: #fff; border-color: #476FEA; background: #476FEA; }.clip-length-options button.active small{color:#e8eeff}
.media-editor{display:grid;gap:1rem;padding:1rem;border:1px solid #dce4ef;border-radius:15px;background:#f8faff}.media-editor>header,.media-editor>header>div{display:flex;align-items:center;gap:.65rem}.media-editor>header{justify-content:space-between}.media-editor>header svg{width:19px;color:#476FEA}.media-editor>header span{display:grid}.media-editor>header strong{font-size:.76rem}.media-editor>header small,.media-editor>p{color:#718096;font-size:.62rem}.media-editor>header button{color:#476FEA;border:0;background:transparent;font-size:.63rem;font-weight:800}.adjustment-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}.adjustment-grid label{display:grid;grid-template-columns:1fr auto;gap:.4rem;color:#526174;font-size:.65rem;font-weight:750}.adjustment-grid input{grid-column:1/-1;width:100%;accent-color:#476FEA}.adjustment-grid b{color:#476FEA}.editor-options{display:grid;grid-template-columns:auto 1fr 1fr;gap:.6rem}.editor-options>button,.editor-options label{display:flex;align-items:center;gap:.4rem;min-height:42px;padding:.55rem .65rem;border:1px solid #d8e0eb;border-radius:10px;background:#fff;color:#445268;font-size:.62rem;font-weight:750}.editor-options button svg{width:15px}.editor-options select{min-width:0;flex:1;border:0;background:transparent;color:#445268}.media-editor>p{margin:0;line-height:1.45}@media(max-width:620px){.adjustment-grid{grid-template-columns:1fr}.editor-options{grid-template-columns:1fr}.media-editor>header{align-items:flex-start}}
@keyframes sport-ball-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .sport-upload-spinner { animation: sport-ball-pulse 1.2s ease-in-out infinite; } @keyframes sport-ball-pulse { 50% { transform: scale(.9); opacity: .7; } } }
</style>
