<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['physical', 'legal']);
        $isPhysical = $type === 'physical';

        $sharedData = [
            // 'id' => Str::uuid()->toString(), // Descomente se não estiver usando HasUuids no modelo Contact e o ID for UUID
            'type' => $type,
            'zip_code' => $this->faker->numerify('#####-###'),
            'address' => $this->faker->streetName(),
            'neighborhood' => $this->faker->word(), 
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'complement' => $this->faker->optional(0.3)->secondaryAddress(), 
            'number' => $this->faker->buildingNumber(),
            'created_at' => $this->faker->dateTimeBetween('-3 years', '-1 month'),
            'updated_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];

        if ($isPhysical) {
            return array_merge($sharedData, [
                'name' => $this->faker->name(),
                'business_name' => null,
                'cpf_cnpj' => $this->generateCpf(),
                'rg' => $this->faker->optional(0.8)->numerify('##.###.###-#'),
                'gender' => $this->faker->optional(0.9)->randomElement(['male', 'female', 'other', 'prefer_not_to_say']),
                'nationality' => $this->faker->optional(0.95)->randomElement(['BR', 'US', 'PT', 'AR']),
                'marital_status' => $this->faker->optional(0.9)->randomElement(['single', 'married', 'divorced', 'widowed', 'separated', 'common_law']),
                'profession' => $this->faker->optional(0.7)->jobTitle(),
                'date_of_birth' => $this->faker->optional(0.9)->date('Y-m-d', '-18 years'),
                'business_activity' => null,
                'tax_state' => null,
                'tax_city' => null,
                'administrator_id' => null,
            ]);
        } else { // Legal Person
            $tradeName = $this->faker->company();
            return array_merge($sharedData, [
                'name' => $tradeName, 
                'business_name' => $this->faker->company() . ' ' . $this->faker->companySuffix(),
                'cpf_cnpj' => $this->generateCnpj(),
                'rg' => null,
                'gender' => null,
                'nationality' => 'BR',
                'marital_status' => null,
                'profession' => null,
                'date_of_birth' => null,
                'business_activity' => $this->faker->sentence(rand(2, 5)), // CORRIGIDO AQUI
                'tax_state' => $this->faker->optional(0.8)->stateAbbr(),
                'tax_city' => $this->faker->optional(0.8)->city(),
                'administrator_id' => function () {
                    $physicalContact = Contact::where('type', 'physical')->inRandomOrder()->first();
                    if (!$physicalContact && Contact::count() < 50) { 
                        return Contact::factory()->physical()->create()->id;
                    }
                    return $physicalContact?->id; 
                },
            ]);
        }
    }

    /**
     * Indicate that the contact is a physical person.
     */
    public function physical(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'physical',
                'name' => $this->faker->name(),
                'business_name' => null,
                'cpf_cnpj' => $this->generateCpf(),
                'rg' => $this->faker->numerify('##.###.###-#'),
                'gender' => $this->faker->randomElement(['male', 'female', 'other', 'prefer_not_to_say']),
                'nationality' => 'BR',
                'marital_status' => $this->faker->randomElement(['single', 'married', 'divorced', 'widowed', 'separated', 'common_law']),
                'profession' => $this->faker->jobTitle(),
                'date_of_birth' => $this->faker->date('Y-m-d', '-18 years'),
                'business_activity' => null,
                'tax_state' => null,
                'tax_city' => null,
                'administrator_id' => null,
            ];
        });
    }

    /**
     * Indicate that the contact is a legal person.
     */
    public function legal(): Factory
    {
        return $this->state(function (array $attributes) {
            $tradeName = $this->faker->company();
            return [
                'type' => 'legal',
                'name' => $tradeName, 
                'business_name' => $this->faker->company() . ' ' . $this->faker->companySuffix(),
                'cpf_cnpj' => $this->generateCnpj(),
                'rg' => null,
                'gender' => null,
                'nationality' => 'BR',
                'marital_status' => null,
                'profession' => null,
                'date_of_birth' => null,
                'business_activity' => $this->faker->sentence(rand(2, 5)), // CORRIGIDO AQUI
                'tax_state' => $this->faker->stateAbbr(),
                'tax_city' => $this->faker->city(),
                'administrator_id' => function () {
                    $physicalContact = Contact::where('type', 'physical')->inRandomOrder()->first();
                    if (!$physicalContact && Contact::count() < 50) { 
                        return Contact::factory()->physical()->create()->id;
                    }
                    return $physicalContact?->id;
                },
            ];
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Contact $contact) {
            if ($this->faker->boolean(80)) { 
                ContactEmail::factory(rand(1, 3))->create([
                    'contact_id' => $contact->id,
                ]);
            }
            if ($this->faker->boolean(80)) { 
                ContactPhone::factory(rand(1, 2))->create([
                    'contact_id' => $contact->id,
                ]);
            }
        });
    }

    private function generateCpf(): string
    {
        $n1 = rand(0, 9); $n2 = rand(0, 9); $n3 = rand(0, 9);
        $n4 = rand(0, 9); $n5 = rand(0, 9); $n6 = rand(0, 9);
        $n7 = rand(0, 9); $n8 = rand(0, 9); $n9 = rand(0, 9);
        $d1 = $n9 * 2 + $n8 * 3 + $n7 * 4 + $n6 * 5 + $n5 * 6 + $n4 * 7 + $n3 * 8 + $n2 * 9 + $n1 * 10;
        $d1 = 11 - ($d1 % 11);
        if ($d1 >= 10) $d1 = 0;
        $d2 = $d1 * 2 + $n9 * 3 + $n8 * 4 + $n7 * 5 + $n6 * 6 + $n5 * 7 + $n4 * 8 + $n3 * 9 + $n2 * 10 + $n1 * 11;
        $d2 = 11 - ($d2 % 11);
        if ($d2 >= 10) $d2 = 0;
        return "$n1$n2$n3$n4$n5$n6$n7$n8$n9$d1$d2";
    }

    private function generateCnpj(): string
    {
        $n1 = rand(0, 9); $n2 = rand(0, 9); $n3 = rand(0, 9); $n4 = rand(0, 9);
        $n5 = rand(0, 9); $n6 = rand(0, 9); $n7 = rand(0, 9); $n8 = rand(0, 9);
        $n9 = 0; $n10 = 0; $n11 = 0; $n12 = 1; 
        $d1 = $n12*2 + $n11*3 + $n10*4 + $n9*5 + $n8*6 + $n7*7 + $n6*8 + $n5*9 + $n4*2 + $n3*3 + $n2*4 + $n1*5;
        $d1 = 11 - ($d1 % 11);
        if($d1 >= 10) $d1 = 0;
        $d2 = $d1*2 + $n12*3 + $n11*4 + $n10*5 + $n9*6 + $n8*7 + $n7*8 + $n6*9 + $n5*2 + $n4*3 + $n3*4 + $n2*5 + $n1*6;
        $d2 = 11 - ($d2 % 11);
        if($d2 >= 10) $d2 = 0;
        return "$n1$n2$n3$n4$n5$n6$n7$n8$n9$n10$n11$n12$d1$d2";
    }
}
