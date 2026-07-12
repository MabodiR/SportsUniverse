<?php
namespace App\Domain\Messaging\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
class ConversationRead implements ShouldBroadcastNow {
 use Dispatchable;
 public function __construct(public string $conversationId,public int $userId,public string $readAt){}
 public function broadcastOn():array{return [new PrivateChannel('conversations.'.$this->conversationId)];}
 public function broadcastAs():string{return 'conversation.read';}
 public function broadcastWith():array{return ['conversation_id'=>$this->conversationId,'user_id'=>$this->userId,'read_at'=>$this->readAt];}
}
