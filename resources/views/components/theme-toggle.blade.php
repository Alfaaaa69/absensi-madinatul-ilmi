<button
    type="button"
    x-data="{
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark
            window.Flux.applyAppearance(this.dark ? 'dark' : 'light')
        },
        sync() {
            this.dark = document.documentElement.classList.contains('dark')
        },
    }"
    x-init="window.addEventListener('storage', sync)"
    x-on:click="toggle()"
    x-bind:aria-label="dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
    {{ $attributes->merge([
        'class' => 'flex items-center justify-center rounded-md p-2 text-zinc-500 transition hover:bg-zinc-200/70 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white',
    ]) }}
>
    <flux:icon.sun variant="outline" class="size-5 dark:hidden" />
    <flux:icon.moon variant="outline" class="hidden size-5 dark:block" />
</button>