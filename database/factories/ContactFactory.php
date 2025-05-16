<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $fakerPtBr = \Faker\Factory::create('pt_BR');
        $faker = $this->faker; // Instância padrão do Faker

        $type = $fakerPtBr->randomElement(['physical', 'legal']);

        // Base data common to all types
        $data = [
            'type'               => $type,
            'name'               => $type === 'physical' ? $fakerPtBr->name() : $fakerPtBr->company(),
            'cpf_cnpj'           => $type === 'physical'
                ? $fakerPtBr->cpf(false)
                : $fakerPtBr->cnpj(false),
            
            // Endereço
            'zip_code'           => $fakerPtBr->postcode(),
            'address'            => $fakerPtBr->streetName(),
            'number'             => $fakerPtBr->buildingNumber(),
            'complement'         => $fakerPtBr->optional()->secondaryAddress(),
            'neighborhood'       => $fakerPtBr->randomElement(['Vila', 'Jardim', 'Bairro', 'Setor', 'Parque']) . ' ' . $fakerPtBr->lastName(),
            'city'               => $fakerPtBr->city(),
            'state'              => $fakerPtBr->stateAbbr(),
            'country'            => 'Brasil',
            'administrator_id'   => null,
        ];

        if ($type === 'physical') {
            // Campos específicos para Pessoa Física
            $data['rg'] = $fakerPtBr->numerify('#########');
            $data['gender'] = $fakerPtBr->randomElement(['female', 'male']);
            $data['nationality'] = $fakerPtBr->country();
            $data['marital_status'] = $fakerPtBr->randomElement([
                'single', 'married', 'common_law', 'divorced', 'widowed', 'separated'
            ]);
            $data['profession'] = $fakerPtBr->jobTitle();
            $data['date_of_birth'] = $fakerPtBr->date('Y-m-d', '-18 years');

            // Garante que campos de Pessoa Jurídica não sejam enviados se não forem nulos no DB
            $data['business_name'] = null;
            $data['business_activity'] = null;
            $data['tax_state'] = null;
            $data['tax_city'] = null;

        } elseif ($type === 'legal') {
            // Campos específicos para Pessoa Jurídica
            $data['business_name'] = $faker->company() . ' ' . $faker->companySuffix();
            $data['business_activity'] = $faker->text(80);
            $data['tax_state'] = $fakerPtBr->state();
            $data['tax_city'] = $fakerPtBr->city();

            // Garante que campos de Pessoa Física não sejam enviados se não forem nulos no DB
            $data['rg'] = null;
            $data['gender'] = null;
            $data['nationality'] = null;
            $data['marital_status'] = null;
            $data['profession'] = null;
            $data['date_of_birth'] = null;
        }

        return $data;
    }

    /**
     * Configura o estado do modelo após a sua criação.
     *
     * @return $this
     */
    public function configure()
    {
        return $this->afterCreating(function (Contact $contact) {
            // Cria um número aleatório de emails (ex: 1 a 3) para o contato
            ContactEmail::factory()->count($this->faker->numberBetween(1, 3))->create([
                'contact_id' => $contact->id,
            ]);

            // REMOVIDA a lógica para definir um email como primário,
            // pois a coluna 'is_primary' não existe na tabela 'contact_emails'.
            /*
            if ($contact->emails()->exists()) {
                $primaryEmail = $contact->emails()->inRandomOrder()->first();
                if ($primaryEmail) {
                    // Esta linha causava o erro se a coluna 'is_primary' não existisse:
                    // $primaryEmail->is_primary = true; 
                    // $primaryEmail->save();
                }
            }
            */

            // Cria um número aleatório de telefones (ex: 1 a 2) para o contato
            ContactPhone::factory()->count($this->faker->numberBetween(1, 2))->create([
                'contact_id' => $contact->id,
            ]);

            // REMOVIDA a lógica para definir um telefone como primário,
            // pois a coluna 'is_primary' não existe na tabela 'contact_phones'.
            /*
             if ($contact->phones()->exists()) {
                $primaryPhone = $contact->phones()->inRandomOrder()->first();
                if ($primaryPhone) {
                    // Esta linha causava o erro se a coluna 'is_primary' não existisse:
                    // $primaryPhone->is_primary = true;
                    // $primaryPhone->save();
                }
            }
            */
        });
    }
}
