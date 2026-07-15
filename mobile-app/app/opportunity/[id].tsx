import { useState } from 'react';
import { ActivityIndicator, Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../src/api/client';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { colors, radius, spacing } from '../../src/theme';
import type { ApiResponse, Opportunity } from '../../src/types/api';

export default function OpportunityDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const client = useQueryClient();
  const [applying, setApplying] = useState(false);
  const [coverLetter, setCoverLetter] = useState('');
  const opportunity = useQuery({ queryKey: ['opportunity', id], enabled: Boolean(id), queryFn: async () => (await api.get<ApiResponse<Opportunity>>(`/opportunities/${encodeURIComponent(id!)}`)).data.data });
  const save = useMutation({
    mutationFn: async (saved: boolean) => saved ? api.delete(`/opportunities/${id}/save`) : api.post(`/opportunities/${id}/save`),
    onSuccess: () => { client.invalidateQueries({ queryKey: ['opportunity', id] }); client.invalidateQueries({ queryKey: ['opportunities'] }); },
    onError: error => Alert.alert('Could not update saved opportunity', errorMessage(error)),
  });
  const apply = useMutation({
    mutationFn: async () => api.post(`/opportunities/${id}/apply`, { cover_letter: coverLetter.trim() || undefined }),
    onSuccess: () => { setApplying(false); setCoverLetter(''); client.invalidateQueries({ queryKey: ['opportunity', id] }); client.invalidateQueries({ queryKey: ['opportunities'] }); Alert.alert('Application submitted', 'You can track updates from your applications.'); },
    onError: error => Alert.alert('Application not submitted', errorMessage(error)),
  });

  if (opportunity.isLoading) return <SafeAreaView style={styles.safe}><TopBar /><ActivityIndicator style={{ flex: 1 }} color={colors.blue} /></SafeAreaView>;
  if (opportunity.isError || !opportunity.data) return <SafeAreaView style={styles.safe}><TopBar /><ScreenMessage icon="alert-circle-outline" title="Opportunity unavailable" message="It may have closed or no longer be available." action={<PrimaryButton label="Go back" secondary onPress={() => router.back()} />} /></SafeAreaView>;
  const item = opportunity.data;
  const location = item.location.is_remote ? 'Remote' : [item.location.city, item.location.province, item.location.country].filter(Boolean).join(', ') || 'Location flexible';
  const age = item.age_range.minimum || item.age_range.maximum ? `${item.age_range.minimum ?? 'Any'}–${item.age_range.maximum ?? 'Any'} years` : null;

  return <SafeAreaView edges={['top']} style={styles.safe}><KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
    <TopBar saved={item.viewer.saved} busy={save.isPending} onSave={() => save.mutate(item.viewer.saved)} />
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <View style={styles.badge}><Text style={styles.badgeText}>{item.type}</Text></View>
      <Text style={styles.title}>{item.title}</Text>
      <Pressable disabled={!item.poster.slug} onPress={() => item.poster.slug && router.push(`/profile/${item.poster.slug}` as never)}><Text style={styles.poster}>Posted by {item.poster.name}</Text></Pressable>
      <View style={styles.summary}>
        <Info icon={item.location.is_remote ? 'globe-outline' : 'location-outline'} label="Location" value={location} />
        {item.sport ? <Info icon="football-outline" label="Sport" value={[item.sport.name, item.position?.name].filter(Boolean).join(' · ')} /> : null}
        {age ? <Info icon="people-outline" label="Age range" value={age} /> : null}
        <Info icon="calendar-outline" label="Deadline" value={formatDate(item.deadline)} />
      </View>
      <Section title="About"><Text style={styles.body}>{item.description}</Text></Section>
      {item.requirements.length ? <Section title="Requirements">{item.requirements.map((requirement, index) => <View key={`${index}-${requirement}`} style={styles.requirement}><Ionicons name="checkmark-circle" size={18} color={colors.green} /><Text style={styles.requirementText}>{requirement}</Text></View>)}</Section> : null}
      <Text style={styles.applications}>{item.applications_count} {item.applications_count === 1 ? 'application' : 'applications'}</Text>
      {applying ? <View style={styles.applyPanel}><Text style={styles.applyTitle}>Apply for this opportunity</Text><Text style={styles.applyCopy}>Introduce yourself and explain why you are a strong fit. A cover letter is optional.</Text><TextInput accessibilityLabel="Cover letter" multiline maxLength={5000} value={coverLetter} onChangeText={setCoverLetter} placeholder="Write your cover letter..." placeholderTextColor="#71849B" style={styles.coverLetter} textAlignVertical="top" /><Text style={styles.counter}>{coverLetter.length}/5000</Text><PrimaryButton label="Submit application" loading={apply.isPending} onPress={() => apply.mutate()} /><PrimaryButton label="Cancel" secondary onPress={() => setApplying(false)} style={{ marginTop: 10 }} /></View> : null}
    </ScrollView>
    {!applying ? <View style={styles.footer}>{item.viewer.applied ? <View style={styles.applied}><Ionicons name="checkmark-circle" size={20} color={colors.green} /><Text style={styles.appliedText}>Application submitted</Text></View> : <PrimaryButton label="Apply now" onPress={() => setApplying(true)} />}</View> : null}
  </KeyboardAvoidingView></SafeAreaView>;
}

