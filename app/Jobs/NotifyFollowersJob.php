<?php

namespace App\Jobs;

use App\Models\ContentPack;
use App\Models\Post;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewContentPackNotification;
use App\Notifications\NewPostNotification;
use App\Notifications\NewServiceNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class NotifyFollowersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly User $author,
        public readonly Post|ContentPack|Service $content
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $notification = match (true) {
            $this->content instanceof Post        => new NewPostNotification($this->content),
            $this->content instanceof ContentPack => new NewContentPackNotification($this->content),
            $this->content instanceof Service     => new NewServiceNotification($this->content),
            default => null,
        };

        if (!$notification) {
            return;
        }

        $this->author->followers()->chunk(200, function ($followers) use ($notification) {
            Notification::send($followers, $notification);
        });
    }
}
