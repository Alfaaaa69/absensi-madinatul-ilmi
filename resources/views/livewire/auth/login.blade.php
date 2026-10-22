<div>
    <x-auth-header title="Masuk" description="Masuk dengan email dan kata sandi untuk mencatat kehadiran." />

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="authenticate" class="space-y-5">
        <div>
            <label for="email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Email
            </label>
            <input
                wire:model="email"
                id="email"
                type="email"
                autocomplete="username"
                autofocus
                required
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700"
            />
            @error('email')
                <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div x-data="{ showPassword: false }">
            <label for="password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Password
            </label>
            <div class="relative mt-1">
                <input
                    wire:model="password"
                    id="password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    required
                    class="block w-full rounded-md border border-zinc-300 px-3 py-2 pr-24 text-sm shadow-sm focus:border-madrasah-500 focus:ring-madrasah-500 dark:border-zinc-700"
                />
                <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-xs font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                    x-text="showPassword ? 'Sembunyikan' : 'Lihat'"
                ></button>
            </div>
            @error('password')
                <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <label for="remember" class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
            <input
                wire:model="remember"
                id="remember"
                type="checkbox"
                class="rounded border-zinc-300 text-madrasah-600 focus:ring-madrasah-500 dark:text-madrasah-300 dark:border-zinc-700"
            />
            Ingat saya di perangkat ini
        </label>

        <flux:button type="submit" variant="primary" class="w-full">
            Masuk
        </flux:button>
    </form>
</div>
