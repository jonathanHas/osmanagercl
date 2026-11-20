<?php

namespace Tests\Feature;

use App\Services\PdfCompressionService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Image-heavy invoice PDFs are shrunk after upload, but only when nothing readable
 * can be lost: scans are never touched and the words must survive unchanged.
 */
class PdfCompressionServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['gs', 'pdftotext'] as $binary) {
            if (trim((string) shell_exec('command -v '.$binary)) === '') {
                $this->markTestSkipped("{$binary} is not installed");
            }
        }

        $this->dir = sys_get_temp_dir().'/pdfcompress_test_'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            array_map('unlink', glob($this->dir.'/*') ?: []);
            @rmdir($this->dir);
        }

        parent::tearDown();
    }

    /** A detailed photo that fills an A4 page at about 300 dpi. */
    private function photo(): string
    {
        $image = imagecreatetruecolor(2480, 3508);
        mt_srand(7);

        for ($i = 0; $i < 6000; $i++) {
            imagefilledellipse(
                $image, mt_rand(0, 2480), mt_rand(0, 3508), mt_rand(20, 400), mt_rand(20, 400),
                imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255))
            );
        }

        $path = $this->dir.'/photo.jpg';
        imagejpeg($image, $path, 95);

        return $path;
    }

    private function pdf(bool $withText): string
    {
        $pdf = new \FPDF('P', 'mm', 'A4');

        if ($withText) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 11);
            for ($line = 1; $line <= 30; $line++) {
                $pdf->Cell(0, 7, "Invoice line {$line} net 934.19 VAT 84.08 total 923.17", 0, 1);
            }
        }

        $pdf->AddPage();
        $pdf->Image($this->photo(), 0, 0, 210, 297);

        $path = $this->dir.'/'.($withText ? 'bill' : 'scan').'.pdf';
        $pdf->Output('F', $path);

        return $path;
    }

    private function text(string $pdf): string
    {
        return preg_replace('/\s+/', ' ', Process::run(['pdftotext', $pdf, '-'])->output());
    }

    public function test_an_image_heavy_pdf_gets_smaller_and_keeps_its_text_and_pages(): void
    {
        $path = $this->pdf(withText: true);
        $sizeBefore = filesize($path);
        $textBefore = $this->text($path);

        $result = (new PdfCompressionService)->compress($path);

        $this->assertTrue($result['compressed'], $result['reason']);
        clearstatcache();
        $this->assertSame($result['new_size'], filesize($path));
        $this->assertLessThan($sizeBefore * 0.8, filesize($path));
        $this->assertSame($textBefore, $this->text($path));
        $this->assertStringContainsString('Pages: 2', preg_replace('/ +/', ' ', Process::run(['pdfinfo', $path])->output()));
    }

    public function test_a_scan_with_no_text_layer_is_left_untouched(): void
    {
        $path = $this->pdf(withText: false);
        $hashBefore = hash_file('sha256', $path);

        $result = (new PdfCompressionService)->compress($path);

        $this->assertFalse($result['compressed']);
        $this->assertStringContainsString('scan', $result['reason']);
        $this->assertSame($hashBefore, hash_file('sha256', $path));
    }

    public function test_small_files_and_the_off_switch_leave_the_file_alone(): void
    {
        $path = $this->pdf(withText: true);
        $hashBefore = hash_file('sha256', $path);

        config(['invoices.pdf_compression.enabled' => false]);
        $this->assertFalse((new PdfCompressionService)->compress($path)['compressed']);

        config(['invoices.pdf_compression.enabled' => true, 'invoices.pdf_compression.min_size_kb' => 999999]);
        $this->assertFalse((new PdfCompressionService)->compress($path)['compressed']);

        $this->assertSame($hashBefore, hash_file('sha256', $path));
    }
}
