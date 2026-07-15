import { ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { colors, spacing } from '../theme';

type Props = {
  icon: keyof typeof Ionicons.glyphMap;
  title: string;
  message: string;
  action?: ReactNode;
};

export function ScreenMessage({ icon, title, message, action }: Props) {
  return (
    <View style={styles.container}>
      <View style={styles.icon}><Ionicons name={icon} size={30} color={colors.blue} /></View>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      {action ? <View style={styles.action}>{action}</View> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, minHeight: 360, alignItems: 'center', justifyContent: 'center', padding: spacing.xl },
  icon: { width: 64, height: 64, borderRadius: 32, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(27,99,243,.14)' },
  title: { color: colors.white, fontSize: 21, fontWeight: '900', textAlign: 'center', marginTop: 16 },
  message: { color: colors.muted, fontSize: 14, lineHeight: 21, textAlign: 'center', maxWidth: 330, marginTop: 7 },
  action: { width: '100%', maxWidth: 330, marginTop: 20 },
});
