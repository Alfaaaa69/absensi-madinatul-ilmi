<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Prefiks nama berkas export
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh `RecapExporter` untuk nama berkas Excel dan PDF. Nama berkas
    | selalu berakhir dengan rentang tanggal hasil hitungan, bukan slug periode,
    | supaya export untuk bulan berbeda tidak saling menimpa di folder arsip.
    |
    | Nilai ini masuk ke nama berkas, jadi di produksi sebaiknya hanya berisi
    | huruf, angka, dan tanda hubung - tanpa spasi, slash, atau titik.
    |
    */

    'file_prefix' => env('REKAP_FILE_PREFIX', 'rekap-absensi'),
];
