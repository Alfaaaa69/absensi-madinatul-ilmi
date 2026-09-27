@php
    use App\Support\NavLink;

    $links = [
        NavLink::adminOnly('Tahun Ajaran', 'master.tahun-ajaran', 'calendar'),
        NavLink::adminOnly('Mata Pelajaran', 'master.mapel', 'book-open'),
    ];

    $visible = array_filter(
        $links,
        fn (NavLink $link) => $link->visibleTo(auth()->user()->isAdmin()),
    );
@endphp

@if ($visible !== [])
    <flux:sidebar.group heading="Data Master" class="grid">
        @foreach ($visible as $link)
            <flux:sidebar.item
                icon="{{ $link->icon }}"
                :href="route($link->routeName)"
                :current="request()->routeIs($link->routeName)"
                wire:navigate
            >
                {{ $link->label }}
            </flux:sidebar.item>
        @endforeach
    </flux:sidebar.group>
@endif
