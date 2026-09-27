@php
    $link = new \App\Support\NavLink('Dashboard', 'dashboard', 'home');
@endphp

<flux:sidebar.item
    icon="{{ $link->icon }}"
    :href="route($link->routeName)"
    :current="request()->routeIs($link->routeName)"
    wire:navigate
>
    {{ $link->label }}
</flux:sidebar.item>
