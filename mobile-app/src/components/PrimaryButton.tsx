import { ActivityIndicator, Pressable, StyleSheet, Text, ViewStyle } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { colors, radius } from '../theme';

export function PrimaryButton({ label, onPress, loading, secondary, style }: { label: string; onPress: () => void; loading?: boolean; secondary?: boolean; style?: ViewStyle }) {
  if (secondary) return <Pressable onPress={onPress} style={[styles.button,styles.secondary,style]}><Text style={styles.label}>{label}</Text></Pressable>;
  return <Pressable onPress={onPress} disabled={loading} style={style}><LinearGradient colors={[colors.blue,'#4A83FA']} style={styles.button}>{loading?<ActivityIndicator color="#fff"/>:<Text style={styles.label}>{label}</Text>}</LinearGradient></Pressable>;
}
const styles=StyleSheet.create({button:{minHeight:50,borderRadius:radius.md,alignItems:'center',justifyContent:'center',paddingHorizontal:18},secondary:{borderWidth:1,borderColor:colors.line,backgroundColor:'rgba(255,255,255,.06)'},label:{color:colors.white,fontWeight:'800',fontSize:15}});
