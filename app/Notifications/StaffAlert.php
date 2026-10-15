<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * An in-app message shown under the bell in the staff area.
 */
class StaffAlert extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
