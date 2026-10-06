{{--
    Flux hanya membuka/menutup appearance lewat store reaktif miliknya sendiri
    ($flux.appearance / $flux.dark). Objects window.Flux.applyAppearance yang
    dibuat @fluxAppearance hilang begitu Alpine boot karena Flux mengganti
    window.Flux dengan store itu, jadi komponen ini wajib membaca dan menulis
    lewat $flux.
--}}
<button
    type="button"
    x-data="{
        toggle() {
            $flux.dark = ! $flux.dark
        },
    }"
    x-init="{
        const onStorage = (event) => {
            if (event.key !== 'flux.appearance') return
            $flux.appearance = event.newValue || 'light'
        }
        window.addEventListener('storage', onStorage)
        return () => window.removeEventListener('storage', onStorage)
    }"
    x-on:click="toggle()"
    x-bind:aria-label="$flux.dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
    {{ $attributes->merge([
        'class' => 'flex items-center justify-center rounded-md p-2 text-zinc-500 transition hover:bg-zinc-200/70 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white',
    ]) }}
>
    <flux:icon.sun variant="outline" class="size-5 dark:hidden" />
    <flux:icon.moon variant="outline" class="hidden size-5 dark:block" />
</button>
