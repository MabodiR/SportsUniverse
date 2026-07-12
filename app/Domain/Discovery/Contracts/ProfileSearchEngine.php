<?php

namespace App\Domain\Discovery\Contracts;

interface ProfileSearchEngine
{
    public function search(array $criteria): array;
}
