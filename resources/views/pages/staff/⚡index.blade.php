<?php

declare(strict_types=1);

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use WireUi\Traits\WireUiActions;

new #[Title('Staff')] class extends Component {
    use WireUiActions;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'role_filter')]
    public string $filterRole = '';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingStaffId = null;
    public ?int $staffPendingDeleteId = null;
    public string $staffPendingDeleteLabel = '';

    // User + Staff fields
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = '';
    public string $job_title = '';
    public string $phone = '';

    public function mount(): void
    {
        $this->authorize('staff.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function staffList()
    {
        return Staff::query()
            ->with('user.roles')
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
                ->orWhere('job_title', 'like', "%{$this->search}%"))
            ->when($this->filterRole, fn ($q) => $q->whereHas('user.roles', fn ($rq) => $rq->where('name', $this->filterRole)))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function staffRoles()
    {
        return Role::whereNotIn('name', ['shipper'])->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        $this->authorize('staff.create');
        $this->reset(['name', 'email', 'password', 'role', 'job_title', 'phone', 'editingStaffId']);
        $this->showCreateModal = true;
    }

    public function saveNewStaff(): void
    {
        $this->authorize('staff.create');

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|exists:roles,name',
            'job_title' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$this->role]);

        Staff::create([
            'user_id' => $user->id,
            'job_title' => $this->job_title ?: null,
            'phone' => $this->phone ?: null,
        ]);

        $this->showCreateModal = false;
        $this->notification()->success(__('Staff member created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('staff.update');
        $staff = Staff::with('user.roles')->findOrFail($id);

        $this->editingStaffId = $staff->id;
        $this->name = $staff->user?->name ?? '';
        $this->email = $staff->user?->email ?? '';
        $this->password = '';
        $this->job_title = $staff->job_title ?? '';
        $this->phone = $staff->phone ?? '';
        $this->role = $staff->user?->roles->first()?->name ?? '';

        $this->showEditModal = true;
    }

    public function saveStaff(): void
    {
        $this->authorize('staff.update');

        $staff = Staff::with('user')->findOrFail($this->editingStaffId);

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $staff->user?->id,
            'password' => 'nullable|string|min:8',
            'role' => 'required|exists:roles,name',
            'job_title' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $userData = [
            'name' => $this->name,
            'email' => $this->email,
        ];
        if (filled($this->password)) {
            $userData['password'] = Hash::make($this->password);
        }

        $staff->user?->update($userData);
        $staff->user?->syncRoles([$this->role]);

        $staff->update([
            'job_title' => $this->job_title ?: null,
            'phone' => $this->phone ?: null,
        ]);

        $this->showEditModal = false;
        $this->notification()->success(__('Staff member updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('staff.delete');
        $staff = Staff::with('user')->findOrFail($id);
        $this->staffPendingDeleteId = $staff->id;
        $this->staffPendingDeleteLabel = $staff->user?->name ?? __('Staff #:id', ['id' => $staff->id]);
        $this->showDeleteModal = true;
    }

    public function deleteStaff(): void
    {
        $this->authorize('staff.delete');

        if ($this->staffPendingDeleteId) {
            $staff = Staff::with('user')->findOrFail($this->staffPendingDeleteId);
            $staff->user?->delete(); // cascades to staff via FK
            $this->showDeleteModal = false;
            $this->notification()->success(__('Staff member and user account deleted successfully.'));
        }

        $this->staffPendingDeleteId = null;
        $this->staffPendingDeleteLabel = '';
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('Staff')" :subheading="__('Manage platform staff members and their roles.')" icon="users" class="!mb-0" />
            @can('staff.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Add Staff') }}</flux:button>
            @endcan
        </div>

        <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name, email or job title...')" clearable />
            <flux:select wire:model.live="filterRole" icon="shield-check">
                <option value="">{{ __('All Roles') }}</option>
                @foreach ($this->staffRoles as $r)
                    <option value="{{ $r->name }}">{{ str_replace('_', ' ', $r->name) }}</option>
                @endforeach
            </flux:select>
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->staffList">
                <flux:table.columns>
                    <flux:table.column icon="user">{{ __('Name') }}</flux:table.column>
                    <flux:table.column icon="briefcase">{{ __('Job Title') }}</flux:table.column>
                    <flux:table.column icon="phone">{{ __('Phone') }}</flux:table.column>
                    <flux:table.column icon="shield-check">{{ __('Role') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->staffList as $member)
                        <flux:table.row :key="$member->id">
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ $member->user?->name ?? '—' }}</span>
                                    <span class="text-xs text-zinc-400">{{ $member->user?->email }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $member->job_title ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $member->phone ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @php $roleName = $member->user?->roles->first()?->name; @endphp
                                @if ($roleName)
                                    <flux:badge
                                        color="{{ $roleName === 'super_admin' ? 'red' : ($roleName === 'staff_admin' ? 'blue' : 'zinc') }}"
                                        size="sm"
                                    >
                                        {{ str_replace('_', ' ', $roleName) }}
                                    </flux:badge>
                                @else
                                    <span class="text-xs text-zinc-400">{{ __('No role') }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('staff.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $member->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @endcan
                                        @can('staff.delete')
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $member->id }})">{{ __('Delete') }}</flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">
                                {{ __('No staff members found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-2xl">
        <form wire:submit="saveNewStaff" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="users" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Add Staff Member') }}</flux:heading>
                    <flux:subheading>{{ __('Create a new user account and assign them to staff.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Full Name')" icon="user" required placeholder="Jane Smith" />
                    <flux:input wire:model="email" :label="__('Email')" icon="envelope" type="email" required placeholder="jane@example.com" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="password" :label="__('Password')" icon="lock-closed" type="password" required placeholder="Min. 8 characters" />
                    <flux:select wire:model="role" :label="__('Role')" icon="shield-check" required>
                        <option value="">{{ __('Select role...') }}</option>
                        @foreach ($this->staffRoles as $r)
                            <option value="{{ $r->name }}">{{ str_replace('_', ' ', $r->name) }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="job_title" :label="__('Job Title')" icon="briefcase" placeholder="e.g. Operations Manager" />
                    <flux:input wire:model="phone" :label="__('Phone')" icon="phone" placeholder="+1 555 000 0000" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Staff Member') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-2xl">
        <form wire:submit="saveStaff" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Staff Member') }}</flux:heading>
                    <flux:subheading>{{ __('Update account details, role, and contact info. Leave password blank to keep current.') }}</flux:subheading>
                </div>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Full Name')" icon="user" required />
                    <flux:input wire:model="email" :label="__('Email')" icon="envelope" type="email" required />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="password" :label="__('New Password')" icon="lock-closed" type="password" placeholder="{{ __('Leave blank to keep current') }}" />
                    <flux:select wire:model="role" :label="__('Role')" icon="shield-check" required>
                        <option value="">{{ __('Select role...') }}</option>
                        @foreach ($this->staffRoles as $r)
                            <option value="{{ $r->name }}">{{ str_replace('_', ' ', $r->name) }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="job_title" :label="__('Job Title')" icon="briefcase" />
                    <flux:input wire:model="phone" :label="__('Phone')" icon="phone" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <form wire:submit="deleteStaff" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Staff Member') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will permanently delete ":name" and their user account. This action cannot be undone.', ['name' => $staffPendingDeleteLabel]) }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
