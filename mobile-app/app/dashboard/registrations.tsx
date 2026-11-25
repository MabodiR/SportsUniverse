import { useState } from 'react';
import { Alert, ScrollView, Switch, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../src/api/client';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { colors } from '../../src/theme';

export default function Registrations() {
  const [pendingPage, setPendingPage] = useState(1);
  const [error, setError] = useState('');
  const query = useQuery({queryKey:['admin-registration',pendingPage],queryFn:async()=>(await api.get(`/admin/system-settings?pending_page=${pendingPage}`)).data.data});
  const data = query.data;
  const update = async (key:string,value:boolean) => {try {await api.patch('/admin/system-settings/registration',{...data.registration,[key]:value});await query.refetch();setError('');} catch(e:any) {setError(e.response?.data?.message||'Could not save settings.');}};
  const approve = (id:string) => Alert.alert('Approve registration?', 'This member will be able to sign in.', [{text:'Cancel',style:'cancel'},{text:'Approve',onPress:async()=>{try{await api.post(`/admin/registrations/${id}/approve`);await query.refetch();}catch(e:any){setError(e.response?.data?.message||'Could not approve registration.');}}}]);
  return <SafeAreaView style={{flex:1,backgroundColor:colors.navy}}><ScrollView contentContainerStyle={{padding:24,gap:18}}><Text style={{color:'#fff',fontSize:26,fontWeight:'800'}}>Registrations</Text><Text style={{color:colors.muted}}>{error || (query.isError?'Unable to load registrations.':query.isLoading?'Loading…':'Pending submissions stay saved when delivery fails.')}</Text>{data?.can_manage_registration?<View><Text style={{color:'#fff'}}>Require verification</Text><Switch value={data.registration.verification_required} onValueChange={value=>update('verification_required',value)}/><Text style={{color:'#fff'}}>Offer WhatsApp OTP</Text><Switch value={data.registration.whatsapp_enabled} onValueChange={value=>update('whatsapp_enabled',value)}/></View>:null}{data?.pending_registrations?.map((item:any)=><View key={item.id} style={{padding:16,backgroundColor:colors.surface,borderRadius:12,gap:8}}><Text style={{color:'#fff',fontWeight:'800'}}>{item.name}</Text><Text style={{color:colors.muted}}>{item.email} · {item.phone}{'\n'}{item.channel} · {item.delivery_status}</Text><PrimaryButton label="Approve registration" onPress={()=>approve(item.id)}/></View>)}{data && !data.pending_registrations.length?<Text style={{color:colors.muted}}>No pending registrations.</Text>:null}{data?<View><Text style={{color:colors.muted}}>{data.registration_pagination.total} pending · Page {pendingPage} of {data.registration_pagination.last_page}</Text>{pendingPage>1?<PrimaryButton label="Previous page" secondary onPress={()=>setPendingPage(page=>page-1)}/>:null}{pendingPage<data.registration_pagination.last_page?<PrimaryButton label="Next page" secondary onPress={()=>setPendingPage(page=>page+1)}/>:null}</View>:null}</ScrollView></SafeAreaView>;
}
