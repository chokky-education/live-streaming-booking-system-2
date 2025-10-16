<?php

use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset holidays before each test for determinism
        __set_booking_holidays_env(null);
    }

    public function testValidateReturnsErrorsForMissingFields(): void
    {
        $booking = new Booking(null); // DB not needed for validate
        $errors = $booking->validate([
            // omit package_id, pickup_date, return_date, location
            'pickup_time' => '09:00',
            'return_time' => '18:00',
        ]);

        // Expect at least these core errors
        $this->assertContains('กรุณาเลือกแพ็คเกจ', $errors);
        $this->assertContains('กรุณาเลือกวันรับอุปกรณ์', $errors);
        $this->assertContains('กรุณาเลือกวันคืนอุปกรณ์', $errors);
        $this->assertContains('กรุณาระบุสถานที่ใช้งาน', $errors);
    }

    public function testValidateDetectsInvalidDateOrder(): void
    {
        $booking = new Booking(null);
        $errors = $booking->validate([
            'package_id' => 1,
            'pickup_date' => '2025-10-05',
            'return_date' => '2025-10-04', // earlier than pickup
            'location' => 'Bangkok',
        ]);

        $this->assertContains('วันคืนต้องไม่น้อยกว่าวันรับ', $errors);
    }

    public function testCalculatePricingBreakdownWeekendSurchargeAndDay2(): void
    {
        $booking = new Booking(null);

        // Price: 1000, Fri-Sat range → 2 days
        $price = 1000.0;
        $pickup = '2025-10-03'; // Friday
        $return = '2025-10-04'; // Saturday (weekend surcharge)

        $b = $booking->calculatePricingBreakdown($price, $pickup, $return);

        $this->assertSame(2, $b['rental_days']);
        $this->assertEquals(1000.0, $b['base_day']);
        $this->assertEquals(400.0, $b['day2_surcharge']); // 40% of 1000
        $this->assertEquals(0.0, $b['day3_6_surcharge']);
        $this->assertEquals(0.0, $b['day7_plus_surcharge']);
        $this->assertEquals(100.0, $b['weekend_holiday_surcharge']); // 10% for Saturday
        $this->assertEquals(1500.0, $b['subtotal']);
    }

    public function testCalculatePricingBreakdownHolidayOnWeekday(): void
    {
        $booking = new Booking(null);

        // Set a weekday as holiday to ensure holiday surcharge is counted
        __set_booking_holidays_env('2025-10-06'); // Monday

        $price = 2000.0;
        $pickup = '2025-10-06';
        $return = '2025-10-06';

        $b = $booking->calculatePricingBreakdown($price, $pickup, $return);

        $this->assertSame(1, $b['rental_days']);
        $this->assertEquals(2000.0, $b['base_day']);
        $this->assertEquals(0.0, $b['day2_surcharge']);
        $this->assertEquals(0.0, $b['day3_6_surcharge']);
        $this->assertEquals(0.0, $b['day7_plus_surcharge']);
        $this->assertEquals(200.0, $b['weekend_holiday_surcharge']); // 10% holiday surcharge
        $this->assertEquals(2200.0, $b['subtotal']);
    }
}

