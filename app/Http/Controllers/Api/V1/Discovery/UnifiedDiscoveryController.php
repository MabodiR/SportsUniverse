<?php
namespace App\Http\Controllers\Api\V1\Discovery;
use App\Domain\Feed\Models\Video;
use App\Domain\Opportunities\Models\Opportunity;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class UnifiedDiscoveryController extends Controller {
    public function __invoke(Request $request):JsonResponse {
        $request->validate(['q'=>['nullable','string','max:120']]);
        $user=$request->user();$term=trim((string)$request->query('q'));$like='%'.mb_strtolower($term).'%';$city=$user->profile?->city;
        $profiles=User::query()->where('status','active')->whereKeyNot($user->id)->whereHas('profile',fn($q)=>$q->where('is_public',true))
            ->when($term,fn($q)=>$q->where(fn($b)=>$b->whereRaw('LOWER(users.name) LIKE ?',[$like])->orWhereHas('profile',fn($p)=>$p->whereRaw('LOWER(bio) LIKE ?',[$like])->orWhereRaw('LOWER(city) LIKE ?',[$like])->orWhereRaw('LOWER(locality) LIKE ?',[$like]))->orWhereHas('athleteProfile.sport',fn($s)=>$s->whereRaw('LOWER(name) LIKE ?',[$like]))))
            ->with('roles','profile','athleteProfile.sport','organisationProfile')->withCount('followers')->limit(12)->get();
        $athletes=$profiles->reject(fn($p)=>$p->hasAnyRole(['club','academy']))->take(8)->map(fn($p)=>$this->profile($p));
        $clubs=$profiles->filter(fn($p)=>$p->hasAnyRole(['club','academy']))->take(8)->map(fn($p)=>$this->profile($p));
        $videos=Video::query()->where('status','published')->where('visibility','public')->when($term,fn($q)=>$q->where(fn($b)=>$b->whereRaw('LOWER(caption) LIKE ?',[$like])->orWhereRaw('LOWER(hashtags) LIKE ?',[$like])->orWhereHas('user',fn($u)=>$u->whereRaw('LOWER(name) LIKE ?',[$like]))))->with('user.profile','images')->latest('published_at')->limit(8)->get()->map(fn($v)=>['id'=>$v->public_id,'caption'=>$v->caption,'hashtags'=>$v->hashtags??[],'creator'=>['name'=>$v->user->name,'slug'=>$v->user->profile?->slug],'thumbnail'=>$v->images->first()?route('media.public',$v->images->first()):null,'stream'=>$v->media_id?route('videos.stream',$v):null,'views'=>$v->views_count]);
        $opportunities=Opportunity::query()->where('status','published')->where(fn($q)=>$q->whereNull('deadline')->orWhere('deadline','>=',now()))->when($term,fn($q)=>$q->where(fn($b)=>$b->whereRaw('LOWER(title) LIKE ?',[$like])->orWhereRaw('LOWER(description) LIKE ?',[$like])->orWhereRaw('LOWER(city) LIKE ?',[$like])))->with('poster.profile','poster.organisationProfile','sport')->latest('published_at')->limit(8)->get()->map(fn($o)=>['id'=>$o->public_id,'title'=>$o->title,'description'=>$o->description,'type'=>$o->type,'city'=>$o->city,'sport'=>$o->sport?->name,'poster'=>$o->poster->organisationProfile?->organisation_name??$o->poster->name]);
        $recent=Video::where('status','published')->where('published_at','>=',now()->subDays(14))->get(['hashtags'])->flatMap(fn($v)=>$v->hashtags??[])->map(fn($v)=>mb_strtolower($v))->countBy()->sortDesc()->take(12)->map(fn($count,$tag)=>['tag'=>$tag,'posts'=>$count])->values();
        $nearbyAthletes=$city?User::whereKeyNot($user->id)->whereHas('profile',fn($q)=>$q->where('is_public',true)->whereRaw('LOWER(city)=?',[mb_strtolower($city)]))->with('roles','profile','athleteProfile.sport','organisationProfile')->limit(8)->get()->map(fn($p)=>$this->profile($p)):collect();
        $nearbyOpportunities=$city?Opportunity::where('status','published')->whereRaw('LOWER(city)=?',[mb_strtolower($city)])->with('poster','sport')->limit(8)->get()->map(fn($o)=>['id'=>$o->public_id,'title'=>$o->title,'city'=>$o->city,'sport'=>$o->sport?->name]):collect();
        $followed=$user->following()->pluck('users.id');$recommended=User::whereKeyNot($user->id)->whereNotIn('id',$followed)->whereHas('profile',fn($q)=>$q->where('is_public',true))->with('roles','profile','athleteProfile.sport','organisationProfile')->withCount('followers')->orderByDesc('followers_count')->limit(8)->get()->map(fn($p)=>$this->profile($p));
        if($term)DB::table('search_logs')->insert(['user_id'=>$user->id,'query'=>$term,'filters'=>'{}','results_count'=>$athletes->count()+$clubs->count()+$videos->count()+$opportunities->count(),'duration_ms'=>0,'engine'=>'unified','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['data'=>compact('athletes','videos','clubs','opportunities'),'discovery'=>['trending_hashtags'=>$recent,'nearby'=>['city'=>$city,'athletes'=>$nearbyAthletes,'opportunities'=>$nearbyOpportunities],'recommended_accounts'=>$recommended]]);
    }
    private function profile(User $p):array { return ['id'=>$p->id,'name'=>$p->organisationProfile?->organisation_name??$p->name,'slug'=>$p->profile?->slug,'bio'=>$p->profile?->bio,'roles'=>$p->roles->pluck('name'),'city'=>$p->profile?->city,'sport'=>$p->athleteProfile?->sport?->name,'followers'=>(int)($p->followers_count??0),'image'=>$p->profile?->profile_image_path]; }
}
