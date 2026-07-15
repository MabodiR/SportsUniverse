import { useState } from 'react';
import { ActivityIndicator, Alert, FlatList, Image, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BrandMark } from '../../src/components/BrandMark';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { api } from '../../src/api/client';
import { useAuthStore } from '../../src/stores/auth';
import { colors, radius, spacing } from '../../src/theme';
import type { Conversation, MessageRequest, PaginatedResponse } from '../../src/types/api';
import { absoluteMediaUrl } from '../../src/utils/url';

export default function MessagesScreen() {
  const user = useAuthStore(state => state.user);
  const [tab, setTab] = useState<'messages' | 'requests' | 'archived'>('messages');
  const conversations = useQuery({ queryKey: ['conversations'], enabled: Boolean(user), queryFn: async () => (await api.get<PaginatedResponse<Conversation>>('/conversations')).data.data, refetchInterval: 10000 });
  const requests = useQuery({ queryKey: ['message-requests'], enabled: Boolean(user), queryFn: async () => (await api.get<PaginatedResponse<MessageRequest>>('/message-requests', { params: { status: 'pending' } })).data.data, refetchInterval: 15000 });
  const archived = useQuery({ queryKey: ['conversations', 'archived'], enabled: Boolean(user), queryFn: async () => (await api.get<PaginatedResponse<Conversation>>('/conversations', { params: { archived: 1 } })).data.data });

  if (!user) return <SafeAreaView style={styles.safe}><View style={styles.header}><BrandMark /></View><ScreenMessage icon="chatbubbles-outline" title="Your sports conversations" message="Sign in to message athletes, clubs, coaches and scouts." action={<PrimaryButton label="Sign in to message" onPress={() => router.push('/(auth)/login')} />} /></SafeAreaView>;
  const active = tab === 'messages' ? conversations : tab === 'requests' ? requests : archived;
  const items = tab === 'messages' ? conversations.data ?? [] : tab === 'requests' ? requests.data ?? [] : archived.data ?? [];

  return <SafeAreaView edges={['top']} style={styles.safe}>
    <View style={styles.header}><BrandMark /><Text style={styles.headerTitle}>Messages</Text></View>
    <View style={styles.tabs}><Pressable onPress={() => setTab('messages')} style={[styles.tab, tab === 'messages' && styles.tabActive]}><Text style={[styles.tabText, tab === 'messages' && styles.tabTextActive]}>Messages</Text></Pressable><Pressable onPress={() => setTab('requests')} style={[styles.tab, tab === 'requests' && styles.tabActive]}><Text style={[styles.tabText, tab === 'requests' && styles.tabTextActive]}>Requests</Text>{requests.data?.length ? <View style={styles.requestCount}><Text style={styles.requestCountText}>{requests.data.length}</Text></View> : null}</Pressable><Pressable onPress={() => setTab('archived')} style={[styles.tab, tab === 'archived' && styles.tabActive]}><Text style={[styles.tabText, tab === 'archived' && styles.tabTextActive]}>Archived</Text></Pressable></View>
    <FlatList
      data={items as any[]}
      keyExtractor={item => item.id}
      contentContainerStyle={styles.list}
      refreshControl={<RefreshControl refreshing={active.isRefetching} onRefresh={() => active.refetch()} tintColor={colors.blue} />}
      ItemSeparatorComponent={() => <View style={styles.separator} />}
      renderItem={({ item }) => tab === 'requests' ? <RequestRow request={item as MessageRequest} /> : <ConversationRow conversation={item as Conversation} myId={user.id} archived={tab === 'archived'} />}
      ListEmptyComponent={active.isLoading ? <ActivityIndicator style={styles.loader} color={colors.blue} /> : active.isError ? <ScreenMessage icon="cloud-offline-outline" title="Messages unavailable" message="Check your connection and try again." action={<PrimaryButton label="Try again" secondary onPress={() => active.refetch()} />} /> : <ScreenMessage icon={tab === 'messages' ? 'chatbubbles-outline' : tab === 'requests' ? 'mail-open-outline' : 'archive-outline'} title={tab === 'messages' ? 'No conversations yet' : tab === 'requests' ? 'No message requests' : 'No archived conversations'} message={tab === 'messages' ? 'Your accepted conversations will appear here.' : tab === 'requests' ? 'New requests from other members will appear here.' : 'Conversations you archive will appear here.'} />}
    />
  </SafeAreaView>;
}

function ConversationRow({ conversation, myId, archived }: { conversation: Conversation; myId: number; archived?: boolean }) {
  const client = useQueryClient();
  const restore = useMutation({ mutationFn: () => api.delete(`/conversations/${conversation.id}/archive`), onSuccess: () => { client.invalidateQueries({ queryKey: ['conversations'] }); }, onError: error => Alert.alert('Conversation not restored', errorMessage(error)) });
  const other = conversation.participants.find(person => person.id !== myId) ?? conversation.participants[0];
  const image = absoluteMediaUrl(other?.profile_image);
  const preview = conversation.last_message?.deleted_at ? 'Message deleted' : conversation.last_message?.body || (conversation.last_message?.media ? 'Sent an attachment' : 'Start the conversation');
  return <View><Pressable accessibilityRole="button" onPress={() => router.push(`/conversation/${conversation.id}` as never)} style={({ pressed }) => [styles.row, pressed && styles.pressed]}>
    <Avatar name={other?.name ?? 'Conversation'} image={image} />
    <View style={styles.rowCopy}><View style={styles.rowTop}><Text numberOfLines={1} style={styles.name}>{other?.name ?? 'Conversation'}</Text><Text style={styles.time}>{relativeTime(conversation.last_message_at)}</Text></View><View style={styles.previewRow}><Text numberOfLines={1} style={[styles.preview, conversation.unread_count > 0 && styles.previewUnread]}>{preview}</Text>{conversation.muted ? <Ionicons name="notifications-off" size={14} color={colors.muted} /> : null}{conversation.unread_count > 0 ? <View style={styles.unread}><Text style={styles.unreadText}>{Math.min(conversation.unread_count, 99)}</Text></View> : null}</View></View>
  </Pressable>{archived ? <Pressable disabled={restore.isPending} onPress={() => restore.mutate()} style={{ alignSelf: 'flex-end', marginTop: -12, marginBottom: 9, paddingHorizontal: 11, paddingVertical: 7, borderRadius: 8, backgroundColor: colors.surface }}><Text style={{ color: '#79A3FF', fontSize: 10, fontWeight: '800' }}>Restore</Text></Pressable> : null}</View>;
}

