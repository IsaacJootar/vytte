<?php

namespace App\Policies;

use App\Models\QuestionVersion;
use App\Models\User;

class QuestionVersionPolicy
{
    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, QuestionVersion $version): bool
    {
        return $user->isPlatformAdmin() && $version->status !== QuestionVersion::STATUS_PUBLISHED;
    }

    public function publish(User $user, QuestionVersion $version): bool
    {
        return $user->isPlatformAdmin() && $version->status === QuestionVersion::STATUS_APPROVED;
    }
}
