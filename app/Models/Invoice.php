<?php

namespace App\Models;

use App\Casts\EncryptedData;
use App\Enums\InvoiceStatus;
use App\Models\Invoice\StatusHistory;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $number
 * @property float $amount
 * @property Carbon|null $date
 * @property Carbon|null $due_date
 * @property float $tax_rate
 * @property string $tax_number
 * @property float $total_amount
 * @property InvoiceStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $pdf_path
 * @property string|null $pdf_url
 * @property Carbon|null $sent_at
 * @property Carbon|null $overdue_reminded_at
 */
#[Fillable(['user_id', 'number', 'amount', 'date', 'due_date', 'tax_rate', 'tax_number', 'total_amount', 'status', 'pdf_path', 'sent_at', 'overdue_reminded_at'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    protected $appends = ['pdf_url'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'tax_number' => EncryptedData::class,
            'date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'overdue_reminded_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOverdueCandidates(Builder $query, ?CarbonInterface $asOf = null): void
    {
        $asOf ??= now();

        $query
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $asOf->toDateString())
            ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID]);
    }

    protected function pdfUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->pdf_path ? Storage::disk('public')->url($this->pdf_path) : null,
        );
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }
}
