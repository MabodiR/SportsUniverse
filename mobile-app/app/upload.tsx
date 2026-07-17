import { useState } from 'react';
import { Alert, Image, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Switch, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import { api } from '../src/api/client';
import { PrimaryButton } from '../src/components/PrimaryButton';
import { useAuthStore } from '../src/stores/auth';
import { colors, radius, spacing } from '../src/theme';
import type { ApiResponse, MediaUpload } from '../src/types/api';

type Visibility = 'public' | 'followers' | 'private';

export default function UploadScreen() {
  const user = useAuthStore(state => state.user);
  const [assets, setAssets] = useState<ImagePicker.ImagePickerAsset[]>([]);
  const [caption, setCaption] = useState('');
  const [location, setLocation] = useState('');
  const [visibility, setVisibility] = useState<Visibility>('public');
  const [comments, setComments] = useState(true);
  const [busy, setBusy] = useState(false);
  const [progress, setProgress] = useState(0);
  const [phase, setPhase] = useState('');

  const pick = async (kind: 'image' | 'video') => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) return Alert.alert('Photo access required', 'Allow photo-library access in device settings to choose media.');
    const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: kind === 'image' ? ['images'] : ['videos'], allowsMultipleSelection: kind === 'image', selectionLimit: kind === 'image' ? 10 : 1, quality: 1, videoMaxDuration: 300 });
    if (result.canceled) return;
    const invalid = result.assets.find(asset => (asset.fileSize ?? 0) > (kind === 'image' ? 10 * 1024 * 1024 : 500 * 1024 * 1024));
    if (invalid) return Alert.alert('File too large', kind === 'image' ? 'Each image must be 10 MB or smaller.' : 'Videos must be 500 MB or smaller.');
    setAssets(result.assets);
  };

  const publish = () => Alert.alert('Finish your post', 'Publish it now or keep it private as a draft?', [
    { text: 'Cancel', style: 'cancel' },
    { text: 'Save draft', onPress: () => submit(false) },
    { text: 'Publish now', onPress: () => submit(true) },
  ]);

  const submit = async (publishNow: boolean) => {
    if (!assets.length) return Alert.alert('Choose media', 'Select at least one photo or one video.');
    setBusy(true); setProgress(0); setPhase('Uploading media');
    try {
      const uploaded: MediaUpload[] = [];
      for (let index = 0; index < assets.length; index++) {
        const asset = assets[index];
        const kind = asset.type === 'video' ? 'video' : 'image';
        const form = new FormData();
        form.append('kind', kind); form.append('collection', kind === 'video' ? 'highlights' : 'gallery');
        form.append('file', { uri: asset.uri, name: asset.fileName || `${kind}-${Date.now()}.${kind === 'video' ? 'mp4' : 'jpg'}`, type: asset.mimeType || (kind === 'video' ? 'video/mp4' : 'image/jpeg') } as any);
        const response = await api.post<ApiResponse<MediaUpload>>('/media', form, { timeout: 600000, onUploadProgress: event => setProgress(Math.round(((index + event.loaded / Math.max(event.total ?? event.loaded, 1)) / assets.length) * 70)) });
        uploaded.push(response.data.data);
      }
      setPhase('Processing media'); setProgress(75);
      const ready = await waitUntilReady(uploaded, value => setProgress(75 + Math.round(value * 20)));
      setPhase(publishNow ? 'Publishing post' : 'Saving draft'); setProgress(96);
      const isVideo = ready[0].kind === 'video';
      const hashtags = [...caption.matchAll(/#([\p{L}\p{N}_]+)/gu)].map(match => match[1]);
      await api.post('/videos', { ...(isVideo ? { media_id: ready[0].id } : { image_media_ids: ready.map(item => item.id), cover_media_id: ready[0].id }), caption: caption.trim() || undefined, hashtags, location_name: location.trim() || undefined, comments_enabled: comments, visibility, publish: publishNow });
      setProgress(100); setPhase(publishNow ? 'Published' : 'Draft saved');
      Alert.alert(publishNow ? 'Post published' : 'Draft saved', publishNow ? 'Your post is now available in the feed.' : 'You can publish it later from My Posts.', [{ text: publishNow ? 'View feed' : 'View drafts', onPress: () => router.replace(publishNow ? '/(tabs)/feed' : '/profile/my-posts') }]);
    } catch (error) { Alert.alert(publishNow ? 'Post not published' : 'Draft not saved', errorMessage(error)); }
    finally { setBusy(false); }
  };

  if (!user) return <SafeAreaView style={styles.safe}><TopBar /><View style={styles.center}><Text style={styles.heading}>Sign in to upload.</Text><PrimaryButton label="Sign in" onPress={() => router.replace('/(auth)/login')} style={{ width: '100%', marginTop: 20 }} /></View></SafeAreaView>;
  return <SafeAreaView edges={['top']} style={styles.safe}><KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}><TopBar /><ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled"><Text style={styles.eyebrow}>NEW POST</Text><Text style={styles.heading}>Share your sporting moment.</Text><View style={styles.pickers}><Picker icon="images-outline" label="Photos" onPress={() => pick('image')} /><Picker icon="videocam-outline" label="Video" onPress={() => pick('video')} /></View>{assets.length ? <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.previews}>{assets.map(asset => asset.type === 'video' ? <View key={asset.assetId || asset.uri} style={styles.videoPreview}><Ionicons name="play-circle" size={42} color="#fff" /><Text style={styles.videoLabel}>Video selected</Text></View> : <Image key={asset.assetId || asset.uri} source={{ uri: asset.uri }} style={styles.preview} />)}</ScrollView> : null}<Text style={styles.label}>Caption</Text><TextInput multiline maxLength={2200} value={caption} onChangeText={setCaption} placeholder="Tell the story. Add #hashtags..." placeholderTextColor="#71849B" style={[styles.input, styles.caption]} textAlignVertical="top" /><Text style={styles.counter}>{caption.length}/2200</Text><Text style={styles.label}>Location (optional)</Text><TextInput maxLength={160} value={location} onChangeText={setLocation} placeholder="Stadium, city or training ground" placeholderTextColor="#71849B" style={styles.input} /><Text style={styles.label}>Who can see this?</Text><View style={styles.visibility}>{(['public', 'followers', 'private'] as Visibility[]).map(item => <Pressable key={item} onPress={() => setVisibility(item)} style={[styles.visibilityButton, visibility === item && styles.visibilityActive]}><Ionicons name={item === 'public' ? 'globe-outline' : item === 'followers' ? 'people-outline' : 'lock-closed-outline'} size={17} color={visibility === item ? '#fff' : colors.muted} /><Text style={[styles.visibilityText, visibility === item && { color: '#fff' }]}>{item}</Text></Pressable>)}</View><View style={styles.switchRow}><View><Text style={styles.switchTitle}>Allow comments</Text><Text style={styles.switchCopy}>Members can respond to your post.</Text></View><Switch value={comments} onValueChange={setComments} trackColor={{ false: colors.surfaceRaised, true: colors.blue }} /></View>{busy ? <View style={styles.progress}><View style={styles.progressTop}><Text style={styles.phase}>{phase}</Text><Text style={styles.percent}>{progress}%</Text></View><View style={styles.track}><View style={[styles.fill, { width: `${progress}%` }]} /></View></View> : null}<PrimaryButton label="Publish post" loading={busy} onPress={publish} style={{ marginTop: 22 }} /></ScrollView></KeyboardAvoidingView></SafeAreaView>;
}

