<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of a customer request: a stocked product (product_code = POS
 * PRODUCTS.CODE) or a free-text item to source, with its own status lifecycle.
 */
class CustomerRequestItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_PUT_ASIDE = 'put_aside';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_NOT_AVAILABLE = 'not_available';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ORDERED,
        self::STATUS_PUT_ASIDE,
        self::STATUS_COLLECTED,
        self::STATUS_NOT_AVAILABLE,
        self::STATUS_CANCELLED,
    ];

    /** Statuses where the line still needs something to happen. */
    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ORDERED,
        self::STATUS_PUT_ASIDE,
    ];

    /** Statuses where the item has not physically arrived yet — what the delivery screen flags. */
    public const AWAITING_ARRIVAL = [
        self::STATUS_PENDING,
        self::STATUS_ORDERED,
    ];

    public const LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_ORDERED => 'Ordered',
        self::STATUS_PUT_ASIDE => 'Put aside',
        self::STATUS_COLLECTED => 'Collected',
        self::STATUS_NOT_AVAILABLE => 'Not available',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * Statuses that take a line off the working board: it is finished, one way
     * or another.
     */
    public const DONE_STATUSES = [
        self::STATUS_COLLECTED,
        self::STATUS_NOT_AVAILABLE,
        self::STATUS_CANCELLED,
    ];

    /**
     * Which statuses a line may move to from each status. The "backwards"
     * moves exist to undo a mis-tap on the shop-floor tablet.
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_ORDERED, self::STATUS_PUT_ASIDE, self::STATUS_NOT_AVAILABLE, self::STATUS_CANCELLED],
        self::STATUS_ORDERED => [self::STATUS_PUT_ASIDE, self::STATUS_NOT_AVAILABLE, self::STATUS_CANCELLED, self::STATUS_PENDING],
        self::STATUS_PUT_ASIDE => [self::STATUS_COLLECTED, self::STATUS_ORDERED, self::STATUS_CANCELLED],
        self::STATUS_COLLECTED => [self::STATUS_PUT_ASIDE],
        self::STATUS_NOT_AVAILABLE => [self::STATUS_PENDING],
        self::STATUS_CANCELLED => [self::STATUS_PENDING],
    ];

    protected $fillable = [
        'customer_request_id',
        'product_code',
        'product_name',
        'description',
        'quantity',
        'notes',
        'position',
        'status',
        'status_changed_at',
        'status_changed_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'position' => 'integer',
            'status_changed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'customer_request_id');
    }

    /**
     * The POS product this line refers to (cross-connection, no FK).
     * Lazy use only — never eager-load in lists; product_name is the snapshot to show.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_code', 'CODE');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(CustomerRequestItemStatusLog::class)->orderBy('id');
    }

    public function statusChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeAwaitingArrival(Builder $query): Builder
    {
        return $query->whereIn('status', self::AWAITING_ARRIVAL);
    }

    public function scopeForBarcodes(Builder $query, array $barcodes): Builder
    {
        return $query->whereIn('product_code', array_values(array_unique(array_map('strval', $barcodes))));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Statuses the line can move to from where it is now.
     *
     * @return array<int, string>
     */
    public function nextStatuses(): array
    {
        return self::ALLOWED_TRANSITIONS[$this->status] ?? [];
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public static function labelFor(string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * What to call this line in the UI.
     */
    public function label(): string
    {
        return $this->product_name ?: $this->description;
    }

    public function isLinkedToProduct(): bool
    {
        return $this->product_code !== null && $this->product_code !== '';
    }
}
