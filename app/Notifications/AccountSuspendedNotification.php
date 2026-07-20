<?php

namespace App\Notifications;

use App\Models\PlatformSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone their account has been suspended, and why.
 *
 * Without this the only way to find out was to try signing in and fail, which could be
 * days later. The reason travels with the message because a person told they are locked
 * out with no explanation has nothing to act on.
 *
 * In-app always, email only when platform email is switched on — the same rule every
 * other notification here follows.
 */
class AccountSuspendedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ?string $reason = null) {}

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
        $message = (new MailMessage)
            ->subject('Your Vytte account has been suspended')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your Vytte account has been suspended, so you will not be able to sign in for now.');

        if ($this->reason) {
            $message->line('Reason given: '.$this->reason);
        }

        return $message
            ->line('Nothing has been deleted. Your workspaces, assessments and reports are all still there and will be exactly as you left them once access is restored.')
            ->line('If you think this is a mistake, reply to this email and our team will look into it.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'account_suspended',
            'title' => 'Your account has been suspended',
            'body' => $this->reason
                ? 'Reason given: '.$this->reason
                : 'Contact Vytte support to restore access.',
            'url' => null,
        ];
    }
}
