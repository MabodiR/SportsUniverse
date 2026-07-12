<?php

namespace App\Domain\Discovery\Jobs;

use App\Domain\Discovery\Contracts\ProfileIndexer;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexUserProfile implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId)
    {
        $this->onQueue('search');
    }

    public function handle(ProfileIndexer $indexer): void
    {
        $user = User::find($this->userId);
        if ($user) {
            $indexer->index($user);
        }
    }
}
