<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AgentoomInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agentoom:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Agentoom system (roles, superadmin, languages)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting installation of the Agentoom system...');

        // 1. Create roles
        $this->createRoles();

        // 2. Create superadmin user
        $this->createSuperAdmin();

        // 3. Add languages
        $this->addLanguages();

        // 4. Create system resources
        $this->createSystemResources();

        // 5. Ensure onboarding is shown for fresh installations
        Configuration::setValue('onboarding_complete', '0');
        $this->line('Onboarding wizard enabled.');

        $this->info('Installation completed successfully!');
    }

    protected function createSystemResources(): void
    {
        $this->info('Checking for system resources...');

        if (Agent::query()->count() === 0) {
            $this->info('No agents found. Creating system resources...');
            $this->call('agentoom:create-resources');
        } else {
            $this->line('System resources already exist.');
        }
    }

    protected function createRoles(): void
    {
        $this->info('Creating roles...');
        $roles = [
            'admin',
            'superadmin',
            'user',
            'registered',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
            $this->line("Role: {$roleName}");
        }
    }

    protected function createSuperAdmin(): void
    {
        $this->info('Creating superadmin user...');
        $email = 'superadmin@unusualdope.com';
        $password = '$^vEKfKG4*o5B2641pUY';

        $userModel = config('auth.providers.users.model');
        $user = $userModel::query()->where('email', $email)->first();

        if (! $user) {
            $user = $userModel::create([
                'name' => 'Super Admin',
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $this->line("User created: {$email}");
        } else {
            $user->password = Hash::make($password);
            $user->save();
            $this->line("User already exists: {$email}");
        }

        if (! $user->hasRole('superadmin')) {
            $user->assignRole('superadmin');
            $this->line("Assigned 'superadmin' role to {$email}");
        }
    }

    protected function addLanguages(): void
    {
        $this->info('Adding languages...');

        $languages = [
            ['name' => 'English', 'code' => 'en', 'is_active' => true, 'is_default' => true],
            ['name' => 'Italiano', 'code' => 'it', 'is_active' => true, 'is_default' => false],
        ];

        foreach ($languages as $langData) {
            $lang = Language::query()->where('code', $langData['code'])->first();

            if (! $lang) {
                Language::create($langData);
                $this->line("Language added: {$langData['name']} ({$langData['code']})");
            } else {
                $this->line("Language already exists: {$langData['name']} ({$langData['code']})");
            }
        }
    }
}