async function waitUntilReady(items: MediaUpload[], update: (progress: number) => void) { let current = items; for (let attempt = 0; attempt < 30; attempt++) { current = await Promise.all(current.map(async item => item.processing_status === 'ready' ? item : (await api.get<ApiResponse<MediaUpload>>(`/media/${item.id}`)).data.data)); if (current.some(item => item.processing_status === 'failed')) throw new Error(current.find(item => item.processing_status === 'failed')?.processing_error || 'Media processing failed.'); if (current.every(item => item.processing_status === 'ready' && item.moderation_status === 'approved')) return current; update((attempt + 1) / 30); await new Promise(resolve => setTimeout(resolve, 2000)); } throw new Error('Media is still processing. Make sure the Laravel queue worker is running, then try again.'); }
function TopBar() { return <View style={styles.topBar}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Text style={styles.topTitle}>Upload</Text><View style={{ width: 24 }} /></View>; }
function Picker({ icon, label, onPress }: { icon: keyof typeof Ionicons.glyphMap; label: string; onPress: () => void }) { return <Pressable onPress={onPress} style={styles.picker}><Ionicons name={icon} size={25} color="#79A3FF" /><Text style={styles.pickerText}>{label}</Text></Pressable>; }
function errorMessage(error: any) { return error?.response?.data?.message || Object.values(error?.response?.data?.errors || {}).flat()[0] as string || error?.message || 'Please check your connection and try again.'; }

