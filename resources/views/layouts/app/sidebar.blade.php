<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-sky-50/50 dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-sky-100 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            {{-- Platform: always visible to all authenticated users --}}
            <flux:sidebar.group :heading="__('Platform')" class="grid">
                <flux:sidebar.item icon="home" icon-class="text-indigo-500" :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                @can('prealerts.create')
                    <flux:sidebar.item icon="bell" icon-class="text-amber-500" :href="route('prealerts.create')"
                        :current="request()->routeIs('prealerts.create')" wire:navigate>
                        {{ __('New Prealert') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>

            {{-- Operations: Shipments & Fleet --}}
            @canany(['prealerts.view', 'shipments.view', 'vehicles.view'])
                <flux:sidebar.group :heading="__('Operations')" class="grid" expandable expanded="false">
                    @can('prealerts.view')
                        <flux:sidebar.item icon="bell" icon-class="text-amber-500" :href="route('prealerts.index')"
                            :current="request()->routeIs('prealerts.index')" wire:navigate>
                            {{ __('Prealerts') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('shipments.view')
                        <flux:sidebar.item icon="ship" icon-class="text-indigo-500" :href="route('shipments.index')"
                            :current="request()->routeIs('shipments.*')" wire:navigate>
                            {{ __('Shipments') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('vehicles.view')
                        <flux:sidebar.item icon="car-front" icon-class="text-sky-500" :href="route('vehicles.index')"
                            :current="request()->routeIs('vehicles.*')" wire:navigate>
                            {{ __('Vehicles') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            {{-- Financials: Wallet & Transaction Management --}}
            @canany(['financials.wallets.view', 'wallet_top_ups.view', 'wallets.view'])
                <flux:sidebar.group :heading="__('Financials')" class="grid" expandable="true" expanded="false">
                    @can('financials.wallets.view')
                        <flux:sidebar.item icon="wallet" icon-class="text-blue-500" :href="route('financials.wallets.index')"
                            :current="request()->routeIs('financials.wallets.*')" wire:navigate>
                            {{ __('Master Wallets') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('wallet_top_ups.view')
                        <flux:sidebar.item icon="banknotes" icon-class="text-emerald-500"
                            :href="route('financials.top-ups.index')" :current="request()->routeIs('financials.top-ups.*')"
                            wire:navigate>
                            {{ __('Top-Ups Approvals') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('wallets.view')
                        <flux:sidebar.item icon="credit-card" icon-class="text-indigo-500" :href="route('shipper.wallet.index')"
                            :current="request()->routeIs('shipper.wallet.*')" wire:navigate>
                            {{ __('My Wallet') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            {{-- WhatsApp: Chat & Categories --}}
            @canany(['whatsapp.view_inbox', 'whatsapp.manage_conversations'])
                <flux:sidebar.group :heading="__('WhatsApp')" class="grid" expandable expanded="false">
                    @can('whatsapp.view_inbox')
                        @php
                            $unreadCount = \App\Modules\WhatsApp\Models\WhatsAppMessage::where('sender_type', 'customer')
                                ->where('status', '!=', 'read')
                                ->count();
                        @endphp
                        <flux:sidebar.item icon="chat-bubble-left-right" icon-class="text-green-500"
                            :href="route('whatsapp.index')" :current="request()->routeIs('whatsapp.index')" wire:navigate
                            :badge="$unreadCount > 0 ? $unreadCount : null">
                            {{ __('Inbox') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('whatsapp.manage_conversations')
                        <flux:sidebar.item icon="hashtag" icon-class="text-indigo-500"
                            :href="route('whatsapp.categories.index')" :current="request()->routeIs('whatsapp.categories.*')"
                            wire:navigate>
                            {{ __('Categories') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany


            {{-- Relationships: Partners & Communications --}}
            @canany(['shippers.view', 'staff.view', 'newsletters.view'])
                <flux:sidebar.group :heading="__('Relationships')" class="grid" expandable expanded="false">
                    @can('shippers.view')
                        <flux:sidebar.item icon="building-office-2" icon-class="text-blue-500" :href="route('shippers.index')"
                            :current="request()->routeIs('shippers.*')" wire:navigate>
                            {{ __('Shippers') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('staff.view')
                        <flux:sidebar.item icon="users" icon-class="text-teal-500" :href="route('staff.index')"
                            :current="request()->routeIs('staff.*')" wire:navigate>
                            {{ __('Staff') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('newsletters.view')
                        <flux:sidebar.item icon="envelope" icon-class="text-violet-500" :href="route('newsletters.index')"
                            :current="request()->routeIs('newsletters.*')" wire:navigate>
                            {{ __('Newsletters') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            {{-- Logistics: Carriers, Ports, Drivers & Workshops --}}
            @canany(['carriers.view', 'ports.view', 'drivers.view', 'workshops.view'])
                <flux:sidebar.group :heading="__('Logistics')" class="grid" expandable expanded="false">
                    @can('carriers.view')
                        <flux:sidebar.item icon="truck" icon-class="text-emerald-500" :href="route('carriers.index')"
                            :current="request()->routeIs('carriers.*')" wire:navigate>
                            {{ __('Carriers') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('ports.view')
                        <flux:sidebar.item icon="map-pin" icon-class="text-cyan-500" :href="route('ports.index')"
                            :current="request()->routeIs('ports.*')" wire:navigate>
                            {{ __('Ports') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('drivers.view')
                        <flux:sidebar.item icon="identification" icon-class="text-orange-500" :href="route('drivers.index')"
                            :current="request()->routeIs('drivers.*')" wire:navigate>
                            {{ __('Drivers') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('warehouses.view')
                        <flux:sidebar.item icon="building-library" icon-class="text-amber-600" :href="route('warehouses.index')"
                            :current="request()->routeIs('warehouses.*')" wire:navigate>
                            {{ __('Warehouses') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('workshops.view')
                        <flux:sidebar.item icon="wrench-screwdriver" icon-class="text-amber-500"
                            :href="route('workshops.index')" :current="request()->routeIs('workshops.*')" wire:navigate>
                            {{ __('Workshops') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            {{-- Configuration: System-wide settings --}}
            @canany(['default_shipment_settings.view', 'payment_methods.view', 'charge_items.view'])
                <flux:sidebar.group :heading="__('Configuration')" class="grid" expandable expanded="false">
                    @can('default_shipment_settings.view')
                        <flux:sidebar.item icon="cog" icon-class="text-zinc-500"
                            :href="route('default-shipment-settings.index')"
                            :current="request()->routeIs('default-shipment-settings.*')" wire:navigate>
                            {{ __('Default Options') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('payment_methods.view')
                        <flux:sidebar.item icon="credit-card" icon-class="text-indigo-500"
                            :href="route('payment_methods.index')" :current="request()->routeIs('payment_methods.*')"
                            wire:navigate>
                            {{ __('Payment Methods') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('charge_items.view')
                        <flux:sidebar.item icon="ticket" icon-class="text-amber-500" :href="route('charge-items.index')"
                            :current="request()->routeIs('charge-items.*')" wire:navigate>
                            {{ __('Charge Items') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            {{-- Geography: Regional master data --}}
            @canany(['countries.view', 'states.view', 'cities.view'])
                <flux:sidebar.group :heading="__('Geography')" class="grid" expandable expanded="false">
                    @can('countries.view')
                        <flux:sidebar.item icon="flag" icon-class="text-rose-500" :href="route('countries.index')"
                            :current="request()->routeIs('countries.*')" wire:navigate>
                            {{ __('Countries') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('states.view')
                        <flux:sidebar.item icon="map" icon-class="text-violet-500" :href="route('states.index')"
                            :current="request()->routeIs('states.*')" wire:navigate>
                            {{ __('States') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('cities.view')
                        <flux:sidebar.item icon="map-pin" icon-class="text-sky-500" :href="route('cities.index')"
                            :current="request()->routeIs('cities.*')" wire:navigate>
                            {{ __('Cities') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany

            {{-- System: Logs & Maintenance --}}
            @canany(['email_logs.view', 'failed_jobs.view', 'whatsapp.view_user_stats'])
                <flux:sidebar.group :heading="__('System')" class="grid" expandable="true" expanded="false">
                    @can('email_logs.view')
                        <flux:sidebar.item icon="envelope-open" icon-class="text-sky-500" :href="route('email-logs.index')"
                            :current="request()->routeIs('email-logs.*')" wire:navigate>
                            {{ __('Email Logs') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('failed_jobs.view')
                        <flux:sidebar.item icon="exclamation-triangle" icon-class="text-red-500"
                            :href="route('failed-jobs.index')" :current="request()->routeIs('failed-jobs.*')" wire:navigate>
                            {{ __('Failed Jobs') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('whatsapp.view_user_stats')
                        <flux:sidebar.item icon="chat-bubble-left-right" icon-class="text-green-500"
                            :href="route('whatsapp.user-stats.index')" :current="request()->routeIs('whatsapp.user-stats.*')" wire:navigate>
                            {{ __('WhatsApp Usage') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcanany
        </flux:sidebar.nav>

        <flux:spacer />

        <div class="hidden items-center gap-1 border-t border-zinc-200 p-2 dark:border-zinc-700 lg:flex">
            @auth
                <x-notification-dropdown />
            @endauth
            <div class="min-w-0 flex-1">
                <x-desktop-user-menu :name="auth()->user()->name" />
            </div>
        </div>
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        @auth
            <x-notification-dropdown menu-position="bottom" />
        @endauth

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    <x-dialog />
    <x-notifications />

    {{-- Session flash toasts (e.g. Fortify registration); Livewire WireUI actions use x-notifications above --}}
    <x-ui-toast />

    @include('partials.scripts')
</body>

</html>