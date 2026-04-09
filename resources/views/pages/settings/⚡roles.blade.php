<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use WireUi\Traits\WireUiActions;

new #[Title('Role Management')] class extends Component {
    use WireUiActions;

    public bool $showEditModal = false;
    public bool $showDeleteModal = false;

    public ?int $roleEditingId = null;
    public string $name = '';
    public array $selectedPermissions = [];

    public ?int $rolePendingDeleteId = null;
    public string $rolePendingDeleteName = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin'), 403);
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->with('permissions')->orderBy('name')->get();
    }

    #[Computed]
    public function allPermissions()
    {
        return Permission::query()->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        $this->resetEditForm();
        $this->showEditModal = true;
    }

    public function openEditModal(int $roleId): void
    {
        $role = Role::query()->with('permissions')->findOrFail($roleId);
        $this->roleEditingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->showEditModal = true;
    }

    public function saveRole(): void
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $this->roleEditingId,
            'selectedPermissions' => 'array',
        ]);

        if ($this->roleEditingId) {
            $role = Role::findOrFail($this->roleEditingId);
            $role->update(['name' => $this->name]);
        } else {
            $role = Role::create(['name' => $this->name, 'guard_name' => 'web']);
        }

        $role->syncPermissions($this->selectedPermissions);

        $this->showEditModal = false;
        $this->resetEditForm();
        $this->notification()->success($this->roleEditingId ? __('Role updated') : __('Role created'));
    }

    public function openDeleteModal(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        $this->rolePendingDeleteId = $role->id;
        $this->rolePendingDeleteName = $role->name;
        $this->showDeleteModal = true;
    }

    public function deleteRole(): void
    {
        if ($this->rolePendingDeleteId) {
            $role = Role::findOrFail($this->rolePendingDeleteId);
            $role->delete();
            $this->showDeleteModal = false;
            $this->rolePendingDeleteId = null;
            $this->rolePendingDeleteName = '';
            $this->notification()->success(__('Role deleted'));
        }
    }

    private function resetEditForm(): void
    {
        $this->roleEditingId = null;
        $this->name = '';
        $this->selectedPermissions = [];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Role Management') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Roles & Permissions')" :subheading="__('Manage system roles and their associated permissions.')">
        <div class="space-y-6">
            <div class="flex justify-end">
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                    {{ __('Create Role') }}
                </flux:button>
            </div>

            <x-crud.panel>
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Role Name') }}</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Permissions') }}</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->roles as $role)
                            <tr wire:key="role-row-{{ $role->id }}" class="bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="whitespace-nowrap px-4 py-4 align-middle font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $role->name }}
                                </td>
                                <td class="px-4 py-4 align-middle">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($role->permissions as $permission)
                                            <flux:badge size="sm" variant="subtle" color="zinc" class="text-[10px]">{{ $permission->name }}</flux:badge>
                                        @empty
                                            <span class="text-zinc-400 italic text-xs">{{ __('No permissions assigned') }}</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-end align-middle">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="pencil-square"
                                            wire:click="openEditModal({{ $role->id }})"
                                            :tooltip="__('Edit Role')"
                                        />
                                        @if($role->name !== 'super_admin')
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="trash"
                                                color="red"
                                                wire:click="openDeleteModal({{ $role->id }})"
                                                :tooltip="__('Delete Role')"
                                            />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crud.panel>
        </div>
    </x-pages::settings.layout>

    {{-- Edit/Create Modal --}}
    <flux:modal wire:model.self="showEditModal" class="max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $roleEditingId ? __('Edit Role') : __('Create Role') }}</flux:heading>
                <flux:subheading>{{ __('Define the role name and select the permissions it should have.') }}</flux:subheading>
            </div>

            <form wire:submit="saveRole" class="space-y-6">
                <flux:input wire:model="name" :label="__('Role Name')" placeholder="{{ __('e.g. Manager') }}" required />

                <div class="space-y-3">
                    <flux:heading size="sm" weight="semibold">{{ __('Permissions') }}</flux:heading>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        @foreach ($this->allPermissions as $permission)
                            <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg border border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="rounded border-zinc-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-zinc-700 dark:bg-zinc-900">
                                <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ $permission->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-zinc-100 pt-6 dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                        {{ __('Save Changes') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Delete Modal --}}
    <flux:modal wire:model.self="showDeleteModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Role?') }}</flux:heading>
                <flux:subheading>{{ __('Are you sure you want to delete the role ":name"? This action cannot be undone.', ['name' => $rolePendingDeleteName]) }}</flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteRole" wire:loading.attr="disabled">
                    {{ __('Delete Role') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
