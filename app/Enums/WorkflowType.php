<?php

namespace App\Enums;

enum WorkflowType: string
{
    case Prospecting    = 'prospecting';
    case Consultative   = 'consultative';
    case Administrative = 'administrative';
    case Judicial       = 'judicial';

    public function label(): string
    {
        return match($this) {
            WorkflowType::Prospecting    => 'Prospecção',
            WorkflowType::Consultative   => 'Consultivo',
            WorkflowType::Administrative => 'Administrativo',
            WorkflowType::Judicial       => 'Judicial',
        };
    }
}