<?php

namespace App\Livewire\Concerns;

use App\Services\MasterDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Alur hapus dua langkah untuk halaman data master.
 *
 * Tombol per baris hanya membuka dialog konfirmasi (askDelete) yang
 * menjelaskan konsekuensi sebenarnya — dihapus permanen atau diarsipkan —
 * berdasarkan MasterDeletion::punyaRiwayatAbsensi(). Aksi baru benar-benar
 * dijalankan lewat confirmDelete() yang diimplementasikan pemakai trait.
 */
trait ConfirmableDeletion
{
    public ?string $pendingDeleteId = null;

    public string $pendingDeleteName = '';

    public string $pendingDeleteMessage = '';

    public bool $pendingDeletePermanent = false;

    public function askDelete(string $id): void
    {
        $record = $this->deletableRecord($id);

        Gate::authorize('delete', $record);

        $permanen = ! app(MasterDeletion::class)->punyaRiwayatAbsensi($record);

        $this->pendingDeleteId = $id;
        $this->pendingDeleteName = $this->deletableDescription($record);
        $this->pendingDeleteMessage = $permanen
            ? $this->permanentDeleteMessage($record)
            : $this->archiveDeleteMessage($record);
        $this->pendingDeletePermanent = $permanen;

        $this->dispatch('modal-show', name: static::DELETE_MODAL);
    }

    public function cancelDelete(): void
    {
        $this->pendingDeleteId = null;
        $this->pendingDeleteName = '';
        $this->pendingDeleteMessage = '';
        $this->pendingDeletePermanent = false;
    }

    abstract public function confirmDelete(): void;

    abstract protected function deletableRecord(string $id): Model;

    abstract protected function deletableDescription(Model $record): string;

    abstract protected function permanentDeleteMessage(Model $record): string;

    abstract protected function archiveDeleteMessage(Model $record): string;
}
