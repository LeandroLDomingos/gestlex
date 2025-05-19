<?php

namespace App\Enums;

enum ContactMaritalStatus: string
{
    case SINGLE = 'single';
    case MARRIED = 'married';
    case COMMON_LAW = 'common_law';
    case DIVORCED = 'divorced';
    case WIDOWED = 'widowed';
    case SEPARATED = 'separated';
    // Adicione outros casos conforme necessário

    public function label(): string
    {
        return match ($this) {
            self::SINGLE => 'Solteiro(a)',
            self::MARRIED => 'Casado(a)',
            self::COMMON_LAW => 'União Estável',
            self::DIVORCED => 'Divorciado(a)',
            self::WIDOWED => 'Viúvo(a)',
            self::SEPARATED => 'Separado(a)',
            default => 'Desconhecido', // Fallback
        };
    }
}