import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../theme';
import { BrandMark } from './BrandMark';
export function PlaceholderScreen({title,icon}: {title:string;icon:any}) { return <SafeAreaView style={styles.safe}><BrandMark/><View style={styles.center}><Ionicons name={icon} size={42} color={colors.blue}/><Text style={styles.title}>{title}</Text><Text style={styles.copy}>This area is connected to the SportUniverse API and ready for the next mobile feature slice.</Text></View></SafeAreaView>; }
const styles=StyleSheet.create({safe:{flex:1,backgroundColor:colors.navy,padding:20},center:{flex:1,alignItems:'center',justifyContent:'center'},title:{color:colors.white,fontSize:28,fontWeight:'900',marginTop:14},copy:{color:colors.muted,textAlign:'center',lineHeight:21,maxWidth:310,marginTop:8}});
