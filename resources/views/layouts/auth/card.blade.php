<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 antialiased dark:bg-linear-to-b dark:from-zinc-950 dark:to-zinc-900">
        <div class="bg-muted flex min-h-svh flex-col items-center justify-center gap-6 bg-sand-50 p-6 md:p-10 dark:bg-zinc-900" style="background-image: radial-gradient(circle at 1px 1px, rgb(201 162 39 / 0.12) 1px, transparent 0); background-size: 22px 22px;">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9" />
                    </span>

                        <span class="sr-only">{{ config('app.name', 'Sistem Absensi Sekolah') }}</span>
                </a>

                <div class="flex flex-col gap-6">
                    <div class="rounded-xl border border-sand-200 border-t-4 border-t-sand-500 bg-white text-zinc-800 shadow-xs dark:border-zinc-800 dark:border-t-sand-500 dark:bg-zinc-950 dark:text-zinc-200">
                        <div class="px-10 py-8">{{ $slot }}</div>
                    </div>
                    <p class="text-center text-xs text-zinc-500 dark:text-zinc-400">Sistem Absensi &middot; SMP Madinatul Ilmi</p>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
