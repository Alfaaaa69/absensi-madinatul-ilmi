@php
    $link = new \App\Support\NavLink('Rekap Absensi', 'rekap.index', 'document-chart-bar');
@endphp

{{-- Tanpa tautan `/rekap` di sidebar, fitur rekap hanya bisa dibuka dengan
     mengetik URL, dan tidak ada halaman lain yang menautkannya. --}}
<flux:sidebar.item
    icon="{{ $link->icon }}"
    :href="route($link->routeName)"
    :current="request()->routeIs('rekap.*')"
    wire:navigate
>
    {{ $link->label }}
</flux:sidebar.item>