<?php

namespace Tests\Unit;

use App\Models\Holiday;
use App\Models\Project;
use App\Services\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji logika CalendarService terhadap setting weekend dan hari libur.
 * Jalankan dengan: php artisan test --filter=CalendarServiceTest
 */
class CalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalendarService $kalender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kalender = new CalendarService();
    }

    /** Buat proyek dengan weekend (Sabtu, Minggu) dan mode no_work. */
    private function proyek(array $atribut = []): Project
    {
        return Project::create(array_merge([
            'name' => 'Proyek Uji',
            'start_date' => '2026-10-05', // Senin
            'weekend_days' => [6, 0],
            'weekend_mode' => 'no_work',
        ], $atribut));
    }

    public function test_hari_kerja_pada_hari_biasa(): void
    {
        $proyek = $this->proyek();

        $this->assertTrue($this->kalender->isWorkingDay($proyek, '2026-10-14')); // Rabu
        $this->assertFalse($this->kalender->isWorkingDay($proyek, '2026-10-17')); // Sabtu
        $this->assertFalse($this->kalender->isWorkingDay($proyek, '2026-10-18')); // Minggu
    }

    public function test_mode_work_membuat_weekend_jadi_hari_kerja(): void
    {
        $proyek = $this->proyek(['weekend_mode' => 'work']);

        $this->assertTrue($this->kalender->isWorkingDay($proyek, '2026-10-17')); // Sabtu
        $this->assertTrue($this->kalender->isWorkingDay($proyek, '2026-10-18')); // Minggu
    }

    public function test_hari_libur_sekali_dan_berulang(): void
    {
        $proyek = $this->proyek();

        // libur sekali: 17 Agustus 2026 saja
        Holiday::create([
            'project_id' => $proyek->id,
            'date' => '2026-08-17',
            'description' => 'Kemerdekaan',
            'is_recurring' => false,
        ]);
        // libur berulang: 1 Juni setiap tahun
        Holiday::create([
            'project_id' => $proyek->id,
            'date' => '2026-06-01',
            'description' => 'Hari Anak',
            'is_recurring' => true,
        ]);

        $this->assertFalse($this->kalender->isWorkingDay($proyek, '2026-08-17')); // libur sekali
        $this->assertTrue($this->kalender->isWorkingDay($proyek, '2027-08-17')); // tahun lain: kerja
        $this->assertFalse($this->kalender->isWorkingDay($proyek, '2026-06-01')); // berulang
        // 2032-06-01 Selasa (bukan weekend), tapi tetap libur karena berulang
        $this->assertFalse($this->kalender->isWorkingDay($proyek, '2032-06-01')); // berulang, tahun lain
    }

    public function test_add_working_days_melewati_weekend(): void
    {
        $proyek = $this->proyek();

        // Jumat + 1 hari kerja = Senin (Sabtu-Minggu libur)
        $hasil = $this->kalender->addWorkingDays($proyek, '2026-10-16', 1);
        $this->assertSame('2026-10-19', $hasil->toDateString());

        // n = 0 mengembalikan tanggal yang sama
        $this->assertSame('2026-10-14', $this->kalender->addWorkingDays($proyek, '2026-10-14', 0)->toDateString());
    }

    public function test_add_working_days_negatif_menggeser_ke_belakang(): void
    {
        $proyek = $this->proyek();

        // Senin - 1 hari kerja = Jumat sebelumnya
        $hasil = $this->kalender->addWorkingDays($proyek, '2026-10-19', -1);
        $this->assertSame('2026-10-16', $hasil->toDateString());
    }

    public function test_next_working_dan_hitung_hari_kerja(): void
    {
        $proyek = $this->proyek();

        // Sabtu -> next working day = Senin
        $this->assertSame('2026-10-19', $this->kalender->nextWorkingDay($proyek, '2026-10-17')->toDateString());
        // hari kerja tetap sendiri
        $this->assertSame('2026-10-14', $this->kalender->nextWorkingDay($proyek, '2026-10-14')->toDateString());

        // Senin..Jumat penuh = 5 hari kerja (inklusif)
        $this->assertSame(5, $this->kalender->countWorkingDays($proyek, '2026-10-12', '2026-10-16'));
        // rentang terbalik = 0
        $this->assertSame(0, $this->kalender->countWorkingDays($proyek, '2026-10-16', '2026-10-12'));
    }
}
