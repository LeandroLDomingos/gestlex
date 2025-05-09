<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        // cria um faker específico para pt_BR (CPF/CNPJ, CEP etc.)
        $fakerPtBr = \Faker\Factory::create('pt_BR');

        // escolhe aleatoriamente entre pessoa física ou jurídica
        $type = $fakerPtBr->randomElement(['physical', 'legal']);

        return [
            'type'               => $type,
            'name'               => $fakerPtBr->name(),
            // gera CPF (11 dígitos) ou CNPJ (14 dígitos) sem pontuação
            'cpf_cnpj'           => $type === 'physical'
                ? $fakerPtBr->cpf(false)
                : $fakerPtBr->cnpj(false),
            'rg'                 => $type === 'physical'
                ? $fakerPtBr->numerify('#########')
                : null,
            'gender'             => $type === 'physical'
                ? $fakerPtBr->randomElement(['female', 'male'])
                : null,
            'nationality'        => $type === 'physical'
                ? $fakerPtBr->country()
                : null,
            'marital_status'     => $fakerPtBr->randomElement([
                'single', 'married', 'common_law', 'divorced', 'widowed', 'separated'
            ]),
            'profession'         => $type === 'physical'
                ? $fakerPtBr->jobTitle()
                : null,
            'business_activity'  => $type === 'legal'
                ? $fakerPtBr->company()
                : null,
            'tax_state'          => $type === 'legal'
                ? $fakerPtBr->state()
                : null,
            'tax_city'           => $type === 'legal'
                ? $fakerPtBr->city()
                : null,
            'trade_name'         => $type === 'legal'
                ? $fakerPtBr->companySuffix()
                : null,
            'administrator_id'   => null, // ou outro factory/seed se desejar
            'zip_code'           => $fakerPtBr->postcode(),
            'number'     => $fakerPtBr->buildingNumber(),
            'complement' => $fakerPtBr->secondaryAddress(),
            // adicione aqui outros campos que seu model exigir...
        ];
    }
}
