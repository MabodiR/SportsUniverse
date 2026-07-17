import { useState } from 'react';
import { ActivityIndicator, Alert, FlatList, Modal, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import * as DocumentPicker from 'expo-document-picker';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../src/api/client';
import { PrimaryButton } from '../../../src/components/PrimaryButton';
import { ScreenMessage } from '../../../src/components/ScreenMessage';
import { colors, radius, spacing } from '../../../src/theme';
import type { MediaUpload, PaginatedResponse } from '../../../src/types/api';

const collections = [
  { key: 'all', label: 'All' }, { key: 'uploads', label: 'Uploads' }, { key: 'gallery', label: 'Photos' },
  { key: 'highlights', label: 'Videos' }, { key: 'resumes', label: 'CVs' }, { key: 'certificates', label: 'Certificates' },
  { key: 'identity', label: 'Identity' }, { key: 'medical', label: 'Medical' }, { key: 'contracts', label: 'Contracts' },
] as const;

export default function LibraryScreen() {
  const client = useQueryClient();
  const [filter, setFilter] = useState('all');
  const [uploadCollection, setUploadCollection] = useState('uploads');
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [editing, setEditing] = useState<MediaUpload | null>(null);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [editCollection, setEditCollection] = useState('uploads');

  const media = useQuery({
    queryKey: ['media', 'library'],
    queryFn: async () => (await api.get<PaginatedResponse<MediaUpload>>('/media', { params: { per_page: 100 } })).data.data,
    refetchInterval: query => query.state.data?.some(item => item.processing_status === 'pending' || item.processing_status === 'processing') ? 4000 : false,
  });
  const save = useMutation({
    mutationFn: () => api.patch('/media/' + editing!.id, { title: title.trim() || null, description: description.trim() || null, collection: editCollection }),
    onSuccess: () => { setEditing(null); client.invalidateQueries({ queryKey: ['media'] }); },
    onError: error => Alert.alert('File not updated', errorMessage(error)),
  });
  const remove = useMutation({
    mutationFn: (id: string) => api.delete('/media/' + id),
    onSuccess: () => client.invalidateQueries({ queryKey: ['media'] }),
    onError: error => Alert.alert('File not deleted', errorMessage(error)),
  });

  const chooseFile = async () => {
    const result = await DocumentPicker.getDocumentAsync({
      type: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime', 'video/webm'],
      copyToCacheDirectory: true,
    });
    if (result.canceled) return;
    const asset = result.assets[0];
    const mime = asset.mimeType || 'application/pdf';
    const kind = mime.startsWith('image/') ? 'image' : mime.startsWith('video/') ? 'video' : 'document';
    const selectedCollection = uploadCollection === 'uploads' ? (kind === 'image' ? 'gallery' : kind === 'video' ? 'highlights' : 'resumes') : uploadCollection;
    setUploading(true);
    setProgress(0);
    try {
      const form = new FormData();
      form.append('kind', kind);
      form.append('collection', selectedCollection);
      form.append('title', asset.name.replace(/\.[^.]+$/, ''));
      form.append('file', { uri: asset.uri, name: asset.name, type: mime } as any);
      await api.post('/media', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: 600000,
        onUploadProgress: event => setProgress(event.total ? Math.round((event.loaded / event.total) * 100) : 0),
      });
      await client.invalidateQueries({ queryKey: ['media'] });
    } catch (error) {
      Alert.alert('Upload failed', errorMessage(error));
    } finally {
      setUploading(false);
      setProgress(0);
    }
  };
  const openEditor = (item: MediaUpload) => { setEditing(item); setTitle(item.title || ''); setDescription(item.description || ''); setEditCollection(item.collection); };
  const confirmDelete = (item: MediaUpload) => Alert.alert('Delete this file?', 'This permanently removes it from your Library.', [{ text: 'Cancel', style: 'cancel' }, { text: 'Delete', style: 'destructive', onPress: () => remove.mutate(item.id) }]);
  const items = (media.data ?? []).filter(item => filter === 'all' || item.collection === filter);

  return <SafeAreaView edges={['top']} style={styles.safe}>
    <View style={styles.header}><Pressable accessibilityLabel="Go back" onPress={() => router.back()}><Ionicons name="chevron-back" size={26} color="#fff" /></Pressable><Text style={styles.headerTitle}>Library</Text><Pressable accessibilityLabel="Upload a file" disabled={uploading} onPress={chooseFile}><Ionicons name="cloud-upload-outline" size={25} color="#fff" /></Pressable></View>
    <View style={styles.uploadBox}><Text style={styles.uploadTitle}>Add to Library</Text><Text style={styles.help}>Choose a category, then select a photo, video or PDF.</Text><ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>{collections.filter(item => item.key !== 'all').map(item => <Chip key={item.key} label={item.label} active={uploadCollection === item.key} onPress={() => setUploadCollection(item.key)} />)}</ScrollView><PrimaryButton label={uploading ? `Uploading ${progress}%` : 'Choose file'} loading={uploading} onPress={chooseFile} /></View>
    <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterScroll} contentContainerStyle={styles.filters}>{collections.map(item => <Chip key={item.key} label={item.label} active={filter === item.key} onPress={() => setFilter(item.key)} />)}</ScrollView>
    <FlatList data={items} keyExtractor={item => item.id} contentContainerStyle={items.length ? styles.list : styles.empty} refreshing={media.isRefetching} onRefresh={() => media.refetch()} ItemSeparatorComponent={() => <View style={{ height: 10 }} />} renderItem={({ item }) => <MediaRow item={item} busy={remove.isPending} onEdit={() => openEditor(item)} onDelete={() => confirmDelete(item)} />} ListEmptyComponent={media.isLoading ? <ActivityIndicator style={{ marginTop: 80 }} color={colors.blue} /> : media.isError ? <ScreenMessage icon="cloud-offline-outline" title="Library unavailable" message="Check your connection and try again." /> : <ScreenMessage icon="folder-open-outline" title="Nothing here yet" message="Upload a file or choose another category." />} />
    <Modal visible={Boolean(editing)} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setEditing(null)}><SafeAreaView style={styles.modal}><View style={styles.modalHeader}><Pressable onPress={() => setEditing(null)}><Text style={styles.cancel}>Cancel</Text></Pressable><Text style={styles.headerTitle}>Edit file</Text><Pressable disabled={save.isPending} onPress={() => save.mutate()}><Text style={styles.save}>Save</Text></Pressable></View><ScrollView contentContainerStyle={styles.form}><Label text="Title" /><TextInput value={title} onChangeText={setTitle} placeholder="File title" placeholderTextColor={colors.muted} style={styles.input} /><Label text="Description" /><TextInput value={description} onChangeText={setDescription} multiline placeholder="Optional notes" placeholderTextColor={colors.muted} style={[styles.input, styles.textarea]} /><Label text="Category" /><View style={styles.wrap}>{collections.filter(item => item.key !== 'all').map(item => <Chip key={item.key} label={item.label} active={editCollection === item.key} onPress={() => setEditCollection(item.key)} />)}</View></ScrollView></SafeAreaView></Modal>
  </SafeAreaView>;
}

