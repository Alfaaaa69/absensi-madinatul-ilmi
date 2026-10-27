<?php

namespace App\Services;

use App\Enums\HasilHapus;
use App\Models\Subject;

final class SubjectManager
{
    public function __construct(private readonly MasterDeletion $deletion) {}

    /**
     * @param  array{code: string, name: string}  $data
     */
    public function save(?Subject $subject, array $data): Subject
    {
        $mapel = $subject ?? new Subject;

        $mapel->fill([
            'code' => $data['code'],
            'name' => $data['name'],
        ]);
        $mapel->save();

        return $mapel;
    }

    public function hapus(Subject $subject): HasilHapus
    {
        return $this->deletion->hapus($subject);
    }

    public function pulihkan(Subject $subject): void
    {
        $subject->restore();
    }
}
