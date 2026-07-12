<?php
namespace App\Http\Controllers\Api\V1\Discovery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SearchLibraryController extends Controller {
    public function history(Request $r):JsonResponse { $items=DB::table('search_logs')->where('user_id',$r->user()->id)->whereNotNull('query')->select('query',DB::raw('MAX(created_at) as searched_at'))->groupBy('query')->orderByDesc('searched_at')->limit(15)->get();return response()->json(['data'=>$items]); }
    public function clear(Request $r):JsonResponse { DB::table('search_logs')->where('user_id',$r->user()->id)->delete();return response()->json(['message'=>'Search history cleared.']); }
    public function saved(Request $r):JsonResponse { return response()->json(['data'=>DB::table('saved_searches')->where('user_id',$r->user()->id)->latest()->get()->map(fn($i)=>[...((array)$i),'filters'=>json_decode($i->filters,true)])]); }
    public function store(Request $r):JsonResponse { $data=$r->validate(['name'=>['required','string','max:120'],'query'=>['nullable','string','max:255'],'filters'=>['nullable','array']]);$id=DB::table('saved_searches')->insertGetId(['user_id'=>$r->user()->id,'name'=>$data['name'],'query'=>$data['query']??null,'filters'=>json_encode($data['filters']??[]),'created_at'=>now(),'updated_at'=>now()]);return response()->json(['data'=>['id'=>$id,...$data]],201); }
    public function destroy(Request $r,int $id):JsonResponse { DB::table('saved_searches')->where('id',$id)->where('user_id',$r->user()->id)->delete();return response()->json([],204); }
}
