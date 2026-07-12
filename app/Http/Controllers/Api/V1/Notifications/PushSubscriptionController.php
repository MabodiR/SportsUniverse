<?php
namespace App\Http\Controllers\Api\V1\Notifications;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PushSubscriptionController extends Controller {
 public function store(Request $r):JsonResponse{$d=$r->validate(['endpoint'=>['required','url'],'keys.p256dh'=>['required','string'],'keys.auth'=>['required','string'],'content_encoding'=>['nullable','string','max:30']]);DB::table('push_subscriptions')->updateOrInsert(['endpoint'=>$d['endpoint']],['user_id'=>$r->user()->id,'public_key'=>$d['keys']['p256dh'],'auth_token'=>$d['keys']['auth'],'content_encoding'=>$d['content_encoding']??'aes128gcm','updated_at'=>now(),'created_at'=>now()]);return response()->json(['message'=>'Push subscription saved.'],201);}
 public function destroy(Request $r):JsonResponse{$d=$r->validate(['endpoint'=>['required','url']]);DB::table('push_subscriptions')->where('user_id',$r->user()->id)->where('endpoint',$d['endpoint'])->delete();return response()->json([],204);}
}
