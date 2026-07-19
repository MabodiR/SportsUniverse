import { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { MediaStream, mediaDevices, RTCPeerConnection, RTCIceCandidate, RTCSessionDescription, RTCView } from 'react-native-webrtc';
import { api } from '../../src/api/client';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { useReverbChannel } from '../../src/hooks/useReverbChannel';
import { useAuthStore } from '../../src/stores/auth';
import { colors, radius, spacing } from '../../src/theme';

const iceServers = [{ urls: 'stun:stun.l.google.com:19302' }, { urls: 'stun:stun1.l.google.com:19302' }];

export default function CreateLiveScreen() {
  const me = useAuthStore(state => state.user);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [camera, setCamera] = useState<MediaStream | null>(null);
  const [streamId, setStreamId] = useState('');
  const [busy, setBusy] = useState(false);
  const [muted, setMuted] = useState(false);
  const [videoOff, setVideoOff] = useState(false);
  const [viewers, setViewers] = useState(0);
  const peers = useRef(new Map<number, RTCPeerConnection>());
  const cameraRef = useRef<MediaStream | null>(null);
  const streamIdRef = useRef('');

  const closePeer = useCallback((viewer: number) => { peers.current.get(viewer)?.close(); peers.current.delete(viewer); }, []);
  const signal = useCallback(async (viewer: number, kind: 'offer' | 'ice', payload: Record<string, any>) => { await api.post(`/live/${streamId}/signal`, { target_id: viewer, kind, payload }); }, [streamId]);
  const offer = useCallback(async (viewer: number) => {
    if (!camera || !streamId) return;
    closePeer(viewer);
    const connection = new RTCPeerConnection({ iceServers });
    peers.current.set(viewer, connection);
    camera.getTracks().forEach(track => connection.addTrack(track, camera));
    (connection as any).addEventListener('icecandidate', (event: any) => { if (event.candidate) signal(viewer, 'ice', event.candidate.toJSON()).catch(() => undefined); });
    (connection as any).addEventListener('connectionstatechange', () => { if (['failed', 'closed'].includes(connection.connectionState)) closePeer(viewer); });
    const localOffer = await connection.createOffer();
    await connection.setLocalDescription(localOffer);
    await signal(viewer, 'offer', localOffer.toJSON());
  }, [camera, closePeer, signal, streamId]);
  const onActivity = useCallback(async (activity: Record<string, any>) => {
    if (activity.type === 'viewer_join') { setViewers(Number(activity.count) || 0); await offer(Number(activity.viewer_id)).catch(() => undefined); return; }
    if (activity.type !== 'signal' || Number(activity.target_id) !== me?.id) return;
    const viewer = Number(activity.sender_id);
    const connection = peers.current.get(viewer);
    if (!connection) return;
    if (activity.kind === 'answer') await connection.setRemoteDescription(new RTCSessionDescription(activity.payload)).catch(() => closePeer(viewer));
    if (activity.kind === 'ice') await connection.addIceCandidate(new RTCIceCandidate(activity.payload)).catch(() => undefined);
  }, [closePeer, me?.id, offer]);
  const reverb = useReverbChannel(streamId ? `live.${streamId}` : '', onActivity);

  const enableCamera = async () => {
    try { const media = await mediaDevices.getUserMedia({ audio: true, video: { facingMode: 'user', frameRate: 30, width: 1280, height: 720 } }); cameraRef.current = media; setCamera(media); }
    catch { Alert.alert('Camera and microphone required', 'Allow camera and microphone access in your device settings to broadcast live.'); }
  };
  const start = async () => {
    if (!title.trim()) return Alert.alert('Add a title', 'Tell viewers what your broadcast is about.');
    if (!camera) return enableCamera();
    if (!reverb.configured) return Alert.alert('Live connection not configured', 'Add the Reverb values from .env.example and rebuild the development app.');
    setBusy(true);
    try { const response = await api.post('/live', { title: title.trim(), description: description.trim() || undefined }); streamIdRef.current = response.data.data.id; setStreamId(response.data.data.id); }
    catch (error) { Alert.alert('Unable to start live', errorMessage(error)); }
    finally { setBusy(false); }
  };
  const stopMedia = useCallback(() => { peers.current.forEach(peer => peer.close()); peers.current.clear(); cameraRef.current?.getTracks().forEach(track => track.stop()); cameraRef.current = null; }, []);
  const end = async () => { setBusy(true); try { await api.post(`/live/${streamId}/end`); } catch (error) { Alert.alert('Unable to end live', errorMessage(error)); return; } finally { setBusy(false); } streamIdRef.current = ''; stopMedia(); router.replace('/(tabs)/live'); };
  useEffect(() => () => { stopMedia(); if (streamIdRef.current) api.post(`/live/${streamIdRef.current}/end`).catch(() => undefined); }, [stopMedia]);
  const toggleAudio = () => { const next = !muted; camera?.getAudioTracks().forEach(track => { track.enabled = !next; }); setMuted(next); };
  const toggleVideo = () => { const next = !videoOff; camera?.getVideoTracks().forEach(track => { track.enabled = !next; }); setVideoOff(next); };
  const switchCamera = () => (camera?.getVideoTracks()[0] as any)?._switchCamera?.();

  if (!me) return <SafeAreaView style={styles.safe}><TopBar /><View style={styles.center}><Text style={styles.centerTitle}>Sign in to broadcast live.</Text><PrimaryButton label="Sign in" onPress={() => router.replace('/(auth)/login')} style={{ width: '100%', marginTop: 20 }} /></View></SafeAreaView>;
  if (streamId) return <SafeAreaView edges={['top']} style={styles.safe}><TopBar live viewers={viewers} /><View style={styles.liveStage}>{camera ? <RTCView streamURL={camera.toURL()} style={styles.video} objectFit="cover" mirror /> : null}<View style={styles.liveInfo}><Text style={styles.liveTitle}>{title}</Text><Text style={styles.connection}>{reverb.connected ? 'Broadcast connected' : 'Connecting broadcast…'}</Text></View></View><View style={styles.controls}><Control icon={muted ? 'mic-off' : 'mic'} label={muted ? 'Unmute' : 'Mute'} active={muted} onPress={toggleAudio} /><Control icon={videoOff ? 'videocam-off' : 'videocam'} label={videoOff ? 'Camera on' : 'Camera off'} active={videoOff} onPress={toggleVideo} /><Control icon="camera-reverse" label="Flip" onPress={switchCamera} /></View><View style={styles.footer}><Text style={styles.hint}>{viewers ? `${viewers} watching now` : 'Waiting for viewers'}</Text><PrimaryButton label="End live" loading={busy} onPress={() => Alert.alert('End live?', 'Your broadcast will stop for everyone.', [{ text: 'Keep streaming', style: 'cancel' }, { text: 'End live', style: 'destructive', onPress: end }])} style={{ backgroundColor: colors.danger }} /></View></SafeAreaView>;

  return <SafeAreaView edges={['top']} style={styles.safe}><KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}><TopBar /><ScrollView contentContainerStyle={styles.setup} keyboardShouldPersistTaps="handled"><Text style={styles.eyebrow}>BROADCAST</Text><Text style={styles.heading}>Go live from the field.</Text><Text style={styles.copy}>Share training, trials, matches and conversations with the SportsUniverse community.</Text><View style={styles.preview}>{camera ? <RTCView streamURL={camera.toURL()} style={styles.video} objectFit="cover" mirror /> : <Pressable onPress={enableCamera} style={styles.cameraPrompt}><Ionicons name="videocam-outline" size={42} color="#79A3FF" /><Text style={styles.cameraTitle}>Preview camera</Text><Text style={styles.cameraCopy}>Camera and microphone access is requested only when you tap here.</Text></Pressable>}</View>{camera ? <Pressable onPress={switchCamera} style={styles.flip}><Ionicons name="camera-reverse-outline" size={18} color={colors.white} /><Text style={styles.flipText}>Switch camera</Text></Pressable> : null}<Text style={styles.label}>Live title</Text><TextInput value={title} onChangeText={setTitle} maxLength={150} placeholder="What are you broadcasting?" placeholderTextColor="#71849B" style={styles.input} /><Text style={styles.label}>Description (optional)</Text><TextInput multiline value={description} onChangeText={setDescription} maxLength={1000} placeholder="Give viewers more context" placeholderTextColor="#71849B" style={[styles.input, styles.textarea]} textAlignVertical="top" /><PrimaryButton label={camera ? 'Start live broadcast' : 'Enable camera first'} loading={busy} onPress={start} style={{ marginTop: 22 }} /></ScrollView></KeyboardAvoidingView></SafeAreaView>;
}

