<?php

namespace App\Support;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * ช่วงเวลาจองโต๊ะ (เก็บเป็น VARCHAR บนตาราง booking.time_slot ตาม Database Schema)
 * ตัวเลือกทั้งหมดกำหนดไว้ที่ config/booking.php
 */
final class TimeSlot
{
    public function __construct(
        public readonly string $name,
        public readonly string $start,
        public readonly string $end,
    ) {
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return array_map(
            fn (string $name, array $times) => new self($name, $times['start'], $times['end']),
            array_keys((array) config('booking.slots', [])),
            array_values((array) config('booking.slots', [])),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(fn (self $slot) => $slot->name, self::all());
    }

    public static function find(string $name): self
    {
        foreach (self::all() as $slot) {
            if ($slot->name === $name) {
                return $slot;
            }
        }

        throw new InvalidArgumentException("ไม่พบช่วงเวลา \"{$name}\"");
    }

    public static function exists(string $name): bool
    {
        return in_array($name, self::names(), true);
    }

    /**
     * ช่วงเวลาที่ "กำลัง" ครอบคลุมเวลาปัจจุบันอยู่
     */
    public static function current(): ?self
    {
        $now = Carbon::now();

        foreach (self::all() as $slot) {
            if ($now->betweenIncluded($slot->openOn($now->toDateString()), $slot->closeOn($now->toDateString()))) {
                return $slot;
            }
        }

        return null;
    }

    public function openOn(string $date): Carbon
    {
        return Carbon::parse($date.' '.$this->start);
    }

    public function closeOn(string $date): Carbon
    {
        return Carbon::parse($date.' '.$this->end);
    }

    /**
     * เช็คอินได้ตั้งแต่ก่อนเริ่มงานกี่นาที
     */
    public function opensAt(string $date): Carbon
    {
        return $this->openOn($date)->subMinutes((int) config('booking.early_checkin_minutes', 60));
    }

    /**
     * เลยกำหนดเช็คอิน -> ระบบ auto-cancel
     */
    public function deadlineOn(string $date): Carbon
    {
        return $this->openOn($date)->addMinutes((int) config('booking.late_grace_minutes', 60));
    }

    public function containsTime(Carbon $moment): bool
    {
        return $moment->betweenIncluded($this->openOn($moment->toDateString()), $this->closeOn($moment->toDateString()));
    }
}
