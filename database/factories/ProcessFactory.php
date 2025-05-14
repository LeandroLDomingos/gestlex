<?php

namespace Database\Factories;

use App\Models\Process;
use App\Models\User;
use App\Enums\WorkflowType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProcessFactory extends Factory
{
    protected $model = Process::class;

    public function definition()
    {
        // Obter todos os valores possíveis do enum
        $workflowValues = collect(WorkflowType::cases())->pluck('value')->toArray();

        return [
            'id'               => (string) Str::uuid(),
            'title'            => $this->faker->sentence(3),
            'origin'           => $this->faker->randomElement(['web', 'email', 'phone']),
            'negotiated_value' => $this->faker->randomFloat(2, 1000, 100000),
            'description'      => $this->faker->paragraph(),
            'responsible_id'   => User::factory(),
            'workflow'         => $this->faker->randomElement($workflowValues),
            'stage'            => $this->faker->numberBetween(1, 5),
        ];
    }
}
