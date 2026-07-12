import { StyleSheet, Text, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { colors } from '../theme';

export function BrandMark({ compact = false }: { compact?: boolean }) {
  return <View style={styles.row}><LinearGradient colors={[colors.blue, colors.pink]} style={styles.mark}><View style={styles.orbitA}/><View style={styles.orbitB}/><View style={styles.dot}/></LinearGradient>{!compact && <View><Text style={styles.name}>Sport Universe</Text><Text style={styles.tagline}>Talent. Opportunity. Community.</Text></View>}</View>;
}
const styles = StyleSheet.create({ row:{flexDirection:'row',alignItems:'center',gap:10},mark:{width:44,height:44,borderRadius:22,alignItems:'center',justifyContent:'center',overflow:'hidden'},orbitA:{position:'absolute',width:31,height:17,borderRadius:18,borderWidth:1.3,borderColor:'rgba(255,255,255,.8)',transform:[{rotate:'25deg'}]},orbitB:{position:'absolute',width:17,height:31,borderRadius:18,borderWidth:1.3,borderColor:'rgba(255,255,255,.72)',transform:[{rotate:'-40deg'}]},dot:{width:7,height:7,borderRadius:4,backgroundColor:colors.orange},name:{color:colors.white,fontSize:17,fontWeight:'900',letterSpacing:-.5},tagline:{color:colors.muted,fontSize:9,marginTop:1} });
