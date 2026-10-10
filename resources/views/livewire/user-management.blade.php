<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Manajemen User</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Buat akun, nonaktifkan, dan reset password tanpa lewat email.
            </p>
        </div>

        <flux:button variant="primary" wire:click="create">
            Tambah User
        </flux:button>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Cari nama atau NUPTK"
    />

    <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
        <input type="checkbox" wire:click="toggleArsip" />
        Tampilkan yang diarsipkan
    </label>

    @if ($users->isEmpty())
        <div class="rounded-lg border border-zinc-200 bg-white p-10 text-center dark:bg-zinc-900 dark:border-zinc-800">
            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Belum ada user</p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Tambahkan akun pertama untuk mulai mencatat absensi.
            </p>
        </div>
    @else
        <flux:table :paginate="$users">
            <flux:table.columns>
                <flux:table.column>Nama</flux:table.column>
                <flux:table.column>NUPTK</flux:table.column>
                <flux:table.column>Email</flux:table.column>
                <flux:table.column>Role</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="text-right">Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $user->name }}
                            @if ($user->trashed())
                                <span class="ml-2 rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-600 dark:text-zinc-400 dark:bg-zinc-800">Diarsipkan</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->nuptk }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $user->role->value }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($user->is_active)
                                <flux:badge color="green" size="sm">Aktif</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">Nonaktif</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button size="xs" wire:click="edit('{{ $user->id }}')">Ubah</flux:button>
                                <flux:button size="xs" wire:click="startReset('{{ $user->id }}')">Reset Password</flux:button>
                                <flux:button size="xs" wire:click="toggleActive('{{ $user->id }}')">
                                    {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </flux:button>
                                @if ($user->trashed())
                                    <flux:button size="xs" wire:click="pulihkan('{{ $user->id }}')">Pulihkan</flux:button>
                                @else
                                    <flux:button size="xs" variant="danger" wire:click="askDelete('{{ $user->id }}')">
                                        {{ $user->memiliki_riwayat ? 'Arsipkan' : 'Hapus' }}
                                    </flux:button>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($showForm)
        <flux:modal name="{{ \App\Livewire\UserManagement::FORM_MODAL }}">
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="user-name">Nama</label>
                    <flux:input id="user-name" wire:model="form.name" />
                    @error('form.name') <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="user-nuptk">NUPTK</label>
                    <flux:input id="user-nuptk" wire:model="form.nuptk" inputmode="numeric" />
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">18 digit angka. Dipakai sebagai identitas login.</p>
                    @error('form.nuptk') <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="user-email">Email (opsional)</label>
                    <flux:input id="user-email" type="email" wire:model="form.email" />
                    @error('form.email') <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="user-role">Role</label>
                    <select
                        id="user-role"
                        wire:model="form.role"
                        class="mt-1 block w-full rounded-md border border-zinc-300 text-sm dark:border-zinc-700"
                    >
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->value }}</option>
                        @endforeach
                    </select>
                    @error('form.role') <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                </div>

                @if ($form['id'] === null)
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="user-password">Password</label>
                        <flux:input id="user-password" type="password" wire:model="password" />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Minimal 8 karakter.</p>
                        @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="user-password-confirm">
                            Ulangi Password
                        </label>
                        <flux:input id="user-password-confirm" type="password" wire:model="password_confirmation" />
                    </div>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelForm">Batal</flux:button>
                    <flux:button type="submit" variant="primary">Simpan</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($resetTargetId !== null)
        <flux:modal name="{{ \App\Livewire\UserManagement::RESET_MODAL }}">
            <form wire:submit="confirmReset" class="space-y-4">
                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                    Password baru untuk <strong>{{ $resetTargetName }}</strong>.
                </p>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="reset-password">Password Baru</label>
                    <flux:input id="reset-password" type="password" wire:model="password" />
                    @error('password') <p class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="reset-password-confirm">
                        Ulangi Password
                    </label>
                    <flux:input id="reset-password-confirm" type="password" wire:model="password_confirmation" />
                </div>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Sampaikan password baru ke user lewat kanal resmi. Sistem tidak mengirim email.
                </p>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelReset">Batal</flux:button>
                    <flux:button type="submit" variant="primary">Simpan Password</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($pendingDeleteId !== null)
        <flux:modal name="{{ \App\Livewire\UserManagement::DELETE_MODAL }}">
            <div class="space-y-4">
                <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $pendingDeleteMessage }}</p>
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="cancelDelete">Batal</flux:button>
                    <flux:button type="button" variant="danger" wire:click="confirmDelete">
                        {{ $pendingDeletePermanent ? 'Hapus Permanen' : 'Arsipkan' }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
