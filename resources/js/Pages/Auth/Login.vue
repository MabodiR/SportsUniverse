<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, LockKeyhole } from '@lucide/vue';
import { computed, ref } from 'vue';
import BrandLogo from '../../Components/BrandLogo.vue';

const form = useForm({ login: '', password: '', remember: false });
const showPassword = ref(false);
const page = usePage();
const socialError = computed(() => page.props.errors?.social as string | undefined);
const submit = () => form.post('/login');
</script>

<template>
    <Head title="Sign in" />
    <main class="login-screen">
        <section class="login-shell">
            <header class="login-hero">
                <div class="hero-orb" aria-hidden="true" />
                <BrandLogo />
                <div class="hero-copy">
                    <span>YOUR SPORT. YOUR UNIVERSE.</span>
                    <h1>Welcome back</h1>
                    <p>Sign in to discover talent, follow your favourite athletes and unlock new opportunities.</p>
                </div>
                <small>Talent. Opportunity. Community.</small>
            </header>

            <section class="login-side">
                <p class="new-account">New to SportsUniverse? <Link href="/register">Create account</Link></p>
                <div class="login-card">
                    <div class="card-heading">
                        <span>WELCOME BACK</span>
                        <h2>Login to your account</h2>
                        <p>Pick up where you left off in the SportsUniverse community.</p>
                    </div>

                    <form @submit.prevent="submit">
                        <label>
                            <span>Email address</span>
                            <span class="login-field"><b>@</b><input v-model="form.login" autocomplete="username" placeholder="name@example.com" /></span>
                            <small v-if="form.errors.login" class="field-error">{{ form.errors.login }}</small>
                        </label>
                        <label>
                            <span>Password</span>
                            <span class="login-field"><LockKeyhole /><input v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" placeholder="Enter your password" /><button type="button" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword"><EyeOff v-if="showPassword" /><Eye v-else /></button></span>
                            <small v-if="form.errors.password" class="field-error">{{ form.errors.password }}</small>
                        </label>
                        <div class="login-options">
                            <label><input v-model="form.remember" type="checkbox" /> Remember me</label>
                            <Link href="/password/reset">Forgot password?</Link>
                        </div>
                        <button class="login-submit" :disabled="form.processing || !form.login || !form.password">{{ form.processing ? 'Signing in…' : 'Login' }}</button>
                        <div class="login-divider"><i />or continue with<i /></div>
                        <small v-if="socialError" class="field-error social-error">{{ socialError }}</small>
                        <div class="social-actions">
                            <a href="/auth/google/redirect" class="google-login">Continue with Google</a>
                            <a href="/auth/apple/redirect">Continue with Apple</a>
                        </div>
                    </form>
                </div>
                <footer><span>© {{ new Date().getFullYear() }} SportsUniverse</span><nav><Link href="/about">About</Link><Link href="/privacy-policy">Privacy Policy</Link></nav></footer>
            </section>
        </section>
    </main>
</template>

