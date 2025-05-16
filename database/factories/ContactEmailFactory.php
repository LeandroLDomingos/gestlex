<?php

namespace Database\Factories;

use App\Models\ContactEmail; // Make sure this path is correct for your ContactEmail model
use App\Models\Contact;      // Required if you need to link back or use Contact model logic here
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactEmailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ContactEmail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 'contact_id' will typically be set when calling this factory
            // from the ContactFactory. If you need to create emails standalone,
            // you might add: 'contact_id' => Contact::factory(),
            
            // 'email' column in the migration corresponds to 'address' here.
            // It's common to use 'email' as the key in the factory to match the column name.
            'email' => $this->faker->unique()->safeEmail(),
            
            // The 'type' field was removed as it's not in the contact_emails migration.
            // The 'is_primary' field was removed as it's not in the contact_emails migration.
        ];
    }

    // The primary() state method was removed as the 'is_primary' column
    // is not present in the contact_emails migration.
    // If you add an 'is_primary' boolean column to your migration later,
    // you can re-add a similar method:
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
