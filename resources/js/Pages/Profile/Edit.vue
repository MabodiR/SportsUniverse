<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Camera, ChevronLeft, Save } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AppShell from '../../Layouts/AppShell.vue';

const professionalRoles = ['coach', 'referee', 'linesman', 'scout', 'agent'];
const organisationRoles = ['club', 'academy', 'business', 'sponsor'];
const roles = ['athlete', 'fan', ...professionalRoles, ...organisationRoles];

const profile = ref<any>();
const sports = ref<any[]>([]);
const photo = ref<File | null>(null);
const preview = ref('');
const cropZoom = ref(1);
const cropX = ref(50);
const cropY = ref(50);
const saving = ref(false);
const notice = ref('');
const error = ref('');
const form = ref<any>({
    name: '', role: 'athlete', date_of_birth: '', gender: '', bio: '', country: 'ZA',
    province: '', city: '', locality: '', township: '', is_public: true, is_available: false,
    interested_sports: [], sport_id: '', position_id: '', club_name: '', playing_level: '',
    dominant_side: '', height_cm: '', weight_kg: '', specialisation: '', years_experience: '',
    certifications_text: '', organisation_name: '', registration_number: '', website: '',
    contact_email: '', contact_phone: '', services_text: '',
});

const isProfessional = computed(() => professionalRoles.includes(form.value.role));
const isOrganisation = computed(() => organisationRoles.includes(form.value.role));
const positions = computed(() => sports.value.find((sport) => String(sport.id) === form.value.sport_id)?.positions ?? []);
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const splitList = (value: string) => value.split(/[\n,]+/).map((item) => item.trim()).filter(Boolean);

const api = async (url: string, options: RequestInit = {}) => {
    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf(),
            ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
        },
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message ?? Object.values(payload.errors ?? {}).flat().join(' '));
    return payload.data ?? payload;
};

const fill = (value: any) => {
    profile.value = value;
    form.value = {
        ...form.value,
        name: value.name ?? '', role: value.roles?.[0] ?? 'athlete', date_of_birth: value.date_of_birth ?? '',
        gender: value.gender ?? '', bio: value.bio ?? '', country: value.location?.country ?? 'ZA',
        province: value.location?.province ?? '', city: value.location?.city ?? '',
        locality: value.location?.locality ?? '', township: value.location?.township ?? '',
        is_public: !!value.is_public, is_available: !!value.is_available,
        interested_sports: value.fan?.interested_sports ?? [], sport_id: String(value.athlete?.sport?.id ?? ''),
        position_id: String(value.athlete?.position?.id ?? ''), club_name: value.athlete?.club_name ?? '',
        playing_level: value.athlete?.playing_level ?? '', dominant_side: value.athlete?.dominant_side ?? '',
        height_cm: value.athlete?.height_cm ?? '', weight_kg: value.athlete?.weight_kg ?? '',
        specialisation: value.professional?.specialisation ?? '',
        years_experience: value.professional?.years_experience ?? '',
        certifications_text: (value.professional?.certifications ?? []).join('\n'),
        organisation_name: value.organisation?.organisation_name ?? value.name ?? '',
        registration_number: value.organisation?.registration_number ?? '', website: value.organisation?.website ?? '',
        contact_email: value.organisation?.contact_email ?? '', contact_phone: value.organisation?.contact_phone ?? '',
        services_text: (value.organisation?.services ?? []).join('\n'),
    };
};

const pick = (file?: File) => {
    if (!file) return;
    photo.value = file;
    preview.value = URL.createObjectURL(file);
    cropZoom.value = 1; cropX.value = 50; cropY.value = 50;
};

const croppedPhoto = async () => {
    if (!photo.value) return null;
    const image = await createImageBitmap(photo.value);
    const canvas = document.createElement('canvas'); canvas.width = 1024; canvas.height = 1024;
    const context = canvas.getContext('2d')!;
    const scale = Math.max(1024 / image.width, 1024 / image.height) * cropZoom.value;
    const width = image.width * scale, height = image.height * scale;
    const x = -(width - 1024) * cropX.value / 100, y = -(height - 1024) * cropY.value / 100;
    context.drawImage(image, x, y, width, height); image.close();
    const blob = await new Promise<Blob>((resolve, reject) => canvas.toBlob(value => value ? resolve(value) : reject(new Error('Could not crop the profile image.')), 'image/jpeg', .9));
    return new File([blob], 'profile-cropped.jpg', { type: 'image/jpeg' });
};

