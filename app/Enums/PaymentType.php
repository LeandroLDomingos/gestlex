<?php

namespace App\Enums;

enum PaymentType: string
{
    case A_VISTA = 'a_vista';
    case PARCELADO = 'parcelado';
    case HONORARIO = 'honorario'; // NOVO TIPO PARA HONORÁRIOS

    // Método para obter valores para validação e frontend
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    // Ajuste este método se não quiser que "Honorário" apareça no select principal de tipo de pagamento no Create.vue
    public static function forFrontend(): array
    {
        return collect(self::cases())
            // Exemplo de filtro:
            // ->filter(fn($case) => $case->value !== self::HONORARIO->value)
            ->map(fn($case) => [
                'value' => $case->value,
                'label' => str_replace('_', ' ', ucfirst(str_replace('_', ' ', $case->name))) // Melhora a label para "A Vista"
            ])->values()->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::A_VISTA => 'À Vista',
            self::PARCELADO => 'Parcelado',
            self::HONORARIO => 'Honorário',
        };
    }
}