<?php

namespace App\Services;

use PDO;

/** Menandai laporan harian yang perlu dibaca admin sebelum disetujui. */
final class ReportQuality
{
    private const MIN_LENGTH = 15;

    private const MIN_REPEAT_LENGTH = 20;

    private const MIN_COPY_LENGTH = 30;

    public static function normalize(string $content): string
    {
        $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text)));
    }

    /** @return array<int, list<array{type: string, label: string}>> Hanya laporan yang bermasalah. */
    public static function flagged(PDO $pdo): array
    {
        $reports = $pdo->query("SELECT id, user_id, report_date, content FROM daily_reports WHERE TRIM(COALESCE(content, '')) <> '' ORDER BY report_date, id")->fetchAll(PDO::FETCH_ASSOC);
        $present = [];
        foreach ($pdo->query('SELECT user_id, DATE(jam_masuk_iso) AS day FROM attendance UNION SELECT user_id, `date` FROM attendance_notes')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $present[$row['user_id'].'|'.$row['day']] = true;
        }

        return self::flags($reports, $present);
    }

    /**
     * @param  list<array<string, mixed>>  $reports  Urut dari tanggal terlama.
     * @param  array<string, bool>  $present  Kunci "user_id|tanggal".
     */
    public static function flags(array $reports, array $present): array
    {
        $firstSeen = $authors = [];
        foreach ($reports as &$report) {
            $report['text'] = self::normalize((string) $report['content']);
            $authors[$report['text']][$report['user_id']] = true;
        }
        unset($report);

        $flags = [];
        foreach ($reports as $report) {
            $id = (int) $report['id'];
            $text = $report['text'];
            $length = mb_strlen($text);
            $own = $report['user_id'].'|'.$text;

            if ($length < self::MIN_LENGTH) {
                $flags[$id][] = ['type' => 'short', 'label' => 'Isi terlalu singkat'];
            }
            if ($length >= self::MIN_REPEAT_LENGTH && isset($firstSeen[$own])) {
                $flags[$id][] = ['type' => 'repeat', 'label' => 'Sama dengan laporan '.$firstSeen[$own]];
            }
            // Nama mahasiswa lain sengaja tidak ditampilkan di label.
            if ($length >= self::MIN_COPY_LENGTH && count($authors[$text]) > 1) {
                $flags[$id][] = ['type' => 'copied', 'label' => 'Sama dengan laporan mahasiswa lain'];
            }
            if (! isset($present[$report['user_id'].'|'.$report['report_date']])) {
                $flags[$id][] = ['type' => 'no_attendance', 'label' => 'Tidak ada presensi di tanggal ini'];
            }
            $firstSeen[$own] ??= $report['report_date'];
        }

        return $flags;
    }
}
