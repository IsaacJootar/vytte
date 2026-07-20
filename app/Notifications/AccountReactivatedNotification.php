<?php

namespace App\Notifications;

use App\Models\PlatformSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone their access is back.
 *
 * Suspension is reversible, so the reversal has to be communicated too. Otherwise a
 * person who stopped trying to sign in has no way of learning they can start again.
 */
class AccountReactivatedNotification extends Notification
{
    use Queueable;

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
        return (new MailMessage)
            ->subject('Your Vytte account has been restored')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your Vytte account has been restored. You can sign in again and everything is exactly where you left it.')
            ->action('Sign in', route('login'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'account_reactivated',
            'title' => 'Your account has been restored',
            'body' => 'You can sign in again. Nothing was lost.',
            'url' => route('login'),
        ];
    }
}
