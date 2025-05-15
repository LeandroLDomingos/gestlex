<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactEmail; // Adicionar import
use App\Models\ContactPhone; // Adicionar import
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $fakerPtBr = \Faker\Factory::create('pt_BR');
        $type = $fakerPtBr->randomElement(['physical', 'legal']);

        $data = [
            'type' => $type,
            'name' => $type === 'physical' ? $fakerPtBr->name() : $fakerPtBr->company(),
            'cpf_cnpj' => $type === 'physical' ? $fakerPtBr->cpf(false) : $fakerPtBr->cnpj(false),
            'zip_code' => preg_replace('/[^0-9]/', '', $fakerPtBr->postcode()), // Remover formatação para guardar como string de números
            'address' => $fakerPtBr->streetName(),
            'neighborhood' => $fakerPtBr->word(),
            'number' => $fakerPtBr->buildingNumber(),
            'complement' => $fakerPtBr->optional(0.3)->secondaryAddress(),
            'city' => $fakerPtBr->city(),
            'state' => $fakerPtBr->stateAbbr(),
            'country' => 'Brasil',
            'marital_status' => $type === 'physical' ? $fakerPtBr->randomElement(['single', 'married', 'common_law', 'divorced', 'widowed', 'separated']) : null,
            'administrator_id' => null,
        ];

        if ($type === 'physical') {
            $data['rg'] = preg_replace('/[^0-9X]/i', '', $fakerPtBr->rg()); // Remover formatação
            $data['gender'] = $fakerPtBr->randomElement(['female', 'male', 'other']);
            $data['date_of_birth'] = $fakerPtBr->optional(0.8)->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d');
            $data['nationality'] = $fakerPtBr->optional(0.9)->country();
            $data['profession'] = $fakerPtBr->jobTitle();
        } else { // legal
            $data['business_activity'] = $fakerPtBr->bs();
            $data['tax_state'] = $fakerPtBr->state();
            $data['tax_city'] = $fakerPtBr->city();
            $data['trade_name'] = $fakerPtBr->company() . ' ' . $fakerPtBr->companySuffix();
        }

        return $data;
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Contact $contact) {
            // Criar 1 a 3 emails aleatórios
            ContactEmail::factory()->count(rand(1, 3))->create([
                'contact_id' => $contact->id,
            ]);

            // Criar 1 a 2 telefones aleatórios
            ContactPhone::factory()->count(rand(1, 2))->create([
                'contact_id' => $contact->id,
            ]);
        });
    }
}
