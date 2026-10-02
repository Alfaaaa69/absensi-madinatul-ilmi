@php
    $link = new \App\Support\NavLink('Jadwal Saya', 'jadwal.index', 'calendar-days');
@endphp

{{-- Jadwal juga belum pernah ditautkan dari mana pun sejak Plan 2. --}}
<flux:sidebar.item
    icon="{{ $link->icon }}"
    :href="route($link->routeName)"
    :current="request()->routeIs('jadwal.*')"
    wire:navigate
>
    {{ $link->label }}
</flux:sidebar.item>