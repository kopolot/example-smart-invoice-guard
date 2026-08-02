<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePdfGenerated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(private Invoice $invoice)
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->invoice->user_id),
        ];
    }

    /**
     * @return array{pdf_path: string|null, pdf_url: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'pdf_path' => $this->invoice->pdf_path,
            'pdf_url' => $this->invoice->pdf_url,
        ];
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }
}
