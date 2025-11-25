<?php

namespace App\Support;

class AppVersion
{
    protected static ?string $cached = null;

    public static function current(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $configured = config('app.version');
        if (! empty($configured)) {
            return self::$cached = $configured;
        }

        $commit = self::resolveGitCommit();
        if ($commit !== null) {
            return self::$cached = substr($commit, 0, 7);
        }

        return self::$cached = 'dev';
    }

    protected static function resolveGitCommit(): ?string
    {
        $headPath = base_path('.git/HEAD');
        if (! is_readable($headPath)) {
            return null;
        }

        $head = trim((string) file_get_contents($headPath));
        if ($head === '') {
            return null;
        }

        if (str_starts_with($head, 'ref:')) {
            $refPath = base_path('.git/'.trim(substr($head, 4)));
            if (is_readable($refPath)) {
                return trim((string) file_get_contents($refPath));
            }

            return null;
        }

        return $head;
    }
}
