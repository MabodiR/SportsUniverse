import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Alert, FlatList, Image, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../src/api/client';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { getAuthToken, useAuthStore } from '../../src/stores/auth';
import { colors, radius, spacing } from '../../src/theme';
import type { ApiResponse, Conversation, CursorResponse, Message } from '../../src/types/api';
import { absoluteMediaUrl } from '../../src/utils/url';
import * as ImagePicker from 'expo-image-picker';
import * as DocumentPicker from 'expo-document-picker';
import { useReverbChannel } from '../../src/hooks/useReverbChannel';
import type { MediaUpload } from '../../src/types/api';

export default function ConversationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const me = useAuthStore(state => state.user);
  const client = useQueryClient();
  const [draft, setDraft] = useState('');
  const [typing, setTyping] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [token, setToken] = useState<string>();
  const typingTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  useEffect(() => { getAuthToken().then(value => setToken(value ?? undefined)); return () => { if (typingTimer.current) clearTimeout(typingTimer.current); }; }, []);
  const conversation = useQuery({ queryKey: ['conversation', id], enabled: Boolean(id), queryFn: async () => (await api.get<ApiResponse<Conversation>>(`/conversations/${encodeURIComponent(id!)}`)).data.data });
  const messages = useInfiniteQuery({
    queryKey: ['conversation', id, 'messages'], enabled: Boolean(id), initialPageParam: null as string | null,
    queryFn: async ({ pageParam }) => (await api.get<CursorResponse<Message>>(`/conversations/${encodeURIComponent(id!)}/messages`, { params: { cursor: pageParam || undefined } })).data,
    getNextPageParam: page => page.meta.next_cursor || undefined,
    refetchInterval: 5000,
  });
  const send = useMutation({
    mutationFn: async (payload: { body?: string; media_id?: string }) => (await api.post<ApiResponse<Message>>(`/conversations/${id}/messages`, payload)).data.data,
    onSuccess: message => { setDraft(''); client.setQueryData(['conversation', id, 'messages'], (old: any) => old ? { ...old, pages: [{ ...old.pages[0], data: [message, ...old.pages[0].data] }, ...old.pages.slice(1)] } : old); client.invalidateQueries({ queryKey: ['conversations'] }); },
    onError: error => Alert.alert('Message not sent', errorMessage(error)),
  });
  useEffect(() => { if (!id) return; api.post(`/conversations/${id}/read`).then(() => client.invalidateQueries({ queryKey: ['conversations'] })).catch(() => undefined); }, [id, messages.dataUpdatedAt, client]);
  const items = useMemo(() => { const seen = new Set<string>(); return (messages.data?.pages.flatMap(page => page.data) ?? []).filter(item => !seen.has(item.id) && Boolean(seen.add(item.id))); }, [messages.data]);
  const other = conversation.data?.participants.find(person => person.id !== me?.id) ?? conversation.data?.participants[0];
  const submit = () => { const body = draft.trim(); if (body && !send.isPending) send.mutate({ body }); };
  const onRealtime = useCallback((activity: Record<string, any>) => { if (activity.event === 'message.sent') messages.refetch(); if (activity.event === 'typing.updated' && Number(activity.user_id) !== me?.id) setTyping(Boolean(activity.typing)); if (activity.event === 'conversation.read' && Number(activity.user_id) !== me?.id) messages.refetch(); }, [me?.id, messages]);
  useReverbChannel(id ? `private-conversations.${id}` : '', onRealtime, ['message.sent', 'typing.updated', 'conversation.read']);
  const changeDraft = (value: string) => { setDraft(value); if (!typingTimer.current) api.post(`/conversations/${id}/typing`, { typing: true }).catch(() => undefined); if (typingTimer.current) clearTimeout(typingTimer.current); typingTimer.current = setTimeout(() => { typingTimer.current = undefined; api.post(`/conversations/${id}/typing`, { typing: false }).catch(() => undefined); }, 1200); };
  const options = () => Alert.alert('Conversation settings', undefined, [{ text: conversation.data!.muted ? 'Unmute notifications' : 'Mute notifications', onPress: async () => { await api.post(`/conversations/${id}/mute`, { muted: !conversation.data!.muted }); client.invalidateQueries({ queryKey: ['conversation', id] }); client.invalidateQueries({ queryKey: ['conversations'] }); } }, { text: 'Archive conversation', onPress: async () => { await api.post(`/conversations/${id}/archive`); client.invalidateQueries({ queryKey: ['conversations'] }); router.back(); } }, { text: 'Cancel', style: 'cancel' }]);
  const attach = () => Alert.alert('Send attachment', 'Choose what you want to send.', [{ text: 'Photo', onPress: () => pickMedia('image') }, { text: 'Video', onPress: () => pickMedia('video') }, { text: 'Document', onPress: pickDocument }, { text: 'Cancel', style: 'cancel' }]);
  const upload = async (asset: { uri: string; name: string; type: string }, kind: 'image' | 'video' | 'document') => { setUploading(true); try { const form = new FormData(); form.append('kind', kind); form.append('collection', 'uploads'); form.append('file', { uri: asset.uri, name: asset.name, type: asset.type } as any); let media = (await api.post<ApiResponse<MediaUpload>>('/media', form, { timeout: 600000 })).data.data; for (let attempt = 0; attempt < 30 && media.processing_status !== 'ready'; attempt++) { await new Promise(resolve => setTimeout(resolve, 1500)); media = (await api.get<ApiResponse<MediaUpload>>('/media/' + media.id)).data.data; if (media.processing_status === 'failed') throw new Error(media.processing_error || 'Attachment processing failed.'); } if (media.processing_status !== 'ready') throw new Error('Attachment is still processing.'); send.mutate({ media_id: media.id }); } catch (error) { Alert.alert('Attachment not sent', errorMessage(error)); } finally { setUploading(false); } };
  const pickMedia = async (kind: 'image' | 'video') => { const permission = await ImagePicker.requestMediaLibraryPermissionsAsync(); if (!permission.granted) return Alert.alert('Photo access required', 'Allow photo-library access to choose an attachment.'); const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: kind === 'image' ? ['images'] : ['videos'], allowsMultipleSelection: false, quality: 1 }); if (!result.canceled) { const asset = result.assets[0]; upload({ uri: asset.uri, name: asset.fileName || `${kind}-${Date.now()}.${kind === 'video' ? 'mp4' : 'jpg'}`, type: asset.mimeType || (kind === 'video' ? 'video/mp4' : 'image/jpeg') }, kind); } };
  const pickDocument = async () => { const result = await DocumentPicker.getDocumentAsync({ type: ['application/pdf', 'image/jpeg', 'image/png'], copyToCacheDirectory: true }); if (!result.canceled) { const asset = result.assets[0]; upload({ uri: asset.uri, name: asset.name, type: asset.mimeType || 'application/pdf' }, 'document'); } };

  if (conversation.isLoading || messages.isLoading) return <SafeAreaView style={styles.safe}><ThreadHeader /><ActivityIndicator style={{ flex: 1 }} color={colors.blue} /></SafeAreaView>;
  if (conversation.isError || messages.isError || !conversation.data) return <SafeAreaView style={styles.safe}><ThreadHeader /><ScreenMessage icon="alert-circle-outline" title="Conversation unavailable" message="It may have been archived or you may no longer have access." /></SafeAreaView>;

  return <SafeAreaView edges={['top']} style={styles.safe}><KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={0}>
    <ThreadHeader conversationId={id} userId={other?.id} name={other?.name} slug={other?.slug} muted={conversation.data.muted} onOptions={options} />
    <FlatList
      inverted
      data={items}
      keyExtractor={item => item.id}
      contentContainerStyle={styles.thread}
      renderItem={({ item }) => <MessageBubble message={item} mine={item.sender?.id === me?.id} token={token} />}
      onEndReached={() => { if (messages.hasNextPage && !messages.isFetchingNextPage) messages.fetchNextPage(); }}
      onEndReachedThreshold={0.3}
      ListFooterComponent={messages.isFetchingNextPage ? <ActivityIndicator style={{ marginVertical: 18 }} color={colors.blue} /> : null}
      ListEmptyComponent={<View style={styles.empty}><Ionicons name="chatbubble-ellipses-outline" size={36} color={colors.blue} /><Text style={styles.emptyTitle}>Start the conversation</Text><Text style={styles.emptyText}>Send a message to {other?.name ?? 'this member'}.</Text></View>}
    />
    {typing ? <Text style={{ color: '#79A3FF', fontSize: 11, fontWeight: '700', paddingHorizontal: 18, paddingVertical: 5, backgroundColor: '#091725' }}>{other?.name ?? 'Member'} is typing…</Text> : null}<View style={styles.composer}><Pressable accessibilityLabel="Add attachment" disabled={uploading} onPress={attach} style={{ width: 38, height: 46, alignItems: 'center', justifyContent: 'center' }}>{uploading ? <ActivityIndicator size="small" color="#79A3FF" /> : <Ionicons name="add" size={24} color="#79A3FF" />}</Pressable><TextInput accessibilityLabel="Message" multiline maxLength={5000} value={draft} onChangeText={changeDraft} placeholder="Type a message…" placeholderTextColor="#71849B" style={styles.input} /><Pressable accessibilityLabel="Send message" disabled={!draft.trim() || send.isPending} onPress={submit} style={[styles.send, (!draft.trim() || send.isPending) && styles.sendDisabled]}>{send.isPending ? <ActivityIndicator color="#fff" /> : <Ionicons name="send" size={20} color="#fff" />}</Pressable></View>
  </KeyboardAvoidingView></SafeAreaView>;
}

