<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class AutoCompleteOrders extends Command
{
    protected $signature   = 'orders:auto-complete';
    protected $description = 'Auto-complete paid orders after 72 hours';

    public function __construct(private OrderService $service)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $count = $this->service->autoComplete();
        $this->info("Auto-completed {$count} order(s).");
    }
}
