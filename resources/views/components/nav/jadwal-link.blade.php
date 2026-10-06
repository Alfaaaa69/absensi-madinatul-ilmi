@php
    $link = \App\Support\NavLink::guruOnly('Jadwal Saya', 'jadwal.index', 'calendar-days');
@endphp

{{-- Admin mengelola jadwal lewat Data Master, jadi tidak punya "Jadwal Saya". --}}
@if ($link->visibleTo(auth()->user()->isAdmin()))
    <flux:sidebar.item
        icon="{{ $link->icon }}"
        :href="route($link->routeName)"
        :current="request()->routeIs('jadwal.*')"
        wire:navigate
    >
        {{ $link->label }}
    </flux:sidebar.item>
@endif