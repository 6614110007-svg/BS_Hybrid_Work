<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * โจทย์สุ่มสำหรับการถ่ายรูปเช็คอิน (Selfie Challenge)
 *
 * พนักงานจะได้โจทย์ 1 ข้อแบบสุ่มทุกครั้งที่เข้าหน้าเช็คอิน
 * เพื่อให้รูปเช็คอินหลากหลายและเป็นธรรมชาติมากขึ้น
 * ทั้งหมดมี 5 หมวด หมวดละ 10 โจทย์ รวม 50 โจทย์
 */
final class SelfieChallenge
{
    public const CATEGORY_POSE = 'pose';

    public const CATEGORY_PROP = 'prop';

    public const CATEGORY_SMILE = 'smile';

    public const CATEGORY_VIBE = 'vibe';

    public const CATEGORY_QUICK = 'quick';

    /**
     * หมวดโจทย์: key => [ป้ายกำกับที่แสดงผล, โจทย์ทั้งหมดในหมวด]
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    private const PROMPTS = [
        self::CATEGORY_POSE => ['ท่าทางมือและใบหน้า', [
            'ชู 2 นิ้วสู้ๆ ข้างแก้ม',
            'ทำท่ามินิฮาร์ท (Mini Heart) สไตล์เกาหลี',
            'ทำท่าไอเลิฟยู (🤟) ส่งยิ้มสดใส',
            'ทำท่าโอเค (👌) ไว้ข้างแก้ม',
            'ทำท่ามือไอติมซอฟต์เสิร์ฟประกบข้างคาง',
            'เอามือแตะคางทำท่าคิดวิเคราะห์',
            'ทำมือรูปหัวใจดวงโตบนศีรษะ',
            'ทำท่ากำมือไฟท์ติ้ง (Fighting) สู้ๆ',
            'ทำท่าเอามือทาบอกพร้อมยิ้มหวาน',
            'เอามือแตะแก้มสองข้างทำท่าตกใจเบาๆ',
        ]],
        self::CATEGORY_PROP => ['อุปกรณ์และของชิ้นโปรด', [
            'ถ่ายคู่กับแก้วน้ำหรือกาแฟแก้วโปรด',
            'ถ่ายคู่กับปากกาด้ามโปรดในมือ',
            'ถ่ายคู่กับสมุดโน้ตประจำตัว',
            'ถ่ายคู่กับเมาส์หรือคีย์บอร์ดคู่ใจ',
            'ถ่ายคู่กับหูฟังคู่ใจพร้อมทำงาน',
            'ถ่ายคู่กับป้ายชื่อพนักงาน (Employee Card)',
            'ถ่ายคู่กับโพสต์อิท (Post-it) โน้ตเตือนความจำ',
            'ถ่ายคู่กับกระติกน้ำสายคลีน',
            'ถ่ายคู่กับคลิปหนีบกระดาษหรืออุปกรณ์เครื่องเขียน',
            'ถ่ายคู่กับเป็กทำงานประจำวัน',
        ]],
        self::CATEGORY_SMILE => ['รอยยิ้มและอารมณ์', [
            'ยิ้มยิงฟันสดใสรับวันใหม่',
            'ยิ้มหลับตา 1 ข้าง (หวั่ง) แบบน่ารัก',
            'ยิ้มแบบอมยิ้มเรียบร้อยน่าเอ็นดู',
            'ทำหน้าประทับใจพร้อมยิ้มกว้าง',
            'ยิ้มพร้อมทำท่าโอเคตอบรับวันใหม่',
            'ทำหน้าพร้อมตื่นนอนสดชื่น 100%',
            'ยิ้มแบบผ่อนคลายพร้อมสูดลมหายใจ',
            'ยิ้มกว้างพร้อมชูนิ้วโป้งเยี่ยมยอด (👍)',
            'ยิ้มแบบโปรเฟสชันนอลมั่นใจพร้อมลุย',
            'ส่งยิ้มและยักหน้าให้กล้อง',
        ]],
        self::CATEGORY_VIBE => ['วันสัปดาห์และบรรยากาศ', [
            'วันจันทร์: ทำท่าเติมพลังชูมือขึ้นฟ้า',
            'วันอังคาร: ทำท่าชู 2 นิ้ว สดใสชมพู',
            'วันพุธ: ยิ้มกว้างทักทายกลางสัปดาห์',
            'วันพฤหัสบดี: ทำท่ามินิฮาร์ทเตรียมลุย',
            'วันศุกร์: ทำท่าเย้! (Yay) ต้อนรับสุดสัปดาห์',
            'ถ่ายภาพสไตล์เซลฟี่มุมสูงจากด้านบน',
            'ถ่ายภาพคู่กับแสงแดดยามเช้าที่หน้าต่าง',
            'ถ่ายให้เห็นโต๊ะทำงานพร้อมใช้งาน',
            'ถ่ายเซลฟี่ครึ่งตัวทรงตัวตรงสไตล์ทางการ',
            'ถ่ายภาพคู่กับบรรยากาศต้นไม้ในออฟฟิศ',
        ]],
        self::CATEGORY_QUICK => ['ท่าทางรวดเร็ว', [
            'ชูนิ้วโป้งให้กำลังใจ (Thumbs up 👍)',
            'แตะหน้าผากทำท่าเคารพสไตล์ทหาร',
            'เอานิ้วชี้แตะที่ริมฝีปากเบาๆ',
            'ทำท่าโอเคด้วยสองมือ (Double OK)',
            'ทำท่าแตะไหล่ตัวเองเบาๆ เพื่อให้กำลังใจ',
            'ชู 3 นิ้วสัญลักษณ์ความพร้อม',
            'ทำท่าเอามือแนบข้างแก้มเหมือนนอนหลับ แล้วยิ้ม',
            'ทำท่าพนมมือไหว้ทักทายแบบไทย',
            'แตะแว่นตา (หรือแตะโหนกแก้ม) แบบมั่นใจ',
            'ทำท่ากำมือชกกล้องเบาๆ แบบ "กอดหัวใจ"',
        ]],
    ];

    /**
     * @return array<int, string> key ของทุกหมวด
     */
    public static function categories(): array
    {
        return array_keys(self::PROMPTS);
    }

