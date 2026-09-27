<div>
    <x-auth-header title="Masuk" description="Masuk dengan NUPTK dan password Anda." />

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="authenticate" class="space-y-5">
        <div>
            <label for="nuptk" class="block text-sm font-medium text-zinc-700">
                NUPTK
            </label>
            <input
                wire:model="nuptk"
                id="nuptk"
                type="text"
                inputmode="numeric"
                autocomplete="username"
                autofocus
                required
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
            @error('nuptk')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-zinc-700">
                Password
            </label>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="current-password"
                required
                class="mt-1 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <label for="remember" class="flex items-center gap-2 text-sm text-zinc-600">
            <input
                wire:model="remember"
                id="remember"
                type="checkbox"
                class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500"
            />
            Ingat saya di perangkat ini
        </label>

        <flux:button type="submit" variant="primary" class="w-full">
            Masuk
        </flux:button>
    </form>
</div>
