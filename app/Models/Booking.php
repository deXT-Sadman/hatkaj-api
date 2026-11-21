<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'customer_name',
        'customer_phone',
        'address',
        'scheduled_at',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'status' => BookingStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<BookingStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class);
    }

    /**
     * Bookings that still hold their time slot (pending or confirmed).
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeBlockingSlot(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::blockingSlot());
    }
}
