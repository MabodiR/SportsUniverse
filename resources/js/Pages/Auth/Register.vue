<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, BriefcaseBusiness, Building2, Check, Dumbbell, Eye, EyeOff, Heart, Loader2, Search, ShieldCheck, X } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import BrandLogo from '../../Components/BrandLogo.vue';

const step = ref(1);
const sports = ref<any[]>([]);
const showPassword = ref(false);
const showConfirmation = ref(false);
const availabilityChecking = ref(false);
const localErrors = ref<Record<string, string>>({});
const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '', role: '', interested_sports: [] as string[], sport_id: '', position_id: '', club_name: '', playing_level: '', specialisation: '', years_experience: '', organisation_name: '', services: '', bio: '', date_of_birth: '', province: '', city: '', locality: '' });

const roles = [
    { value: 'athlete', label: 'Athlete', hint: 'Showcase talent and find opportunities', icon: Dumbbell },
    { value: 'fan', label: 'Fan', hint: 'Follow athletes and communities', icon: Heart },
    { value: 'coach', label: 'Coach', hint: 'Train and mentor athletes', icon: ShieldCheck },
    { value: 'referee', label: 'Referee', hint: 'Build your officiating career', icon: ShieldCheck },
    { value: 'linesman', label: 'Linesman', hint: 'Showcase officiating experience', icon: ShieldCheck },
    { value: 'scout', label: 'Scout', hint: 'Discover sporting talent', icon: Search },
    { value: 'agent', label: 'Agent', hint: 'Represent sporting careers', icon: Search },
    { value: 'club', label: 'Club', hint: 'Recruit and publish trials', icon: Building2 },
    { value: 'academy', label: 'Academy', hint: 'Develop emerging talent', icon: Building2 },
    { value: 'business', label: 'Business', hint: 'Offer sports products and services', icon: BriefcaseBusiness },
    { value: 'sponsor', label: 'Sponsor', hint: 'Support talent and campaigns', icon: BriefcaseBusiness },
];
const positions = computed(() => sports.value.find(s => String(s.id) === form.sport_id)?.positions ?? []);
const title = computed(() => ['Create your SportsUniverse account.', 'Choose how you belong.', form.role === 'fan' ? 'Choose the sports you love.' : 'Build your sporting identity.', 'Tell us where your journey is happening.'][step.value - 1]);
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const errorFor = (field: string) => localErrors.value[field] || (form.errors as Record<string, string>)[field] || '';
const invalid = (field: string) => !!errorFor(field);
const clearError = (field: string) => { delete localErrors.value[field]; form.clearErrors(field as any); };

const passwordRules = computed(() => [
    { label: 'At least 8 characters', met: form.password.length >= 8 },
    { label: 'At least one letter', met: /[A-Za-z]/.test(form.password) },
    { label: 'At least one number', met: /\d/.test(form.password) },
    { label: 'At least one special character (!@#$…)', met: /[^A-Za-z0-9]/.test(form.password) },
]);
const passwordScore = computed(() => passwordRules.value.filter(rule => rule.met).length + (form.password.length >= 12 ? 1 : 0));
const passwordStrength = computed(() => !form.password ? '' : passwordScore.value <= 2 ? 'Weak' : passwordScore.value === 3 ? 'Fair' : passwordScore.value === 4 ? 'Good' : 'Strong');

const checkAvailability = async (field?: 'email' | 'phone') => {
    if (field === 'email' && (!form.email || !/^\S+@\S+\.\S+$/.test(form.email))) return false;
    if (field === 'phone' && !form.phone) return true;
    availabilityChecking.value = true;
    try {
        const response = await fetch('/register/check-availability', {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ email: form.email || null, phone: form.phone || null }),
        });
        const payload = await response.json();
        if (!response.ok) return false;
        if (!payload.data.email_available) localErrors.value.email = 'This email address is already registered.';
        if (!payload.data.phone_available) localErrors.value.phone = 'This phone number is already registered.';
        return payload.data.email_available && payload.data.phone_available;
    } finally { availabilityChecking.value = false; }
};

