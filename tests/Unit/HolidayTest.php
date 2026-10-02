<?php

namespace Tests\Unit;

use App\Support\Holiday;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    public function test_weekends_are_not_bookable(): void
    {
        $this->assertTrue(Holiday::isWeekend(Carbon::parse('2026-09-26'))); // เสาร์
        $this->assertTrue(Holiday::isWeekend(Carbon::parse('2026-09-27'))); // อาทิตย์
        $this->assertFalse(Holiday::isWeekend(Carbon::parse('2026-09-29'))); // อังคาร
    }

    public function test_configured_public_holidays_are_detected(): void
    {
        $this->assertTrue(Holiday::isHoliday('2026-04-06'));
        $this->assertFalse(Holiday::isHoliday('2026-04-07'));
        $this->assertSame('วันจักรี', Holiday::name('2026-04-06'));
        $this->assertNull(Holiday::name('2026-04-07'));
    }

    public function test_is_bookable_matches_holiday_rules(): void
    {
        $this->assertTrue(Holiday::isBookable('2026-09-29'));
        $this->assertFalse(Holiday::isBookable('2026-09-26'));
        $this->assertFalse(Holiday::isBookable('2026-12-31'));
    }

    public function test_reason_is_reported_for_blocked_days(): void
    {
        $this->assertNull(Holiday::reason('2026-09-29'));
        $this->assertSame(Holiday::WEEKEND_REASON, Holiday::reason('2026-09-26'));
        $this->assertSame(Holiday::HOLIDAY_REASON, Holiday::reason('2026-12-31'));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function holidayNameProvider(): array
    {
        return [
            'วันหยุดที่มีชื่อ' => ['2026-04-13', true],
            'วันธรรมดา' => ['2026-04-16', false],
        ];
    }

    #[DataProvider('holidayNameProvider')]
    public function test_holiday_name_is_available_for_tooltip(string $date, bool $expectName): void
    {
        $this->assertSame($expectName, Holiday::name($date) !== null);
    }

    public function test_holiday_config_covers_every_date_of_the_current_year(): void
    {
        $holidays = config('booking.holidays', []);

        $this->assertNotEmpty($holidays);
        $this->assertSame(
            date('Y'),
            substr((string) array_key_first($holidays), 0, 4),
            'ต้องมีรายการวันหยุดของปีปัจจุบันเสมอ (อัปเดต config/booking.php ทุกปีใหม่)'
        );

        foreach ($holidays as $date => $name) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $date);
            $this->assertIsString($name);
            $this->assertNotSame('', $name);
        }
    }
}
