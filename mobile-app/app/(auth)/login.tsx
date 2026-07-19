import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as WebBrowser from 'expo-web-browser';
import { BrandMark } from '../../src/components/BrandMark';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { api } from '../../src/api/client';
import { useAuthStore } from '../../src/stores/auth';
import { colors, radius } from '../../src/theme';

const providers = [
  { key: 'google', label: 'Google' },
  { key: 'apple', label: 'Apple' },
] as const;

export default function LoginScreen() {
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [socialBusy, setSocialBusy] = useState<string>();
  const { busy, error, login: signIn, socialExchange, clearError } = useAuthStore();
  const submit = async () => { if (await signIn({ login, password })) router.replace('/(tabs)/feed'); };
  const social = async (provider: string) => {
    clearError(); setSocialBusy(provider);
    try {
      const origin = (api.defaults.baseURL ?? '').replace(/\/api\/v1\/?$/, '');
      const authUrl = `${origin}/auth/${provider}/redirect?mobile=1&device_name=${encodeURIComponent(`${Platform.OS}-mobile`)}`;
      const result = await WebBrowser.openAuthSessionAsync(authUrl, 'sportuniverse://auth/callback');
      if (result.type !== 'success') return;
      const code = result.url.match(/[?&]code=([^&]+)/)?.[1];
      if (!code) throw new Error('Social sign-in did not return a valid code.');
      if (await socialExchange(decodeURIComponent(code))) router.replace('/(tabs)/feed');
    } catch (reason: any) { useAuthStore.setState({ error: reason?.message || 'Social sign-in could not be completed.' }); }
    finally { setSocialBusy(undefined); }
  };

  return <SafeAreaView style={styles.safe}>
    <KeyboardAvoidingView style={styles.page} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
        <LinearGradient colors={['#071A2F', '#0D397E', '#476FEA']} start={{ x: 0, y: .2 }} end={{ x: 1, y: .5 }} style={styles.hero}>
          <View pointerEvents="none" style={styles.heroOrb} />
          <BrandMark />
          <Text style={styles.title}>Welcome back</Text>
          <Text style={styles.copy}>Sign in to discover talent, follow your favourite athletes and unlock new opportunities.</Text>
        </LinearGradient>

        <View style={styles.card}>
          <Text style={styles.label}>Email address</Text>
          <View style={styles.field}><Text style={styles.at}>@</Text><TextInput value={login} onChangeText={value => { setLogin(value); clearError(); }} autoCapitalize="none" keyboardType="email-address" autoComplete="email" style={styles.input} placeholder="name@example.com" placeholderTextColor="#7A879E" /></View>
          <Text style={styles.label}>Password</Text>
          <View style={styles.field}><Ionicons name="lock-closed-outline" size={18} color="#7A879E" /><TextInput value={password} onChangeText={value => { setPassword(value); clearError(); }} secureTextEntry={!showPassword} autoComplete="current-password" style={styles.input} placeholder="Enter your password" placeholderTextColor="#7A879E" /><Pressable accessibilityLabel={showPassword ? 'Hide password' : 'Show password'} hitSlop={10} onPress={() => setShowPassword(value => !value)}><Ionicons name={showPassword ? 'eye-off-outline' : 'eye-outline'} size={20} color="#7A879E" /></Pressable></View>
          <Pressable onPress={() => router.push('/(auth)/forgot-password')} style={styles.forgot}><Text style={styles.link}>Forgot password?</Text></Pressable>
          {error ? <View style={styles.errorBox}><Ionicons name="alert-circle-outline" size={17} color="#FF9CBC" /><Text style={styles.error}>{error}</Text></View> : null}
          <PrimaryButton label="Login" loading={busy} disabled={!login.trim() || !password} onPress={submit} style={styles.submit} />
          <View style={styles.divider}><View style={styles.line} /><Text style={styles.or}>OR CONTINUE WITH</Text><View style={styles.line} /></View>
          <View style={styles.socialGrid}>{providers.map(provider => <Pressable accessibilityRole="button" disabled={busy || Boolean(socialBusy)} key={provider.key} onPress={() => social(provider.key)} style={({ pressed }) => [styles.social, provider.key === 'google' && styles.socialPrimary, pressed && styles.socialPressed]}><Text style={styles.socialText}>{socialBusy === provider.key ? 'Opening…' : `Continue with ${provider.label}`}</Text></Pressable>)}</View>
        </View>
        <Text style={styles.footer}>New to SportsUniverse? <Text style={styles.link} onPress={() => router.replace('/(auth)/register')}>Create account</Text></Text>
      </ScrollView>
    </KeyboardAvoidingView>
  </SafeAreaView>;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#F2F5FA' }, page: { flex: 1 }, scroll: { flexGrow: 1, paddingBottom: 32, backgroundColor: '#F2F5FA' },
  hero: { minHeight: 250, paddingHorizontal: 47, paddingTop: 62, paddingBottom: 50, overflow: 'hidden' }, heroOrb: { position: 'absolute', right: 14, top: 58, width: 130, height: 130, borderRadius: 65, backgroundColor: 'rgba(255,255,255,.10)' },
  title: { color: '#fff', fontSize: 31, lineHeight: 36, fontWeight: '900', letterSpacing: -.8, marginTop: 16 }, copy: { maxWidth: 305, color: '#E7EEFA', fontSize: 14, lineHeight: 19, marginTop: 7 },
  card: { marginHorizontal: 22, marginTop: -30, paddingHorizontal: 22, paddingTop: 70, paddingBottom: 14, borderRadius: 30, backgroundColor: '#fff', shadowColor: '#253858', shadowOpacity: .14, shadowRadius: 25, shadowOffset: { width: 0, height: 14 }, elevation: 9 },
  label: { color: '#172033', fontSize: 12, fontWeight: '800', marginBottom: 8, marginTop: 15 }, field: { height: 51, flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 17, borderRadius: radius.md, borderWidth: 1, borderColor: '#D8DEE8', backgroundColor: '#fff' }, at: { color: '#66758C', fontSize: 18, fontWeight: '700' }, input: { flex: 1, height: '100%', color: '#172033', fontSize: 13 }, forgot: { alignSelf: 'flex-end', paddingVertical: 9 }, link: { color: '#1161F4', fontSize: 12, fontWeight: '800' },
  errorBox: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, padding: 10, borderRadius: radius.sm, backgroundColor: '#FFF0F5' }, error: { flex: 1, color: '#C52C64', fontSize: 12, lineHeight: 17 }, submit: { marginTop: 10 },
  divider: { flexDirection: 'row', alignItems: 'center', gap: 10, marginVertical: 20 }, line: { flex: 1, height: 1, backgroundColor: '#E2E6ED' }, or: { color: '#66758C', fontSize: 10, fontWeight: '500' },
  socialGrid: { gap: 11 }, social: { width: '100%', height: 49, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 9, borderRadius: radius.md, borderWidth: 1, borderColor: '#D8DEE8', backgroundColor: '#fff' }, socialPrimary: { borderWidth: 2, borderColor: '#86C0ED' }, socialPressed: { opacity: .7 }, socialText: { color: '#182235', fontSize: 13, fontWeight: '800' },
  footer: { color: '#1161F4', textAlign: 'center', fontSize: 12, marginTop: 20 },
});
