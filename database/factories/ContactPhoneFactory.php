<?php

namespace Database\Factories;

use App\Models\ContactPhone; // Certifique-se de que este caminho está correto para o seu modelo ContactPhone
use App\Models\Contact;      // Necessário se você precisar referenciar o modelo Contact aqui
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactPhoneFactory extends Factory
{
    /**
     * O nome do modelo correspondente da factory.
     *
     * @var string
     */
    protected $model = ContactPhone::class;

    /**
     * Define o estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Cria um faker específico para pt_BR para gerar números de telefone no formato brasileiro
        $fakerPtBr = \Faker\Factory::create('pt_BR');

        return [
            // 'contact_id' normalmente será definido ao chamar esta factory
            // a partir da ContactFactory.
            
            // A coluna 'phone' na migração corresponde a 'number' aqui.
            // É comum usar 'phone' como chave na factory para corresponder ao nome da coluna.
            // Usando cellphoneNumber para gerar um número de celular.
            // O segundo parâmetro 'false' remove a formatação (pontos, traços).
            'phone' => $fakerPtBr->cellphoneNumber(false), 
            
            // O campo 'type' foi removido, pois não está na migração contact_phones.
            // O campo 'is_primary' foi removido, pois não está na migração contact_phones.
        ];
    }

    // O método de estado primary() foi removido, pois a coluna 'is_primary'
    // não está presente na migração contact_phones.
    // Se você adicionar uma coluna booleana 'is_primary' à sua migração posteriormente,
    // poderá adicionar novamente um método semelhante:
    /*
    public function primary(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'is_primary' => true,
            ];
        });
    }
    */
}
