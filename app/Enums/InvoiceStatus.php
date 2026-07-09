<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case OVERDUE = 'overdue';

    /**
     * @return array<int, self>
     */
    public static function assignableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status !== self::OVERDUE,
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function assignableValues(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            self::assignableCases(),
        );
    }

    /**
     * @return array<int, self>
     */
    public static function openCases(): array
    {
        return [self::UNPAID, self::PARTIALLY_PAID, self::OVERDUE];
    }

    /**
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            self::openCases(),
        );
    }
}
