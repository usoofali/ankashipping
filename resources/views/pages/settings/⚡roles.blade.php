<?php

declare(strict_types=1);

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
    public bool $showViewModal = false;

    public ?int $roleEditingId = null;
    public string $name = '';
    public array $selectedPermissions = [];

    public ?int $rolePendingDeleteId = null;
    public string $rolePendingDeleteName = '';

    public ?int $viewingRoleId = null;

    public function mount(): void
    {
        $this->authorize('roles.edit');
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->with('permissions')->orderBy('name')->get();
    }

    #[Computed]
    public function allPermissions()
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => str_replace('_', ' ', explode('.', $p->name)[0]));
    }

    #[Computed]
    public function viewingRole(): ?Role
    {
        if ($this->viewingRoleId === null) {
            return null;
        }

        return Role::query()->with('permissions')->find($this->viewingRoleId);
    }

    public function openViewModal(int $roleId): void
    {
        $this->viewingRoleId = $roleId;
        $this->showViewModal = true;
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
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Role') }}</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Permissions') }}</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->roles as $role)
                            <tr wire:key="role-row-{{ $role->id }}" class="bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="whitespace-nowrap px-4 py-4 align-middle">
                                    <div class="flex items-center gap-3">
                                        <div class="size-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                                            <flux:icon.shield-check class="size-4 text-indigo-500" />
                                        </div>
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100 capitalize">
                                            {{ str_replace('_', ' ', $role->name) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-middle">
                                    @php $count = $role->permissions->count(); @endphp
                                    @if ($count > 0)
                                        <button type="button"
                                            wire:click="openViewModal({{ $role->id }})"
                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200 transition-colors group">
                                            <flux:icon.key class="size-3.5 opacity-70 group-hover:opacity-100" />
                                            {{ $count }} {{ Str::plural('permission', $count) }}
                                        </button>
                                    @else
                                        <span class="text-zinc-400 italic text-xs">{{ __('No permissions') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-end align-middle">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="eye"
                                            wire:click="openViewModal({{ $role->id }})"
                                            :tooltip="__('View Permissions')"
                                        />
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

    {{-- View Role Modal --}}
    <flux:modal wire:model.self="showViewModal" class="max-w-xl">
        @if ($this->viewingRole)
            <div class="space-y-6">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                        <flux:icon.shield-check class="size-5 text-indigo-500" />
                    </div>
                    <div>
                        <flux:heading size="lg" class="capitalize">{{ str_replace('_', ' ', $this->viewingRole->name) }}</flux:heading>
                        <flux:subheading>
                            {{ $this->viewingRole->permissions->count() }} {{ Str::plural('permission', $this->viewingRole->permissions->count()) }} {{ __('assigned') }}
                        </flux:subheading>
                    </div>
                </div>

                @php
                    $grouped = $this->viewingRole->permissions
                        ->groupBy(fn ($p) => str_replace('_', ' ', Str::before($p->name, '.')))
                        ->sortKeys();
                @endphp

                <div class="space-y-5 max-h-[55vh] overflow-y-auto pr-1 -mr-1">
                    @forelse ($grouped as $group => $permissions)
                        <div class="space-y-2">
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 dark:text-zinc-500">
                                {{ $group }}
                            </flux:text>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($permissions as $permission)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800">
                                        {{ Str::after($permission->name, '.') }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @if (!$loop->last)
                            <flux:separator />
                        @endif
                    @empty
                        <div class="flex flex-col items-center justify-center py-10 text-zinc-400">
                            <flux:icon.shield-exclamation class="size-10 mb-2 opacity-30" />
                            <flux:text size="sm">{{ __('No permissions assigned to this role.') }}</flux:text>
                        </div>
                    @endforelse
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button variant="ghost" icon="pencil-square" wire:click="openEditModal({{ $this->viewingRole->id }}); $set('showViewModal', false)">
                        {{ __('Edit Role') }}
                    </flux:button>
                    <flux:modal.close>
                        <flux:button variant="primary">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Edit/Create Modal --}}
    <flux:modal wire:model.self="showEditModal" class="max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $roleEditingId ? __('Edit Role') : __('Create Role') }}</flux:heading>
                <flux:subheading>{{ __('Define the role name and select the permissions it should have.') }}</flux:subheading>
            </div>

            <form wire:submit="saveRole" class="space-y-6">
                <flux:input wire:model="name" :label="__('Role Name')" placeholder="{{ __('e.g. Manager') }}" required />

                <div class="space-y-6">
                    @foreach ($this->allPermissions as $group => $permissions)
                        <div class="space-y-3">
                            <flux:heading size="sm" class="uppercase tracking-wider text-zinc-500 font-bold opacity-75">
                                {{ $group }}
                            </flux:heading>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                @foreach ($permissions as $permission)
                                    <label class="group flex items-center gap-2 cursor-pointer p-2 rounded-lg border border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="rounded border-zinc-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-zinc-700 dark:bg-zinc-900 cursor-pointer">
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors">
                                            {{ str_replace($group . '.', '', str_replace('_', ' ', $permission->name)) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @if (!$loop->last)
                                <flux:separator class="mt-4" />
                            @endif
                        </div>
                    @endforeach
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