function RequestRow({ request }: { request: MessageRequest }) {
  const client = useQueryClient();
  const response = useMutation({ mutationFn: async (action: 'accept' | 'decline') => (await api.post(`/message-requests/${request.id}/${action}`)).data, onSuccess: (data, action) => { client.invalidateQueries({ queryKey: ['message-requests'] }); client.invalidateQueries({ queryKey: ['conversations'] }); if (action === 'accept' && data.data?.conversation_id) router.push(`/conversation/${data.data.conversation_id}` as never); }, onError: error => Alert.alert('Unable to respond', errorMessage(error)) });
  return <View style={styles.request}><Pressable onPress={() => request.sender.slug && router.push(`/profile/${request.sender.slug}` as never)} style={styles.requestPerson}><Avatar name={request.sender.name} /><View style={styles.rowCopy}><Text style={styles.name}>{request.sender.name}</Text><Text style={styles.requestLabel}>Message request</Text></View></Pressable><Text style={styles.requestMessage}>{request.message}</Text><View style={styles.actions}><PrimaryButton label="Accept" loading={response.isPending && response.variables === 'accept'} onPress={() => response.mutate('accept')} style={{ flex: 1 }} /><PrimaryButton label="Decline" secondary loading={response.isPending && response.variables === 'decline'} onPress={() => response.mutate('decline')} style={{ flex: 1 }} /></View></View>;
}

function Avatar({ name, image }: { name: string; image?: string }) { const initials = name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase(); return image ? <Image source={{ uri: image }} style={styles.avatar} /> : <View style={styles.avatarFallback}><Text style={styles.initials}>{initials}</Text></View>; }
function relativeTime(value?: string | null) { if (!value) return ''; const seconds = Math.max(1, Math.floor((Date.now() - new Date(value).getTime()) / 1000)); if (seconds < 60) return 'now'; if (seconds < 3600) return `${Math.floor(seconds / 60)}m`; if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`; if (seconds < 604800) return `${Math.floor(seconds / 86400)}d`; return new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short' }); }
function errorMessage(error: any) { return error?.response?.data?.message || Object.values(error?.response?.data?.errors || {}).flat()[0] as string || 'Please try again.'; }

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, header: { height: 58, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, headerTitle: { color: colors.white, fontSize: 15, fontWeight: '900' }, tabs: { flexDirection: 'row', margin: spacing.md, marginBottom: 4, padding: 4, borderRadius: radius.md, backgroundColor: colors.surface }, tab: { flex: 1, height: 40, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7, borderRadius: radius.sm }, tabActive: { backgroundColor: colors.blue }, tabText: { color: colors.muted, fontSize: 12, fontWeight: '900' }, tabTextActive: { color: '#fff' }, requestCount: { minWidth: 19, height: 19, paddingHorizontal: 5, borderRadius: 10, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.pink }, requestCountText: { color: '#fff', fontSize: 10, fontWeight: '900' }, list: { flexGrow: 1, paddingHorizontal: spacing.md, paddingTop: 10 }, separator: { height: 1, marginLeft: 72, backgroundColor: colors.line }, loader: { marginTop: 120 }, row: { minHeight: 80, flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 }, pressed: { opacity: .7 }, avatar: { width: 52, height: 52, borderRadius: 26, backgroundColor: colors.surfaceRaised }, avatarFallback: { width: 52, height: 52, borderRadius: 26, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, initials: { color: '#fff', fontSize: 15, fontWeight: '900' }, rowCopy: { flex: 1, minWidth: 0 }, rowTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }, name: { flexShrink: 1, color: colors.white, fontSize: 15, fontWeight: '900' }, time: { color: colors.muted, fontSize: 10 }, previewRow: { flexDirection: 'row', alignItems: 'center', gap: 7, marginTop: 5 }, preview: { flex: 1, color: colors.muted, fontSize: 13 }, previewUnread: { color: '#DDE8F5', fontWeight: '800' }, unread: { minWidth: 20, height: 20, paddingHorizontal: 5, borderRadius: 10, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, unreadText: { color: '#fff', fontSize: 10, fontWeight: '900' }, request: { marginBottom: 12, padding: spacing.md, borderWidth: 1, borderColor: colors.line, borderRadius: radius.lg, backgroundColor: colors.surface }, requestPerson: { flexDirection: 'row', alignItems: 'center', gap: 12 }, requestLabel: { color: '#79A3FF', fontSize: 11, fontWeight: '800', marginTop: 3 }, requestMessage: { color: '#D8E2EE', fontSize: 14, lineHeight: 21, marginTop: 15 }, actions: { flexDirection: 'row', gap: 10, marginTop: 16 },
});
