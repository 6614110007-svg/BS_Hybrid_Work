<?php

namespace App\Support;

/**
 * แปลง/อ่านพิกัดบนผังโต๊ะที่เก็บเป็น string "x,y"
 */
final class DeskGrid
{
    public const MAX_COLUMN = 8;

    public const MAX_ROW = 6;

    /**
     * @return array{0:int,1:int}
     */
    public static function parse(?string $value): array
    {
        $parts = array_map('trim', explode(',', (string) $value));

        return [(int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0)];
    }

    /**
     * แปลง "x,y" เป็นพิกัดบนผัง ใช้ตอนคำนวณระยะห่างระหว่างโต๊ะ
     *
     * @return array{x:int,y:int}
     */
    public static function toArray(?string $value): array
    {
        [$x, $y] = self::parse($value);

        return ['x' => $x, 'y' => $y];
    }

    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $parts = array_map('trim', explode(',', $value));

        if (count($parts) !== 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (! ctype_digit($part)) {
                return false;
            }
        }

        [$x, $y] = [(int) $parts[0], (int) $parts[1]];

        return $x >= 0 && $x <= self::MAX_COLUMN && $y >= 0 && $y <= self::MAX_ROW;
    }
}