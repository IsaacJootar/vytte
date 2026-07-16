<?php

namespace App\Notifications;

use App\Models\Assessment;
use App\Models\PlatformSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssessmentCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Assessment $assessment) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (PlatformSetting::get('email.notifications_enabled', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $targetName = $this->assessment->target?->name ?? 'a target';

        return (new MailMessage)
            ->subject('Assessment complete — Vytte')
            ->greeting('Hi '.$notifiable->name.',')
            ->line("An assessment for \"{$targetName}\" has been completed.")
            ->action('View Results', route('assessments.results', $this->assessment->assessment_id))
            ->line('Log in to Vytte to see the full score breakdown and findings.');
    }

    public function toDatabase(object $notifiable): array
    {
        $targetName = $this->assessment->target?->name ?? 'a target';

        return [
            'type' => 'assessment_complete',
            'title' => 'Assessment complete',
            'body' => "Assessment for \"{$targetName}\" is complete and scores are ready.",
            'url' => route('assessments.results', $this->assessment->assessment_id),
            'assessment_id' => $this->assessment->assessment_id,
        ];
    }
}
