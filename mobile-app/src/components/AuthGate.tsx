import { Modal, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { colors, radius, spacing } from '../theme';
import { PrimaryButton } from './PrimaryButton';

export function AuthGate({ visible, onBack }: { visible: boolean; onBack: () => void }) {
  return <Modal visible={visible} transparent animationType="fade" statusBarTranslucent><View style={styles.overlay}><View style={styles.card}><View style={styles.icon}><Ionicons name="lock-closed" color="#fff" size={25}/></View><Text style={styles.eyebrow}>YOU'VE SEEN A GLIMPSE</Text><Text style={styles.title}>The next play starts with your profile.</Text><Text style={styles.copy}>Sign in to keep scrolling, follow athletes, save highlights and connect with the people shaping sport.</Text><PrimaryButton label="Create free account" onPress={()=>router.push('/(auth)/register')}/><PrimaryButton label="Sign in" secondary onPress={()=>router.push('/(auth)/login')} style={{marginTop:10}}/><Text onPress={onBack} style={styles.back}>Back to preview</Text></View></View></Modal>;
}
const styles=StyleSheet.create({overlay:{flex:1,backgroundColor:'rgba(5,12,21,.88)',alignItems:'center',justifyContent:'center',padding:spacing.lg},card:{width:'100%',maxWidth:460,padding:spacing.lg,borderRadius:radius.lg,backgroundColor:colors.surface,borderWidth:1,borderColor:'rgba(121,163,255,.25)'},icon:{width:58,height:58,borderRadius:18,backgroundColor:colors.blue,alignSelf:'center',alignItems:'center',justifyContent:'center',marginBottom:16},eyebrow:{color:'#8FB2FF',fontSize:12,fontWeight:'900',textAlign:'center',letterSpacing:1},title:{color:colors.white,fontSize:29,fontWeight:'900',textAlign:'center',lineHeight:31,letterSpacing:-1,marginVertical:12},copy:{color:colors.muted,fontSize:15,lineHeight:22,textAlign:'center',marginBottom:20},back:{color:colors.muted,textAlign:'center',fontWeight:'700',marginTop:18}});
