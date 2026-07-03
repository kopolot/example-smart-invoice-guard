<?php

namespace Tests\Unit\Casts;

use App\Casts\EncryptedData;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EncryptedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_null_when_value_is_null(): void
    {
        $cast = new EncryptedData;

        $this->assertNull($cast->get(new Invoice, 'tax_number', null, []));
    }

    public function test_set_returns_null_when_value_is_null(): void
    {
        $cast = new EncryptedData;

        $this->assertNull($cast->set(new Invoice, 'tax_number', null, []));
    }

    public function test_get_decrypts_stored_value(): void
    {
        $cast = new EncryptedData;
        $encrypted = Crypt::encryptString('secret-tax-number');

        $this->assertSame(
            'secret-tax-number',
            $cast->get(new Invoice, 'tax_number', $encrypted, [])
        );
    }
}
