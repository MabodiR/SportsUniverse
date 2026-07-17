import { ActivityIndicator, Pressable, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../../src/api/client';
import { FeedCard } from '../../../src/components/FeedCard';
import { ScreenMessage } from '../../../src/components/ScreenMessage';
import { colors, spacing } from '../../../src/theme';
import type { ApiResponse, Video } from '../../../src/types/api';

export default function PostPreviewScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { height } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const post = useQuery({ queryKey: ['video', id], enabled: Boolean(id), queryFn: async () => (await api.get<ApiResponse<Video>>('/videos/' + id)).data.data });
  const cardHeight = Math.max(420, height - insets.top - 56);
  return <SafeAreaView edges={['top']} style={styles.safe}><View style={styles.header}><Pressable accessibilityLabel="Go back" onPress={() => router.back()}><Ionicons name="chevron-back" size={26} color="#fff" /></Pressable><Text style={styles.headerTitle}>Post</Text><Pressable accessibilityLabel="Post comments" disabled={!post.data} onPress={() => post.data && router.push(`/post/${post.data.id}/comments` as never)}><Ionicons name="chatbubble-outline" size={22} color="#fff" /></Pressable></View>{post.isLoading ? <ActivityIndicator style={{ flex: 1 }} color={colors.blue} /> : post.isError || !post.data ? <ScreenMessage icon="alert-circle-outline" title="Post unavailable" message={(post.error as any)?.response?.data?.message || 'This post may be private, removed or unavailable.'} /> : <FeedCard video={post.data} index={0} height={cardHeight} active authenticated onProtectedAction={() => undefined} />}</SafeAreaView>;
}
const styles = StyleSheet.create({ safe: { flex: 1, backgroundColor: colors.navy }, header: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line, backgroundColor: '#091725' }, headerTitle: { color: '#fff', fontSize: 17, fontWeight: '900' } });