function ThreadHeader({ conversationId, userId, name, slug, muted, onOptions }: { conversationId?: string; userId?: number; name?: string; slug?: string | null; muted?: boolean; onOptions?: () => void }) { const block = () => userId && Alert.alert(`Block ${name}?`, 'They will no longer be able to send you new message requests.', [{ text: 'Cancel', style: 'cancel' }, { text: 'Block', style: 'destructive', onPress: async () => { try { await api.post('/profiles/' + userId + '/block'); router.back(); } catch (error) { Alert.alert('User not blocked', errorMessage(error)); } } }]); return <View style={styles.header}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Pressable disabled={!slug} onPress={() => slug && router.push(`/profile/${slug}` as never)} style={styles.headerPerson}><Text numberOfLines={1} style={styles.headerName}>{name ?? 'Conversation'}</Text><Text style={styles.headerStatus}>{muted ? 'Muted' : 'Conversation'}</Text></Pressable>{conversationId ? <View style={{ flexDirection: 'row', alignItems: 'center', gap: 14 }}><Pressable accessibilityLabel="Conversation settings" hitSlop={9} onPress={onOptions}><Ionicons name="ellipsis-horizontal" size={21} color={colors.muted} /></Pressable><Pressable accessibilityLabel="Report conversation" hitSlop={9} onPress={() => router.push({ pathname: '/report', params: { type: 'conversation', id: conversationId, label: 'this conversation' } })}><Ionicons name="flag-outline" size={20} color={colors.muted} /></Pressable><Pressable accessibilityLabel="Block user" hitSlop={9} onPress={block}><Ionicons name="person-remove-outline" size={21} color={colors.danger} /></Pressable></View> : <Ionicons name={muted ? 'notifications-off-outline' : 'shield-checkmark-outline'} size={21} color={colors.muted} />}</View>; }

