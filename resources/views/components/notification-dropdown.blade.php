@props([
    'menuPosition' => 'top',
])

<livewire:notification-dropdown :menu-position="$menuPosition" :key="'notification-dropdown-'.$menuPosition" />
