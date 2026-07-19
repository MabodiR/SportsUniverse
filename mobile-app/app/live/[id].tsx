import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, FlatList, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { RTCPeerConnection, RTCIceCandidate, RTCSessionDescription, RTCView, MediaStream } from 'react-native-webrtc';
import { api } from '../../src/api/client';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { useReverbChannel } from '../../src/hooks/useReverbChannel';
import { useAuthStore } from '../../src/stores/auth';
import { colors, radius, spacing } from '../../src/theme';
import type { LiveMessage, LiveRoomResponse } from '../../src/types/api';
import { shareLink } from '../../src/utils/share';

const iceServers = [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }];
const reactionEmoji = { heart: '❤️', fire: '🔥', clap: '👏', football: '⚽' } as const;

export default function LiveRoomScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const me = useAuthStore(state => state.user);
  const peer = useRef<RTCPeerConnection | null>(null);
  const joined = useRef(false);
  const [remoteStream, setRemoteStream] = useState<MediaStream | null>(null);
  const [messages, setMessages] = useState<LiveMessage[]>([]);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [ended, setEnded] = useState(false);
  const [reaction, setReaction] = useState<string>();
  const room = useQuery({ queryKey: ['live', id], enabled: Boolean(id), queryFn: async () => (await api.get<LiveRoomResponse>(`/live/${encodeURIComponent(id!)}`)).data.data, refetchInterval: ended ? false : 12000 });

  useEffect(() => { if (room.data?.messages) setMessages(current => current.length ? current : room.data!.messages); }, [room.data?.messages]);
  const signal = useCallback(async (target: number, kind: 'answer' | 'ice', payload: Record<string, any>) => { await api.post(`/live/${id}/signal`, { target_id: target, kind, payload }); }, [id]);
  const onActivity = useCallback(async (activity: Record<string, any>) => {
    if (activity.type === 'message') setMessages(current => current.some(item => item.id === activity.id) ? current : [...current, activity as LiveMessage]);
    if (activity.type === 'reaction') { setReaction(reactionEmoji[activity.reaction as keyof typeof reactionEmoji]); setTimeout(() => setReaction(undefined), 900); }
    if (activity.type === 'ended') { setEnded(true); peer.current?.close(); setRemoteStream(null); }
    if (activity.type !== 'signal' || Number(activity.target_id) !== me?.id) return;
    if (activity.kind === 'ice') {
      if (peer.current) await peer.current.addIceCandidate(new RTCIceCandidate(activity.payload)).catch(() => undefined);
      return;
    }
    if (activity.kind !== 'offer') return;
    try {
      peer.current?.close();
      const connection = new RTCPeerConnection({ iceServers });
      peer.current = connection;
      (connection as any).addEventListener('track', (event: any) => { if (event.streams[0]) setRemoteStream(event.streams[0]); });
      (connection as any).addEventListener('icecandidate', (event: any) => { if (event.candidate) signal(Number(activity.sender_id), 'ice', event.candidate.toJSON()).catch(() => undefined); });
      await connection.setRemoteDescription(new RTCSessionDescription(activity.payload));
      const answer = await connection.createAnswer();
      await connection.setLocalDescription(answer);
      await signal(Number(activity.sender_id), 'answer', answer.toJSON());
    } catch { Alert.alert('Live video connection failed', 'Return to the live list and try joining again.'); }
  }, [me?.id, signal]);
  const reverb = useReverbChannel(id ? `live.${id}` : '', onActivity);

  useEffect(() => { if (!reverb.connected || joined.current || !id) return; joined.current = true; api.post(`/live/${id}/join`).then(response => room.refetch()).catch(error => { joined.current = false; Alert.alert('Unable to join live', errorMessage(error)); }); }, [reverb.connected, id]);
  useEffect(() => () => { peer.current?.close(); }, []);

  const sendMessage = async () => { const body = draft.trim(); if (!body || sending) return; setSending(true); try { const response = await api.post(`/live/${id}/messages`, { body }); setMessages(current => current.some(item => item.id === response.data.data.id) ? current : [...current, response.data.data]); setDraft(''); } catch (error) { Alert.alert('Message not sent', errorMessage(error)); } finally { setSending(false); } };
  const sendReaction = async (value: keyof typeof reactionEmoji) => { setReaction(reactionEmoji[value]); setTimeout(() => setReaction(undefined), 900); try { await api.post(`/live/${id}/messages`, { reaction: value }); } catch { /* A reaction can fail silently. */ } };

  if (room.isLoading) return <SafeAreaView style={styles.safe}><TopBar /><ActivityIndicator style={{ flex: 1 }} color={colors.blue} /></SafeAreaView>;
  if (room.isError || !room.data) return <SafeAreaView style={styles.safe}><TopBar /><ScreenMessage icon="radio-outline" title="Live unavailable" message="This broadcast may have ended or no longer exists." /></SafeAreaView>;
  const stream = room.data.stream;
  const isEnded = ended || stream.status === 'ended';

  return <SafeAreaView edges={['top']} style={styles.safe}><KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
    <TopBar viewers={stream.viewer_count} onShare={() => shareLink(stream.title, `${stream.host_name} is live on SportsUniverse`, `/live/${stream.id}`)} />
    <View style={styles.stage}>{remoteStream && !isEnded ? <RTCView streamURL={remoteStream.toURL()} style={styles.video} objectFit="cover" mirror={false} /> : <View style={styles.waiting}><Ionicons name={isEnded ? 'stop-circle-outline' : 'radio-outline'} size={52} color={isEnded ? colors.muted : colors.pink} /><Text style={styles.waitingTitle}>{isEnded ? 'This live has ended' : reverb.configured ? 'Connecting to live video…' : 'Live connection needs configuration'}</Text><Text style={styles.waitingText}>{!reverb.configured ? 'Add the Reverb values from .env.example and rebuild the development app.' : !isEnded ? 'Waiting for the host to connect your stream.' : 'Return to Live to find another broadcast.'}</Text></View>}{reaction ? <Text style={styles.floatingReaction}>{reaction}</Text> : null}<View style={styles.stageInfo}><View style={styles.liveBadge}><Text style={styles.liveText}>{isEnded ? 'ENDED' : 'LIVE'}</Text></View><Text style={styles.host}>{stream.host_name}</Text><Text style={styles.title}>{stream.title}</Text></View></View>
    <View style={styles.chatHeader}><Text style={styles.chatTitle}>Live chat</Text><Text style={styles.chatCount}>{messages.length} messages</Text></View>
    <FlatList data={messages} keyExtractor={item => String(item.id)} contentContainerStyle={styles.messages} renderItem={({ item }) => <View style={styles.message}>{item.reaction ? <Text style={styles.inlineReaction}>{reactionEmoji[item.reaction]}</Text> : <><Text style={styles.name}>{item.name}</Text><Text style={styles.body}>{item.body}</Text></>}</View>} ListEmptyComponent={<Text style={styles.empty}>Be the first to say something.</Text>} />
    {!isEnded ? <><View style={styles.reactions}>{(Object.keys(reactionEmoji) as (keyof typeof reactionEmoji)[]).map(item => <Pressable accessibilityLabel={`React with ${item}`} key={item} onPress={() => sendReaction(item)} style={styles.reaction}><Text style={styles.reactionText}>{reactionEmoji[item]}</Text></Pressable>)}</View><View style={styles.composer}><TextInput accessibilityLabel="Live chat message" value={draft} onChangeText={setDraft} maxLength={300} placeholder="Say something…" placeholderTextColor="#71849B" style={styles.input} /><Pressable accessibilityLabel="Send" disabled={!draft.trim() || sending} onPress={sendMessage} style={[styles.send, (!draft.trim() || sending) && { opacity: .4 }]}>{sending ? <ActivityIndicator color="#fff" /> : <Ionicons name="send" size={19} color="#fff" />}</Pressable></View></> : null}
  </KeyboardAvoidingView></SafeAreaView>;
}

