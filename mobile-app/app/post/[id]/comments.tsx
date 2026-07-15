import { useState } from 'react';
import { ActivityIndicator, Alert, FlatList, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../src/api/client';
import { colors, radius } from '../../../src/theme';
import type { Comment, Video } from '../../../src/types/api';

export default function CommentsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const queryClient = useQueryClient();
  const [body, setBody] = useState('');
  const [replyTo, setReplyTo] = useState<Comment>();
  const comments = useQuery({ queryKey: ['post-comments', id], queryFn: async () => (await api.get('/videos/' + id + '/comments')).data.data as Comment[], enabled: Boolean(id) });
  const post = useQuery({ queryKey: ['video', id], queryFn: async () => (await api.get('/videos/' + id)).data.data as Video, enabled: Boolean(id) });
  const send = useMutation({
    mutationFn: async () => (await api.post('/videos/' + id + '/comments', { body: body.trim(), ...(replyTo ? { parent_id: replyTo.id } : {}) })).data.data as Comment,
    onSuccess: async () => {
      setBody(''); setReplyTo(undefined);
      await Promise.all([queryClient.invalidateQueries({ queryKey: ['post-comments', id] }), queryClient.invalidateQueries({ queryKey: ['feed'] }), queryClient.invalidateQueries({ queryKey: ['video', id] })]);
    },
    onError: (error: any) => Alert.alert('Comment not sent', error?.response?.data?.message || error?.response?.data?.errors?.body?.[0] || 'Please try again.'),
  });
  const toggleLike = async (comment: Comment) => {
    try {
      const data = (await api.post('/comments/' + comment.id + '/like')).data.data;
      queryClient.setQueryData<Comment[]>(['post-comments', id], current => updateComment(current ?? [], comment.id, item => ({ ...item, liked: data.liked, likes_count: data.likes_count })));
    } catch (error: any) { Alert.alert('Unable to like comment', error?.response?.data?.message || 'Please try again.'); }
  };
  const enabled = post.data?.comments_enabled !== false;

  return <SafeAreaView style={styles.safe} edges={['top', 'bottom']}><KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
    <View style={styles.header}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="chevron-back" size={26} color="#fff" /></Pressable><Text style={styles.title}>Comments</Text><View style={styles.headerSpace} /></View>
    {comments.isLoading ? <ActivityIndicator style={styles.flex} color={colors.blue} /> : comments.isError ? <View style={styles.center}><Text style={styles.emptyTitle}>Comments unavailable</Text><Pressable onPress={() => comments.refetch()}><Text style={styles.retry}>Try again</Text></Pressable></View> : <FlatList data={comments.data ?? []} keyExtractor={item => item.id} contentContainerStyle={(comments.data?.length ?? 0) ? styles.list : styles.emptyList} keyboardShouldPersistTaps="handled" renderItem={({ item }) => <CommentRow comment={item} onReply={setReplyTo} onLike={toggleLike} />} ListEmptyComponent={<View style={styles.center}><Ionicons name="chatbubbles-outline" size={42} color={colors.muted} /><Text style={styles.emptyTitle}>No comments yet</Text><Text style={styles.emptyCopy}>{enabled ? 'Start the conversation.' : 'Comments are disabled for this post.'}</Text></View>} />}
    {enabled ? <View style={styles.composer}>{replyTo ? <View style={styles.replying}><Text numberOfLines={1} style={styles.replyingText}>Replying to {replyTo.user.name}</Text><Pressable onPress={() => setReplyTo(undefined)}><Ionicons name="close-circle" size={20} color={colors.muted} /></Pressable></View> : null}<View style={styles.composeRow}><TextInput value={body} onChangeText={setBody} maxLength={1000} multiline placeholder={replyTo ? 'Write a reply…' : 'Add a comment…'} placeholderTextColor="#71849B" style={styles.input} /><Pressable accessibilityLabel="Send comment" disabled={!body.trim() || send.isPending} onPress={() => send.mutate()} style={[styles.send, (!body.trim() || send.isPending) && styles.sendDisabled]}>{send.isPending ? <ActivityIndicator size="small" color="#fff" /> : <Ionicons name="arrow-up" size={21} color="#fff" />}</Pressable></View></View> : null}
  </KeyboardAvoidingView></SafeAreaView>;
}

