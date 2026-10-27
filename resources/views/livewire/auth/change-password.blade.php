<div>
    <x-auth-header
        title="Ganti Kata Sandi"
        description="Kata sandi dari admin hanya sementara. Ganti dulu sebelum memakai aplikasi."
    />

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="save" class="space-y-5">
        <div>
            <label for="current_password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Kata sandi saat ini
            </label>
            <input
                wire:model="current_password"
                id="current_password"
                type="password"
                autocomplete="current-password"
                autofocus
                required
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700"
            />
            @error('current_password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Kata sandi baru
            </label>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="new-password"
                required
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700"
            />
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                Minimal 8 karakter, ada huruf besar, huruf kecil, dan angka.
            </p>
            @error('password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Ulangi kata sandi baru
            </label>
            <input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700"
            />
        </div>

        <flux:button type="submit" variant="primary" class="w-full">
            Simpan Kata Sandi
        </flux:button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-xs text-zinc-500 underline hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
            Keluar
        </button>
    </form>
</div>
