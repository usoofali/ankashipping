<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

new #[Title('System Setup')] #[\Livewire\Attributes\Layout('layouts.auth')] class extends Component {
    use WireUiActions;

    public bool $db_connected = false;
    public bool $storage_linked = false;
    public string $php_version = '';
    public array $folder_perms = [];
    public string $last_output = '';

    public string $admin_name = '';
    public string $admin_email = '';
    public string $admin_password = '';
    public string $admin_password_confirmation = '';

    public array $target_folders = [
        'storage',
        'storage/app',
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ];

    public function mount(): void
    {
        if ($this->isSetupComplete() || $this->hasSuperAdmin()) {
            $this->redirect(route(auth()->check() ? 'dashboard' : 'login'), navigate: false);

            return;
        }

        $this->refreshStats();
    }

    public function refreshStats(): void
    {
        $this->php_version = PHP_VERSION;

        try {
            DB::connection()->getPdo();
            $this->db_connected = true;
        } catch (\Throwable) {
            $this->db_connected = false;
        }

        $this->storage_linked = File::exists(public_path('storage'));

        foreach ($this->target_folders as $folder) {
            $path = base_path($folder);
            $this->folder_perms[$folder] = File::exists($path)
                ? substr(sprintf('%o', fileperms($path)), -4)
                : 'missing';
        }
    }

    public function fixPermission(string $folder): void
    {
        $path = base_path($folder);

        if (File::exists($path)) {
            @chmod($path, 0775);
        }

        $this->refreshStats();
    }

    public function createStorageLink(): void
    {
        try {
            Artisan::call('storage:link');
            $this->refreshStats();
            $this->notification()->success(__('Storage symbolic link created successfully.'));
        } catch (\Throwable $exception) {
            $this->notification()->error(__('Failed to create storage link: :message', ['message' => $exception->getMessage()]));
        }
    }

    public function initializeDatabase(): void
    {
        if (app()->isProduction() && ! config('app.setup_enabled')) {
            $this->notification()->error(__('Database initialization from setup is disabled in production.'));

            return;
        }

        set_time_limit(300);

        try {
            $migrateExitCode = Artisan::call('migrate', ['--force' => true]);
            if ($migrateExitCode !== 0) {
                throw new \RuntimeException('Migration failed: '.Artisan::output());
            }

            $seedExitCode = Artisan::call('db:seed', ['--force' => true]);
            if ($seedExitCode !== 0) {
                throw new \RuntimeException('Database seeding failed: '.Artisan::output());
            }

            $this->last_output = Artisan::output();
            session()->flash('status', __('Database initialized successfully.'));
            $this->refreshStats();
            $this->redirect(route('setup'), navigate: false);
        } catch (\Throwable $exception) {
            $this->last_output = $exception->getMessage();
            $this->notification()->error(__('Database initialization failed.'));
        }
    }

    public function createAdmin(): void
    {
        $this->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $user = User::query()->create([
                'name' => $this->admin_name,
                'email' => $this->admin_email,
                'password' => Hash::make($this->admin_password),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('super_admin');
            File::put($this->setupMarkerPath(), now()->toDateTimeString());

            $this->notification()->success(__('Super admin created successfully.'));

            redirect()->route('login')->with('status', __('Setup complete! You can now log in.'));
        } catch (\Throwable $exception) {
            $this->notification()->error(__('Failed to create admin: :message', ['message' => $exception->getMessage()]));
        }
    }

    protected function hasSuperAdmin(): bool
    {
        try {
            return User::role('super_admin')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function isSetupComplete(): bool
    {
        return File::exists($this->setupMarkerPath());
    }

    protected function setupMarkerPath(): string
    {
        return storage_path('app/setup-complete');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('System Setup')" :description="__('Initialize your environment, database, and admin account.')" />

        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <div class="flex size-6 items-center justify-center rounded-md bg-stone-100 text-xs font-semibold text-stone-600 dark:bg-stone-800 dark:text-stone-300">1</div>
                <flux:heading size="sm">{{ __('Environment Audit') }}</flux:heading>
            </div>

            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-stone-500">{{ __('PHP Version') }}</span>
                    <flux:badge size="sm" color="zinc">{{ $php_version }}</flux:badge>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-stone-500">{{ __('Database Connection') }}</span>
                    <flux:badge size="sm" :color="$db_connected ? 'green' : 'red'">
                        {{ $db_connected ? __('Connected') : __('Failed') }}
                    </flux:badge>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-stone-500">{{ __('Storage Link') }}</span>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" :color="$storage_linked ? 'green' : 'amber'">
                            {{ $storage_linked ? __('Linked') : __('Missing') }}
                        </flux:badge>
                        @if(! $storage_linked)
                            <flux:button wire:click="createStorageLink" size="xs" variant="ghost" icon="link" />
                        @endif
                    </div>
                </div>

                <div class="mt-2 flex flex-col gap-2">
                    @foreach($target_folders as $folder)
                        <div class="flex items-center justify-between font-mono text-xs">
                            <span class="text-stone-500">{{ $folder }}</span>
                            <div class="flex items-center gap-2">
                                <span class="{{ in_array($folder_perms[$folder] ?? '', ['0775', '0755']) ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                    {{ $folder_perms[$folder] ?? 'missing' }}
                                </span>
                                @if(! in_array($folder_perms[$folder] ?? '', ['0775', '0755', 'missing']))
                                    <flux:button wire:click="fixPermission('{{ $folder }}')" size="xs" variant="ghost" icon="wrench" class="h-6 w-6" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <div class="flex size-6 items-center justify-center rounded-md bg-stone-100 text-xs font-semibold text-stone-600 dark:bg-stone-800 dark:text-stone-300">2</div>
                <flux:heading size="sm">{{ __('Database Initialization') }}</flux:heading>
            </div>

            <div class="flex flex-col gap-4">
                <span class="text-xs text-stone-500 dark:text-stone-400">
                    {{ __('This action will create all necessary tables and insert default roles. Ensure your database is empty to avoid conflicts.') }}
                </span>
                <flux:button wire:click="initializeDatabase" variant="primary" class="w-full" :disabled="! $db_connected" icon="play">
                    {{ __('Initialize Database') }}
                </flux:button>
                @if($last_output !== '')
                    <pre class="max-h-40 overflow-auto whitespace-pre-wrap rounded-lg bg-stone-950 p-3 text-xs text-emerald-400">{{ $last_output }}</pre>
                @endif
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <div class="flex size-6 items-center justify-center rounded-md bg-stone-100 text-xs font-semibold text-stone-600 dark:bg-stone-800 dark:text-stone-300">3</div>
                <flux:heading size="sm">{{ __('Super Admin') }}</flux:heading>
            </div>

            <form wire:submit="createAdmin" class="flex flex-col gap-4">
                <flux:input wire:model="admin_name" :label="__('Full Name')" placeholder="System Admin" required />
                <flux:input wire:model="admin_email" type="email" :label="__('Email Address')" placeholder="admin@example.com" required />
                <flux:input wire:model="admin_password" type="password" :label="__('Password')" required viewable />
                <flux:input wire:model="admin_password_confirmation" type="password" :label="__('Confirm Password')" required viewable />

                <div class="pt-2">
                    <flux:button type="submit" variant="primary" class="w-full" icon="check-badge">
                        {{ __('Complete Setup') }}
                    </flux:button>
                </div>
            </form>
        </div>
</div>