function MessageBubble({ message, mine, token }: { message: Message; mine: boolean; token?: string }) {
  const image = message.media?.kind === 'image' ? absoluteMediaUrl(message.media.download_url) : undefined;
  return <View style={[styles.messageRow, mine && styles.messageRowMine]}><View><View style={[styles.bubble, mine && styles.bubbleMine]}>{image ? <Image source={{ uri: image, headers: token ? { Authorization: 'Bearer ' + token } : undefined }} style={styles.messageImage} /> : null}{message.media && !image ? <View style={styles.attachment}><Ionicons name={message.media.kind === 'video' ? 'videocam-outline' : 'document-attach-outline'} size={20} color="#79A3FF" /><Text style={styles.attachmentText}>{message.media.kind === 'video' ? 'Video attachment' : 'Attachment'}</Text></View> : null}<Text style={[styles.body, mine && styles.bodyMine]}>{message.deleted_at ? 'This message was deleted' : message.body}</Text><View style={styles.messageMeta}><Text style={[styles.time, mine && styles.timeMine]}>{messageTime(message.created_at)}</Text>{mine ? <Ionicons name={message.read_at ? 'checkmark-done' : 'checkmark'} size={14} color={message.read_at ? '#AFC8FF' : 'rgba(255,255,255,.65)'} /> : null}</View></View>{!mine && !message.deleted_at ? <Pressable accessibilityLabel="Report message" onPress={() => router.push({ pathname: '/report', params: { type: 'message', id: message.id, label: 'this message' } })} style={{ alignSelf: 'flex-start', paddingVertical: 5, paddingHorizontal: 4 }}><Text style={{ color: colors.muted, fontSize: 9, fontWeight: '700' }}>Report</Text></Pressable> : null}</View></View>;
}

