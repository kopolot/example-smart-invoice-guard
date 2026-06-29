<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use App\Casts\EncryptedData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Invoice\StatusHistory;

/**
 * @property int $id
 * @property string $number
 * @property float $amount
 * @property Carbon|null $date
 * @property float $tax_rate
 * @property EncryptedData $tax_number
 * @property float $total_amount
 * @property InvoiceStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $pdf_path
 * @property string|null $pdf_url
 * @property Carbon|null $sent_at
 */
#[Fillable(['user_id', 'number', 'amount', 'date', 'tax_rate', 'tax_number', 'total_amount', 'status', 'pdf_path', 'sent_at'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    protected $appends = ['pdf_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'tax_number' => EncryptedData::class,
        ];
    }

    protected function pdfUrl(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $this->pdf_path ? Storage::disk('public')->url($this->pdf_path) : null,
        );
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }
}
