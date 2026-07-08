<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdueReminder extends Notification
{
    use Queueable;

    public function __construct(private Invoice $invoice) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Invoice :number is overdue', ['number' => $this->invoice->number]))
            ->markdown('mail.invoice-overdue', ['invoice' => $this->invoice]);
    }
}