function TopBar({ viewers, onShare }: { viewers?: number; onShare?: () => void }) { return <View style={styles.topBar}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Text style={styles.topTitle}>Live</Text><View style={styles.topActions}>{onShare ? <Pressable accessibilityLabel="Share live stream" hitSlop={10} onPress={onShare}><Ionicons name="share-social-outline" size={20} color={colors.white} /></Pressable> : null}<View style={styles.viewerCount}><Ionicons name="eye-outline" size={16} color={colors.muted} /><Text style={styles.viewerCountText}>{viewers ?? 0}</Text></View></View></View>; }
function errorMessage(error: any) { return error?.response?.data?.message || 'Please check your connection and try again.'; }

const styles = StyleSheet.create({ safe: { flex: 1, backgroundColor: colors.navy }, topBar: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line, backgroundColor: '#091725' }, topTitle: { color: colors.white, fontSize: 15, fontWeight: '900' }, topActions: { flexDirection: 'row', alignItems: 'center', gap: 16 }, viewerCount: { flexDirection: 'row', alignItems: 'center', gap: 5 }, viewerCountText: { color: colors.muted, fontSize: 11, fontWeight: '800' }, stage: { height: '48%', minHeight: 280, backgroundColor: '#02070D' }, video: { width: '100%', height: '100%' }, waiting: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.xl }, waitingTitle: { color: colors.white, fontSize: 17, fontWeight: '900', textAlign: 'center', marginTop: 13 }, waitingText: { color: colors.muted, fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 6 }, stageInfo: { position: 'absolute', left: 15, right: 15, bottom: 14 }, liveBadge: { alignSelf: 'flex-start', paddingHorizontal: 9, paddingVertical: 5, borderRadius: radius.sm, backgroundColor: colors.pink }, liveText: { color: '#fff', fontSize: 9, fontWeight: '900' }, host: { color: '#B5CAFF', fontSize: 11, fontWeight: '800', marginTop: 9 }, title: { color: '#fff', fontSize: 20, fontWeight: '900', marginTop: 2 }, floatingReaction: { position: 'absolute', alignSelf: 'center', top: '38%', fontSize: 64 }, chatHeader: { height: 44, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, chatTitle: { color: colors.white, fontSize: 13, fontWeight: '900' }, chatCount: { color: colors.muted, fontSize: 10 }, messages: { padding: spacing.md }, message: { flexDirection: 'row', alignItems: 'flex-start', gap: 7, marginBottom: 10 }, name: { color: '#79A3FF', fontSize: 12, fontWeight: '900' }, body: { flex: 1, color: '#D9E4F1', fontSize: 12, lineHeight: 17 }, inlineReaction: { fontSize: 23 }, empty: { color: colors.muted, fontSize: 12, textAlign: 'center', marginTop: 20 }, reactions: { height: 48, flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: spacing.md, borderTopWidth: 1, borderTopColor: colors.line }, reaction: { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surface }, reactionText: { fontSize: 19 }, composer: { flexDirection: 'row', alignItems: 'center', gap: 9, padding: 9, borderTopWidth: 1, borderTopColor: colors.line, backgroundColor: '#091725' }, input: { flex: 1, height: 44, paddingHorizontal: 15, borderRadius: 22, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface, color: colors.white }, send: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue } });
