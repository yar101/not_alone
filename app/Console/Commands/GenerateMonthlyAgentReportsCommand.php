<?php

namespace App\Console\Commands;

use App\Services\AgentReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyAgentReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent-reports:generate {--month= : Target month in YYYY-MM format (defaults to previous month)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate monthly agent reports pursuant to Art. 1008 of the Civil Code of RF (ст. 1008 ГК РФ)';

    public function handle(AgentReportService $service): int
    {
        $monthOption = $this->option('month');
        $targetMonth = $monthOption
            ? Carbon::createFromFormat('Y-m', $monthOption)->startOfMonth()
            : now()->subMonth()->startOfMonth();

        $this->info("Generating agent reports for {$targetMonth->format('Y-m')}...");

        $reports = $service->generateAllReportsForMonth($targetMonth);

        $this->info("Successfully generated {$reports->count()} agent reports.");

        // Also auto-accept any pending reports whose deadline has passed
        $autoAccepted = $service->autoAcceptExpiredReports();
        if ($autoAccepted > 0) {
            $this->info("Auto-accepted {$autoAccepted} expired agent reports (10 business days elapsed).");
        }

        return self::SUCCESS;
    }
}
