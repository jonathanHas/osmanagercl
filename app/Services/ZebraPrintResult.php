<?php

namespace App\Services;

/**
 * Outcome of a single lp invocation.
 *
 * `timedOut` is kept distinct from a plain failure on purpose: when the printer stops
 * responding, CUPS may still have accepted the job. The caller must not treat that as
 * "nothing was printed" and offer a retry that duplicates the job.
 */
class ZebraPrintResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $jobId = null,
        public readonly string $output = '',
        public readonly bool $timedOut = false,
        public readonly string $command = '',
    ) {}

    /**
     * The printer definitively refused the job: lp ran to completion and returned no
     * request id. Only this case is safe to roll back and retry.
     */
    public function definitivelyFailed(): bool
    {
        return ! $this->success && ! $this->timedOut;
    }
}
