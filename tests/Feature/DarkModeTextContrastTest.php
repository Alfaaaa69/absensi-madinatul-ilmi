<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Aturan warna dark mode untuk seluruh view.
 *
 * Tanpa pasangan dark:, tombol mode gelapnya berfungsi tapi halamannya tidak
 * terbaca: teks gelap di atas latar gelap, hover putih menyilaukan, badge
 * menyala. Dijaga di sini supaya halaman baru tidak bisa diam-diam menambah
 * warna terang tanpa padangannya.
 *
 * Yang diperiksa per class attribute:
 *  1. warna teks dasar harus punya pasangan dark: dari keluarga yang sama
 *  2. latar terang (shade <= 200) harus punya pasangan dark:
 *  3. border terang (shade <= 300) harus punya pasangan dark:
 *  4. hover/focus berlatar terang harus punya pasangan dark:
 *
 * Warna dasar berarti token tanpa prefix. Token di bawah hover:, sm:, atau
 * dark: bukan warna dasar elemen, jadi tidak dihitung sebagai pelanggaran.
 */
class DarkModeTextContrastTest extends TestCase
{
    /**
     * Dikelompokkan per perannya, bukan per nama: dark:text-sky-300 sah
     * dipasangkan dengan text-blue-700.
     *
     * @var array<string, list<string>>
     */
    private const FAMILIES = [
        'netral' => ['zinc', 'gray', 'neutral', 'slate', 'stone'],
        'biru' => ['blue', 'sky', 'indigo'],
        'kuning' => ['yellow', 'amber', 'orange'],
        'merah' => ['red', 'rose', 'pink'],
        'hijau' => ['green', 'emerald', 'teal', 'lime'],
    ];

    /**
     * white dan black tetap achromatic dan itu pola yang dipakai Flux sendiri.
     *
     * @var list<string>
     */
    private const ACHROMATIC = ['white', 'black'];

    private const MAX_LIGHT_BG = 200;

    private const MAX_LIGHT_BORDER = 300;

    private const DARK_GRADIENT = '/\bdark:bg-(?:linear|gradient|radial|conic)-/';

    /**
     * @return list<string>
     */
    private function viewFiles(): array
    {
        $files = [];

        $directory = new \RecursiveDirectoryIterator(
            resource_path('views'),
            \FilesystemIterator::SKIP_DOTS,
        );

        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        // Kalau filter di atas salah, test ini hijau tanpa memeriksa apa pun.
        $this->assertNotEmpty($files, 'Tidak ada view yang berhasil dipindai.');

        return $files;
    }

    public function test_semua_warna_terang_punya_pasangan_dark(): void
    {
        $violations = [];

        foreach ($this->viewFiles() as $path) {
            $relative = str_replace(
                resource_path('views').DIRECTORY_SEPARATOR,
                '',
                $path,
            );

            foreach (explode("\n", (string) file_get_contents($path)) as $number => $line) {
                foreach ($this->classListsIn($line) as $classes) {
                    foreach ($this->violationsIn($classes) as $violation) {
                        $violations[] = sprintf(
                            '%s:%d  %s  ->  %s',
                            $relative,
                            $number + 1,
                            $violation['token'],
                            $violation['hint'],
                        );
                    }
                }
            }
        }

        // Class di dalam ekspresi Blade ikut masuk daftar yang sama, jadi satu
        // token bisa tercatat lebih dari sekali.
        $violations = array_values(array_unique($violations));

        $this->assertSame(
            [],
            $violations,
            sprintf(
                "Warna terang tanpa pasangan dark: (%d).\n".
                'Contoh perbaikan: text-zinc-700 dark:text-zinc-200, '.
                "hover:bg-zinc-50 dark:hover:bg-zinc-800\n\n%s",
                count($violations),
                implode("\n", $violations),
            ),
        );
    }

    /**
     * Class attribute di baris ini, sudah digabung dengan string class yang
     * ada di dalam ekspresi Blade. Class di dalam {{ $x ? 'a' : 'b' }} tetap
     * merusak halaman, jadi ikut diperiksa.
     *
     * @return list<string>
     */
    private function classListsIn(string $line): array
    {
        preg_match_all('/\bclass=(["\'])(.*?)\1/', $line, $matches);

        $lists = [];

        foreach ($matches[2] as $raw) {
            $lists[] = $raw.' '.$this->bladeClassStringsIn($raw);
        }

        return $lists;
    }

