import { useEffect, useMemo, useState } from 'react';
import { Alert, Image, Pressable, ScrollView, Share, StyleSheet, Text, useWindowDimensions, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { VideoView, useVideoPlayer } from 'expo-video';
import { router } from 'expo-router';
import { useQueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { getAuthToken } from '../stores/auth';
import { colors, radius } from '../theme';
import type { Video } from '../types/api';
import { absoluteMediaUrl } from '../utils/url';
import { webUrl } from '../utils/share';

const compact = (n: number) => Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(n || 0);

export function FeedCard({ video, height, onProtectedAction, index, active, authenticated }: { video: Video; height: number; onProtectedAction: () => void; index: number; active: boolean; authenticated: boolean }) {
  const { width } = useWindowDimensions();
  const queryClient = useQueryClient();
  const [token, setToken] = useState<string>();
  const [liked, setLiked] = useState(Boolean(video.viewer?.liked));
  const [saved, setSaved] = useState(Boolean(video.viewer?.saved));
  const [following, setFollowing] = useState(Boolean(video.viewer?.following_creator));
  const [counts, setCounts] = useState(video.counts);
  const [busy, setBusy] = useState<string>();
  const [mediaMenuOpen, setMediaMenuOpen] = useState(false);
  const [soundEnabled, setSoundEnabled] = useState(false);
  const [volume, setVolume] = useState(.75);
  const videoUrl = absoluteMediaUrl(video.media?.download_url);
  useEffect(() => { getAuthToken().then(value => setToken(value ?? undefined)); }, []);
  const source = useMemo(() => videoUrl ? { uri: videoUrl, headers: token ? { Authorization: 'Bearer ' + token } : undefined } : null, [videoUrl, token]);
  const player = useVideoPlayer(source, instance => { instance.loop = true; instance.volume = .75; instance.muted = true; });
  useEffect(() => { if (!source) return; active ? player.play() : player.pause(); }, [active, player, source]);
  useEffect(() => { player.volume = volume; player.muted = !soundEnabled || volume === 0; }, [player, soundEnabled, volume]);
  useEffect(() => {
    if (!active || !authenticated || video.id.startsWith('demo-')) return;
    const timer = setTimeout(() => api.post('/videos/' + video.id + '/views', { watched_ms: 1000, completed: false }).then(response => setCounts(current => ({ ...current, views: response.data.data.views_count }))).catch(() => undefined), 1000);
    return () => clearTimeout(timer);
  }, [active, authenticated, video.id]);

  const engage = async (kind: 'like' | 'save') => {
    if (!authenticated) return onProtectedAction();
    if (busy) return;
    setBusy(kind);
    try {
      const response = await api.post('/videos/' + video.id + '/' + kind);
      if (kind === 'like') { setLiked(response.data.data.liked); setCounts(current => ({ ...current, likes: response.data.data.likes_count })); }
      else { setSaved(response.data.data.saved); setCounts(current => ({ ...current, saves: response.data.data.saves_count })); queryClient.invalidateQueries({ queryKey: ['feed', 'saved'] }); }
    } catch (error) { Alert.alert('Action failed', errorMessage(error)); }
    finally { setBusy(undefined); }
  };
  const follow = async () => {
    if (!authenticated) return onProtectedAction();
    if (busy) return;
    setBusy('follow');
    try {
      const response = following ? await api.delete('/profiles/' + video.creator.id + '/follow') : await api.post('/profiles/' + video.creator.id + '/follow');
      setFollowing(response.data.data.following);
      queryClient.invalidateQueries({ queryKey: ['feed', 'following'] });
    } catch (error) { Alert.alert('Unable to follow', errorMessage(error)); }
    finally { setBusy(undefined); }
  };
  const share = async () => {
    if (!authenticated) return onProtectedAction();
    const url = webUrl('/feed#' + video.id);
    const result = await Share.share({ message: (video.caption ?? 'SportUniverse highlight') + '\n' + url, url });
    if (result.action === Share.sharedAction) api.post('/videos/' + video.id + '/share', { channel: 'other' }).then(response => setCounts(current => ({ ...current, shares: response.data.data.shares_count }))).catch(() => undefined);
  };
  const comments = () => {
    if (!authenticated) return onProtectedAction();
    router.push({ pathname: '/post/[id]/comments', params: { id: video.id } });
  };
  const toggleSound = () => {
    if (!soundEnabled && volume === 0) setVolume(.5);
    setSoundEnabled(current => !current);
  };
  const adjustVolume = (direction: -1 | 1) => {
    const next = Math.min(1, Math.max(0, Math.round((volume + direction * .1) * 10) / 10));
    setVolume(next);
    setSoundEnabled(next > 0);
  };
  const action = (icon: keyof typeof Ionicons.glyphMap, value: string, onPress: () => void, selected = false) => <Pressable accessibilityRole="button" style={styles.action} onPress={onPress}><View style={[styles.actionCircle, selected && styles.actionActive]}><Ionicons name={icon} size={23} color="#fff" /></View><Text style={styles.actionText}>{value}</Text></Pressable>;
  const glow = index % 3 === 0 ? '#245FA1' : index % 3 === 1 ? '#783A74' : '#176B58';
  const profileImage = absoluteMediaUrl(video.creator.profile_image);

  return <View style={[styles.card, { height }]}>
    {source ? <VideoView player={player} style={StyleSheet.absoluteFill} contentFit="cover" nativeControls={false} /> : video.images?.length ? <ScrollView horizontal pagingEnabled showsHorizontalScrollIndicator={false} style={StyleSheet.absoluteFill}>{video.images.map(image => <Image key={image.id} source={{ uri: absoluteMediaUrl(image.download_url), headers: token ? { Authorization: 'Bearer ' + token } : undefined }} style={{ width, height: '100%' }} resizeMode="cover" />)}</ScrollView> : <><LinearGradient colors={[glow, '#122239', '#04090F']} locations={[0, .55, 1]} style={StyleSheet.absoluteFill} /><View style={styles.person}><View style={styles.head} /><View style={styles.bodyShape} /></View></>}
    <LinearGradient colors={['transparent', 'rgba(2,7,13,.08)', 'rgba(2,7,13,.9)']} locations={[0, .52, 1]} style={StyleSheet.absoluteFill} pointerEvents="none" />
    {(source || (authenticated && !video.id.startsWith('demo-'))) ? <View style={styles.mediaMenuWrap}><Pressable accessibilityLabel="Post media controls" accessibilityState={{ expanded: mediaMenuOpen }} onPress={() => setMediaMenuOpen(current => !current)} style={styles.mediaMenuButton}><Ionicons name="ellipsis-horizontal" size={21} color="#fff" /></Pressable>{mediaMenuOpen ? <View style={styles.mediaMenu}>{source ? <><Pressable accessibilityRole="button" accessibilityLabel={soundEnabled ? 'Mute video' : 'Turn on video sound'} onPress={toggleSound} style={styles.mediaMenuRow}><Ionicons name={soundEnabled && volume > 0 ? 'volume-high-outline' : 'volume-mute-outline'} size={20} color="#fff" /><Text style={styles.mediaMenuText}>{soundEnabled && volume > 0 ? 'Sound on' : 'Sound off'}</Text></Pressable><View style={styles.volumeRow}><Ionicons name="volume-low-outline" size={18} color="#BFD0E1" /><Pressable accessibilityLabel="Reduce video volume" disabled={volume === 0} onPress={() => adjustVolume(-1)} style={styles.volumeButton}><Ionicons name="remove" size={18} color="#fff" /></Pressable><View style={styles.volumeTrack}><View style={[styles.volumeFill, { width: `${volume * 100}%` }]} /></View><Pressable accessibilityLabel="Increase video volume" disabled={volume === 1} onPress={() => adjustVolume(1)} style={styles.volumeButton}><Ionicons name="add" size={18} color="#fff" /></Pressable><Text style={styles.volumeText}>{Math.round(volume * 100)}%</Text></View></> : null}{authenticated && !video.id.startsWith('demo-') ? <Pressable accessibilityRole="button" onPress={() => { setMediaMenuOpen(false); router.push({ pathname: '/report', params: { type: 'video', id: video.id, label: 'this post' } }); }} style={styles.mediaMenuRow}><Ionicons name="flag-outline" size={19} color="#fff" /><Text style={styles.mediaMenuText}>Report post</Text></Pressable> : null}</View> : null}</View> : null}
    <View style={styles.badge}><Text style={styles.badgeText}>{video.type === 'images' ? 'PHOTO' : 'HIGHLIGHT'}</Text></View>
    <View style={styles.copy}><View style={styles.nameRow}><Pressable onPress={() => video.creator.slug && router.push(('/profile/' + video.creator.slug) as never)}>{profileImage ? <Image source={{ uri: profileImage, headers: token ? { Authorization: 'Bearer ' + token } : undefined }} style={styles.avatarImage} /> : <View style={styles.avatar}><Text style={styles.avatarText}>{video.creator.name[0]}</Text></View>}</Pressable><View style={{ flex: 1 }}><Text style={styles.name}>{video.creator.name}</Text><Text style={styles.meta}>{video.sport?.name || video.creator.sport || 'Sport'}{video.location?.name ? ' · ' + video.location.name : ''}</Text></View><Pressable style={[styles.follow, following && styles.following]} disabled={busy === 'follow'} onPress={follow}><Text style={styles.followText}>{following ? 'Following' : 'Follow'}</Text></Pressable></View>{video.caption ? <Text numberOfLines={3} style={styles.caption}>{video.caption}</Text> : null}<Text numberOfLines={2} style={styles.tags}>{video.hashtags.map(tag => '#' + tag).join('  ')}</Text></View>
    <View style={styles.rail}>{action(liked ? 'heart' : 'heart-outline', compact(counts.likes), () => engage('like'), liked)}{action('chatbubble-outline', compact(counts.comments), comments)}{action('paper-plane-outline', compact(counts.shares), share)}{action(saved ? 'bookmark' : 'bookmark-outline', compact(counts.saves), () => engage('save'), saved)}<View style={styles.views}><Ionicons name="eye-outline" size={17} color="#fff" /><Text style={styles.actionText}>{compact(counts.views)}</Text></View></View>
  </View>;
}

function errorMessage(error: any) { return error?.response?.data?.message || 'Please try again.'; }
const styles = StyleSheet.create({ card: { width: '100%', backgroundColor: '#07101B', overflow: 'hidden' }, person: { position: 'absolute', top: '18%', left: '21%', right: '22%', height: '53%', alignItems: 'center', opacity: .5 }, head: { width: 92, height: 92, borderRadius: 46, backgroundColor: '#7692AF' }, bodyShape: { width: '100%', height: '80%', marginTop: -8, borderTopLeftRadius: 90, borderTopRightRadius: 90, backgroundColor: '#243B52' }, badge: { position: 'absolute', top: 18, left: 16, paddingHorizontal: 10, paddingVertical: 6, borderRadius: radius.pill, backgroundColor: colors.pink }, badgeText: { color: '#fff', fontSize: 10, fontWeight: '900', letterSpacing: .5 }, mediaMenuWrap: { position: 'absolute', zIndex: 6, top: 15, right: 15, alignItems: 'flex-end' }, mediaMenuButton: { width: 38, height: 38, borderRadius: 19, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,.18)', backgroundColor: 'rgba(5,12,20,.72)' }, mediaMenu: { width: 220, marginTop: 7, padding: 8, borderWidth: 1, borderColor: 'rgba(255,255,255,.16)', borderRadius: 15, backgroundColor: 'rgba(5,12,20,.94)' }, mediaMenuRow: { minHeight: 42, paddingHorizontal: 9, flexDirection: 'row', alignItems: 'center', gap: 10, borderRadius: 10 }, mediaMenuText: { color: '#fff', fontSize: 12, fontWeight: '800' }, volumeRow: { minHeight: 44, paddingHorizontal: 7, flexDirection: 'row', alignItems: 'center', gap: 7 }, volumeButton: { width: 29, height: 29, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(255,255,255,.12)' }, volumeTrack: { flex: 1, height: 4, overflow: 'hidden', borderRadius: 2, backgroundColor: 'rgba(255,255,255,.2)' }, volumeFill: { height: '100%', borderRadius: 2, backgroundColor: colors.blue }, volumeText: { width: 31, color: '#BFD0E1', fontSize: 9, fontWeight: '800', textAlign: 'right' }, copy: { position: 'absolute', left: 16, right: 76, bottom: 24 }, nameRow: { flexDirection: 'row', alignItems: 'center', gap: 9 }, avatar: { width: 42, height: 42, borderRadius: 21, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, avatarImage: { width: 42, height: 42, borderRadius: 21, backgroundColor: colors.surface }, avatarText: { color: '#fff', fontWeight: '900', fontSize: 17 }, name: { color: '#fff', fontSize: 17, fontWeight: '900' }, meta: { color: '#C3D0DE', fontSize: 11, marginTop: 2 }, follow: { paddingHorizontal: 12, paddingVertical: 7, borderRadius: 10, backgroundColor: colors.blue }, following: { backgroundColor: 'rgba(255,255,255,.18)', borderWidth: 1, borderColor: 'rgba(255,255,255,.3)' }, followText: { color: '#fff', fontSize: 11, fontWeight: '800' }, caption: { color: '#fff', fontSize: 14, lineHeight: 20, marginTop: 12 }, tags: { color: '#8EB0FF', fontWeight: '700', fontSize: 12, marginTop: 7 }, rail: { position: 'absolute', right: 12, bottom: 20, gap: 12 }, action: { alignItems: 'center' }, actionCircle: { width: 45, height: 45, borderRadius: 23, backgroundColor: 'rgba(5,12,20,.66)', borderWidth: 1, borderColor: 'rgba(255,255,255,.15)', alignItems: 'center', justifyContent: 'center' }, actionActive: { backgroundColor: colors.pink }, actionText: { color: '#fff', fontSize: 10, fontWeight: '700', marginTop: 3 }, views: { alignItems: 'center', paddingTop: 3 } });