function messageTime(value: string) { return new Date(value).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }); }
function errorMessage(error: any) { return error?.response?.data?.message || Object.values(error?.response?.data?.errors || {}).flat()[0] as string || 'Please check your connection and try again.'; }

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, header: { minHeight: 58, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', gap: 14, borderBottomWidth: 1, borderBottomColor: colors.line, backgroundColor: '#091725' }, headerPerson: { flex: 1, minWidth: 0 }, headerName: { color: colors.white, fontSize: 15, fontWeight: '900' }, headerStatus: { color: colors.muted, fontSize: 10, marginTop: 2 }, thread: { flexGrow: 1, padding: spacing.md }, messageRow: { flexDirection: 'row', justifyContent: 'flex-start', marginVertical: 4 }, messageRowMine: { justifyContent: 'flex-end' }, bubble: { maxWidth: '82%', paddingHorizontal: 13, paddingVertical: 9, borderRadius: 18, borderBottomLeftRadius: 5, backgroundColor: colors.surfaceRaised }, bubbleMine: { borderBottomLeftRadius: 18, borderBottomRightRadius: 5, backgroundColor: colors.blue }, body: { color: '#E5EDF7', fontSize: 14, lineHeight: 20 }, bodyMine: { color: '#fff' }, messageMeta: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 3, marginTop: 4 }, time: { color: colors.muted, fontSize: 9 }, timeMine: { color: 'rgba(255,255,255,.7)' }, messageImage: { width: 220, height: 180, borderRadius: 12, marginBottom: 7, backgroundColor: colors.navyLight }, attachment: { flexDirection: 'row', alignItems: 'center', gap: 8, minWidth: 190, padding: 10, borderRadius: radius.sm, backgroundColor: 'rgba(0,0,0,.16)', marginBottom: 6 }, attachmentText: { color: '#D9E5F5', fontSize: 12, fontWeight: '800' }, composer: { flexDirection: 'row', alignItems: 'flex-end', gap: 9, padding: 10, borderTopWidth: 1, borderTopColor: colors.line, backgroundColor: '#091725' }, input: { flex: 1, maxHeight: 120, minHeight: 46, paddingHorizontal: 15, paddingTop: Platform.OS === 'ios' ? 13 : 11, paddingBottom: 10, borderWidth: 1, borderColor: colors.line, borderRadius: 23, color: colors.white, backgroundColor: colors.surface }, send: { width: 46, height: 46, borderRadius: 23, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, sendDisabled: { opacity: .42 }, empty: { flex: 1, minHeight: 350, alignItems: 'center', justifyContent: 'center' }, emptyTitle: { color: colors.white, fontSize: 18, fontWeight: '900', marginTop: 12 }, emptyText: { color: colors.muted, fontSize: 13, marginTop: 5 },
});
