<?php

namespace App\Recap;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use RuntimeException;

class RecapPeriodOutOfRange extends RuntimeException
{
    public static function for(RecapPeriod $period, AcademicYear $academicYear): self
    {
        return new self(sprintf(
            'Periode %s berada di luar tahun ajaran %s (%s sampai %s).',
            $period->label(),
            $academicYear->name,
            $academicYear->start_date->translatedFormat('j F Y'),
            $academicYear->end_date->translatedFormat('j F Y'),
        ));
    }

    public static function forClassOutsideYear(SchoolClass $schoolClass, AcademicYear $academicYear): self
    {
        return new self(sprintf(
            'Kelas %s bukan bagian dari tahun ajaran %s.',
            $schoolClass->name,
            $academicYear->name,
        ));
    }
}
