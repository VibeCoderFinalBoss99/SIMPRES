<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecapTest extends TestCase
{
    use DatabaseTransactions;

    private User $siti;

    private User $budi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
        User::query()->delete();

        $this->siti = $this->makePegawai(null, ['name' => 'Siti Aminah', 'nip' => '198501012010012001', 'created_at' => '2026-10-01 08:00:00']);
        $this->budi = $this->makePegawai(null, ['name' => 'Budi Santoso', 'nip' => '199002022015021002', 'created_at' => '2026-10-01 08:00:00']);
        $this->actingAs($this->makeAdmin());

        $this->attend($this->siti, '2026-11-16', 'hadir');
        $this->attend($this->siti, '2026-11-17', 'terlambat', 25);
        $this->attend($this->siti, '2026-11-18', 'terlambat', 5);
        $this->attend($this->siti, '2026-11-19', 'izin');
        $this->attend($this->budi, '2026-11-16', 'hadir');
        $this->attend($this->siti, '2026-10-31', 'hadir'); // bulan lain

        // Budi dijadwalkan 17-19 dan 21 Nov, tapi tidak absen 17 dan 18 (19 libur, 21 belum lewat).
        foreach (['2026-11-17' => false, '2026-11-18' => false, '2026-11-19' => true, '2026-11-21' => false] as $date => $dayOff) {
            Schedule::create(['user_id' => $this->budi->id, 'date' => $date, 'is_day_off' => $dayOff]);
        }
    }

    private function attend(User $user, string $date, string $status, int $late = 0): void
    {
        Attendance::create(['user_id' => $user->id, 'date' => $date, 'status' => $status, 'late_minutes' => $late]);
    }

    public function test_angka_rekap_sama_dengan_query_rekap_di_panduan(): void
    {
        $rows = $this->get('/admin/rekap?month=2026-11')->assertOk()->viewData('rows')->keyBy('name');

        // Query nomor 6 di docs/PANDUAN.md (dengan bulan tetap November 2026).
        $panduan = collect(DB::select("
            SELECT u.name,
                   SUM(a.status = 'hadir') AS hadir,
                   SUM(a.status = 'terlambat') AS terlambat,
                   SUM(a.status = 'izin') AS izin
            FROM users u
            LEFT JOIN attendances a ON a.user_id = u.id AND a.date BETWEEN '2026-11-01' AND '2026-11-30'
            WHERE u.role = 'pegawai'
            GROUP BY u.id, u.name"))->keyBy('name');

        foreach (['Siti Aminah', 'Budi Santoso'] as $name) {
            foreach (['hadir', 'terlambat', 'izin'] as $column) {
                $this->assertSame((int) $panduan[$name]->$column, $rows[$name]->$column, "$name $column");
            }
        }

        $this->assertSame([1, 2, 1, 0, 30], [$rows['Siti Aminah']->hadir, $rows['Siti Aminah']->terlambat, $rows['Siti Aminah']->izin, $rows['Siti Aminah']->alpa, $rows['Siti Aminah']->late_minutes]);
        $this->assertSame(2, $rows['Budi Santoso']->alpa);
    }

    public function test_ganti_bulan_data_ikut_berubah(): void
    {
        $rows = $this->get('/admin/rekap?month=2026-10')->assertOk()->assertSee('Oktober 2026')->viewData('rows')->keyBy('name');

        $this->assertSame(1, $rows['Siti Aminah']->hadir);
        $this->assertSame(0, $rows['Siti Aminah']->terlambat);
        $this->assertSame(0, $rows['Budi Santoso']->alpa);
    }

    public function test_unduh_csv_rapi_untuk_excel(): void
    {
        $response = $this->get('/admin/rekap/csv?month=2026-11')->assertOk();

        $response->assertHeader('Content-Disposition', 'attachment; filename=rekap-absensi-2026-11.csv');
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertSame('No;NIP;Nama;Jabatan;"Masuk (hari)";"Tepat waktu (hari)";"Terlambat (hari)";"Izin/Sakit (hari)";"Alpa (hari)";"Total terlambat (menit)"', $lines[0]);
        // Masuk = tepat waktu + terlambat.
        $this->assertSame('1;"=""199002022015021002""";"Budi Santoso";Perawat;1;1;0;0;2;0', $lines[1]);
        $this->assertSame('2;"=""198501012010012001""";"Siti Aminah";Perawat;3;1;2;1;0;30', $lines[2]);
    }

    public function test_unduh_xlsx_berisi_rekap_dan_total(): void
    {
        $response = $this->get('/admin/rekap/xlsx?month=2026-11')->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=rekap-absensi-2026-11.xlsx');

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
        $zip->close();

        // NIP disimpan sebagai teks (tidak berubah jadi 1,98501E+17), angka sebagai angka.
        $this->assertStringContainsString('<t xml:space="preserve">198501012010012001</t>', $sheet);
        $this->assertStringContainsString('<t xml:space="preserve">Siti Aminah</t>', $sheet);
        $this->assertStringContainsString('Rekap Absensi Pegawai - November 2026', $sheet);
        // Baris total: hadir 2, terlambat 2, izin 1, alpa 2, menit 30.
        $this->assertMatchesRegularExpression('#Total</t></is></c>.*<v>2</v></c>.*<v>2</v></c>.*<v>1</v></c>.*<v>2</v></c>.*<v>30</v></c></row>#', $sheet);
    }

    public function test_unduh_pdf_berisi_rekap(): void
    {
        $response = $this->get('/admin/rekap/pdf?month=2026-11')->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename=rekap-absensi-2026-11.pdf');

        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString('(Siti Aminah) Tj', $pdf);
        $this->assertStringContainsString('(Rekap Absensi Pegawai - November 2026) Tj', $pdf);

        // Posisi tabel xref harus menunjuk tepat ke kata "xref", kalau tidak PDF dianggap rusak.
        preg_match('/startxref\n(\d+)/', $pdf, $m);
        $this->assertSame('xref', substr($pdf, (int) $m[1], 4));
    }

    public function test_unduhan_rekap_hanya_untuk_admin(): void
    {
        $this->actingAs($this->siti);

        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $this->get("/admin/rekap/$format?month=2026-11")->assertRedirect('/admin/login');
        }
    }

    public function test_bulan_tidak_valid_ditolak(): void
    {
        $this->get('/admin/rekap?month=November')->assertSessionHasErrors('month');
    }
}