function TopBar({ saved, busy, onSave }: { saved?: boolean; busy?: boolean; onSave?: () => void }) { return <View style={styles.topBar}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Text style={styles.topTitle}>Opportunity</Text>{onSave ? <Pressable accessibilityLabel={saved ? 'Remove saved opportunity' : 'Save opportunity'} disabled={busy} hitSlop={12} onPress={onSave}>{busy ? <ActivityIndicator color={colors.orange} /> : <Ionicons name={saved ? 'bookmark' : 'bookmark-outline'} size={24} color={saved ? colors.orange : colors.white} />}</Pressable> : <View style={{ width: 24 }} />}</View>; }
function Info({ icon, label, value }: { icon: keyof typeof Ionicons.glyphMap; label: string; value: string }) { return <View style={styles.info}><View style={styles.infoIcon}><Ionicons name={icon} size={19} color="#79A3FF" /></View><View style={{ flex: 1 }}><Text style={styles.infoLabel}>{label}</Text><Text style={styles.infoValue}>{value}</Text></View></View>; }
function Section({ title, children }: { title: string; children: React.ReactNode }) { return <View style={styles.section}><Text style={styles.sectionTitle}>{title}</Text>{children}</View>; }
function formatDate(value?: string | null) { if (!value) return 'Open until filled'; return new Date(value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }); }
function errorMessage(error: any) { return error?.response?.data?.message || Object.values(error?.response?.data?.errors || {}).flat()[0] as string || 'Please check your connection and try again.'; }

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, topBar: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, topTitle: { color: colors.white, fontSize: 15, fontWeight: '900' }, content: { padding: spacing.lg, paddingBottom: 32 }, badge: { alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 6, borderRadius: radius.pill, backgroundColor: 'rgba(27,99,243,.15)' }, badgeText: { color: '#79A3FF', fontSize: 10, fontWeight: '900', textTransform: 'uppercase' }, title: { color: colors.white, fontSize: 30, lineHeight: 34, fontWeight: '900', letterSpacing: -1, marginTop: 14 }, poster: { color: '#79A3FF', fontSize: 13, fontWeight: '800', marginTop: 8 },
  summary: { marginTop: 24, padding: 4, borderWidth: 1, borderColor: colors.line, borderRadius: radius.lg, backgroundColor: colors.surface }, info: { minHeight: 66, flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 13, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line }, infoIcon: { width: 38, height: 38, borderRadius: 19, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(27,99,243,.12)' }, infoLabel: { color: colors.muted, fontSize: 10, fontWeight: '800', textTransform: 'uppercase' }, infoValue: { color: colors.white, fontSize: 14, fontWeight: '800', marginTop: 2 },
  section: { marginTop: 27 }, sectionTitle: { color: colors.white, fontSize: 18, fontWeight: '900', marginBottom: 10 }, body: { color: '#D2DEEB', fontSize: 14, lineHeight: 22 }, requirement: { flexDirection: 'row', alignItems: 'flex-start', gap: 9, marginTop: 9 }, requirementText: { flex: 1, color: '#D2DEEB', fontSize: 14, lineHeight: 20 }, applications: { color: colors.muted, fontSize: 12, marginTop: 26 },
  footer: { padding: spacing.md, borderTopWidth: 1, borderTopColor: colors.line, backgroundColor: '#091725' }, applied: { height: 52, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderRadius: radius.md, backgroundColor: 'rgba(24,178,107,.12)' }, appliedText: { color: '#69DDA4', fontWeight: '900' }, applyPanel: { marginTop: 25, padding: spacing.md, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, applyTitle: { color: colors.white, fontSize: 19, fontWeight: '900' }, applyCopy: { color: colors.muted, fontSize: 13, lineHeight: 19, marginTop: 6 }, coverLetter: { minHeight: 160, color: colors.white, fontSize: 14, lineHeight: 20, marginTop: 16, padding: 14, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.navy }, counter: { color: colors.muted, fontSize: 10, textAlign: 'right', marginTop: 5, marginBottom: 13 },
});
