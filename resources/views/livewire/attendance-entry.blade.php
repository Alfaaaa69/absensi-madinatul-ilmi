<div class="py-12">
    <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
        {{-- Header ganda fungsinya: identitas jadwal sekali pandang --}}
        <div class="overflow-hidden rounded-2xl border border-madrasah-600/30 bg-gradient-to-l from-madrasah-50 via-madrasah-100 to-madrasah-50 dark:border-madrasah-400/20 dark:from-madrasah-900/40 dark:via-madrasah-800/30 dark:to-madrasah-900/40">
            <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-madrasah-600 text-white shadow-sm">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-madrasah-700 dark:text-madrasah-300">Input Absensi</p>
                        <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ $schedule->subject->name }}</h1>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">Kelas {{ $schedule->schoolClass->name }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 rounded-xl bg-white/70 px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm dark:bg-zinc-900/50 dark:text-zinc-200">
                    <svg class="h-4 w-4 text-madrasah-600 dark:text-madrasah-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ \App\Support\IndonesianDate::jam($schedule->start_time) }} - {{ \App\Support\IndonesianDate::jam($schedule->end_time) }}
                </div>
            </div>
            <div class="border-t border-madrasah-600/20 px-6 py-2.5 text-sm text-zinc-600 dark:border-madrasah-400/20 dark:text-zinc-300">
                Tanggal absensi:
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</span>
            </div>
        </div>

        @if (! $bolehUbah)
            <div class="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-6 dark:bg-amber-400/10 dark:border-amber-400/40">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-amber-600 dark:text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <p class="text-base font-semibold text-amber-900 dark:text-amber-200">{{ $this->pesanTerkunci() }}</p>
                </div>

                @if ($kontakAdmin->isNotEmpty())
                    <p class="text-sm text-amber-900 dark:text-amber-200">Hubungi salah satu admin sekolah:</p>
                    <ul class="space-y-1 text-sm text-amber-900 dark:text-amber-200">
                        @foreach ($kontakAdmin as $admin)
                            <li>
                                <span class="font-medium">{{ $admin['nama'] }}</span>
                                @if ($admin['email'])
                                    - <a class="underline" href="mailto:{{ $admin['email'] }}">{{ $admin['email'] }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-amber-900 dark:text-amber-200">Belum ada akun admin yang aktif. Hubungi operator sekolah.</p>
                @endif
            </div>
        @else
            @if ($sukses)
                <div x-data="{ open: true }" x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex flex-col items-center text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-madrasah-100 text-madrasah-700 dark:bg-madrasah-400/20 dark:text-madrasah-300">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-zinc-100">Absensi Berhasil Disimpan</h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $this->pesanTersimpan() }}</p>

                            <div class="mt-6 flex w-full flex-col gap-2 sm:flex-row sm:justify-center">
                                <flux:button variant="primary" href="{{ route('dashboard') }}" wire:navigate>
                                    Ke Dashboard
                                </flux:button>
                                <flux:button variant="filled" href="{{ route('jadwal.index') }}" wire:navigate>
                                    Jadwal Saya
                                </flux:button>
                                <flux:button variant="ghost" @click="open = false">
                                    Tutup
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-red-300 bg-red-50 p-4 dark:bg-red-400/10 dark:border-red-400/40">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-200">{{ $errors->has('form') ? $errors->first('form') : 'Ada isian yang belum benar. Periksa kembali form di bawah.' }}</p>
                    @foreach ($errors->getMessages() as $kunci => $daftar)
                        @if ($kunci === 'form')
                            @continue
                        @endif
                        @foreach ($daftar as $pesan)
                            <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $pesan }}</p>
                        @endforeach
                    @endforeach
                </div>
            @endif

            <form wire:submit="simpan()" class="space-y-4">
                {{-- Ringkasan langsung: guru tahu komposisi kelas sebelum menyimpan --}}
                @php $rekap = collect($status)->countBy(); @endphp
                <div class="flex flex-wrap items-center gap-2 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                    <span class="mr-1 px-1 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Ringkasan</span>
                    @foreach (['hadir', 'izin', 'sakit', 'alpa'] as $kunciStatus)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $ringkasanWarna[$kunciStatus] }}">{{ \App\Enums\AttendanceStatus::from($kunciStatus)->label() }} {{ $rekap->get($kunciStatus, 0) }}</span>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="tandaiSemua('hadir')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Tandai Semua Hadir
                    </button>
                    <button type="button" wire:click="tandaiSemua('alpa')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-red-50 px-3.5 py-2 text-sm font-semibold text-red-800 hover:bg-red-100 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Tandai Semua Alfa
                    </button>
                </div>

                {{-- Satu baris per siswa, bertumpuk vertikal: bentuknya sama di HP
                     maupun laptop, jadi tidak ada kolom yang terpotong. --}}
                <div class="space-y-3">
                    @foreach ($baris as $id => $siswa)
                        @php $st = $status[$id] ?? null; @endphp
                        <div wire:key="baris-{{ $id }}"
                             @class([
                                 'rounded-xl border p-4',
                                 'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900' => $st === 'hadir',
                                 'border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-800/40' => $st !== 'hadir',
                             ])>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $siswa['nama'] }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">NISN {{ $siswa['nisn'] }} - {{ $siswa['jk'] }}</p>

                            {{-- Tombol status alih-alih dropdown: pilih satu klik, warna langsung terlihat --}}
                            <div class="mt-3 inline-flex overflow-hidden rounded-lg border border-zinc-300 dark:border-zinc-700">
                                @foreach (\App\Enums\AttendanceStatus::cases() as $opsi)
                                    @php $aktif = $opsi->value === $st; @endphp
                                    <button type="button"
                                            wire:click="$set('status.{{ $id }}', '{{ $opsi->value }}')"
                                            class="px-3 py-1.5 text-sm font-semibold transition-colors {{ $statusWarna[$aktif ? $opsi->value : 'netral'][$aktif ? 'aktif' : 'netral'] }}"
                                            @if ($aktif) aria-pressed="true" @endif>
                                        {{ $opsi->label() }}
                                    </button>
                                @endforeach
                            </div>
                            @error("status.{{ $id }}")
                                <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                            @enderror

                            @if ($this->alasanTerlihat($id))
                                <div class="mt-3">
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400" for="alasan-{{ $id }}">Alasan</label>
                                    <input id="alasan-{{ $id }}" type="text" wire:model="note.{{ $id }}" placeholder="Contoh: Demam, acara keluarga, dll" class="w-full rounded-lg border border-zinc-300 text-sm dark:border-zinc-700" />
                                    @error("note.{{ $id }}")
                                        <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-madrasah-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-madrasah-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-madrasah-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-900">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75V16.5L22.5 12l-6-8.25M3.75 12h6" />
                        </svg>
                        {{ $sudahDisimpan ? 'Simpan Perubahan' : 'Simpan Absensi' }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
