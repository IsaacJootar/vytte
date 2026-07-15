<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMember extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
    ];

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'workspace_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isOwner(): bool
    {
        return $this->role === 'OWNER';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['OWNER', 'ADMIN']);
    }
}
