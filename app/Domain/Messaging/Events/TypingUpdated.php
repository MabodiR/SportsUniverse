<?php
namespace App\Domain\Messaging\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
class TypingUpdated implements ShouldBroadcastNow {
 use Dispatchable;
 public function __construct(public string $conversationId,public int $userId,public string $name,public bool $typing){}
 public function broadcastOn():array{return [new PrivateChannel('conversations.'.$this->conversationId)];}
 public function broadcastAs():string{return 'typing.updated';}
 public function broadcastWith():array{return ['conversation_id'=>$this->conversationId,'user_id'=>$this->userId,'name'=>$this->name,'typing'=>$this->typing];}
}
