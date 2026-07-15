import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import * as WebBrowser from 'expo-web-browser';
import { BrandMark } from '../../src/components/BrandMark';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { api } from '../../src/api/client';
import { useAuthStore } from '../../src/stores/auth';
import { colors, radius, spacing } from '../../src/theme';

const providers = [
  { key: 'google', label: 'Google', icon: 'logo-google' },
  { key: 'apple', label: 'Apple', icon: 'logo-apple' },
  { key: 'facebook', label: 'Facebook', icon: 'logo-facebook' },
  { key: 'microsoft', label: 'Microsoft', icon: 'logo-windows' },
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

  return <SafeAreaView style={styles.safe}><KeyboardAvoidingView style={styles.page} behavior={Platform.OS === 'ios' ? 'padding' : undefined}><ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled"><View style={styles.top}><Pressable onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color="#fff" /></Pressable><BrandMark /></View><View style={styles.content}><Text style={styles.eyebrow}>WELCOME BACK</Text><Text style={styles.title}>Your sports world starts here.</Text><Text style={styles.copy}>Sign in to keep scrolling, follow athletes and unlock opportunities.</Text><View style={styles.socialGrid}>{providers.map(provider => <Pressable accessibilityRole="button" disabled={busy || Boolean(socialBusy)} key={provider.key} onPress={() => social(provider.key)} style={styles.social}><Ionicons name={provider.icon} size={20} color="#fff" /><Text style={styles.socialText}>{socialBusy === provider.key ? 'Opening…' : provider.label}</Text></Pressable>)}</View><View style={styles.divider}><View style={styles.line} /><Text style={styles.or}>OR USE PASSWORD</Text><View style={styles.line} /></View><Text style={styles.label}>Email address or phone</Text><TextInput value={login} onChangeText={value => { setLogin(value); clearError(); }} autoCapitalize="none" keyboardType="email-address" style={styles.input} placeholder="name@example.com" placeholderTextColor="#6F8298" /><Text style={styles.label}>Password</Text><View style={styles.passwordWrap}><TextInput value={password} onChangeText={value => { setPassword(value); clearError(); }} secureTextEntry={!showPassword} style={styles.passwordInput} placeholder="Enter your password" placeholderTextColor="#6F8298" /><Pressable accessibilityLabel={showPassword ? 'Hide password' : 'Show password'} hitSlop={10} onPress={() => setShowPassword(value => !value)}><Ionicons name={showPassword ? 'eye-off-outline' : 'eye-outline'} size={22} color={colors.muted} /></Pressable></View><Pressable onPress={() => router.push('/(auth)/forgot-password')} style={styles.forgot}><Text style={styles.link}>Forgot password?</Text></Pressable>{error ? <Text style={styles.error}>{error}</Text> : null}<PrimaryButton label="Sign in" loading={busy} onPress={submit} style={{ marginTop: 18 }} /><Text style={styles.footer}>New to SportUniverse? <Text style={styles.link} onPress={() => router.replace('/(auth)/register')}>Create an account</Text></Text></View></ScrollView></KeyboardAvoidingView></SafeAreaView>;
}

const styles = StyleSheet.create({ safe: { flex: 1, backgroundColor: colors.navy }, page: { flex: 1 }, scroll: { flexGrow: 1, padding: spacing.lg }, top: { flexDirection: 'row', alignItems: 'center', gap: 20 }, content: { flex: 1, justifyContent: 'center', paddingVertical: 30 }, eyebrow: { color: '#79A3FF', fontSize: 12, fontWeight: '900', letterSpacing: 1 }, title: { color: colors.white, fontSize: 36, lineHeight: 39, fontWeight: '900', letterSpacing: -1.5, marginTop: 10 }, copy: { color: colors.muted, fontSize: 14, lineHeight: 21, marginTop: 10, marginBottom: 23 }, socialGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 9 }, social: { width: '48%', height: 48, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, socialText: { color: colors.white, fontSize: 12, fontWeight: '800' }, divider: { flexDirection: 'row', alignItems: 'center', gap: 10, marginVertical: 22 }, line: { flex: 1, height: 1, backgroundColor: colors.line }, or: { color: colors.muted, fontSize: 9, fontWeight: '900' }, label: { color: '#DCE7F5', fontSize: 12, fontWeight: '800', marginBottom: 7, marginTop: 13 }, input: { height: 52, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: 'rgba(255,255,255,.055)', paddingHorizontal: 16, color: '#fff', fontSize: 15 }, passwordWrap: { height: 52, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: 'rgba(255,255,255,.055)' }, passwordInput: { flex: 1, height: '100%', color: '#fff', fontSize: 15 }, forgot: { alignSelf: 'flex-end', paddingVertical: 10 }, error: { color: '#FF8FBF', fontSize: 12, marginTop: 5 }, footer: { color: colors.muted, textAlign: 'center', marginTop: 22 }, link: { color: '#79A3FF', fontWeight: '800' } });
