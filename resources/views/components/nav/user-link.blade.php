@php
    $link = \App\Support\NavLink::adminOnly('Manajemen User', 'user.index', 'user');
@endphp

@if ($link->visibleTo(auth()->user()->isAdmin()))
    <flux:sidebar.item
        icon="{{ $link->icon }}"
        :href="route($link->routeName)"
        :current="request()->routeIs($link->routeName)"
        wire:navigate
    >
        {{ $link->label }}
    </flux:sidebar.item>
@endif
