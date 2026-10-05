<?php

namespace App\Support;

/**
 * ชุด avatar สัตว์น่ารักสำหรับให้ผู้ใช้เลือกตอนตั้งค่าบัญชี
 *
 * วาดด้วย SVG รูปทรงเรขาคณิตล้วน ไม่พึ่งไลบรารีภายนอก
 * หน้าต่าง 40x40 ทุกตัวใช้โครงหน้า/ตา/จมูยร่วมกัน ต่างกันที่สีและรูปหู
 */
final class AnimalAvatar
{
    /** สี/รูปทรงประจำตัว: fill = สีหน้า, accent = สีรายละเอียด, ear = รูปหู */
    private const ANIMALS = [
        'cat' => ['label' => 'แมว', 'fill' => '#FBBF24', 'accent' => '#F59E0B', 'ear' => 'pointy'],
        'dog' => ['label' => 'หมา', 'fill' => '#D9A066', 'accent' => '#B9834B', 'ear' => 'floppy'],
        'rabbit' => ['label' => 'กระต่าย', 'fill' => '#F3F4F6', 'accent' => '#FBCFE8', 'ear' => 'tall'],
        'bear' => ['label' => 'หมี', 'fill' => '#A16207', 'accent' => '#78350F', 'ear' => 'round'],
        'lion' => ['label' => 'สิงห์', 'fill' => '#FCD34D', 'accent' => '#B45309', 'ear' => 'mane'],
        'tiger' => ['label' => 'เสือ', 'fill' => '#F97316', 'accent' => '#7C2D12', 'ear' => 'pointy'],
        'panda' => ['label' => 'แพนด้า', 'fill' => '#F9FAFB', 'accent' => '#111827', 'ear' => 'round'],
        'koala' => ['label' => 'โคอาลา', 'fill' => '#9CA3AF', 'accent' => '#4B5563', 'ear' => 'fluffy'],
        'fox' => ['label' => 'สุนัขจิ้งจอก', 'fill' => '#FB923C', 'accent' => '#7C2D12', 'ear' => 'wide'],
        'wolf' => ['label' => 'หมูป่า', 'fill' => '#6B7280', 'accent' => '#374151', 'ear' => 'pointy'],
        'deer' => ['label' => 'กวาง', 'fill' => '#D6BC8A', 'accent' => '#8B5E34', 'ear' => 'antler'],
        'owl' => ['label' => 'นกฮูก', 'fill' => '#C4B5FD', 'accent' => '#5B21B6', 'ear' => 'tuft'],
        'monkey' => ['label' => 'ลิง', 'fill' => '#B45309', 'accent' => '#78350F', 'ear' => 'round'],
        'frog' => ['label' => 'กบ', 'fill' => '#86EFAC', 'accent' => '#166534', 'ear' => 'none'],
        'turtle' => ['label' => 'เต่า', 'fill' => '#A3E635', 'accent' => '#3F6212', 'ear' => 'none'],
        'pig' => ['label' => 'หมู', 'fill' => '#F9A8D4', 'accent' => '#BE185D', 'ear' => 'floppy'],
        'sheep' => ['label' => 'แกะ', 'fill' => '#FDE68A', 'accent' => '#FFFFFF', 'ear' => 'fluffy'],
        'horse' => ['label' => 'ม้า', 'fill' => '#92400E', 'accent' => '#1C1917', 'ear' => 'pointy'],
        'elephant' => ['label' => 'ช้าง', 'fill' => '#93C5FD', 'accent' => '#1E3A8A', 'ear' => 'fan'],
        'penguin' => ['label' => 'เพนกวิน', 'fill' => '#1F2937', 'accent' => '#F9A8D4', 'ear' => 'none'],
    ];

