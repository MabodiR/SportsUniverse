import { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../src/api/client';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { colors, radius, spacing } from '../../src/theme';
import type { ApiResponse, Sport } from '../../src/types/api';

type Preferences = { interested_sports: string[]; favourites: { athletes: string[]; teams: string[]; clubs: string[] } };

export default function FanPreferencesScreen() {
  const client = useQueryClient();
  const preferences = useQuery({ queryKey: ['fan-preferences'], queryFn: async () => (await api.get<ApiResponse<Preferences>>('/profile/fan-preferences')).data.data });
  const sports = useQuery({ queryKey: ['sports'], queryFn: async () => (await api.get<ApiResponse<Sport[]>>('/sports')).data.data });
  const [selected, setSelected] = useState<string[]>([]);
  const [athletes, setAthletes] = useState('');
  const [teams, setTeams] = useState('');
  const [clubs, setClubs] = useState('');
  const [ready, setReady] = useState(false);
  useEffect(() => { if (!preferences.data || ready) return; setSelected(preferences.data.interested_sports); setAthletes(preferences.data.favourites.athletes.join(', ')); setTeams(preferences.data.favourites.teams.join(', ')); setClubs(preferences.data.favourites.clubs.join(', ')); setReady(true); }, [preferences.data, ready]);
  const save = useMutation({
    mutationFn: () => api.put('/profile/fan-preferences', { interested_sports: selected, favourites: { athletes: list(athletes), teams: list(teams), clubs: list(clubs) } }),
    onSuccess: () => { client.invalidateQueries({ queryKey: ['fan-preferences'] }); client.invalidateQueries({ queryKey: ['profile', 'mine'] }); client.invalidateQueries({ queryKey: ['feed'] }); Alert.alert('Preferences saved', 'Your For You feed will now prioritise the sports you selected.', [{ text: 'Done', onPress: () => router.back() }]); },
    onError: (error: any) => Alert.alert('Preferences not saved', error?.response?.data?.message || Object.values(error?.response?.data?.errors || {}).flat()[0] as string || 'Please try again.'),
  });

  if (preferences.isLoading || sports.isLoading || !ready) return <SafeAreaView style={styles.safe}><TopBar /><ActivityIndicator style={{ flex: 1 }} color={colors.blue} /></SafeAreaView>;
  if (preferences.isError) return <SafeAreaView style={styles.safe}><TopBar /><ScreenMessage icon="heart-dislike-outline" title="Fan preferences unavailable" message={(preferences.error as any)?.response?.data?.message || 'Please check your account and try again.'} /></SafeAreaView>;
  const toggle = (sport: string) => setSelected(current => current.includes(sport) ? current.filter(item => item !== sport) : [...current, sport]);

  return <SafeAreaView edges={['top']} style={styles.safe}><KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
    <TopBar />
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.eyebrow}>YOUR FAN EXPERIENCE</Text><Text style={styles.title}>Sports and favourites</Text><Text style={styles.copy}>Choose what you love so SportsUniverse can prioritise more relevant highlights, athletes and opportunities.</Text>
      <View style={styles.card}><Text style={styles.sectionTitle}>Favourite sports</Text><Text style={styles.help}>Select at least one sport.</Text><View style={styles.chips}>{sports.data?.map(sport => <Pressable accessibilityRole="checkbox" accessibilityState={{ checked: selected.includes(sport.name) }} key={sport.id} onPress={() => toggle(sport.name)} style={[styles.chip, selected.includes(sport.name) && styles.chipActive]}><Ionicons name={selected.includes(sport.name) ? 'checkmark-circle' : 'add-circle-outline'} size={17} color={selected.includes(sport.name) ? '#fff' : '#79A3FF'} /><Text style={[styles.chipText, selected.includes(sport.name) && styles.chipTextActive]}>{sport.name}</Text></Pressable>)}</View></View>
      <View style={styles.card}><Text style={styles.sectionTitle}>People and teams you support</Text><Text style={styles.help}>Separate multiple names with commas. These are private preference notes.</Text><Field label="Favourite athletes" value={athletes} onChangeText={setAthletes} placeholder="e.g. Thembi Kgatlana, Siya Kolisi" /><Field label="Favourite teams" value={teams} onChangeText={setTeams} placeholder="e.g. Banyana Banyana, Proteas" /><Field label="Favourite clubs or academies" value={clubs} onChangeText={setClubs} placeholder="e.g. Mamelodi Sundowns" /></View>
      <PrimaryButton label="Save preferences" loading={save.isPending} disabled={!selected.length} onPress={() => save.mutate()} style={{ marginTop: 22 }} />
    </ScrollView>
  </KeyboardAvoidingView></SafeAreaView>;
}

function TopBar() { return <View style={styles.topBar}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Text style={styles.topTitle}>Fan preferences</Text><View style={{ width: 24 }} /></View>; }
function Field(props: React.ComponentProps<typeof TextInput> & { label: string }) { const { label, ...input } = props; return <View style={{ marginTop: 17 }}><Text style={styles.label}>{label}</Text><TextInput {...input} autoCapitalize="words" placeholderTextColor="#71849B" style={styles.input} /></View>; }
function list(value: string) { return value.split(',').map(item => item.trim()).filter(Boolean); }

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, topBar: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, topTitle: { color: '#fff', fontSize: 15, fontWeight: '900' }, content: { padding: spacing.lg, paddingBottom: 44 }, eyebrow: { color: colors.pink, fontSize: 10, fontWeight: '900', letterSpacing: 1.1 }, title: { color: '#fff', fontSize: 30, fontWeight: '900', letterSpacing: -1, marginTop: 7 }, copy: { color: colors.muted, fontSize: 13, lineHeight: 20, marginTop: 8 }, card: { padding: spacing.md, marginTop: 22, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, sectionTitle: { color: '#fff', fontSize: 17, fontWeight: '900' }, help: { color: colors.muted, fontSize: 11, lineHeight: 16, marginTop: 5 }, chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 14 }, chip: { minHeight: 39, flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 12, borderRadius: radius.pill, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.navy }, chipActive: { borderColor: colors.blue, backgroundColor: colors.blue }, chipText: { color: '#DCE7F5', fontSize: 12, fontWeight: '800' }, chipTextActive: { color: '#fff' }, label: { color: '#DCE7F5', fontSize: 11, fontWeight: '800', marginBottom: 7 }, input: { minHeight: 50, paddingHorizontal: 13, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.navy, color: '#fff' },
});