function TopBar({ live, viewers }: { live?: boolean; viewers?: number }) { return <View style={styles.topBar}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Text style={styles.topTitle}>{live ? 'You are live' : 'Create live'}</Text>{live ? <View style={styles.viewerBadge}><Ionicons name="eye" size={14} color="#fff" /><Text style={styles.viewerText}>{viewers ?? 0}</Text></View> : <View style={{ width: 24 }} />}</View>; }
function Control({ icon, label, active, onPress }: { icon: keyof typeof Ionicons.glyphMap; label: string; active?: boolean; onPress: () => void }) { return <Pressable onPress={onPress} style={styles.control}><View style={[styles.controlIcon, active && styles.controlActive]}><Ionicons name={icon} size={23} color="#fff" /></View><Text style={styles.controlLabel}>{label}</Text></Pressable>; }
function errorMessage(error: any) { return error?.response?.data?.message || Object.values(error?.response?.data?.errors || {}).flat()[0] as string || 'Please check your connection and try again.'; }

const styles = StyleSheet.create({ safe: { flex: 1, backgroundColor: colors.navy }, topBar: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line, backgroundColor: '#091725' }, topTitle: { color: colors.white, fontSize: 15, fontWeight: '900' }, setup: { padding: spacing.lg, paddingBottom: 40 }, eyebrow: { color: '#79A3FF', fontSize: 11, fontWeight: '900', letterSpacing: 1 }, heading: { color: colors.white, fontSize: 31, lineHeight: 35, fontWeight: '900', letterSpacing: -1, marginTop: 8 }, copy: { color: colors.muted, fontSize: 14, lineHeight: 21, marginTop: 9 }, preview: { height: 260, overflow: 'hidden', marginTop: 24, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: '#02070D' }, video: { width: '100%', height: '100%' }, cameraPrompt: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.xl }, cameraTitle: { color: colors.white, fontSize: 16, fontWeight: '900', marginTop: 12 }, cameraCopy: { color: colors.muted, fontSize: 11, lineHeight: 17, textAlign: 'center', marginTop: 5 }, flip: { alignSelf: 'flex-end', flexDirection: 'row', alignItems: 'center', gap: 6, padding: 9, marginTop: 7 }, flipText: { color: colors.white, fontSize: 11, fontWeight: '800' }, label: { color: '#DCE7F5', fontSize: 12, fontWeight: '800', marginBottom: 7, marginTop: 15 }, input: { minHeight: 50, paddingHorizontal: 15, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface, color: colors.white }, textarea: { height: 110, paddingTop: 14 }, liveStage: { flex: 1, backgroundColor: '#02070D' }, liveInfo: { position: 'absolute', left: 16, right: 16, bottom: 18 }, liveTitle: { color: '#fff', fontSize: 22, fontWeight: '900' }, connection: { color: '#D5E0EE', fontSize: 11, marginTop: 4 }, viewerBadge: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 9, paddingVertical: 6, borderRadius: radius.sm, backgroundColor: colors.pink }, viewerText: { color: '#fff', fontSize: 10, fontWeight: '900' }, controls: { height: 100, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-evenly', borderTopWidth: 1, borderTopColor: colors.line }, control: { alignItems: 'center', gap: 6 }, controlIcon: { width: 50, height: 50, borderRadius: 25, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surfaceRaised }, controlActive: { backgroundColor: colors.danger }, controlLabel: { color: colors.muted, fontSize: 10, fontWeight: '800' }, footer: { padding: spacing.md, borderTopWidth: 1, borderTopColor: colors.line, backgroundColor: '#091725' }, hint: { color: colors.muted, fontSize: 11, textAlign: 'center', marginBottom: 10 }, center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.xl }, centerTitle: { color: colors.white, fontSize: 22, fontWeight: '900' } });