function CommentRow({ comment, onReply, onLike, nested = false }: { comment: Comment; onReply: (comment: Comment) => void; onLike: (comment: Comment) => void; nested?: boolean }) {
  return <View style={[styles.commentBlock, nested && styles.nested]}><View style={styles.avatar}><Text style={styles.avatarText}>{comment.user.name.charAt(0).toUpperCase()}</Text></View><View style={styles.commentBody}><View style={styles.commentTop}><Text style={styles.name}>{comment.user.name}</Text><Text style={styles.time}>{relativeTime(comment.created_at)}</Text></View><Text style={styles.body}>{comment.body}</Text><View style={styles.actions}><Pressable onPress={() => onLike(comment)} style={styles.smallAction}><Ionicons name={comment.liked ? 'heart' : 'heart-outline'} size={16} color={comment.liked ? colors.pink : colors.muted} /><Text style={[styles.actionText, comment.liked && { color: colors.pink }]}>{comment.likes_count || 'Like'}</Text></Pressable>{!nested ? <Pressable onPress={() => onReply(comment)}><Text style={styles.actionText}>Reply</Text></Pressable> : null}<Pressable onPress={() => router.push({ pathname: '/report', params: { type: 'comment', id: comment.id, label: 'this comment' } })}><Text style={styles.actionText}>Report</Text></Pressable></View>{comment.replies?.map(reply => <CommentRow key={reply.id} comment={reply} onReply={onReply} onLike={onLike} nested />)}</View></View>;
}

function updateComment(comments: Comment[], id: string, update: (comment: Comment) => Comment): Comment[] { return comments.map(comment => comment.id === id ? update(comment) : ({ ...comment, replies: updateComment(comment.replies ?? [], id, update) })); }
function relativeTime(value: string) { const seconds = Math.max(1, Math.floor((Date.now() - new Date(value).getTime()) / 1000)); if (seconds < 60) return 'now'; if (seconds < 3600) return Math.floor(seconds / 60) + 'm'; if (seconds < 86400) return Math.floor(seconds / 3600) + 'h'; if (seconds < 604800) return Math.floor(seconds / 86400) + 'd'; return new Date(value).toLocaleDateString(); }

const styles = StyleSheet.create({ safe: { flex: 1, backgroundColor: colors.navy }, flex: { flex: 1 }, header: { height: 54, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, title: { color: '#fff', fontSize: 18, fontWeight: '900' }, headerSpace: { width: 26 }, list: { padding: 16, paddingBottom: 26 }, emptyList: { flexGrow: 1 }, center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32, gap: 8 }, emptyTitle: { color: '#fff', fontSize: 17, fontWeight: '800' }, emptyCopy: { color: colors.muted, textAlign: 'center' }, retry: { color: colors.blue, fontWeight: '800', marginTop: 6 }, commentBlock: { flexDirection: 'row', gap: 10, marginBottom: 20 }, nested: { marginTop: 16, marginBottom: 0 }, avatar: { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surfaceRaised }, avatarText: { color: '#fff', fontWeight: '900' }, commentBody: { flex: 1 }, commentTop: { flexDirection: 'row', alignItems: 'center', gap: 7 }, name: { color: '#fff', fontSize: 13, fontWeight: '800' }, time: { color: colors.muted, fontSize: 11 }, body: { color: '#E7EFF8', fontSize: 14, lineHeight: 20, marginTop: 4 }, actions: { flexDirection: 'row', gap: 18, marginTop: 8 }, smallAction: { flexDirection: 'row', alignItems: 'center', gap: 4 }, actionText: { color: colors.muted, fontSize: 12, fontWeight: '700' }, composer: { borderTopWidth: 1, borderTopColor: colors.line, padding: 10, backgroundColor: colors.navyLight }, replying: { flexDirection: 'row', justifyContent: 'space-between', paddingHorizontal: 8, paddingBottom: 8 }, replyingText: { flex: 1, color: colors.muted, fontSize: 12 }, composeRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 8 }, input: { flex: 1, maxHeight: 110, minHeight: 44, paddingHorizontal: 14, paddingVertical: 11, borderRadius: radius.lg, backgroundColor: colors.surface, color: '#fff', fontSize: 14 }, send: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, sendDisabled: { opacity: .4 } });
