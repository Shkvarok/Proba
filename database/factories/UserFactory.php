<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Отримати випадкову країну з БД або null якщо країн немає
        $countryId = Country::inRandomOrder()->first()?->id;
        
        // Отримати роль студента (за замовчуванням) або випадкову роль
        $roleId = Role::where('name', 'student')->first()?->id ?? 
                  Role::inRandomOrder()->first()?->id;
                  
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        
        return [
            // Встановлюємо name як null або використовуємо first_name + last_name
            'name' => null, // Або можна використати "$firstName $lastName"
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => fake()->phoneNumber(),
            'country_id' => $countryId,
            'role_id' => $roleId,
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // password
            'remember_token' => Str::random(10),
            // Можна також додати аватар, якщо потрібно
            'avatar' => null, // Або шлях до випадкового аватара
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
    
    /**
     * Indicate that the user should have admin role.
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            $adminRoleId = Role::where('name', 'admin')->first()?->id;
            
            return [
                'role_id' => $adminRoleId,
            ];
        });
    }
    
    /**
     * Indicate that the user should have super_admin role.
     */
    public function superAdmin(): static
    {
        return $this->state(function (array $attributes) {
            $superAdminRoleId = Role::where('name', 'super_admin')->first()?->id;
            
            return [
                'role_id' => $superAdminRoleId,
            ];
        });
    }
}