    /**
     * ป้ายกำกับของทุกหมวด
     *
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return array_map(fn (array $definition) => $definition[0], self::PROMPTS);
    }

    /**
     * โจทย์ทั้งหมดแบบแบน ๆ (50 ข้อ)
     *
     * @return array<int, string>
     */
    public static function texts(): array
    {
        return array_merge(...array_column(self::PROMPTS, 1));
    }

    /**
     * โจทย์ของหมวดเดียว
     *
     * @return array<int, string>
     */
    public static function textsOf(string $category): array
    {
        return self::PROMPTS[$category][1] ?? [];
    }

    /**
     * สุ่มโจทย์ 1 ข้อ พร้อมป้ายกำกับหมวดมาให้พร้อมกัน
     *
     * @return array{category: string, label: string, text: string}
     */
    public static function random(): array
    {
        $category = Arr::random(self::categories());

        return [
            'category' => $category,
            'label' => self::categoryLabels()[$category],
            'text' => Arr::random(self::textsOf($category)),
        ];
    }

    /**
     * จำนวนโจทย์ทั้งหมด (ใช้ในเทสต์และหน้าจอแสดงผล)
     */
    public static function count(): int
    {
        return count(self::texts());
    }

    /**
     * ข้อมูลชุดเดียวสำหรับส่งให้หน้าเว็บสุ่มโจทย์ใหม่เองได้
     *
     * @return array{labels: array<string, string>, prompts: array<string, array<int, string>>}
     */
    public static function toArray(): array
    {
        $prompts = [];

        foreach (self::PROMPTS as $category => [$label, $items]) {
            $prompts[$category] = $items;
        }

        return [
            'labels' => self::categoryLabels(),
            'prompts' => $prompts,
        ];
    }
}