    /**
     * รายการ avatar ทั้งหมด
     *
     * @return array<string,array{label:string,fill:string,accent:string}>
     */
    public static function all(): array
    {
        $out = [];

        foreach (self::ANIMALS as $key => $meta) {
            $out[$key] = [
                'label' => $meta['label'],
                'fill' => $meta['fill'],
                'accent' => $meta['accent'],
            ];
        }

        return $out;
    }

    /**
     * @return array<int,string>
     */
    public static function keys(): array
    {
        return array_keys(self::ANIMALS);
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::ANIMALS);
    }

    public static function label(?string $key): string
    {
        return self::ANIMALS[$key]['label'] ?? 'ไม่ระบุ';
    }

    /**
     * คีย์เริ่มต้น ใช้ตอนยังไม่ได้เลือก
     */
    public static function fallback(): string
    {
        return 'cat';
    }

    /**
     * วาด avatar เป็น inline SVG
     *
     * @param  string  $class  คลาส Tailwind ของ <svg> เช่น "h-9 w-9"
     */
    public static function svg(?string $key, string $class = 'h-10 w-10'): string
    {
        $key = self::isValid($key) ? $key : self::fallback();
        $meta = self::ANIMALS[$key];

        return '<svg viewBox="0 0 40 40" class="'.e($class).'" role="img" aria-label="'.e($meta['label']).'" fill="none" xmlns="http://www.w3.org/2000/svg">'
            .self::ears($key, $meta)
            .self::head($key, $meta)
            .self::face($key, $meta)
            .'</svg>';
    }

    /**
     * รูปหู/เครื่องประดับหัว วาดก่อนหน้าหน้าเพื่อให้หูดูอยู่ข้างหลังใบหน้า
     */
    private static function ears(string $key, array $meta): string
    {
        $fill = $meta['fill'];
        $accent = $meta['accent'];

        return match ($meta['ear']) {
            'pointy' => '<path d="M10 15 L11.5 4.5 L19 11 Z" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2" stroke-linejoin="round"/>'
                .'<path d="M30 15 L28.5 4.5 L21 11 Z" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2" stroke-linejoin="round"/>',
            'wide' => '<path d="M8 16 L9 3.5 L19.5 10 Z" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2" stroke-linejoin="round"/>'
                .'<path d="M32 16 L31 3.5 L20.5 10 Z" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2" stroke-linejoin="round"/>',
            'tall' => '<ellipse cx="14" cy="8" rx="3.4" ry="7.5" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>'
                .'<ellipse cx="14" cy="8" rx="1.6" ry="4.6" fill="'.$accent.'" opacity=".55"/>'
                .'<ellipse cx="26" cy="8" rx="3.4" ry="7.5" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>'
                .'<ellipse cx="26" cy="8" rx="1.6" ry="4.6" fill="'.$accent.'" opacity=".55"/>',
            'round' => '<circle cx="10.5" cy="13" r="5.2" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>'
                .'<circle cx="29.5" cy="13" r="5.2" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>',
            'fluffy' => '<circle cx="9.5" cy="13.5" r="6.6" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>'
                .'<circle cx="30.5" cy="13.5" r="6.6" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>'
                .'<circle cx="9.5" cy="13.5" r="2.4" fill="'.$accent.'" opacity=".5"/>'
                .'<circle cx="30.5" cy="13.5" r="2.4" fill="'.$accent.'" opacity=".5"/>',
            'floppy' => '<ellipse cx="8.5" cy="23" rx="4.4" ry="8.4" fill="'.$accent.'"/>'
                .'<ellipse cx="31.5" cy="23" rx="4.4" ry="8.4" fill="'.$accent.'"/>',
            'antler' => '<path d="M13 14 L10 5 M10 5 L7 7 M10 5 L12 2" stroke="'.$accent.'" stroke-width="1.6" stroke-linecap="round" fill="none"/>'
                .'<path d="M27 14 L30 5 M30 5 L33 7 M30 5 L28 2" stroke="'.$accent.'" stroke-width="1.6" stroke-linecap="round" fill="none"/>'
                .'<ellipse cx="13.5" cy="18" rx="3.4" ry="2.4" fill="'.$accent.'"/>'
                .'<ellipse cx="26.5" cy="18" rx="3.4" ry="2.4" fill="'.$accent.'"/>',
            'tuft' => '<path d="M13 13 L15 5 L19 11 Z" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.1" stroke-linejoin="round"/>'
                .'<path d="M27 13 L25 5 L21 11 Z" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.1" stroke-linejoin="round"/>',
            'fan' => '<ellipse cx="7.5" cy="22" rx="6.4" ry="9.4" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>'
                .'<ellipse cx="32.5" cy="22" rx="6.4" ry="9.4" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>',
            'mane' => '<circle cx="20" cy="22" r="15" fill="'.$accent.'" opacity=".35"/>',
            default => '',
        };
    }

    /**
     * รูปหน้า/หัว
     */
    private static function head(string $key, array $meta): string
    {
        $fill = $meta['fill'];
        $accent = $meta['accent'];

        // บางตัวไม่ใช้หน้ากลม แต่ใช้ทรงเฉพาะตัวเพื่อให้จดจำได้ทันที
        return match ($key) {
            'elephant' => '<ellipse cx="20" cy="22" rx="11" ry="10.5" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>'
                .'<path d="M20 26 q4 3 3 7 q-3 2 -6 0 q0 -4 3 -7 Z" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2" stroke-linejoin="round"/>',
            'frog' => '<ellipse cx="20" cy="24" rx="13" ry="9.5" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>',
            'turtle' => '<ellipse cx="20" cy="22" rx="14" ry="10" fill="'.$accent.'"/>'
                .'<path d="M20 13 L28 20 L24 30 L16 30 L12 20 Z" fill="'.$fill.'" opacity=".7"/>'
                .'<circle cx="7" cy="24" r="4.2" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.1"/>',
            'penguin' => '<ellipse cx="20" cy="22" rx="11" ry="13" fill="'.$fill.'"/>'
                .'<ellipse cx="20" cy="25" rx="7" ry="8.5" fill="#F9FAFB"/>',
            'sheep' => '<circle cx="20" cy="22" r="12" fill="'.$accent.'"/>'
                .'<circle cx="11" cy="17" r="5" fill="'.$accent.'"/>'
                .'<circle cx="29" cy="17" r="5" fill="'.$accent.'"/>'
                .'<circle cx="10" cy="25" r="5" fill="'.$accent.'"/>'
                .'<circle cx="30" cy="25" r="5" fill="'.$accent.'"/>'
                .'<circle cx="16" cy="12" r="5" fill="'.$accent.'"/>'
                .'<circle cx="25" cy="13" r="5" fill="'.$accent.'"/>'
                .'<ellipse cx="20" cy="23" rx="7" ry="6.5" fill="'.$fill.'"/>',
            default => '<circle cx="20" cy="22" r="12" fill="'.$fill.'" stroke="'.$accent.'" stroke-width="1.2"/>',
        };
    }

    /**
     * ตา จมูย และรายละเอียดเฉพาะตัว
     */
    private static function face(string $key, array $meta): string
    {
        $accent = $meta['accent'];
        $ink = '#1F2937';

        $eyes = match ($key) {
            'owl' => '<circle cx="15.5" cy="21" r="4.6" fill="#FFF" stroke="'.$accent.'" stroke-width="1.1"/>'
                .'<circle cx="24.5" cy="21" r="4.6" fill="#FFF" stroke="'.$accent.'" stroke-width="1.1"/>'
                .'<circle cx="15.5" cy="21" r="2.1" fill="'.$ink.'"/>'
                .'<circle cx="24.5" cy="21" r="2.1" fill="'.$ink.'"/>',
            'frog', 'turtle', 'penguin' => '<circle cx="15.5" cy="21.5" r="2.1" fill="'.$ink.'"/>'
                .'<circle cx="24.5" cy="21.5" r="2.1" fill="'.$ink.'"/>'
                .'<circle cx="16.4" cy="20.6" r=".7" fill="#FFF"/>'
                .'<circle cx="25.4" cy="20.6" r=".7" fill="#FFF"/>',
            default => '<circle cx="15.8" cy="21.5" r="2" fill="'.$ink.'"/>'
                .'<circle cx="24.2" cy="21.5" r="2" fill="'.$ink.'"/>'
                .'<circle cx="16.6" cy="20.7" r=".7" fill="#FFF"/>'
                .'<circle cx="25" cy="20.7" r=".7" fill="#FFF"/>',
        };

        // แต่งเติมเฉพาะตัวที่ต้องการรายละเอียดเพิ่ม
        $extra = match ($key) {
            'tiger' => '<path d="M11 17 l4 1.5 M29 17 l-4 1.5 M12 26 l3.5 -1 M28 26 l-3.5 -1" stroke="'.$accent.'" stroke-width="1.6" stroke-linecap="round"/>',
            'panda' => '<ellipse cx="15.8" cy="21.5" rx="3.6" ry="4.2" fill="'.$accent.'" opacity=".85"/>'
                .'<ellipse cx="24.2" cy="21.5" rx="3.6" ry="4.2" fill="'.$accent.'" opacity=".85"/>',
            'lion' => '',
            'turtle' => '<path d="M9 20 q4 -2 7 0 M24 20 q4 -2 7 0" stroke="'.$accent.'" stroke-width="1.4" stroke-linecap="round"/>',
            'sheep' => '<ellipse cx="20" cy="25" rx="3" ry="2.4" fill="'.$ink.'"/>',
            'elephant' => '<path d="M13 19 q2 -1.5 4 0 M23 19 q2 -1.5 4 0" stroke="'.$accent.'" stroke-width="1.4" stroke-linecap="round"/>',
            'penguin' => '<path d="M20 23 l-2.6 2 h5.2 Z" fill="'.$accent.'"/>',
            default => '',
        };

        $mouth = match ($key) {
            'cat', 'lion', 'tiger', 'panda', 'koala', 'rabbit', 'deer', 'monkey', 'horse', 'sheep'
                => '<path d="M20 26 q-2.6 2.6 -5 .4 M20 26 q2.6 2.6 5 .4" stroke="'.$ink.'" stroke-width="1.4" stroke-linecap="round" fill="none"/>'
                    .'<path d="M20 24.6 l-1.9 -1.7 h3.8 Z" fill="'.$ink.'"/>',
            'dog', 'fox', 'wolf', 'bear' => '<ellipse cx="20" cy="27" rx="3" ry="2.3" fill="'.$ink.'"/>',
            'pig' => '<ellipse cx="20" cy="26.6" rx="5" ry="3.6" fill="'.$accent.'"/>'
                .'<circle cx="18.2" cy="26.4" r="1" fill="'.$ink.'"/>'
                .'<circle cx="21.8" cy="26.4" r="1" fill="'.$ink.'"/>',
            'elephant' => '',
            'frog', 'turtle', 'penguin' => '<path d="M15.5 27 q4.5 3.4 9 0" stroke="'.$ink.'" stroke-width="1.4" stroke-linecap="round" fill="none"/>',
            default => '<path d="M16.5 26.5 q3.5 3 7 0" stroke="'.$ink.'" stroke-width="1.4" stroke-linecap="round" fill="none"/>',
        };

        $whiskers = in_array($key, ['cat', 'tiger', 'fox', 'wolf'], true)
            ? '<path d="M4 22 h5 M4 25.5 h5 M31 22 h5 M31 25.5 h5" stroke="'.$accent.'" stroke-width="1.1" stroke-linecap="round"/>'
            : '';

        return $eyes.$extra.$mouth.$whiskers;
    }
}