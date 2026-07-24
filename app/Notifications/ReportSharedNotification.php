<?php

namespace App\Notifications;

use App\Models\AssessmentShareLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a shared report link to a recipient — on demand or on a schedule.
 *
 * The email carries only a link to the read-only shared report (the existing share-link
 * mechanism); it never attaches the data itself. Sent to an arbitrary address via
 * Notification::route('mail', ...), so the recipient need not be a Vytte user.
 */
class ReportSharedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AssessmentShareLink $link,
        public readonly string $facilityName,
        public readonly ?string $customMessage = null,
        public readonly bool $scheduled = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->scheduled
                ? 'Scheduled report — '.$this->facilityName
                : 'A report has been shared with you — '.$this->facilityName)
            ->line($this->scheduled
                ? "The latest assessment report for \"{$this->facilityName}\" is ready."
                : "A Vytte assessment report for \"{$this->facilityName}\" has been shared with you.");

        if ($this->customMessage) {
            $mail->line('Message: '.$this->customMessage);
        }

        return $mail
            ->action('View report', route('reports.shared.token', $this->link->token))
            ->line('This is a read-only link. It expires automatically.');
    }
}
