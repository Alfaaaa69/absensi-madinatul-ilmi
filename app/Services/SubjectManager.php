<?php

namespace App\Services;

use App\Models\Subject;

final class SubjectManager
{
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

    public function arsipkan(Subject $subject): void
    {
        $subject->delete();
    }

    public function pulihkan(Subject $subject): void
    {
        $subject->restore();
    }
}
