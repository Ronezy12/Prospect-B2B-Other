<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'pm:create-admin {email} {--name=Administrateur}';

    protected $description = 'Crée (ou promeut) un compte administrateur du module Prospection & Marketing';

    public function handle(): int
    {
        $email = mb_strtolower($this->argument('email'));
        $password = $this->secret('Mot de passe (12 caractères minimum)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', Password::min(12)->mixedCase()->numbers()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $user = User::updateOrCreate(['email' => $email], [
            'name' => $this->option('name'),
            'password' => $password,
            'is_active' => true,
        ]);
        $user->syncRoles(['administrateur']);

        $this->info("Administrateur {$email} prêt.");

        return self::SUCCESS;
    }
}
