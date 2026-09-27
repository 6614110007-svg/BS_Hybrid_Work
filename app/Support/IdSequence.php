<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * สร้างรหัสประจำตัวแบบ CHAR(11) เช่น EMP00000001 / DSK00000012
 *
 * ลำดับเลขถูกอ่านจากค่าสูงสุดในตารางภายใต้ row lock เพื่อกันการออกรหัสซ้ำ
 */
class IdSequence
{
    public const WIDTH = 11;

    public static function next(string $prefix, string $table, string $key): string
    {
        $latest = DB::table($table)->orderByDesc($key)->lockForUpdate()->value($key);

        $sequence = $latest ? ((int) substr((string) $latest, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, self::WIDTH - strlen($prefix), '0', STR_PAD_LEFT);
    }
}
