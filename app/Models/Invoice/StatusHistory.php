<?php

namespace App\Models\Invoice;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */

#[Table('invoice_status_histories')]
#[Fillable(['invoice_id', 'status'])]
#[Hidden(['created_at', 'updated_at'])]
class StatusHistory extends Model
{
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
