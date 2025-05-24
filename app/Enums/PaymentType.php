<?php

namespace App\Enums;

enum PaymentType: string
{
    case A_VISTA = 'a_vista';
    case PARCELADO = 'parcelado';

    public function label(): string
    {
        return match ($this) {
            self::A_VISTA => 'À Vista',
            self::PARCELADO => 'Parcelado',
        };
    }

    /**
     * Retorna todos os casos como um array associativo para selects.
     * @return array<string, string>
     */
    public static function asSelectArray(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label()
        ])->all();
    }

    /**
     * Retorna todos os casos como um array de objetos para selects no frontend.
     * @return array<object>
     */
    public static function forFrontend(): array
    {
        return collect(self::cases())->map(fn ($case) => (object) [
            'value' => $case->value,
            'label' => $case->label(),
        ])->all();
    }
}

