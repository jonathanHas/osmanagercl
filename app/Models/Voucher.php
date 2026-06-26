<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Status constants.
     */
    const STATUS_INACTIVE = 'inactive';   // generated/printed, not yet sold

    const STATUS_ACTIVE = 'active';       // issued with a balance

    const STATUS_EXHAUSTED = 'exhausted'; // balance fully spent

    const STATUS_DEACTIVATED = 'deactivated'; // admin disabled (balance preserved, not redeemable)

    /**
     * Code generation: prefix + unambiguous uppercase charset (no 0/O/1/I).
     */
    const CODE_PREFIX = 'GV';

    const CODE_CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    const CODE_LENGTH = 10;

    protected $fillable = [
        'code',
        'initial_value',
        'current_balance',
        'status',
        'created_by',
    ];

    protected $casts = [
        'initial_value' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(VoucherTransaction::class)->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isExhausted(): bool
    {
        return $this->status === self::STATUS_EXHAUSTED;
    }

    public function isDeactivated(): bool
    {
        return $this->status === self::STATUS_DEACTIVATED;
    }

    /**
     * Build a single Zebra ZPL label for this voucher's code.
     *
     * Small label preset (config/label-sizes.php → 'small'): 56×30mm = 673×366 dots
     * at ~12 dpmm/300 dpi. Renders a "GIFT VOUCHER" title, a CODE-128 barcode and the
     * human-readable code beneath it (the trailing `Y` on ^BC prints the text line).
     *
     * Codes are fixed-length uppercase alphanumeric (GV + 10 chars), so the barcode
     * width is constant and a fixed x-origin centres reliably. No ZPL escaping needed
     * as the charset excludes ^, ~ and \.
     */
    public function toZplLabel(): string
    {
        return implode("\n", [
            '^XA',
            '^CI28',
            '^PW673',
            '^LL366',
            '^FO0,28^A0N,36,36^FB673,1,0,C,0^FDGIFT VOUCHER^FS',
            '^BY3,3,110',
            '^FO110,95^BCN,110,Y,N,N',
            '^FD'.$this->code.'^FS',
            '^XZ',
        ])."\n";
    }

    /**
     * Generate a unique, non-sequential voucher code (CSPRNG).
     *
     * @throws \RuntimeException when a unique code cannot be found after several attempts
     */
    public static function generateUniqueCode(): string
    {
        $charsetLength = strlen(self::CODE_CHARSET);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = self::CODE_PREFIX;
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_CHARSET[random_int(0, $charsetLength - 1)];
            }

            if (! self::where('code', $code)->withTrashed()->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate a unique voucher code.');
    }
}