function MediaRow({ item, busy, onEdit, onDelete }: { item: MediaUpload; busy: boolean; onEdit: () => void; onDelete: () => void }) {
  const icon = item.kind === 'image' ? 'image-outline' : item.kind === 'video' ? 'videocam-outline' : 'document-text-outline';
  return <View style={styles.row}><View style={styles.fileIcon}><Ionicons name={icon} size={26} color="#79A3FF" /></View><View style={styles.fileInfo}><Text numberOfLines={1} style={styles.fileName}>{item.title || item.original_name}</Text><Text style={styles.meta}>{labelFor(item.collection)} · {formatBytes(item.size_bytes)}</Text><Text style={[styles.status, item.processing_status === 'failed' && { color: colors.danger }]}>{item.processing_status === 'ready' ? 'Ready' : item.processing_status}</Text></View><Pressable disabled={busy} accessibilityLabel="Edit file" onPress={onEdit} style={styles.iconButton}><Ionicons name="create-outline" size={19} color="#fff" /></Pressable><Pressable disabled={busy} accessibilityLabel="Delete file" onPress={onDelete} style={styles.iconButton}><Ionicons name="trash-outline" size={19} color={colors.danger} /></Pressable></View>;
}
function Chip({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) { return <Pressable onPress={onPress} style={[styles.chip, active && styles.chipActive]}><Text style={[styles.chipText, active && styles.chipTextActive]}>{label}</Text></Pressable>; }
function Label({ text }: { text: string }) { return <Text style={styles.label}>{text}</Text>; }
function labelFor(key: string) { return collections.find(item => item.key === key)?.label || key; }
function formatBytes(bytes: number) { if (!bytes) return '0 KB'; if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`; return `${Math.ceil(bytes / 1024)} KB`; }
function errorMessage(error: any) { return error?.response?.data?.message || Object.values(error?.response?.data?.errors || {}).flat()[0] as string || error?.message || 'Please check your connection and try again.'; }

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, header: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, headerTitle: { color: '#fff', fontSize: 18, fontWeight: '900' },
  uploadBox: { margin: spacing.md, marginBottom: 8, padding: spacing.md, borderRadius: radius.lg, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.line }, uploadTitle: { color: '#fff', fontSize: 17, fontWeight: '900' }, help: { color: colors.muted, fontSize: 12, marginTop: 4, marginBottom: 12 }, chips: { gap: 7, paddingBottom: 14 }, filterScroll: { flexGrow: 0 }, filters: { gap: 7, paddingHorizontal: spacing.md, paddingVertical: 9 }, chip: { paddingHorizontal: 13, minHeight: 34, alignItems: 'center', justifyContent: 'center', borderRadius: radius.pill, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, chipActive: { borderColor: colors.blue, backgroundColor: colors.blue }, chipText: { color: colors.muted, fontSize: 11, fontWeight: '800' }, chipTextActive: { color: '#fff' },
  list: { padding: spacing.md, paddingBottom: 40 }, empty: { flexGrow: 1 }, row: { minHeight: 84, padding: 12, flexDirection: 'row', alignItems: 'center', borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, fileIcon: { width: 48, height: 48, borderRadius: 13, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surfaceRaised }, fileInfo: { flex: 1, marginLeft: 11 }, fileName: { color: '#fff', fontSize: 14, fontWeight: '800' }, meta: { color: colors.muted, fontSize: 10, marginTop: 4 }, status: { color: colors.green, fontSize: 10, fontWeight: '800', textTransform: 'capitalize', marginTop: 4 }, iconButton: { width: 37, height: 37, alignItems: 'center', justifyContent: 'center' },
  modal: { flex: 1, backgroundColor: colors.navy }, modalHeader: { height: 58, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, cancel: { color: colors.muted, fontWeight: '700' }, save: { color: '#79A3FF', fontWeight: '900' }, form: { padding: spacing.lg }, label: { color: '#fff', fontSize: 12, fontWeight: '800', marginTop: 15, marginBottom: 7 }, input: { minHeight: 50, paddingHorizontal: 14, color: '#fff', borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, backgroundColor: colors.surface }, textarea: { height: 120, paddingTop: 14, textAlignVertical: 'top' }, wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
});
