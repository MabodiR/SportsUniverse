import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '../../src/theme';

export default function TabLayout(){
  return <Tabs screenOptions={{headerShown:false,tabBarActiveTintColor:colors.blue,tabBarInactiveTintColor:'#71849B',tabBarStyle:{height:70,paddingTop:8,paddingBottom:10,backgroundColor:'#091725',borderTopColor:colors.line},tabBarLabelStyle:{fontSize:10,fontWeight:'700'}}}>
    <Tabs.Screen name="feed" options={{title:'For You',tabBarIcon:({color,size})=><Ionicons name="home" color={color} size={size}/>}}/>
    <Tabs.Screen name="explore" options={{title:'Explore',tabBarIcon:({color,size})=><Ionicons name="search" color={color} size={size}/>}}/>
    <Tabs.Screen name="opportunities" options={{title:'Opportunity',tabBarIcon:({color,size})=><Ionicons name="briefcase" color={color} size={size}/>}}/>
    <Tabs.Screen name="messages" options={{title:'Messages',tabBarIcon:({color,size})=><Ionicons name="chatbubble" color={color} size={size}/>}}/>
    <Tabs.Screen name="profile" options={{title:'Profile',tabBarIcon:({color,size})=><Ionicons name="person" color={color} size={size}/>}}/>
  </Tabs>;
}
