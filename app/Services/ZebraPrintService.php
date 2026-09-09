<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for talking to the Zebra label printer.
 *
 * Replaces five byte-identical copies of the same lp shell block that used to live in
 * DeliveryLegacyController, LabelTranslationController, ZebraLabelController,
 * VoucherController and LabelAreaController (the last of which hardcoded the printer IP
 * and silently ignored ZEBRA_PRINTER_HOST).
 *
 * The spool lives on the CUPS server at services.zebra.host — NOT on the web server —
 * which is why clearing the local queue never had any effect. queue() and cancel()
 * exist so the app can see and clear the real one.
 */
class ZebraPrintService
{
    /** Seconds before a hung lp/lpstat call is killed, so a dead printer fails fast
     *  instead of riding the PHP/nginx request timeout. */
    protected int $timeout;

    /** Injectable command runner, so tests never shell out. */
    protected Closure $runner;

    public function __construct(
        protected ?string $host = null,
        protected ?string $port = null,
        protected ?string $printer = null,
        ?int $timeout = null,
        ?Closure $runner = null,
    ) {
        $this->host = $host ?? config('services.zebra.host', '10.42.1.71');
        $this->port = $port ?? config('services.zebra.port', '631');
        $this->printer = $printer ?? config('services.zebra.name', 'ZTC-GX430t');
        $this->timeout = $timeout ?? (int) config('services.zebra.timeout', 15);
        $this->runner = $runner ?? static fn (string $command): ?string => shell_exec($command);
    }

    /**
     * Swap the command runner. Intended for tests.
     */
    public function usingRunner(Closure $runner): static
    {
        $this->runner = $runner;

        return $this;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function printerName(): string
    {
        return $this->printer;
    }

    /**
     * Send raw ZPL to the printer as a single job.
     */
    public function sendRaw(string $zpl): ZebraPrintResult
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'zpl_');

        try {
            file_put_contents($tmpFile, $zpl);

            $command = sprintf(
                'timeout %d lp -h %s -d %s -o raw %s 2>&1',
                $this->timeout,
                escapeshellarg($this->destination()),
                escapeshellarg($this->printer),
                escapeshellarg($tmpFile)
            );

            return $this->interpret($command, $this->run($command));
        } finally {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Jobs currently sitting in the CUPS queue on the printer host.
     *
     * @return array{success: bool, jobs: array<int, array<string, string>>, output: string}
     */
    public function queue(): array
    {
        $command = sprintf(
            'timeout %d lpstat -h %s -o 2>&1',
            $this->timeout,
            escapeshellarg($this->destination())
        );

        $output = trim((string) $this->run($command));

        return [
            'success' => ! str_contains($output, 'lpstat:'),
            'jobs' => $this->parseQueue($output),
            'output' => $output,
        ];
    }

    /**
     * Printer/daemon status on the printer host.
     *
     * @return array{success: bool, output: string}
     */
    public function printerStatus(): array
    {
        $command = sprintf(
            'timeout %d lpstat -h %s -p -d 2>&1',
            $this->timeout,
            escapeshellarg($this->destination())
        );

        $output = trim((string) $this->run($command));

        return [
            'success' => ! str_contains($output, 'lpstat:'),
            'output' => $output,
        ];
    }

    /**
     * Cancel one queued job by its CUPS job id.
     */
    public function cancel(string $jobId): array
    {
        $command = sprintf(
            'timeout %d cancel -h %s %s 2>&1',
            $this->timeout,
            escapeshellarg($this->destination()),
            escapeshellarg($jobId)
        );

        return $this->cancelResult($command, $this->run($command));
    }

    /**
     * Cancel every queued job for this printer. This is the app-side equivalent of
     * `cancel -h 10.42.1.71:631 -a ZTC-GX430t`.
     */
    public function cancelAll(): array
    {
        $command = sprintf(
            'timeout %d cancel -h %s -a %s 2>&1',
            $this->timeout,
            escapeshellarg($this->destination()),
            escapeshellarg($this->printer)
        );

        return $this->cancelResult($command, $this->run($command));
    }

    /**
     * host:port/version=1.1 — the /version=1.1 forces IPP 1.1, which the GX-series needs.
     */
    protected function destination(): string
    {
        return "{$this->host}:{$this->port}/version=1.1";
    }

    protected function run(string $command): ?string
    {
        return ($this->runner)($command);
    }

    /**
     * Turn lp's output into a result. `timeout` exits 124 and prints nothing, which is
     * indistinguishable from a silent success at the shell — so a null/empty output is
     * treated as a timeout, never as a definitive failure.
     */
    protected function interpret(string $command, ?string $raw): ZebraPrintResult
    {
        $output = trim((string) $raw);

        if (preg_match('/request id is (\S+)/i', $output, $matches)) {
            return new ZebraPrintResult(
                success: true,
                jobId: $matches[1],
                output: $output,
                timedOut: false,
                command: $command,
            );
        }

        $timedOut = $output === '';

        if ($timedOut) {
            Log::warning('Zebra print job could not be confirmed (no lp output).', [
                'printer' => $this->printer,
                'host' => $this->destination(),
            ]);
        }

        return new ZebraPrintResult(
            success: false,
            jobId: null,
            output: $output === '' ? 'No output from lp (timed out or printer unreachable).' : $output,
            timedOut: $timedOut,
            command: $command,
        );
    }

    protected function cancelResult(string $command, ?string $raw): array
    {
        $output = trim((string) $raw);

        return [
            // cancel is silent on success.
            'success' => $output === '' || ! str_contains(strtolower($output), 'cancel:'),
            'output' => $output === '' ? 'Cancelled.' : $output,
            'command' => $command,
        ];
    }

    /**
     * Parse `lpstat -o` lines, e.g.
     *   ZTC-GX430t-42  jon  1024  Tue 09 Sep 2026 10:15:00 IST
     *
     * @return array<int, array<string, string>>
     */
    protected function parseQueue(string $output): array
    {
        $jobs = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_contains($line, 'lpstat:')) {
                continue;
            }

            if (preg_match('/^(\S+)\s+(\S+)\s+(\d+)\s+(.*)$/', $line, $m)) {
                $jobs[] = [
                    'job_id' => $m[1],
                    'user' => $m[2],
                    'size' => $m[3],
                    'submitted_at' => trim($m[4]),
                ];
            }
        }

        return $jobs;
    }
}
