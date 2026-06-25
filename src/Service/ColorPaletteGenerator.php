<?php

declare(strict_types=1);

namespace Webane\Jalagistrasi\Service;

/**
 * Generate skala warna 50-900 dari satu warna brand (dianggap shade 600),
 * lewat interpolasi RGB linear ke putih (shade lebih terang) atau hitam
 * (shade lebih gelap). Lihat docs/arsitektur-color-palette.md.
 */
final class ColorPaletteGenerator
{
    /** @var array<int,array{0:string,1:float}> shade => [target, rasio campur] */
    private const STOPS = [
        50  => ['white', 0.95],
        100 => ['white', 0.90],
        200 => ['white', 0.75],
        300 => ['white', 0.60],
        400 => ['white', 0.35],
        500 => ['white', 0.15],
        600 => ['none', 0.0],
        700 => ['black', 0.15],
        800 => ['black', 0.30],
        900 => ['black', 0.45],
    ];

    /**
     * @return array<int,string> shade => hex (mis. [50 => '#eff6ff', ..., 600 => '#2563eb', ...])
     */
    public function generateScale(string $hex): array
    {
        $rgb = $this->hexToRgb($hex);

        $scale = [];
        foreach (self::STOPS as $shade => [$target, $rasio]) {
            if ($target === 'none') {
                $scale[$shade] = $hex;
                continue;
            }

            $targetRgb = $target === 'white' ? [255, 255, 255] : [0, 0, 0];
            $scale[$shade] = $this->rgbToHex($this->mix($rgb, $targetRgb, $rasio));
        }

        return $scale;
    }

    /**
     * "37, 99, 235" — siap pakai langsung di rgba($rgb, $alpha) pada CSS.
     */
    public function toRgbString(string $hex): string
    {
        return implode(', ', $this->hexToRgb($hex));
    }

    /**
     * Campur warna menuju hitam sebesar $ratio — dipakai untuk base gradient gelap
     * yang masih punya rona warna brand (lihat halaman login/registrasi).
     */
    public function mixTowardBlack(string $hex, float $ratio): string
    {
        return $this->rgbToHex($this->mix($this->hexToRgb($hex), [0, 0, 0], $ratio));
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function rgbToHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * Campur warna `$from` menuju `$to` sebesar `$ratio` (0 = tetap $from, 1 = jadi $to).
     *
     * @param array{0:int,1:int,2:int} $from
     * @param array{0:int,1:int,2:int} $to
     * @return array{0:int,1:int,2:int}
     */
    private function mix(array $from, array $to, float $ratio): array
    {
        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $ratio),
            (int) round($from[1] + ($to[1] - $from[1]) * $ratio),
            (int) round($from[2] + ($to[2] - $from[2]) * $ratio),
        ];
    }
}
