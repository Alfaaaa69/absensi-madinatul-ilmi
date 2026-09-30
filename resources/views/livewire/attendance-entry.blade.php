<div class="py-12">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900">Absensi {{ $schedule->subject->name }} - Kelas {{ $schedule->schoolClass->name }}</h1>
            <p class="mt-1 text-2xl font-bold text-zinc-700">
                {{ \App\Support\IndonesianDate::jam($schedule->start_time) }} - {{ \App\Support\IndonesianDate::jam($schedule->end_time) }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Tanggal absensi dikunci</p>
            <p class="mt-1 text-lg font-semibold text-zinc-900">{{ \App\Support\IndonesianDate::tanggal($tanggal) }}</p>
        </div>

        @if (! $bolehUbah)
            <div class="space-y-3 rounded-xl border border-amber-300 bg-amber-50 p-6">
                <p class="text-base font-semibold text-amber-900">{{ $this->pesanTerkunci() }}</p>

                @if ($kontakAdmin->isNotEmpty())
                    <p class="text-sm text-amber-900">Hubungi salah satu admin sekolah:</p>
                    <ul class="space-y-1 text-sm text-amber-900">
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
                    <p class="text-sm text-amber-900">Belum ada akun admin yang aktif. Hubungi operator sekolah.</p>
                @endif
            </div>
        @else
            @if ($sukses)
                <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                    {{ $this->pesanTersimpan() }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-red-300 bg-red-50 p-4">
                    <p class="text-sm font-semibold text-red-800">{{ $errors->has('form') ? $errors->first('form') : 'Ada isian yang belum benar. Periksa kembali form di bawah.' }}</p>
                    @foreach ($errors->getMessages() as $kunci => $daftar)
                        @if ($kunci === 'form')
                            @continue
                        @endif
                        @foreach ($daftar as $pesan)
                            <p class="mt-1 text-sm text-red-700">{{ $pesan }}</p>
                        @endforeach
                    @endforeach
                </div>
            @endif

            <form wire:submit="simpan()" class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="tandaiSemua('hadir')"
                            class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                        Tandai Semua Hadir
                    </button>
                    <button type="button" wire:click="tandaiSemua('alpa')"
                            class="rounded-lg border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-50">
                        Tandai Semua Alpa
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    <table class="min-w-full divide-y divide-zinc-200">
                        <thead class="bg-zinc-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Siswa</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($baris as $id => $siswa)
                                <tr wire:key="baris-{{ $id }}">
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-medium text-zinc-900">{{ $siswa['nama'] }}</p>
                                        <p class="text-xs text-zinc-500">NISN {{ $siswa['nisn'] }} - {{ $siswa['jk'] }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select wire:model.live="status.{{ $id }}"
                                                class="rounded-lg border-zinc-300 text-sm text-zinc-900">
                                            @foreach (\App\Enums\AttendanceStatus::cases() as $opsi)
                                                <option value="{{ $opsi->value }}" @selected($opsi->value === ($status[$id] ?? null))>{{ ucfirst($opsi->value) }}</option>
                                            @endforeach
                                        </select>
                                        @error("status.{{ $id }}")
                                            <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($this->alasanTerlihat($id))
                                            <input type="text" wire:model="note.{{ $id }}"
                                                   placeholder="Contoh: Demam, acara keluarga, dll"
                                                   class="w-full rounded-lg border-zinc-300 text-sm" />
                                            @error("note.{{ $id }}")
                                                <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                            @enderror
                                        @else
                                            <span class="text-xs text-zinc-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-lg bg-zinc-800 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-700">
                        {{ $sudahDisimpan ? 'Simpan Perubahan' : 'Simpan Absensi' }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
