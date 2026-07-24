<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring instruction to email a project's latest report to a recipient.
 *
 * Workspace-scoped. The scheduler command reads active schedules whose next run is due, sends
 * the report link, and advances the cadence.
 */
class ReportSchedule extends Model
{
    use BelongsToWorkspace, HasFactory, HasUuids;

    protected $primaryKey = 'report_schedule_id';

    public const FREQUENCIES = ['WEEKLY', 'MONTHLY', 'QUARTERLY'];

    protected $fillable = [
        'workspace_id', 'project_id', 'recipient_email', 'frequency',
        'next_run_at', 'last_run_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    /**
     * The next run time for this cadence, measured from a given moment.
     */
    public function advanceFrom(CarbonInterface $from): CarbonInterface
    {
        return match ($this->frequency) {
            'WEEKLY' => $from->copy()->addWeek(),
            'QUARTERLY' => $from->copy()->addMonths(3),
            default => $from->copy()->addMonth(),
        };
    }
}