    private function bladeClassStringsIn(string $classes): string
    {
        preg_match_all('/\{\{(.*?)\}\}/s', $classes, $expressions);

        $inner = '';

        foreach ($expressions[1] as $expression) {
            preg_match_all('/(["\'])(.*?)\1/', $expression, $strings);

            foreach ($strings[2] as $string) {
                $inner .= ' '.$string;
            }
        }

        return $inner;
    }

    /**
     * @return list<array{token: string, hint: string}>
     */
    private function violationsIn(string $classes): array
    {
        $violations = [];

        foreach ($this->baseColorsIn($classes, 'text') as [$name, $shade]) {
            $family = $this->familyOf($name);

            if ($family === null || $this->hasPair($classes, 'text', $family)) {
                continue;
            }

            $violations[] = [
                'token' => "text-{$name}-{$shade}",
                'hint' => 'butuh dark:text-* dari keluarga '.$family,
            ];
        }

        if (preg_match(self::DARK_GRADIENT, $classes) !== 1) {
            foreach ($this->baseColorsIn($classes, 'bg') as [$name, $shade]) {
                $family = $this->familyOf($name);

                if ($family === null
                    || $shade > self::MAX_LIGHT_BG
                    || $this->hasPair($classes, 'bg', $family)) {
                    continue;
                }

                $violations[] = [
                    'token' => "bg-{$name}-{$shade}",
                    'hint' => 'latar terang butuh dark:bg-* dari keluarga '.$family,
                ];
            }

            if (preg_match('/(?<![\w:\/-])bg-white\b/', $classes) === 1
                && preg_match('/\bdark:bg-/', $classes) !== 1) {
                $violations[] = [
                    'token' => 'bg-white',
                    'hint' => 'latar terang butuh dark:bg-*',
                ];
            }
        }

        foreach ($this->baseColorsIn($classes, 'border') as [$name, $shade]) {
            $family = $this->familyOf($name);

            if ($family === null
                || $shade > self::MAX_LIGHT_BORDER
                || $this->hasPair($classes, 'border', $family)) {
                continue;
            }

            $violations[] = [
                'token' => "border-{$name}-{$shade}",
                'hint' => 'border terang butuh dark:border-* dari keluarga '.$family,
            ];
        }

        foreach (['hover', 'focus'] as $state) {
            preg_match_all(
                '/(?<![\w:\/-])'.$state.':bg-([a-z]+)-(\d{2,3})\b/',
                $classes,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $family = $this->familyOf($match[1]);
                $shade = (int) $match[2];

                if ($family === null
                    || $shade > self::MAX_LIGHT_BG
                    || $this->hasPair($classes, 'bg', $family, 'dark:'.$state.':')) {
                    continue;
                }

                $violations[] = [
                    'token' => "{$state}:bg-{$match[1]}-{$shade}",
                    'hint' => "butuh dark:{$state}:bg-* dari keluarga {$family}",
                ];
            }
        }

        return $violations;
    }

    /**
     * Warna dasar saja: token di bawah prefix lain bukan warna dasar elemen.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function baseColorsIn(string $classes, string $kind): array
    {
        preg_match_all(
            '/(?<![\w:\/-])'.$kind.'-([a-z]+)-(\d{2,3})\b/',
            $classes,
            $matches,
            PREG_SET_ORDER,
        );

        $out = [];

        foreach ($matches as $match) {
            $out[] = [$match[1], (int) $match[2]];
        }

        return $out;
    }

    private function familyOf(string $name): ?string
    {
        foreach (self::FAMILIES as $family => $names) {
            if (in_array($name, $names, true)) {
                return $family;
            }
        }

        return null;
    }

    private function hasPair(string $classes, string $kind, string $family, string $prefix = 'dark:'): bool
    {
        $names = $family === 'netral'
            ? array_merge(self::ACHROMATIC, self::FAMILIES[$family])
            : self::FAMILIES[$family];

        $pattern = '/'
            .preg_quote($prefix, '/').$kind
            .'-(?:'.implode('|', $names).')(?:-|\/)/';

        return preg_match($pattern, $classes) === 1;
    }
}
