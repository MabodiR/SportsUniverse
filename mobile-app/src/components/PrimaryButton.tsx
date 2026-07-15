import { ActivityIndicator, Pressable, StyleSheet, Text, ViewStyle } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { colors, radius } from '../theme';

export function PrimaryButton({ label, onPress, loading, disabled, secondary, style }: { label: string; onPress: () => void; loading?: boolean; disabled?: boolean; secondary?: boolean; style?: ViewStyle }) {
  const inactive = Boolean(loading || disabled);
  if (secondary) return <Pressable accessibilityRole="button" accessibilityState={{ disabled: inactive }} disabled={inactive} onPress={onPress} style={[styles.button, styles.secondary, inactive && styles.disabled, style]}><Text style={styles.label}>{label}</Text></Pressable>;
  return <Pressable accessibilityRole="button" accessibilityState={{ disabled: inactive, busy: Boolean(loading) }} onPress={onPress} disabled={inactive} style={[style, inactive && styles.disabled]}><LinearGradient colors={[colors.blue, '#4A83FA']} style={styles.button}>{loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.label}>{label}</Text>}</LinearGradient></Pressable>;
}
const styles=StyleSheet.create({button:{minHeight:50,borderRadius:radius.md,alignItems:'center',justifyContent:'center',paddingHorizontal:18},secondary:{borderWidth:1,borderColor:colors.line,backgroundColor:'rgba(255,255,255,.06)'},disabled:{opacity:.45},label:{color:colors.white,fontWeight:'800',fontSize:15}});
