<?php

namespace App\Policies;

use App\Models\DepartmentFrameworkVersion;
use App\Models\User;

class DepartmentFrameworkVersionPolicy
{
    public function create(User $user): bool
    {
        return $user->isCurator();
    }

    public function update(User $user, DepartmentFrameworkVersion $version): bool
    {
        return $user->isCurator() && $version->status !== DepartmentFrameworkVersion::STATUS_PUBLISHED;
    }

    public function publish(User $user, DepartmentFrameworkVersion $version): bool
    {
        return $user->isCurator() && $version->status === DepartmentFrameworkVersion::STATUS_DRAFT;
    }
}
