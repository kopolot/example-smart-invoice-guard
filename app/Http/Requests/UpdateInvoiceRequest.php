<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->invoice);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', Rule::unique('invoices', 'number')->ignore($this->invoice->id, 'id')],
            'amount' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0',
            'date' => 'required|date',
            'status' => 'required|in:' . implode(',', array_column(InvoiceStatus::cases(), 'value')),
        ];
    }
}
