{{-- Satu-satunya tempat yang menyatakan batas data, dipakai bersama oleh
     matriks di layar, layout PDF, dan baris metadata Excel. Kalau tiap
     permukaan menulis kalimatnya sendiri, cepat atau lambat ketiganya berbeda
     lagi - persis yang dicegah `countedEnd()` di level query. --}}
@php
    $catatanBatas = $result->period->countedThroughNote();
@endphp

@if ($catatanBatas !== null)
    <p class="{{ $class ?? 'mb-2 text-xs text-amber-700 dark:text-amber-400' }}">{{ $catatanBatas }}.</p>
@endif