<style scoped>
.login-screen{min-height:100vh;padding:clamp(16px,3vw,42px);background:#eef2f7}.login-shell{width:min(1120px,100%);min-height:calc(100vh - clamp(32px,6vw,84px));margin:auto;display:grid;grid-template-columns:minmax(360px,46%) 1fr;overflow:hidden;border-radius:32px;background:#f6f8fc;box-shadow:0 30px 90px rgba(21,42,76,.16)}
.login-hero{position:relative;display:flex;overflow:hidden;flex-direction:column;padding:clamp(38px,5vw,68px);color:#fff;background:linear-gradient(135deg,#071a2f 0%,#0d397e 50%,#1765f4 100%)}.login-hero::after{position:absolute;right:-140px;bottom:-110px;width:360px;height:360px;content:'';border:1px solid rgba(255,255,255,.12);border-radius:50%;box-shadow:0 0 0 72px rgba(255,255,255,.025),0 0 0 144px rgba(255,255,255,.02)}.hero-orb{position:absolute;top:12%;right:-38px;width:190px;height:190px;border-radius:50%;background:rgba(255,255,255,.09)}.login-hero :deep(.brand-logo-image){position:relative;z-index:1;width:min(285px,80%);filter:drop-shadow(0 4px 12px rgba(0,0,0,.18))}.hero-copy{position:relative;z-index:1;margin:auto 0}.hero-copy>span{font-size:11px;font-weight:900;letter-spacing:.14em;color:#bcd3ff}.hero-copy h1{margin:12px 0 10px;font-size:clamp(40px,5vw,64px);line-height:.98;letter-spacing:-.055em}.hero-copy p{max-width:420px;margin:0;color:#e4edfc;font-size:16px;line-height:1.55}.login-hero>small{position:relative;z-index:1;color:#c8d7ec;font-size:11px}
.login-side{display:flex;min-width:0;flex-direction:column;padding:30px clamp(28px,5vw,70px)}.new-account{align-self:flex-end;margin:0;color:#6c788b;font-size:12px}.new-account a,.login-options a{color:#1765f4;font-weight:800;text-decoration:none}.login-card{width:min(100%,510px);margin:auto;padding:clamp(28px,3vw,44px);border:1px solid #e4e8ef;border-radius:28px;background:#fff;box-shadow:0 20px 55px rgba(35,55,88,.10)}.card-heading>span{color:#1765f4;font-size:10px;font-weight:900;letter-spacing:.13em}.card-heading h2{margin:7px 0 5px;color:#121c2d;font-size:28px;letter-spacing:-.035em}.card-heading p{margin:0 0 24px;color:#748095;font-size:13px;line-height:1.5}.login-card form{display:grid;gap:15px}.login-card form>label{display:grid;gap:7px;color:#172033;font-size:12px;font-weight:800}.login-field{display:flex;height:53px;align-items:center;gap:11px;padding:0 15px;border:1px solid #d8dee8;border-radius:15px;background:#fff;transition:.2s}.login-field:focus-within{border-color:#1765f4;box-shadow:0 0 0 3px rgba(23,101,244,.1)}.login-field>b{color:#66758c;font-size:17px}.login-field>svg{width:18px;color:#7a879e}.login-field input{min-width:0;height:100%;flex:1;color:#172033;border:0;outline:0;background:transparent;font:inherit;font-weight:500}.login-field button{display:grid;width:34px;height:34px;padding:0;place-items:center;color:#7a879e;border:0;border-radius:9px;background:transparent;cursor:pointer}.login-field button:hover{color:#1765f4;background:#eff5ff}.login-field button svg{width:18px}.login-options{display:flex;align-items:center;justify-content:space-between;color:#66758c;font-size:12px}.login-options label{display:flex;align-items:center;gap:7px}.login-options input{accent-color:#1765f4}.login-submit{height:53px;color:#fff;border:0;border-radius:15px;background:#2468f2;box-shadow:0 10px 24px rgba(36,104,242,.22);font-size:14px;font-weight:850;cursor:pointer}.login-submit:hover:not(:disabled){background:#1558df;transform:translateY(-1px)}.login-submit:disabled{cursor:not-allowed;opacity:.5}.login-divider{display:flex;align-items:center;gap:12px;color:#66758c;font-size:11px;text-align:center}.login-divider i{height:1px;flex:1;background:#e2e6ed}.social-actions{display:grid;gap:11px}.social-actions a{display:grid;min-height:49px;place-items:center;color:#182235;border:1px solid #d8dee8;border-radius:15px;background:#fff;font-size:13px;font-weight:800;text-decoration:none}.social-actions a:hover{border-color:#8bb3fb;background:#f8fbff}.social-actions .google-login{border:2px solid #1295f5}.field-error{color:#c52c64;font-size:11px;font-weight:600}.social-error{text-align:center}.login-side footer{display:flex;align-items:center;justify-content:space-between;padding-top:20px;color:#8a94a5;font-size:11px}.login-side footer nav{display:flex;gap:15px}.login-side footer a{color:#66758c;text-decoration:none}
@media(max-width:800px){.login-screen{padding:0}.login-shell{min-height:100vh;display:block;border-radius:0}.login-hero{min-height:280px;padding:48px 30px 64px}.hero-copy{margin:38px 0 0}.hero-copy h1{font-size:38px}.login-hero>small{display:none}.login-side{margin-top:-30px;padding:0 20px 28px;position:relative;z-index:2}.new-account{display:none}.login-card{padding:60px 22px 20px;border-radius:30px}.card-heading{display:none}.login-side footer{justify-content:center;margin-top:20px}.login-side footer>span{display:none}}
@media(max-width:430px){.login-hero{padding-inline:24px}.login-side{padding-inline:14px}.login-card{padding-inline:20px}.login-options{align-items:flex-end;flex-direction:column;gap:8px}}
</style>
