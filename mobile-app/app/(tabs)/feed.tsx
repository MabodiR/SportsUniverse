import { useCallback, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useQuery } from '@tanstack/react-query';
import { BrandMark } from '../../src/components/BrandMark';
import { FeedCard } from '../../src/components/FeedCard';
import { AuthGate } from '../../src/components/AuthGate';
import { api } from '../../src/api/client';
import { useAuthStore } from '../../src/stores/auth';
import { colors } from '../../src/theme';
import type { Video } from '../../src/types/api';

const demos:Video[]=[
  {id:'demo-1',creator:{id:1,name:'Thabo Mokoena',sport:'Football',position:'Midfielder',city:'Johannesburg'},caption:'Turning pressure into possibility. One touch, one chance, one goal.',hashtags:['Football','RisingTalent','SouthAfrica'],counts:{views:12840,likes:2840,comments:196,shares:84,saves:310}},
  {id:'demo-2',creator:{id:2,name:'Naledi Dlamini',sport:'Netball',position:'Goal Attack',city:'Pretoria'},caption:'Speed, vision and the courage to take the shot.',hashtags:['Netball','WomenInSport','NextGeneration'],counts:{views:9200,likes:1840,comments:122,shares:64,saves:205}},
  {id:'demo-3',creator:{id:3,name:'Lwazi Khumalo',sport:'Athletics',position:'Sprinter',city:'Durban'},caption:'The work nobody sees creates the result everybody remembers.',hashtags:['Sprinting','Training','RoadToGold'],counts:{views:7400,likes:1220,comments:88,shares:42,saves:174}},
];
export default function FeedScreen(){
  const user=useAuthStore(s=>s.user); const [gate,setGate]=useState(false); const {height}=useWindowDimensions(); const insets=useSafeAreaInsets(); const cardHeight=height-insets.top-70;
  const query=useQuery({queryKey:['feed','for-you'],queryFn:async()=> (await api.get('/feed/for-you')).data.data as Video[],enabled:Boolean(user)});
  const feed=query.data?.length?query.data:demos;
  const viewability=useRef({itemVisiblePercentThreshold:60}).current;
  const changed=useCallback(({viewableItems}:any)=>{const index=viewableItems[0]?.index??0;if(!user&&index>=2)setGate(true);},[user]);
  const protectedAction=useCallback(()=>{if(!user)setGate(true);},[user]);
  return <SafeAreaView edges={['top']} style={styles.safe}><View style={styles.header}><BrandMark/><Text style={styles.tab}>For You</Text></View>{query.isLoading&&user?<ActivityIndicator style={{flex:1}} color={colors.blue}/>:<FlatList data={feed} keyExtractor={item=>item.id} renderItem={({item,index})=><FeedCard video={item} index={index} height={cardHeight-52} onProtectedAction={protectedAction}/>} pagingEnabled showsVerticalScrollIndicator={false} scrollEnabled={!gate} onViewableItemsChanged={changed} viewabilityConfig={viewability} snapToAlignment="start" decelerationRate="fast"/>}<AuthGate visible={gate} onBack={()=>setGate(false)}/></SafeAreaView>;
}
const styles=StyleSheet.create({safe:{flex:1,backgroundColor:colors.navy},header:{height:52,paddingHorizontal:14,flexDirection:'row',alignItems:'center',justifyContent:'space-between',backgroundColor:'rgba(9,23,37,.98)',borderBottomWidth:1,borderBottomColor:colors.line},tab:{color:colors.white,fontSize:15,fontWeight:'900'}});
