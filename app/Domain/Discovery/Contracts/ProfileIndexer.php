<?php

namespace App\Domain\Discovery\Contracts;

use App\Models\User;

interface ProfileIndexer
{
    public function index(User $user): void;

    public function delete(User $user): void;
}
