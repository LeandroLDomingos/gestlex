<?php

namespace App\Enums;

enum TransactionNature: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';

    /**
     * Get the label for the transaction nature.
     */
    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Receita',
            self::EXPENSE => 'Despesa',
        };
    }

    /**
     * Get all cases for frontend select/display.
     */
    public static function forFrontend(): array
    {
        return collect(self::cases())
            ->map(fn($case) => [
                'value' => $case->value,
                'label' => $case->label()
            ])->values()->all();
    }
}
