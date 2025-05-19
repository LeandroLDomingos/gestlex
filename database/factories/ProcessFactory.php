<?php

namespace Database\Factories;

use App\Models\Process;
use App\Models\User;    // Certifique-se de que o modelo User existe e está no namespace correto
use App\Models\Contact; // Importar o modelo Contact
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str; // Para gerar UUIDs, se necessário

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Process>
 */
class ProcessFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Process::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workflowStates = ['prospecting', 'consultative', 'administrative', 'judicial'];

        // Determina aleatoriamente o workflow e depois os estágios possíveis para esse workflow
        $selectedWorkflow = $this->faker->randomElement($workflowStates);
        $possibleStages = Process::getStagesForWorkflow($selectedWorkflow);
        $selectedStage = !empty($possibleStages) ? $this->faker->optional(0.8)->randomElement(array_keys($possibleStages)) : null;


        return [
            // 'id' => Str::uuid()->toString(), // Não é necessário se o modelo Process usa HasUuids

            'title' => $this->faker->sentence(rand(3, 7)),
            'origin' => $this->faker->optional()->company(),
            'negotiated_value' => $this->faker->optional(0.7)->randomFloat(2, 1000, 50000),
            'description' => $this->faker->optional()->paragraph(rand(2, 5)),
            
            'responsible_id' => function () {
                if (User::count() === 0) {
                    return User::factory()->create()->id;
                }
                return User::inRandomOrder()->first()->id;
            },

            // Atribui um contact_id.
            // Cria um novo Contato se não houver nenhum, ou pega um aleatório se existirem.
            'contact_id' => function () {
                // Verifica se existem contatos. Se não, cria um.
                // Certifique-se de que você tem uma ContactFactory definida.
                if (Contact::count() === 0) {
                    return Contact::factory()->create()->id;
                }
                // Pega um ID de contato aleatório existente.
                // Se Contact.id for UUID, certifique-se que o tipo de contact_id no Process model é string.
                return Contact::inRandomOrder()->first()->id;
            },
            
            'workflow' => $selectedWorkflow,
            'stage' => $selectedStage,
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'updated_at' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes['created_at'], 'now');
            },
        ];
    }

    /**
     * Indica que o processo está no workflow de prospecção.
     */
    public function prospecting(): Factory
    {
        return $this->state(function (array $attributes) {
            $possibleStages = Process::getStagesForWorkflow(Process::WORKFLOW_PROSPECTING);
            return [
                'workflow' => Process::WORKFLOW_PROSPECTING,
                'stage' => !empty($possibleStages) ? $this->faker->randomElement(array_keys($possibleStages)) : null,
            ];
        });
    }

    /**
     * Indica que o processo está no workflow judicial.
     */
    public function judicial(): Factory
    {
        return $this->state(function (array $attributes) {
            $possibleStages = Process::getStagesForWorkflow(Process::WORKFLOW_JUDICIAL);
            return [
                'workflow' => Process::WORKFLOW_JUDICIAL,
                'stage' => !empty($possibleStages) ? $this->faker->randomElement(array_keys($possibleStages)) : null,
                'negotiated_value' => $this->faker->randomFloat(2, 10000, 200000),
                'origin' => $this->faker->randomElement(['Tribunal de Justiça', 'Vara Cível', 'Justiça Federal']),
            ];
        });
    }

    /**
     * Indica que o processo tem um valor negociado alto.
     */
    public function highValue(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'negotiated_value' => $this->faker->randomFloat(2, 50000, 500000),
            ];
        });
    }

    /**
     * Define o processo sem um contato associado.
     */
    public function withoutContact(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'contact_id' => null,
            ];
        });
    }
}
