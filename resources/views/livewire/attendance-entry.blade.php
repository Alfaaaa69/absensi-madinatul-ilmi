<div class="py-12">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Absensi {{ $schedule->subject->name }} - Kelas {{ $schedule->schoolClass->name }}</h1>
            <p class="mt-1 text-2xl font-bold text-zinc-700 dark:text-zinc-300">
                {{ \App\Support\IndonesianDate::jam($schedule->start_time) }} - {{ \App\Support\IndonesianDate::jam($schedule->end_time) }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:bg-zinc-800/50 dark:border-zinc-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Tanggal absensi dikunci</p>
            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</p>
        </div>

        @if (! $bolehUbah)
            <div class="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-6 dark:bg-amber-400/10 dark:border-amber-400/40">
                <p class="text-base font-semibold text-amber-900 dark:text-amber-200">{{ $this->pesanTerkunci() }}</p>

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
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="tandaiSemua('hadir')"
                            class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50 dark:text-zinc-200 dark:bg-zinc-900 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        Tandai Semua Hadir
                    </button>
                    <button type="button" wire:click="tandaiSemua('alpa')"
                            class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50 dark:text-zinc-200 dark:bg-zinc-900 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        Tandai Semua Alfa
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:bg-zinc-900 dark:border-zinc-800">
                    <table class="min-w-full divide-y divide-zinc-200">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Siswa</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($baris as $id => $siswa)
                                <tr wire:key="baris-{{ $id }}">
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $siswa['nama'] }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">NISN {{ $siswa['nisn'] }} - {{ $siswa['jk'] }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select wire:model.live="status.{{ $id }}"
                                                class="rounded-lg border border-zinc-300 text-sm text-zinc-900 dark:text-zinc-100 dark:border-zinc-700">
                                            @foreach (\App\Enums\AttendanceStatus::cases() as $opsi)
                                                <option value="{{ $opsi->value }}" @selected($opsi->value === ($status[$id] ?? null))>{{ $opsi->label() }}</option>
                                            @endforeach
                                        </select>
                                        @error("status.{{ $id }}")
                                            <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($this->alasanTerlihat($id))
                                            <input type="text" wire:model="note.{{ $id }}"
                                                   placeholder="Contoh: Demam, acara keluarga, dll"
                                                   class="w-full rounded-lg border border-zinc-300 text-sm dark:border-zinc-700" />
                                            @error("note.{{ $id }}")
                                                <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                                            @enderror
                                        @else
                                            <span class="text-xs text-zinc-400 dark:text-zinc-500">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-lg bg-madrasah-600 px-4 py-2 text-sm font-semibold text-white hover:bg-madrasah-700">
                        {{ $sudahDisimpan ? 'Simpan Perubahan' : 'Simpan Absensi' }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
