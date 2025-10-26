<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Création de l'admin
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'), // change le mot de passe si tu veux
            'role' => 'admin',
        ]);

        // Génération du token pour l'admin
        $token = $admin->createToken('admin_token')->plainTextToken;

        // Affichage du token dans la console
        $this->command->info('Admin créé avec succès !');
        $this->command->info('Token pour l\'admin : ' . $token);
    }
}
