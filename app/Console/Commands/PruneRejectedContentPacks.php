<?php

namespace App\Console\Commands;

use App\Models\ContentPack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneRejectedContentPacks extends Command
{
    protected $signature = 'content-packs:prune-rejected';

    protected $description = 'Delete rejected content packs and their files older than 7 days';

    public function handle(): void
    {
        $disk = Storage::disk(config('filesystems.default'));

        $packs = ContentPack::query()
            ->where('status', 'rejected')
            ->where('updated_at', '<=', now()->subDays(7))
            ->with('photos')
            ->get();

        $count = 0;
        foreach ($packs as $pack) {
            if ($pack->cover_path && $disk->exists($pack->cover_path)) {
                $disk->delete($pack->cover_path);
            }

            foreach ($pack->photos as $photo) {
                if ($photo->path && $disk->exists($photo->path)) {
                    $disk->delete($photo->path);
                }
                $photo->delete();
            }

            $pack->forceDelete();
            $count++;
        }

        $this->info("Pruned {$count} rejected content pack(s).");
    }
}
