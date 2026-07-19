import { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../src/api/client';
import { colors, radius, spacing } from '../../src/theme';
import type { ApiResponse, NotificationPreferences } from '../../src/types/api';
import { disablePushNotifications, enablePushNotifications, pushEnabled } from '../../src/notifications/push';

const options: { key: keyof NotificationPreferences; title: string; description: string; icon: keyof typeof Ionicons.glyphMap }[] = [
  { key: 'messages', title: 'Messages', description: 'New messages in accepted conversations.', icon: 'chatbubble-outline' },
  { key: 'message_requests', title: 'Message requests', description: 'Members asking to start a conversation.', icon: 'mail-outline' },
  { key: 'opportunities', title: 'Opportunities', description: 'Applications, invitations and status updates.', icon: 'briefcase-outline' },
  { key: 'followers', title: 'Followers', description: 'New members following your profile.', icon: 'person-add-outline' },
  { key: 'engagement', title: 'Post engagement', description: 'Likes, comments, shares and mentions.', icon: 'heart-outline' },
  { key: 'profile_views', title: 'Profile activity', description: 'Important activity on your public profile.', icon: 'eye-outline' },
  { key: 'moderation', title: 'Safety and moderation', description: 'Required account and content safety updates.', icon: 'shield-checkmark-outline' },
  { key: 'email_digest', title: 'Email digest', description: 'Periodic highlights delivered by email.', icon: 'newspaper-outline' },
];

export default function NotificationPreferencesScreen() {
  const client = useQueryClient(); const [saving, setSaving] = useState<string>(); const [push, setPush] = useState(false); const [pushBusy, setPushBusy] = useState(false);
  useEffect(() => { pushEnabled().then(setPush); }, []);
  const preferences = useQuery({ queryKey: ['notification-preferences'], queryFn: async () => (await api.get<ApiResponse<NotificationPreferences>>('/notification-preferences')).data.data });
  const update = useMutation({ mutationFn: ({ key, value }: { key: keyof NotificationPreferences; value: boolean }) => api.patch('/notification-preferences', { [key]: value }), onMutate: ({ key }) => setSaving(key), onSuccess: response => client.setQueryData(['notification-preferences'], response.data.data), onError: error => Alert.alert('Setting not saved', (error as any)?.response?.data?.message || 'Please try again.'), onSettled: () => setSaving(undefined) });
  const togglePush = async (value: boolean) => { setPushBusy(true); try { value ? await enablePushNotifications() : await disablePushNotifications(); setPush(value); } catch (error: any) { Alert.alert('Push notifications not updated', error?.message || 'Please try again.'); } finally { setPushBusy(false); } };
  return <SafeAreaView edges={['top']} style={styles.safe}><View style={styles.topBar}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Text style={styles.topTitle}>Notification settings</Text><View style={{ width: 24 }} /></View>{preferences.isLoading ? <ActivityIndicator style={{ flex: 1 }} color={colors.blue} /> : <ScrollView contentContainerStyle={styles.content}><Text style={styles.heading}>Choose what reaches you.</Text><Text style={styles.copy}>Safety and account-critical messages may still be delivered when necessary.</Text><View style={[styles.card, { marginBottom: 14 }]}><View style={styles.row}><View style={styles.icon}><Ionicons name="phone-portrait-outline" size={20} color="#79A3FF" /></View><View style={styles.rowCopy}><Text style={styles.title}>Push notifications</Text><Text style={styles.description}>Receive timely updates when the app is closed.</Text></View>{pushBusy ? <ActivityIndicator color={colors.blue} /> : <Switch value={push} onValueChange={togglePush} trackColor={{ false: colors.surfaceRaised, true: colors.blue }} />}</View></View><View style={styles.card}>{options.map((option, index) => <View key={option.key} style={[styles.row, index < options.length - 1 && styles.rowBorder]}><View style={styles.icon}><Ionicons name={option.icon} size={20} color="#79A3FF" /></View><View style={styles.rowCopy}><Text style={styles.title}>{option.title}</Text><Text style={styles.description}>{option.description}</Text></View>{saving === option.key ? <ActivityIndicator color={colors.blue} /> : <Switch value={preferences.data?.[option.key] ?? true} onValueChange={value => update.mutate({ key: option.key, value })} trackColor={{ false: colors.surfaceRaised, true: colors.blue }} />}</View>)}</View></ScrollView>}</SafeAreaView>;
}

const styles = StyleSheet.create({ safe: { flex: 1, backgroundColor: colors.navy }, topBar: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line, backgroundColor: '#091725' }, topTitle: { color: colors.white, fontSize: 15, fontWeight: '900' }, content: { padding: spacing.lg, paddingBottom: 40 }, heading: { color: colors.white, fontSize: 29, lineHeight: 33, fontWeight: '900', letterSpacing: -.9 }, copy: { color: colors.muted, fontSize: 13, lineHeight: 20, marginTop: 8, marginBottom: 22 }, card: { paddingHorizontal: spacing.md, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, row: { minHeight: 82, flexDirection: 'row', alignItems: 'center', gap: 11, paddingVertical: 12 }, rowBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line }, icon: { width: 39, height: 39, borderRadius: 20, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(71,111,234,.13)' }, rowCopy: { flex: 1 }, title: { color: colors.white, fontSize: 13, fontWeight: '900' }, description: { color: colors.muted, fontSize: 10, lineHeight: 15, marginTop: 3 } });
