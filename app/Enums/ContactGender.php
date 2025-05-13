<?php

namespace App\Enums;

enum ContactGender: string
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';

    public function label(): string
    {
        return match($this) {
            self::Female => 'Feminino',
            self::Male => 'Masculino',
            self::Other => 'Outro',
        };
    }
}