const save = async () => {
    saving.value = true;
    notice.value = '';
    error.value = '';
    try {
        if (photo.value) {
            const body = new FormData();
            body.append('photo', (await croppedPhoto())!);
            profile.value.images.profile = (await api('/api/v1/profile/photo', { method: 'POST', body })).url;
        }
        await api('/api/v1/profile/role', { method: 'PATCH', body: JSON.stringify({ role: form.value.role }) });
        await api('/api/v1/profile', {
            method: 'PATCH',
            body: JSON.stringify({
                name: form.value.name, date_of_birth: form.value.date_of_birth || null,
                gender: form.value.gender || null, bio: form.value.bio || null, country: form.value.country || null,
                province: form.value.province || null, city: form.value.city || null,
                locality: form.value.locality || null, township: form.value.township || null,
                is_public: form.value.is_public, is_available: form.value.is_available,
            }),
        });
        if (form.value.role === 'athlete') {
            await api('/api/v1/profile/athlete', {
                method: 'PATCH',
                body: JSON.stringify({
                    sport_id: +form.value.sport_id || null, position_id: +form.value.position_id || null,
                    club_name: form.value.club_name || null, playing_level: form.value.playing_level || null,
                    dominant_side: form.value.dominant_side || null, height_cm: +form.value.height_cm || null,
                    weight_kg: +form.value.weight_kg || null,
                }),
            });
        }
        if (form.value.role === 'fan') {
            await api('/api/v1/onboarding/fan-interests', { method: 'PUT', body: JSON.stringify({ interested_sports: form.value.interested_sports }) });
        }
        if (isProfessional.value) {
            await api('/api/v1/profile/professional', {
                method: 'PATCH',
                body: JSON.stringify({
                    professional_type: form.value.role, specialisation: form.value.specialisation || null,
                    years_experience: +form.value.years_experience || null,
                    certifications: splitList(form.value.certifications_text), is_available: form.value.is_available,
                }),
            });
        }
        if (isOrganisation.value) {
            await api('/api/v1/profile/organisation', {
                method: 'PATCH',
                body: JSON.stringify({
                    organisation_name: form.value.organisation_name, organisation_type: form.value.role,
                    registration_number: form.value.registration_number || null, website: form.value.website || null,
                    contact_email: form.value.contact_email || null, contact_phone: form.value.contact_phone || null,
                    services: splitList(form.value.services_text),
                }),
            });
        }
        fill(await api('/api/v1/profile'));
        notice.value = 'Profile updated successfully.';
    } catch (exception: any) {
        error.value = exception.message;
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    const [currentProfile, availableSports] = await Promise.all([api('/api/v1/profile'), api('/api/v1/sports')]);
    sports.value = availableSports;
    fill(currentProfile);
});
</script>