const validateAccount = async () => {
    localErrors.value = {};
    if (!form.name.trim()) localErrors.value.name = 'Enter your full name.';
    if (!form.email.trim()) localErrors.value.email = 'Enter your email address.';
    else if (!/^\S+@\S+\.\S+$/.test(form.email)) localErrors.value.email = 'Enter a valid email address.';
    if (!passwordRules.value[0].met) localErrors.value.password = 'Password needs at least 8 characters.';
    else if (!passwordRules.value[1].met) localErrors.value.password = 'Password needs at least one letter.';
    else if (!passwordRules.value[2].met) localErrors.value.password = 'Password needs at least one number.';
    else if (!passwordRules.value[3].met) localErrors.value.password = 'Password needs at least one special character.';
    if (!form.password_confirmation) localErrors.value.password_confirmation = 'Confirm your password.';
    else if (form.password !== form.password_confirmation) localErrors.value.password_confirmation = 'The passwords do not match.';
    if (Object.keys(localErrors.value).length) return false;
    return checkAvailability();
};
const next = async () => {
    if (step.value === 1 && !await validateAccount()) return;
    if (step.value === 2 && !form.role) { localErrors.value.role = 'Select the role that best describes you.'; return; }
    if (step.value === 3 && form.role === 'fan' && !form.interested_sports.length) { localErrors.value.interested_sports = 'Choose at least one sport.'; return; }
    step.value++;
};
const toggle = (name: string) => { clearError('interested_sports'); form.interested_sports = form.interested_sports.includes(name) ? form.interested_sports.filter(s => s !== name) : [...form.interested_sports, name]; };
const submit = () => form.post('/register', { onError: (errors: any) => { const fields = Object.keys(errors); if (fields.some(field => ['name', 'email', 'phone', 'password', 'password_confirmation'].includes(field))) step.value = 1; else if (fields.includes('role')) step.value = 2; else if (fields.some(field => ['interested_sports', 'sport_id', 'position_id', 'club_name', 'playing_level', 'specialisation', 'years_experience', 'organisation_name', 'services', 'bio'].includes(field))) step.value = 3; else step.value = 4; } });

watch(() => form.password, () => clearError('password'));
watch(() => form.password_confirmation, () => clearError('password_confirmation'));
onMounted(async () => { const response = await fetch('/api/v1/sports', { headers: { Accept: 'application/json' } }); sports.value = (await response.json()).data ?? []; });
</script>

