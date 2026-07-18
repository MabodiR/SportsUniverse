<?php
namespace App\Http\Resources\Api\V1\Feed;
use App\Domain\Media\Services\MediaDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class VideoResource extends JsonResource {
    public function toArray(Request $request): array {
        $hasImages=$this->relationLoaded('images') && $this->images->isNotEmpty();
        return [
            'id'=>$this->public_id,'type'=>$this->media?($hasImages?'carousel':'video'):'images',
            'caption'=>$this->caption,'hashtags'=>$this->hashtags??[],
            'location'=>['name'=>$this->location_name,'latitude'=>$this->latitude,'longitude'=>$this->longitude],
            'comments_enabled'=>(bool)$this->comments_enabled,'visibility'=>$this->visibility,'status'=>$this->status,'published_at'=>$this->published_at,'updated_at'=>$this->updated_at,
            'creator'=>['id'=>$this->user->id,'name'=>$this->user->name,'slug'=>$this->user->profile?->slug,'profile_image'=>$this->user->profile?->profile_image_path],
            'sport'=>$this->sport?->only(['id','name','slug']),
            'media'=>$this->media?['id'=>$this->media->public_id,'mime_type'=>$this->media->mime_type,'duration_ms'=>$this->media->duration_ms,'width'=>$this->media->width,'height'=>$this->media->height,'download_url'=>MediaDelivery::url($this->media),'renditions'=>$this->renditions($this->media)]:null,
            'images'=>$this->whenLoaded('images',fn()=>$this->images->map(fn($image)=>['id'=>$image->public_id,'download_url'=>MediaDelivery::url($image),'is_cover'=>(bool)$image->pivot->is_cover,'position'=>$image->pivot->position])->values()),
            'counts'=>['views'=>$this->views_count,'likes'=>$this->likes_count,'comments'=>$this->comments_count,'shares'=>$this->shares_count,'saves'=>$this->saves_count],
            'viewer'=>[
                'liked'=>(bool)($this->liked_by_viewer_exists??false),
                'saved'=>(bool)($this->saved_by_viewer_exists??false),
                'reposted'=>(bool)($this->reposted_by_viewer_exists??false),
                'following_creator'=>(bool)($this->creator_followed_by_viewer_exists??false),
                'is_owner'=>$request->user()?->id===$this->user_id,
                'can_manage'=>$request->user()?->id===$this->user_id,
            ],
        ];
    }
    private function renditions($media): array { return collect($media->metadata['renditions']??[])->map(fn($item)=>[...$item,'url'=>isset($item['path'])?MediaDelivery::url($media,$item['path']):null])->all(); }
}
