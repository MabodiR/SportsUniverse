import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { api } from '../../src/api/client';
import { BrandMark } from '../../src/components/BrandMark';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { colors, radius, spacing } from '../../src/theme';

export default function ForgotPasswordScreen() {
  const [email, setEmail] = useState(''); const [busy, setBusy] = useState(false);
  const submit = async () => { if (!email.trim()) return; setBusy(true); try { const response = await api.post('/auth/forgot-password', { email: email.trim() }); Alert.alert('Check your email', response.data.message, [{ text: 'Back to sign in', onPress: () => router.back() }]); } catch (error: any) { Alert.alert('Unable to send reset link', error?.response?.data?.message || 'Enter a valid email address and try again.'); } finally { setBusy(false); } };
  return <SafeAreaView style={styles.safe}><KeyboardAvoidingView style={styles.page} behavior={Platform.OS === 'ios' ? 'padding' : undefined}><View style={styles.top}><Pressable onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color="#fff" /></Pressable><BrandMark /></View><View style={styles.content}><View style={styles.icon}><Ionicons name="key-outline" size={30} color="#79A3FF" /></View><Text style={styles.title}>Reset your password.</Text><Text style={styles.copy}>Enter your email address and we’ll send instructions if it belongs to an account.</Text><Text style={styles.label}>Email address</Text><TextInput autoCapitalize="none" keyboardType="email-address" value={email} onChangeText={setEmail} placeholder="name@example.com" placeholderTextColor="#6F8298" style={styles.input} /><PrimaryButton label="Send reset link" loading={busy} onPress={submit} style={{ marginTop: 18 }} /></View></KeyboardAvoidingView></SafeAreaView>;
}
const styles = StyleSheet.create({ safe: { flex: 1, backgroundColor: colors.navy }, page: { flex: 1, padding: spacing.lg }, top: { flexDirection: 'row', alignItems: 'center', gap: 20 }, content: { flex: 1, justifyContent: 'center' }, icon: { width: 62, height: 62, borderRadius: 31, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(27,99,243,.14)' }, title: { color: colors.white, fontSize: 34, lineHeight: 38, fontWeight: '900', letterSpacing: -1.2, marginTop: 18 }, copy: { color: colors.muted, fontSize: 14, lineHeight: 21, marginTop: 10, marginBottom: 22 }, label: { color: '#DCE7F5', fontSize: 12, fontWeight: '800', marginBottom: 7 }, input: { height: 52, paddingHorizontal: 16, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface, color: colors.white } });