<template>
    <Head title="Join SportsUniverse" />
    <div class="design-auth">
        <aside class="design-auth-hero"><BrandLogo/><div><h1>{{ title }}</h1><p>{{ step === 1 ? 'Start with your account details.' : step === 2 ? 'Your role personalises your feed and opportunities.' : step === 3 ? 'Tell us what should shape your SportsUniverse experience.' : 'Location helps surface nearby talent, clubs and opportunities.' }}</p></div><small>Step {{ step }} of 4</small></aside>
        <section class="design-auth-form">
            <div class="design-auth-top"><div class="design-dots"><i v-for="n in 4" :key="n" :class="{ on: n <= step }"/></div><p>Step {{ step }} of 4</p></div>
            <div class="design-form-inner">
                <h2>{{ ['Account basics', 'Select your role', form.role === 'fan' ? 'Favourite sports' : 'Profile details', 'Age and location'][step - 1] }}</h2>
                <p>{{ step === 3 && form.role === 'fan' ? 'Your For You feed will use these choices.' : 'You can update these details later from your profile.' }}</p>
                <form @submit.prevent="step < 4 ? next() : submit()">
                    <div v-if="step === 1" class="form-stack">
                        <label><span class="su-label">Full name</span><input v-model="form.name" class="su-input" :class="{ invalid: invalid('name') }" autocomplete="name" @input="clearError('name')"/><small v-if="errorFor('name')" class="field-error">{{ errorFor('name') }}</small></label>
                        <div class="auth-field-row">
                            <label><span class="su-label">Email</span><input v-model.trim="form.email" class="su-input" :class="{ invalid: invalid('email') }" type="email" autocomplete="email" @input="clearError('email')" @blur="checkAvailability('email')"/><small v-if="errorFor('email')" class="field-error">{{ errorFor('email') }}</small></label>
                            <label><span class="su-label">Phone</span><input v-model.trim="form.phone" class="su-input" :class="{ invalid: invalid('phone') }" type="tel" autocomplete="tel" placeholder="+27" @input="clearError('phone')" @blur="checkAvailability('phone')"/><small v-if="errorFor('phone')" class="field-error">{{ errorFor('phone') }}</small></label>
                        </div>
                        <label><span class="su-label">Password</span><span class="registration-password"><input v-model="form.password" class="su-input" :class="{ invalid: invalid('password') }" :type="showPassword ? 'text' : 'password'" autocomplete="new-password"/><button type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword"><EyeOff v-if="showPassword"/><Eye v-else/></button></span><small v-if="errorFor('password')" class="field-error">{{ errorFor('password') }}</small></label>
                        <div v-if="form.password" class="password-strength"><div><span :style="{ width: `${passwordScore * 20}%` }" :class="passwordStrength.toLowerCase()"/></div><strong>{{ passwordStrength }} password</strong><ul><li v-for="rule in passwordRules" :key="rule.label" :class="{ met: rule.met }"><Check v-if="rule.met"/><X v-else/>{{ rule.label }}</li></ul></div>
                        <label><span class="su-label">Confirm password</span><span class="registration-password"><input v-model="form.password_confirmation" class="su-input" :class="{ invalid: invalid('password_confirmation') }" :type="showConfirmation ? 'text' : 'password'" autocomplete="new-password"/><button type="button" :aria-label="showConfirmation ? 'Hide password' : 'Show password'" @click="showConfirmation = !showConfirmation"><EyeOff v-if="showConfirmation"/><Eye v-else/></button></span><small v-if="errorFor('password_confirmation')" class="field-error">{{ errorFor('password_confirmation') }}</small></label>
                        <p v-if="availabilityChecking" class="availability-status"><Loader2/> Checking email and phone…</p>
                    </div>
                    <div v-else-if="step === 2" class="role-grid"><button v-for="role in roles" :key="role.value" type="button" class="role-card" :class="{ selected: form.role === role.value }" @click="form.role = role.value; clearError('role')"><span class="role-icon"><component :is="role.icon"/></span><strong>{{ role.label }}</strong><small>{{ role.hint }}</small></button><small v-if="errorFor('role')" class="field-error role-error">{{ errorFor('role') }}</small></div>
                    <div v-else-if="step === 3" class="form-stack"><template v-if="form.role === 'fan'"><div class="interest-grid"><button v-for="sport in sports" :key="sport.id" type="button" :class="{ selected: form.interested_sports.includes(sport.name) }" @click="toggle(sport.name)">{{ sport.name }}<small>{{ form.interested_sports.includes(sport.name) ? 'Selected' : 'Add to feed' }}</small></button></div><p class="interest-note">Choose at least one sport for a personalised feed.</p><small v-if="errorFor('interested_sports')" class="field-error">{{ errorFor('interested_sports') }}</small></template><template v-else-if="form.role === 'athlete'"><div class="auth-field-row"><label><span class="su-label">Primary sport</span><select v-model="form.sport_id" class="su-input"><option value="">Select sport</option><option v-for="sport in sports" :key="sport.id" :value="String(sport.id)">{{ sport.name }}</option></select></label><label><span class="su-label">Position</span><select v-model="form.position_id" class="su-input"><option value="">Select position</option><option v-for="position in positions" :key="position.id" :value="String(position.id)">{{ position.name }}</option></select></label></div><label><span class="su-label">Club / school / academy</span><input v-model="form.club_name" class="su-input"/></label><label><span class="su-label">Playing level</span><input v-model="form.playing_level" class="su-input"/></label><label><span class="su-label">Short bio</span><textarea v-model="form.bio" class="su-input registration-bio"/></label></template><template v-else><label><span class="su-label">Short description</span><textarea v-model="form.bio" class="su-input registration-bio"/></label></template></div>
                    <div v-else class="form-stack"><label><span class="su-label">Date of birth</span><input v-model="form.date_of_birth" class="su-input" :class="{ invalid: invalid('date_of_birth') }" type="date"/><small v-if="errorFor('date_of_birth')" class="field-error">{{ errorFor('date_of_birth') }}</small></label><div class="auth-field-row"><label><span class="su-label">Province</span><select v-model="form.province" class="su-input"><option value="">Select province</option><option v-for="province in ['Eastern Cape','Free State','Gauteng','KwaZulu-Natal','Limpopo','Mpumalanga','North West','Northern Cape','Western Cape']" :key="province">{{ province }}</option></select></label><label><span class="su-label">City / town</span><input v-model="form.city" class="su-input"/></label></div><label><span class="su-label">Township / suburb / village</span><input v-model="form.locality" class="su-input"/></label></div>
                    <div class="registration-actions"><button v-if="step > 1" type="button" class="su-btn su-btn-ghost" @click="step--"><ArrowLeft/>Back</button><button class="su-btn su-btn-primary" :disabled="form.processing || availabilityChecking">{{ step < 4 ? availabilityChecking ? 'Checking…' : 'Continue' : form.processing ? 'Creating account…' : 'Complete registration' }}<ArrowRight v-if="step < 4 && !availabilityChecking"/></button></div>
                </form>
                <p class="auth-footer">Already a member? <Link href="/login">Sign in</Link></p>
            </div>
        </section>
    </div>
