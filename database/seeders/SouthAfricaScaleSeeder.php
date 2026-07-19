<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SouthAfricaScaleSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'like', 'dummy.sa.%@sportuniverse.test')->count() >= 10000) {
            $this->command?->info('10,000 South African dummy users already exist.'); return;
        }

        $first = ['Thabo','Lerato','Sipho','Nomsa','Kagiso','Naledi','Lwazi','Amahle','Bongani','Zanele','Tshepo','Nandi','Siyabonga','Refilwe','Mpho','Ayanda','Tebogo','Nokuthula','Khanyisa','Oratile','Vuyani','Boitumelo','Sibusiso','Keabetswe','Lethabo','Onthatile','Aphiwe','Minentle','Rethabile','Nkosinathi'];
        $last = ['Mokoena','Ndlovu','Khumalo','Dlamini','Mthembu','Mabena','Molefe','Mahlangu','Nkosi','Maseko','Motaung','Mabaso','Mokoena','Mkhize','Mabunda','Mogale','Sithole','Modise','Mokoena','Mhlongo','Molepo','Mthethwa','Maboko','Ntuli','Baloyi','Radebe','Mahlangu','Msimang','Mokoena','Tshabalala'];
        $places = [
            ['Gauteng','Johannesburg','Soweto'],['Gauteng','Tshwane','Mamelodi'],['Gauteng','Ekurhuleni','Tembisa'],['Limpopo','Thohoyandou','Tshisaulu'],['Limpopo','Giyani','Shivulani'],['Limpopo','Makhado','Elim'],['Limpopo','Jane Furse','Ga-Matlala'],['Mpumalanga','Mbombela','KaNyamazane'],['Mpumalanga','Bushbuckridge','Acornhoek'],['Mpumalanga','Ermelo','Wesselton'],['KwaZulu-Natal','Durban','Umlazi'],['KwaZulu-Natal','Pietermaritzburg','Imbali'],['KwaZulu-Natal','Nongoma','KwaMshanelo'],['KwaZulu-Natal','Port Shepstone','Gam LakwaNzimakwe'],['Eastern Cape','Mthatha','Tsolo'],['Eastern Cape','Gqeberha','New Brighton'],['Eastern Cape','Qonce','Dimbaza'],['Eastern Cape','Komani','Ezibeleni'],['Free State','Bloemfontein','Botshabelo'],['Free State','QwaQwa','Phuthaditjhaba'],['North West','Rustenburg','Phokeng'],['North West','Mahikeng','Mmabatho'],['North West','Taung','Pampierstad'],['Northern Cape','Kimberley','Galeshewe'],['Northern Cape','Kuruman','Mothibistad'],['Northern Cape','Upington','Paballelo'],['Western Cape','Cape Town','Khayelitsha'],['Western Cape','George','Thembalethu'],['Western Cape','Worcester','Zwelethemba'],['Western Cape','Beaufort West','KwaMandlenkosi'],
        ];
        $institutions = ['Kaizer Chiefs Youth Development','Orlando Pirates Academy','Mamelodi Sundowns Academy','University of Pretoria','University of Johannesburg','University of the Witwatersrand','University of Limpopo','University of Venda','Walter Sisulu University','University of Fort Hare','Nelson Mandela University','Stellenbosch University','University of the Free State','North-West University','Durban University of Technology','Tshwane University of Technology','Mangosuthu University of Technology','Sol Plaatje University','TuksSport High School','SAFA Transnet Football School of Excellence','Grey College','Paarl Gimnasium','Dale College','Selborne College','Jeppe High School for Boys'];
        $bios = ['Committed athlete working to improve every day and represent the community with pride.','Focused on discipline, teamwork and creating opportunities through sport.','Developing talent through consistent training, local competition and education.','Passionate about sport, youth development and building strong community connections.'];
        $roles = ['athlete','athlete','athlete','athlete','fan','coach','referee','linesman','scout','agent','club','academy','business','sponsor'];
        foreach (array_unique($roles) as $role) Role::findOrCreate($role, 'web');
        $roleIds = Role::whereIn('name', array_unique($roles))->pluck('id','name');
        $sports = DB::table('sports')->get(); $positions = DB::table('positions')->get()->groupBy('sport_id');
        $password = Hash::make('Password123!'); $now = now();

        for ($offset=0; $offset<10000; $offset+=500) {
            $users=[];
            for ($i=$offset; $i<$offset+500; $i++) { $name=$first[$i%count($first)].' '.$last[($i*7)%count($last)]; $users[]=['name'=>$name,'email'=>"dummy.sa.{$i}@sportuniverse.test",'phone'=>'+27'.str_pad((string)(600000000+$i),9,'0',STR_PAD_LEFT),'email_verified_at'=>$now,'password'=>$password,'status'=>'active','created_at'=>$now,'updated_at'=>$now]; }
            DB::table('users')->insertOrIgnore($users);
        }
        $users = DB::table('users')->where('email','like','dummy.sa.%@sportuniverse.test')->orderBy('id')->get(['id','name','email']);
        $profiles=[];$athletes=[];$roleRows=[];
        foreach($users as $i=>$user){$place=$places[$i%count($places)];$role=$roles[$i%count($roles)];$sport=$sports[$i%max(1,$sports->count())]??null;$position=$sport?($positions->get($sport->id)?->values()[$i%max(1,$positions->get($sport->id)->count())]??null):null;$profiles[]=['user_id'=>$user->id,'slug'=>'sa-talent-'.$user->id,'date_of_birth'=>now()->subYears(14+($i%28))->subDays($i%365)->toDateString(),'gender'=>$i%2?'female':'male','bio'=>$bios[$i%count($bios)],'country'=>'ZA','province'=>$place[0],'city'=>$place[1],'locality'=>$place[2],'township'=>$place[2],'completeness'=>70+($i%31),'is_public'=>1,'is_available'=>$i%3?1:0,'views_count'=>$i%900,'created_at'=>$now,'updated_at'=>$now];$roleRows[]=['role_id'=>$roleIds[$role],'model_type'=>User::class,'model_id'=>$user->id];if($role==='athlete')$athletes[]=['user_id'=>$user->id,'sport_id'=>$sport?->id,'position_id'=>$position?->id,'club_name'=>$institutions[$i%count($institutions)],'playing_level'=>['school','academy','amateur','semi-professional'][$i%4],'dominant_side'=>['left','right','both'][$i%3],'height_cm'=>155+($i%46),'weight_kg'=>50+($i%55),'created_at'=>$now,'updated_at'=>$now];}
        foreach(array_chunk($profiles,500)as$c)DB::table('user_profiles')->insertOrIgnore($c);foreach(array_chunk($roleRows,500)as$c)DB::table('model_has_roles')->insertOrIgnore($c);foreach(array_chunk($athletes,500)as$c)DB::table('athlete_profiles')->insertOrIgnore($c);

        $ids=$users->pluck('id')->values();$follows=[];for($i=0;$i<30000;$i++){ $a=$ids[$i%$ids->count()];$b=$ids[($i*37+101)%$ids->count()];if($a!==$b)$follows["$a:$b"]=['follower_id'=>$a,'followed_id'=>$b,'created_at'=>$now,'updated_at'=>$now]; }foreach(array_chunk(array_values($follows),500)as$c)DB::table('follows')->insertOrIgnore($c);

        $source=DB::table('media')->where('kind','video')->where('processing_status','ready')->first();
        if($source){$media=[];for($i=0;$i<1500;$i++){$media[]=['public_id'=>(string)Str::ulid(),'user_id'=>$ids[$i%$ids->count()],'kind'=>'video','collection'=>'uploads','disk'=>$source->disk,'path'=>$source->path,'original_name'=>'dummy-highlight-'.$i.'.mp4','mime_type'=>'video/mp4','size_bytes'=>$source->size_bytes,'processing_status'=>'ready','moderation_status'=>'approved','thumbnail_path'=>$source->thumbnail_path,'duration_ms'=>$source->duration_ms,'width'=>$source->width,'height'=>$source->height,'processed_at'=>$now,'created_at'=>$now->copy()->subDays($i%60),'updated_at'=>$now];}foreach(array_chunk($media,300)as$c)DB::table('media')->insert($c);$createdMedia=DB::table('media')->where('original_name','like','dummy-highlight-%')->get();$videos=[];foreach($createdMedia as $i=>$m){$videos[]=['public_id'=>(string)Str::ulid(),'user_id'=>$m->user_id,'media_id'=>$m->id,'sport_id'=>$sports[$i%$sports->count()]->id,'caption'=>['Training with purpose and representing my community.','Match-day highlights from this weekend.','Every session is another step forward.','Local talent deserves a global stage.'][$i%4],'hashtags'=>json_encode(['SouthAfrica','RisingTalent',$sports[$i%$sports->count()]->name]),'visibility'=>'public','status'=>'published','views_count'=>50+($i*17)%25000,'likes_count'=>5+($i*7)%3000,'comments_count'=>$i%80,'shares_count'=>$i%50,'saves_count'=>$i%120,'published_at'=>$now->copy()->subHours($i%1000),'created_at'=>$now,'updated_at'=>$now];}foreach(array_chunk($videos,300)as$c)DB::table('videos')->insertOrIgnore($c);}

        $videoIds=DB::table('videos')->whereIn('user_id',$ids)->pluck('id');$comments=[];$likes=[];$saves=[];for($i=0;$i<min(12000,$videoIds->count()*8);$i++){$v=$videoIds[$i%$videoIds->count()];$u=$ids[($i*29)%$ids->count()];$likes["$v:$u"]=['video_id'=>$v,'user_id'=>$u,'created_at'=>$now];if($i%3===0)$saves["$v:$u"]=['video_id'=>$v,'user_id'=>$u,'created_at'=>$now];if($i%2===0)$comments[]=['public_id'=>(string)Str::ulid(),'video_id'=>$v,'user_id'=>$u,'body'=>['Strong performance—keep working!','Great vision and movement.','Proud to see local talent growing.','Please share more match footage.'][$i%4],'moderation_status'=>'approved','likes_count'=>$i%20,'created_at'=>$now->copy()->subHours($i%500),'updated_at'=>$now];}foreach(array_chunk(array_values($likes),500)as$c)DB::table('video_likes')->insertOrIgnore($c);foreach(array_chunk(array_values($saves),500)as$c)DB::table('saved_videos')->insertOrIgnore($c);foreach(array_chunk($comments,500)as$c)DB::table('comments')->insert($c);

        $posters=$users->filter(fn($u,$i)=>in_array($roles[$i%count($roles)],['club','academy','business','sponsor']))->values();$opps=[];for($i=0;$i<300&&$posters->isNotEmpty();$i++){$place=$places[$i%count($places)];$opps[]=['public_id'=>(string)Str::ulid(),'posted_by_id'=>$posters[$i%$posters->count()]->id,'sport_id'=>$sports[$i%$sports->count()]->id,'title'=>['Community Talent Trial','Youth Development Camp','Regional Scout Day','Sport Scholarship Assessment'][$i%4].' — '.$place[1],'type'=>['trial','training_camp','scout_day','job'][$i%4],'description'=>'A fictional demo opportunity associated with '.$institutions[$i%count($institutions)].' for SportsUniverse testing.','country'=>'ZA','province'=>$place[0],'city'=>$place[1],'is_remote'=>0,'minimum_age'=>14,'maximum_age'=>25,'requirements'=>json_encode(['Valid identification','Sporting profile','Recent highlight footage']),'status'=>'published','published_at'=>$now->copy()->subDays($i%30),'deadline'=>$now->copy()->addDays(14+($i%60)),'applications_count'=>$i%120,'created_at'=>$now,'updated_at'=>$now];}foreach(array_chunk($opps,200)as$c)DB::table('opportunities')->insert($c);
        $this->command?->info('Seeded 10,000 fictional South African users and demo content. Password: Password123!');
    }
}