const styles = StyleSheet.create({ safe: { flex: 1, backgroundColor: colors.navy }, topBar: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line, backgroundColor: '#091725' }, topTitle: { color: colors.white, fontSize: 15, fontWeight: '900' }, content: { padding: spacing.lg, paddingBottom: 45 }, eyebrow: { color: '#79A3FF', fontSize: 11, fontWeight: '900', letterSpacing: 1 }, heading: { color: colors.white, fontSize: 30, lineHeight: 34, fontWeight: '900', letterSpacing: -1, marginTop: 8 }, pickers: { flexDirection: 'row', gap: 11, marginTop: 24 }, picker: { flex: 1, height: 86, alignItems: 'center', justifyContent: 'center', gap: 8, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, pickerText: { color: colors.white, fontSize: 13, fontWeight: '900' }, previews: { gap: 9, marginTop: 14 }, preview: { width: 105, height: 105, borderRadius: radius.md, backgroundColor: colors.surface }, videoPreview: { width: 180, height: 105, alignItems: 'center', justifyContent: 'center', borderRadius: radius.md, backgroundColor: '#02070D' }, videoLabel: { color: colors.white, fontSize: 10, fontWeight: '800', marginTop: 4 }, label: { color: '#DCE7F5', fontSize: 12, fontWeight: '800', marginBottom: 7, marginTop: 20 }, input: { minHeight: 50, paddingHorizontal: 15, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface, color: colors.white }, caption: { height: 130, paddingTop: 14 }, counter: { color: colors.muted, fontSize: 10, textAlign: 'right', marginTop: 5 }, visibility: { flexDirection: 'row', gap: 7 }, visibilityButton: { flex: 1, minHeight: 58, alignItems: 'center', justifyContent: 'center', gap: 5, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, visibilityActive: { borderColor: colors.blue, backgroundColor: colors.blue }, visibilityText: { color: colors.muted, fontSize: 10, fontWeight: '800', textTransform: 'capitalize' }, switchRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 24, padding: spacing.md, borderRadius: radius.md, backgroundColor: colors.surface }, switchTitle: { color: colors.white, fontSize: 13, fontWeight: '900' }, switchCopy: { color: colors.muted, fontSize: 10, marginTop: 3 }, progress: { marginTop: 20 }, progressTop: { flexDirection: 'row', justifyContent: 'space-between' }, phase: { color: colors.white, fontSize: 11, fontWeight: '800' }, percent: { color: '#79A3FF', fontSize: 11, fontWeight: '900' }, track: { height: 7, overflow: 'hidden', borderRadius: 4, backgroundColor: colors.surfaceRaised, marginTop: 8 }, fill: { height: '100%', backgroundColor: colors.blue }, center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.xl } });
