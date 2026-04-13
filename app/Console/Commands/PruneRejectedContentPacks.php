<?php

namespace App\Console\Commands;

use App\Models\ContentPack;
use Illuminate\Console\Command;

class PruneRejectedContentPacks extends Command
{
    protected $signature   = 'content-packs:prune-rejected';
    protected $description = 'Delete rejected content packs older than 7 days';

    public function handle(): void
    {
        $count = ContentPack::query()
            ->where('status', 'rejected')
            ->where('updated_at', '<=', now()->subDays(7))
            ->get()
            ->each(fn (ContentPack $pack) => $pack->delete())
            ->count();

        $this->info("Pruned {$count} rejected content pack(s).");
    }
}
