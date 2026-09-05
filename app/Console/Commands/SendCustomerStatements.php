<?php

namespace App\Console\Commands;

use App\Services\CustomerStatementService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendCustomerStatements extends Command
{
    protected $signature = 'customers:send-statements
                          {--date= : Statement date (YYYY-MM-DD), defaults to today}
                          {--customer= : Limit to a single customer id (for testing)}
                          {--dry-run : List who would be emailed without sending}';

    protected $description = 'Email a statement of account to opted-in customers carrying a balance';

    public function handle(CustomerStatementService $statements): int
    {
        $asOf = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no email will be sent.');
        } elseif (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER=log — messages go to the log, not to customers.');
        }

        $this->info('Statements as at '.$asOf->toDateString().'...');

        $result = $statements->sendStatements(
            $asOf,
            $dryRun,
            $this->option('customer') ? (int) $this->option('customer') : null
        );

        foreach ($result['details'] as $line) {
            $this->line('  '.$line);
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d  ·  Skipped: %d  ·  Failed: %d',
            $dryRun ? 'Would send' : 'Queued',
            $result['sent'],
            $result['skipped'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