<template>
    <Head title="Edit Profile" />
    <AppShell>
        <main class="profile-edit-page">
            <header>
                <Link href="/profile"><ChevronLeft />Back to profile</Link>
                <h1>Edit profile</h1>
                <p>Update your identity, location and role-specific details.</p>
            </header>
            <form v-if="profile" @submit.prevent="save">
                <aside>
                    <div class="edit-avatar">
                        <img v-if="preview || profile.images?.profile" :src="preview || profile.images.profile" :alt="`${form.name} profile photo`" :style="preview ? { transform: `scale(${cropZoom})`, objectPosition: `${cropX}% ${cropY}%` } : undefined" />
                        <span v-else>{{ form.name.slice(0, 2).toUpperCase() }}</span>
                        <label><Camera /><input hidden type="file" accept="image/jpeg,image/png,image/webp" @change="pick(($event.target as HTMLInputElement).files?.[0])" /></label>
                    </div>
                    <div v-if="photo" class="profile-crop-controls"><strong>Crop profile photo</strong><label>Zoom<input v-model.number="cropZoom" type="range" min="1" max="3" step=".05" /></label><label>Horizontal position<input v-model.number="cropX" type="range" min="0" max="100" /></label><label>Vertical position<input v-model.number="cropY" type="range" min="0" max="100" /></label></div>
                    <h2>{{ form.name }}</h2>
                    <small>@{{ profile.slug }}</small>
                    <p>{{ profile.completeness }}% complete</p>
                    <small v-if="isOrganisation">Your profile image is displayed as your organisation logo.</small>
                    <label class="edit-toggle"><span>Public profile</span><input v-model="form.is_public" type="checkbox" /></label>
                    <label class="edit-toggle"><span>Available for opportunities</span><input v-model="form.is_available" type="checkbox" /></label>
                </aside>

                <div class="edit-sections">
                    <section>
                        <h2>Personal information</h2>
                        <div class="edit-fields">
                            <label>Full name<input v-model="form.name" required /></label>
                            <label>Primary role<select v-model="form.role"><option v-for="role in roles" :key="role" :value="role">{{ role }}</option></select></label>
                            <label>Date of birth<input v-model="form.date_of_birth" type="date" /></label>
                            <label>Gender<select v-model="form.gender"><option value="">Prefer not to say</option><option>male</option><option>female</option><option>non-binary</option></select></label>
                            <label class="wide">Description <small>{{ form.bio.length }}/1000</small><textarea v-model="form.bio" maxlength="1000" /></label>
                        </div>
                    </section>

                    <section v-if="form.role === 'athlete'">
                        <h2>Sporting details</h2>
                        <div class="edit-fields">
                            <label>Sport<select v-model="form.sport_id"><option value="">Select sport</option><option v-for="sport in sports" :key="sport.id" :value="String(sport.id)">{{ sport.name }}</option></select></label>
                            <label>Position<select v-model="form.position_id"><option value="">Select position</option><option v-for="position in positions" :key="position.id" :value="String(position.id)">{{ position.name }}</option></select></label>
                            <label>Club or academy<input v-model="form.club_name" /></label>
                            <label>Playing level<input v-model="form.playing_level" /></label>
                            <label>Dominant side<select v-model="form.dominant_side"><option value="">Select</option><option>left</option><option>right</option><option>both</option></select></label>
                            <label>Height (cm)<input v-model="form.height_cm" type="number" /></label>
                            <label>Weight (kg)<input v-model="form.weight_kg" type="number" step=".1" /></label>
                        </div>
                    </section>

                    <section v-if="isProfessional">
                        <h2>Professional details</h2>
                        <div class="edit-fields">
                            <label>Specialisation<input v-model="form.specialisation" placeholder="e.g. Youth development" /></label>
                            <label>Years of experience<input v-model="form.years_experience" type="number" min="0" max="80" /></label>
                            <label class="wide">Certifications <small>One per line or comma separated</small><textarea v-model="form.certifications_text" placeholder="SAFA C Licence&#10;First Aid Level 2" /></label>
                        </div>
                    </section>

                    <section v-if="isOrganisation">
                        <h2>Organisation details</h2>
                        <div class="edit-fields">
                            <label>Organisation name<input v-model="form.organisation_name" required /></label>
                            <label>Registration number<input v-model="form.registration_number" /></label>
                            <label>Website<input v-model="form.website" type="url" placeholder="https://" /></label>
                            <label>Contact email<input v-model="form.contact_email" type="email" /></label>
                            <label>Contact phone<input v-model="form.contact_phone" type="tel" /></label>
                            <label class="wide">Services <small>One per line or comma separated</small><textarea v-model="form.services_text" placeholder="Player development&#10;Training facilities" /></label>
                        </div>
                    </section>

                    <section>
                        <h2>Location</h2>
                        <div class="edit-fields">
                            <label>Country code<input v-model="form.country" maxlength="2" /></label>
                            <label>Province<input v-model="form.province" /></label>
                            <label>City<input v-model="form.city" /></label>
                            <label>Locality<input v-model="form.locality" /></label>
                        </div>
                    </section>

                    <p v-if="error" class="edit-message error">{{ error }}</p>
                    <p v-if="notice" class="edit-message success">{{ notice }}</p>
                    <div class="edit-actions"><Link href="/profile">Cancel</Link><button :disabled="saving"><Save />{{ saving ? 'Saving…' : 'Save profile' }}</button></div>
                </div>
            </form>
        </main>
    </AppShell>
</template>

<style scoped>
.edit-avatar { overflow: hidden; }.edit-avatar img { width: 100%; height: 100%; object-fit: cover; transform-origin: center; }.profile-crop-controls { display: grid; gap: .55rem; width: 100%; margin-top: .8rem; padding: .8rem; border: 1px solid #dde5ef; border-radius: 12px; background: #f7f9fc; }.profile-crop-controls strong { font-size: .75rem; }.profile-crop-controls label { display: grid; gap: .2rem; color: #617087; font-size: .65rem; }.profile-crop-controls input { width: 100%; accent-color: #2563eb; }
</style>
