<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentShareLink;
use App\Models\PlatformSetting;
use App\Notifications\ReportSharedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Emails a report's shared link to a recipient — the shared path for on-demand sends and the
 * scheduler. Creates a read-only share link (the existing mechanism) and notifies the address;
 * it never sends the data itself, only a link.
 */
class ReportDeliveryService
{
    public function __construct(private readonly ReportSnapshotService $reports, private readonly AuditService $audit) {}

    /**
     * Send the report for a completed assessment to an email address.
     *
     * @throws \LogicException if the assessment is not complete
     */
    public function sendForAssessment(Assessment $assessment, string $email, ?string $message = null, bool $scheduled = false, ?string $creatorId = null): void
    {
        if ($assessment->status !== Assessment::STATUS_COMPLETE) {
            throw new \LogicException('Only completed assessments can be emailed.');
        }

        // Freeze the report if it has not been, so the shared link renders a stable snapshot.
        if (! $assessment->reportSnapshot()->exists()) {
            $this->reports->createFor($assessment);
        }

        $expiryDays = $scheduled ? 90 : (int) PlatformSetting::get('sharing.link_expiry_days', 30);
        $link = AssessmentShareLink::create([
            'assessment_id' => $assessment->assessment_id,
            'token' => Str::random(64),
            'created_by' => $creatorId ?? $assessment->published_by,
            'created_at' => now(),
            'expires_at' => now()->addDays($expiryDays),
            'is_active' => true,
        ]);

        $facilityName = $assessment->target?->name ?? 'your facility';
        Notification::route('mail', $email)
            ->notify(new ReportSharedNotification($link, $facilityName, $message, $scheduled));

        $this->audit->record('assessment.report.emailed', $assessment, newValues: [
            'recipient' => $email,
            'scheduled' => $scheduled,
            'link_id' => $link->link_id,
        ]);
    }
}
