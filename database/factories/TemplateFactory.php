<?php

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'type' => fake()->randomElement(['page', 'item_detail', 'catalog']),
            'schema' => [],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the template is for an item detail view.
     */
    public function itemDetail(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'item_detail',
            'slug' => null,
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
