<?php

namespace App\Notifications;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberJoinedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $newMember,
        public readonly Workspace $workspace
    ) {}

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
            ->subject('New member joined — Vytte')
            ->greeting('Hi '.$notifiable->name.',')
            ->line("{$this->newMember->name} has joined your workspace \"{$this->workspace->name}\".")
            ->action('View Team', route('team.index'))
            ->line('You can manage roles and members from the Team page.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'member_joined',
            'title' => 'New team member',
            'body' => "{$this->newMember->name} joined {$this->workspace->name}.",
            'url' => route('team.index'),
            'user_id' => $this->newMember->user_id,
            'workspace_id' => $this->workspace->workspace_id,
        ];
    }
}
