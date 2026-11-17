<?php

namespace App\Support;

use Closure;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * .xlsx fayl yaratish (OpenSpout — qatorlar diskka oqim bilan yoziladi, xotira tejaladi).
 *
 * Matn har doim matn katagi sifatida yoziladi: Zoom'dagi "=..." bilan boshlanadigan
 * ism Excel formulasiga aylanib qolmasligi uchun (formula injection).
 */
final class Xlsx
{
    private const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private Writer $writer;

    private bool $started = false;

    private Style $bold;

    private Style $title;

    private Style $header;

    private Style $date;

    private Style $muted;

    private function __construct(string $path)
    {
        $this->writer = new Writer(new Options(new Style(fontSize: 11, fontName: 'Calibri')));
        $this->writer->openToFile($path);
        $this->bold = new Style(fontBold: true, fontSize: 11, fontName: 'Calibri');
        $this->title = new Style(fontBold: true, fontSize: 14, fontName: 'Calibri');
        $this->header = new Style(fontBold: true, fontSize: 11, fontName: 'Calibri', fontColor: 'FFFFFF', backgroundColor: '1F4E79', shouldWrapText: true);
        $this->date = new Style(fontSize: 11, fontName: 'Calibri', format: 'dd.mm.yyyy hh:mm');
        $this->muted = new Style(fontSize: 10, fontName: 'Calibri', fontColor: '666666');
    }

    /** @param Closure(self): void $build */
    public static function download(string $filename, Closure $build): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'mt-xlsx-');
        $xlsx = new self($path);
        try {
            $build($xlsx);
        } finally {
            $xlsx->writer->close();
        }

        return response()->download($path, $filename, ['Content-Type' => self::MIME])->deleteFileAfterSend();
    }

    /**
     * Yangi varaq (birinchi chaqiruv — mavjud birinchi varaqni nomlaydi).
     *
     * @param  list<float>  $widths  ustunlar kengligi (belgilarda)
     */
    public function sheet(string $name, array $widths = [], ?int $freezeRow = null): self
    {
        $sheet = $this->started ? $this->writer->addNewSheetAndMakeItCurrent() : $this->writer->getCurrentSheet();
        $this->started = true;
        // Excel varaq nomi: 31 belgigacha, []:*?/\ belgilarsiz.
        $sheet->setName(mb_substr(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $name) ?: 'Sheet', 0, 31));
        foreach ($widths as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }
        if ($freezeRow) {
            $sheet->setSheetView(new SheetView(freezeRow: $freezeRow + 1));
        }

        return $this;
    }

    public function title(string $text): self
    {
        $this->writer->addRow(new Row([new StringCell($text, $this->title)], 22));

        return $this;
    }

    /** "Nomi: qiymat" qatori (hisobot sarlavhasidagi ma'lumotlar). */
    public function meta(string $label, mixed $value): self
    {
        $this->writer->addRow(new Row([new StringCell($label, $this->bold), $this->cell($value)]));

        return $this;
    }

    public function note(string $text): self
    {
        $this->writer->addRow(new Row([new StringCell($text, $this->muted)]));

        return $this;
    }

    public function blank(): self
    {
        $this->writer->addRow(new Row([]));

        return $this;
    }

    /** @param list<string> $cells */
    public function header(array $cells): self
    {
        $this->writer->addRow(new Row(array_map(fn (string $text) => new StringCell($text, $this->header), $cells), 30));

        return $this;
    }

    /** @param list<mixed> $values */
    public function row(array $values, bool $bold = false): self
    {
        $this->writer->addRow(new Row(array_map(fn ($value) => $this->cell($value, $bold ? $this->bold : null), $values)));

        return $this;
    }

    private function cell(mixed $value, ?Style $style = null): Cell
    {
        return match (true) {
            $value === null || $value === '' => new EmptyCell(null, $style),
            is_int($value), is_float($value) => new NumericCell($value, $style),
            $value instanceof DateTimeInterface => new DateTimeCell($value, $this->date),
            is_bool($value) => new StringCell($value ? '✓' : '—', $style),
            default => new StringCell((string) $value, $style),
        };
    }
}
