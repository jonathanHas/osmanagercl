<?php

namespace App\Services\Deposits;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Installs, checks and rolls back the bottle-deposit event scripts on the
 * uniCenta POS the app points at (docs/deposit/, resources/pos/deposit/).
 *
 * Writes only RESOURCES: the two script rows, Ticket.Buttons, and the
 * Ticket.Buttons.pre-deposit backup row. RESOURCES has no Eloquent model; the
 * query builder is used (recorded exception, as for CATEGORIES / PRODUCTS_CAT).
 * The tills read these at start-up, so every till must restart afterwards.
 */
class DepositTillInstaller
{
    public const SCRIPTS = ['script.Deposit.AddLine', 'script.Deposit.Change'];

    public const BUTTONS = 'Ticket.Buttons';

    public const BACKUP = 'Ticket.Buttons.pre-deposit';

    /** The one existing event line the deposit events go directly after. */
    public const ANCHOR = '        <event key="ticket.close" code="Ticket.Close"/>'."\n";

    public const EVENT_LINES = [
        '        <event key="ticket.addline" code="script.Deposit.AddLine"/>'."\n",
        '        <event key="ticket.change" code="script.Deposit.Change"/>'."\n",
    ];

    /** The dev POS (the VirtualBox test till's database). */
    private const DEV = ['host' => '127.0.0.1', 'port' => '3307', 'database' => 'unicenta2016'];

    /**
     * @return array{host: string, port: string, database: string, is_dev: bool}
     */
    public function target(): array
    {
        $pos = $this->pos();
        $host = (string) $pos->getConfig('host');
        $port = (string) $pos->getConfig('port');
        $database = (string) $pos->getConfig('database');

        return [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'is_dev' => $host === self::DEV['host'] && $port === self::DEV['port'] && $database === self::DEV['database'],
        ];
    }

    /**
     * @return array{scripts: array<string, array{present: bool, matches_file: bool}>, wired: bool, anchor_count: int, backup_present: bool, installed: bool}
     */
    public function status(): array
    {
        $scripts = [];
        foreach (self::SCRIPTS as $name) {
            $content = $this->resource($name);
            $scripts[$name] = [
                'present' => $content !== null,
                'matches_file' => $content !== null && md5($content) === md5($this->scriptFile($name)),
            ];
        }

        $buttons = (string) $this->resource(self::BUTTONS);
        $wired = $this->wiredLines($buttons) === count(self::EVENT_LINES);

        return [
            'scripts' => $scripts,
            'wired' => $wired,
            'anchor_count' => substr_count($buttons, self::ANCHOR),
            'backup_present' => $this->resource(self::BACKUP) !== null,
            'installed' => $wired && collect($scripts)->every(fn ($s) => $s['matches_file']),
        ];
    }

    /**
     * @return array<int, string> what was done, in order
     */
    public function install(): array
    {
        $buttons = $this->resource(self::BUTTONS);
        if ($buttons === null) {
            throw new RuntimeException('No Ticket.Buttons resource on this POS.');
        }

        $wiredLines = $this->wiredLines($buttons);
        $wired = $wiredLines === count(self::EVENT_LINES);
        if ($wiredLines > 0 && ! $wired) {
            throw new RuntimeException('Ticket.Buttons has only part of the deposit events: fix it by hand, nothing was written.');
        }
        if (! $wired && substr_count($buttons, self::ANCHOR) !== 1) {
            throw new RuntimeException(sprintf(
                'The ticket.close event line occurs %d times in Ticket.Buttons (needs exactly 1): nothing was written.',
                substr_count($buttons, self::ANCHOR)
            ));
        }

        $actions = [];

        // (a) Backup before anything changes; never overwrite an existing one.
        if ($this->resource(self::BACKUP) !== null) {
            $actions[] = 'backup: already present (kept)';
        } elseif ($wired) {
            throw new RuntimeException('Ticket.Buttons is already wired but no backup row exists: nothing was written.');
        } else {
            $this->pos()->table('RESOURCES')->insert([
                'ID' => (string) Str::uuid(),
                'NAME' => self::BACKUP,
                'RESTYPE' => 0,
                'CONTENT' => $buttons,
            ]);
            $file = 'deposit/Ticket.Buttons.'.now()->format('Ymd-His').'.pre-deposit.xml';
            Storage::disk('local')->put($file, $buttons);
            $actions[] = 'backup: created '.self::BACKUP.' and storage/app/private/'.$file;
        }

        // (b) The two scripts, byte-for-byte from the resource files.
        foreach (self::SCRIPTS as $name) {
            $bytes = $this->scriptFile($name);
            $current = $this->resource($name);

            if (! $this->pos()->table('RESOURCES')->where('NAME', $name)->exists()) {
                $this->pos()->table('RESOURCES')->insert([
                    'ID' => (string) Str::uuid(),
                    'NAME' => $name,
                    'RESTYPE' => 0,
                    'CONTENT' => $bytes,
                ]);
                $actions[] = "$name: created";
            } elseif ($current !== $bytes) {
                $this->pos()->table('RESOURCES')->where('NAME', $name)->update(['CONTENT' => $bytes]);
                $actions[] = "$name: updated";
            } else {
                $actions[] = "$name: unchanged";
            }
        }

        // (c) The events, directly after the anchor.
        if ($wired) {
            $actions[] = 'Ticket.Buttons: already wired';
        } else {
            $this->pos()->table('RESOURCES')->where('NAME', self::BUTTONS)->update([
                'CONTENT' => str_replace(self::ANCHOR, self::ANCHOR.implode('', self::EVENT_LINES), $buttons),
            ]);
            $actions[] = 'Ticket.Buttons: wired';
        }

        return $actions;
    }

    /**
     * @return array<int, string> what was done, in order
     */
    public function rollback(): array
    {
        $backup = $this->resource(self::BACKUP);
        if ($backup === null) {
            throw new RuntimeException('No Ticket.Buttons.pre-deposit backup row: nothing was written.');
        }

        $actions = [];

        $current = $this->resource(self::BUTTONS);
        if ($current === $backup) {
            $actions[] = 'Ticket.Buttons: already the backup';
        } else {
            $this->pos()->table('RESOURCES')->where('NAME', self::BUTTONS)->update(['CONTENT' => $backup]);
            $actions[] = 'Ticket.Buttons: restored from '.self::BACKUP;
        }

        foreach (self::SCRIPTS as $name) {
            $deleted = $this->pos()->table('RESOURCES')->where('NAME', $name)->delete();
            $actions[] = "$name: ".($deleted ? 'deleted' : 'not present');
        }

        $actions[] = 'backup: kept';

        return $actions;
    }

    public function scriptFile(string $name): string
    {
        $path = resource_path("pos/deposit/$name.bsh");
        $bytes = is_file($path) ? file_get_contents($path) : false;

        if ($bytes === false) {
            throw new RuntimeException("Missing till script file $path.");
        }

        return $bytes;
    }

    private function wiredLines(string $buttons): int
    {
        return collect(self::EVENT_LINES)->filter(fn ($line) => str_contains($buttons, rtrim($line, "\n")))->count();
    }

    private function resource(string $name): ?string
    {
        $content = $this->pos()->table('RESOURCES')->where('NAME', $name)->value('CONTENT');

        return $content === null ? null : (string) $content;
    }

    private function pos(): Connection
    {
        return DB::connection('pos');
    }
}