</template>

<style scoped>
.su-input.invalid { border-color: #e52d4f !important; background: #fff7f8 !important; box-shadow: 0 0 0 3px rgba(229,45,79,.1); }
.field-error { display: block; margin-top: .3rem; color: #d92748; font-size: .66rem; font-weight: 650; }.role-error { grid-column: 1/-1; }
.registration-password { position: relative; display: block; }.registration-password input { padding-right: 44px; }.registration-password button { position: absolute; top: 50%; right: 10px; display: grid; width: 34px; height: 34px; padding: 0; place-items: center; transform: translateY(-50%); color: #667085; border: 0; background: transparent; cursor: pointer; }.registration-password svg { width: 18px; }
.password-strength { display: grid; gap: .45rem; padding: .75rem; border-radius: 11px; background: #f7f9fc; }.password-strength > div { height: 5px; overflow: hidden; border-radius: 99px; background: #e2e7ef; }.password-strength > div span { display: block; height: 100%; transition: width .2s; }.password-strength .weak { background: #dc3545; }.password-strength .fair { background: #f59e0b; }.password-strength .good { background: #476FEA; }.password-strength .strong { background: #16a267; }.password-strength strong { font-size: .68rem; }.password-strength ul { display: grid; grid-template-columns: 1fr 1fr; gap: .3rem .7rem; margin: 0; padding: 0; list-style: none; }.password-strength li { display: flex; align-items: center; gap: .25rem; color: #8a94a5; font-size: .61rem; }.password-strength li.met { color: #16855a; }.password-strength li svg { width: 12px; height: 12px; }
.availability-status { display: flex; align-items: center; gap: .4rem; margin: 0; color: #667085; font-size: .66rem; }.availability-status svg { width: 14px; animation: spin 1s linear infinite; }@keyframes spin { to { transform: rotate(360deg); } }
@media(max-width:520px){.password-strength ul{grid-template-columns:1fr}}
</style>
