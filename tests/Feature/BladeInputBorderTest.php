<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Border form control.
 *
 * Preflight Tailwind mengeset `border: 0 solid` pada setiap elemen, jadi
 * `border-gray-300` yang berdiri sendiri hanya mengatur warna — lebarnya
 * tetap 0 dan input terlihat transparan. Utility yang menentukan lebar adalah
 * `border` (atau `border-<n>`), dan keduanya harus ada.
 *
 * Dijaga di sini supaya form baru tidak bisa diam-diam ditulis tanpa border
 * dan langsung terlihat seperti kolom kosong.
 *
 * Yang diperiksa per form control:
 *  1. punya class attribute
 *  2. lebar border tidak nol (`border` atau `border-<n>` tanpa prefix)
 *  3. punya warna border di level dasar (untuk mode terang; pasangan
 *     dark:border-* sudah dijaga oleh DarkModeTextContrastTest)
 *
 * Checkbox, radio, hidden, dan tombol dilewati: keduanya bukan kotak teks dan
 * bordernya digambar oleh browser sendiri.
 */
class BladeInputBorderTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const NON_TEXT_TYPES = [
        'checkbox',
        'radio',
        'hidden',
        'submit',
        'button',
        'reset',
        'image',
    ];

    /**
     * @var list<string>
     */
    private const COLORS = [
        'slate', 'gray', 'zinc', 'neutral', 'stone',
        'red', 'orange', 'amber', 'yellow', 'lime',
        'green', 'emerald', 'teal', 'cyan',
        'sky', 'blue', 'indigo', 'violet', 'purple',
        'fuchsia', 'pink', 'rose',
        'white', 'black',
    ];

    public function test_semua_kolom_teks_memiliki_border(): void
    {
        $violations = [];

        foreach ($this->viewFiles() as $path) {
            $relative = str_replace(
                resource_path('views').DIRECTORY_SEPARATOR,
                '',
                $path,
            );

            foreach ($this->textControlsIn((string) file_get_contents($path)) as [$number, $classes]) {
                foreach ($this->problemsIn($classes) as $problem) {
                    $violations[] = sprintf(
                        '%s:%d  %s  ->  %s',
                        $relative,
                        $number,
                        $problem['token'],
                        $problem['hint'],
                    );
                }
            }
        }

        $violations = array_values(array_unique($violations));

        $this->assertSame(
            [],
            $violations,
            sprintf(
                "Kolom teks tanpa border: (%d).\n".
                'Contoh perbaikan: tambahkan `border` lalu pastikan warnanya ada, '.
                "misalnya `rounded border border-gray-300 dark:border-zinc-700`.\n\n%s",
                count($violations),
                implode("\n", $violations),
            ),
        );
    }

    /**
     * @return list<array{0: int, 1: string}>
     */
    private function textControlsIn(string $contents): array
    {
        preg_match_all(
            '/<(input|textarea|select)\b([^>]*?)>/',
            $contents,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        $out = [];

        foreach ($matches as $match) {
            $attributes = $match[2][0];

            if ($this->isNonText($attributes)) {
                continue;
            }

            if (preg_match('/class=(["\'])(.*?)\1/s', $attributes, $class) !== 1) {
                $line = substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
                $out[] = [$line, ''];

                continue;
            }

            $line = substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
            $out[] = [$line, $class[2]];
        }

        return $out;
    }

    private function isNonText(string $attributes): bool
    {
        if (preg_match('/\btype=["\']([^"\']*)["\']/', $attributes, $type) !== 1) {
            return false;
        }

        return in_array($type[1], self::NON_TEXT_TYPES, true);
    }

    /**
     * @return list<array{token: string, hint: string}>
     */
    private function problemsIn(string $classes): array
    {
        if ($classes === '') {
            return [[
                'token' => 'tanpa class',
                'hint' => 'kolom teks tanpa class sama sekali',
            ]];
        }

        $problems = [];

        if (! $this->hasBorderWidth($classes)) {
            $problems[] = [
                'token' => $this->sampleToken($classes),
                'hint' => 'butuh `border` (lebar 1px) karena `border-<warna>` saja tidak menggambar apapun',
            ];
        }

        if (! $this->hasBorderColor($classes)) {
            $problems[] = [
                'token' => $this->sampleToken($classes),
                'hint' => 'butuh warna border seperti border-gray-300 untuk mode terang',
            ];
        }

        return $problems;
    }

    private function hasBorderWidth(string $classes): bool
    {
        foreach (preg_split('/\s+/', $classes) ?: [] as $token) {
            // `border-0` atau `border-t` saja bukan kotak yang terlihat.
            if (preg_match('/^border$|^border-[1-9][0-9]*$/', $token) === 1) {
                return true;
            }
        }

        return false;
    }

    private function hasBorderColor(string $classes): bool
    {
        return preg_match(
            '/(?<![\w:\/-])border-(?:'.implode('|', self::COLORS).')(?:[-\/]|$)/',
            $classes,
        ) === 1;
    }

    private function sampleToken(string $classes): string
    {
        $tokens = preg_split('/\s+/', $classes) ?: [];

        return $tokens[0] ?? '';
    }

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
}
