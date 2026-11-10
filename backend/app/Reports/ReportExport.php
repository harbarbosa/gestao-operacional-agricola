<?php
namespace App\Reports;

class ReportExport
{
    public const COLUMNS = [
        'work_log_id' => 'Diário', 'work_date' => 'Data', 'status' => 'Estado', 'client_name' => 'Cliente',
        'site_name' => 'Local / exploração', 'team_name' => 'Equipa', 'responsible_name' => 'Responsável', 'service_name' => 'Serviço',
        'pool_name'=>'Grupo de colheita','pool_quantity'=>'Produção conjunta do grupo (por diário)', 'worker_id' => 'ID trabalhador', 'worker_name' => 'Trabalhador', 'present' => 'Presença', 'time_in' => 'Entrada', 'time_out' => 'Saída',
        'exit_next_day' => 'Saída no dia seguinte', 'gross_duration' => 'Permanência bruta', 'payment_quantity' => 'Produção individual', 'payment_unit_name' => 'Unidade individual',
        'billing_quantity' => 'Produção do cliente por diário', 'billing_unit_name' => 'Unidade cliente',
        'score' => 'Avaliação', 'worker_notes' => 'Observações do trabalhador', 'work_notes' => 'Observações do diário', 'cancel_reason' => 'Motivo do cancelamento',
        'created_at' => 'Criado em (Portugal)', 'updated_at' => 'Atualizado em (Portugal)', 'created_by' => 'ID autor', 'updated_by' => 'ID última alteração', 'version' => 'Versão',
    ];
    private static function date(?string $value): string { return $value ? implode('/', array_reverse(explode('-', substr($value, 0, 10)))) : ''; }
    private static function datetime(?string $value): string { return $value ? (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Lisbon'))->format('d/m/Y H:i') : ''; }
    public static function rows(array $rows): array
    {
        return array_map(static function ($row) {
            $row['work_date'] = self::date($row['work_date']);
            $row['status'] = (['draft'=>'Rascunho','closed'=>'Fechado','canceled'=>'Cancelado'][$row['status']] ?? $row['status']);
            $row['present'] = $row['worker_id'] === null ? '' : ($row['present'] ? 'Presente' : 'Ausente');
            $row['exit_next_day'] = $row['worker_id'] === null ? '' : ($row['exit_next_day'] ? 'Sim' : 'Não');
            $row['gross_duration'] = '';
            if ($row['time_in'] && $row['time_out']) {
                [$inH, $inM] = array_map('intval', explode(':', $row['time_in']));
                [$outH, $outM] = array_map('intval', explode(':', $row['time_out']));
                $minutes = ($outH - $inH) * 60 + $outM - $inM + ($row['exit_next_day'] === 'Sim' ? 1440 : 0);
                if ($minutes >= 0) $row['gross_duration'] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            }
            $row['created_at'] = self::datetime($row['created_at']); $row['updated_at'] = self::datetime($row['updated_at']);
            return array_map(static fn ($key) => $row[$key] ?? '', array_keys(self::COLUMNS));
        }, $rows);
    }
    public static function csv(array $report): string
    {
        $stream = fopen('php://temp', 'w+'); fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_values(self::COLUMNS), ';', '"', '');
        $numeric = ['payment_quantity', 'billing_quantity','pool_quantity'];
        foreach (self::rows($report['items']) as $row) {
            foreach ($row as $index => &$value) {
                $value = (string) $value;
                if (in_array(array_keys(self::COLUMNS)[$index], $numeric, true)) $value = str_replace('.', ',', $value);
                if (preg_match('/^[\s]*[=+\-@]/u', $value)) $value = "'" . $value;
            }
            unset($value); fputcsv($stream, $row, ';', '"', '');
        }
        rewind($stream); $result = stream_get_contents($stream); fclose($stream); return $result;
    }
    private static function sheet(array $rows, array $numericColumns = []): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="32" width="24" customWidth="1"/></cols><sheetData>';
        foreach ($rows as $index => $row) {
            $xml .= '<row r="' . ($index + 1) . '"' . ($index === 0 ? ' ht="42" customHeight="1"' : '') . '>';
            foreach ($row as $column => $value) {
                if ($index > 0 && in_array($column, $numericColumns, true) && preg_match('/^\d+(\.\d+)?$/D', (string) $value)) $xml .= '<c s="1"><v>' . $value . '</v></c>';
                else $xml .= '<c s="' . ($index === 0 ? '2' : '3') . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }
    public static function xlsx(array $report): string
    {
        $summary = [['Relatório operacional agrícola', ''], ['Emitido em Portugal', self::datetime($report['generated_at'])], ['Diários', $report['summary']['work_logs']], ['Trabalhadores', $report['summary']['workers']], ['Presenças', $report['summary']['presences']], ['Ausências', $report['summary']['absences']]];
        foreach ($report['filters'] as $key => $value) if ($value !== '') $summary[] = ['Filtro: ' . $key, $value];
        foreach (['production' => 'Produção individual', 'billing' => 'Produção cliente por diário', 'group_production'=>'Produção em grupo'] as $key => $label) foreach ($report['summary'][$key] as $row) $summary[] = [$label . ' (' . $row['unit'] . ')', (string) $row['quantity']];
        $rows = self::rows($report['items']);
        $keys = array_keys(self::COLUMNS);
        $file = tempnam(sys_get_temp_dir(), 'agri-report-');
        try {
            $zip = new \ZipArchive();
            if ($zip->open($file, \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Falha ao gerar Excel.');
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Trabalhos" sheetId="1" r:id="rId1"/><sheet name="Resumo" sheetId="2" r:id="rId2"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet([array_values(self::COLUMNS), ...$rows], array_values(array_map(static fn ($key) => array_search($key, $keys, true), ['work_log_id', 'worker_id', 'payment_quantity', 'billing_quantity', 'pool_quantity', 'score', 'created_by', 'updated_by', 'version']))));
            $zip->addFromString('xl/worksheets/sheet2.xml', self::sheet($summary, [1]));
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.####"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF123F32"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="4"><xf xfId="0"/><xf numFmtId="164" applyNumberFormat="1" xfId="0"/><xf fontId="1" fillId="2" applyFont="1" applyFill="1" applyAlignment="1" xfId="0"><alignment wrapText="1" vertical="center"/></xf><xf applyAlignment="1" xfId="0"><alignment wrapText="1" vertical="top"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            $zip->close(); return file_get_contents($file);
        } finally { @unlink($file); }
    }
    private static function number($value): string
    {
        if ($value === null || $value === '') return '—';
        [$integer, $fraction] = array_pad(explode('.', (string) $value), 2, '');
        $fraction = rtrim($fraction, '0');
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $integer) . ($fraction !== '' ? ',' . $fraction : '');
    }
    public static function pdf(array $report): string
    {
        $lines = ['AGROPERI - RELATORIO DE TRABALHOS', 'Emitido em Portugal: ' . self::datetime($report['generated_at']), 'Diários: ' . $report['summary']['work_logs'] . ' | Trabalhadores: ' . $report['summary']['workers'] . ' | Presenças: ' . $report['summary']['presences'] . ' | Ausências: ' . $report['summary']['absences']];
        foreach ($report['filters'] as $key => $value) if ($value !== '') $lines[] = $key . ': ' . $value;
        foreach (['production' => 'Produção individual', 'billing' => 'Produção cliente por diário', 'group_production'=>'Produção em grupo'] as $key => $label) foreach ($report['summary'][$key] as $summary) $lines[] = $label . ': ' . self::number($summary['quantity']) . ' ' . $summary['unit'];
        foreach (self::rows($report['items']) as $row) {
            $lines[] = str_repeat('-', 95);
            foreach (array_chunk(array_combine(array_values(self::COLUMNS), $row), 3, true) as $chunk) {
                $parts = []; foreach ($chunk as $label => $value) { if (in_array($label, ['Produção individual', 'Produção do cliente por diário','Produção conjunta do grupo (por diário)'], true)) $value = self::number($value); $parts[] = $label . ': ' . ($value === '' ? '—' : $value); }
                $lines[] = implode(' | ', $parts);
            }
        }
        $wrapped = [];
        foreach ($lines as $line) foreach (explode("\n", wordwrap(iconv('UTF-8', 'Windows-1252//TRANSLIT', $line), 105, "\n", true)) as $part) $wrapped[] = $part;
        $pages = array_chunk($wrapped, 59);
        $objects = [1 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        $escape = static fn ($text) => str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $text);
        foreach ($pages as $i => $page) {
            $pageId = 4 + $i * 2; $contentId = $pageId + 1; $kids[] = $pageId . ' 0 R';
            $stream = "BT /F1 8 Tf 40 800 Td 12 TL\n";
            foreach ($page as $line) $stream .= '(' . $escape($line) . ") Tj T*\n";
            $stream .= "ET\nBT /F1 8 Tf 40 25 Td (" . 'Pagina ' . ($i + 1) . '/' . count($pages) . ") Tj ET";
            $objects[$contentId] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 1 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >>'; $objects[3] = '<< /Type /Catalog /Pages 2 0 R >>'; ksort($objects);
        $pdf = "%PDF-1.4\n"; $offsets = [0];
        foreach ($objects as $id => $object) { $offsets[$id] = strlen($pdf); $pdf .= "$id 0 obj\n$object\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . " /Root 3 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
