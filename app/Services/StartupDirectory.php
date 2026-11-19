<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\Http;
use PDO;
use Throwable;

/**
 * Daftar resmi startup. `users.startup` tetap menyimpan nama sebagai teks supaya
 * export, KPI, dan filter yang sudah ada tidak perlu berubah.
 */
final class StartupDirectory
{
    private const MAX_NAME = 100;

    private const MAX_CLEANUP_ROWS = 200;

    /** Nama lama yang sudah disepakati masuk ke startup lain. */
    private const ALIASES = ['ngolabexpress' => 'ngolab'];

    public static function key(string $name): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtolower($name));
    }

    /** Kunci startup resmi untuk sebuah nama, termasuk nama lama yang sudah digabung. */
    public static function canonicalKey(string $name): string
    {
        $key = self::key($name);

        return self::ALIASES[$key] ?? $key;
    }

    public static function cleanName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($name)));
    }

    public static function overview(PDO $pdo): array
    {
        $startups = $pdo->query('SELECT id, name, name_key, portal_id, is_active FROM startups ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $members = $pdo->query("SELECT startup FROM users WHERE role = 'pegawai' AND startup IS NOT NULL AND TRIM(startup) <> ''")->fetchAll(PDO::FETCH_COLUMN);

        return self::summarize($startups, $members);
    }

    /** Pencocokan harus persis, sehingga "ngolab" tetap perlu dirapikan menjadi "Ngolab". */
    public static function summarize(array $startups, array $memberStartups): array
    {
        $counts = [];
        foreach ($memberStartups as $name) {
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        $listed = $activeByKey = [];
        foreach ($startups as $startup) {
            $listed[$startup['name']] = true;
            if ($startup['is_active']) {
                $activeByKey[self::key($startup['name'])] = (int) $startup['id'];
            }
        }

        $unregistered = [];
        foreach ($counts as $name => $members) {
            $name = (string) $name;
            if (! isset($listed[$name])) {
                $key = self::key($name);
                $unregistered[] = ['name' => $name, 'members' => $members, 'suggested_id' => $activeByKey[self::ALIASES[$key] ?? $key] ?? null];
            }
        }
        usort($unregistered, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return [
            'startups' => array_map(fn ($s) => [
                'id' => (int) $s['id'],
                'name' => $s['name'],
                'is_active' => (bool) $s['is_active'],
                'from_portal' => $s['portal_id'] !== null,
                'members' => $counts[$s['name']] ?? 0,
            ], $startups),
            'unregistered' => $unregistered,
        ];
    }

    public static function activeNames(PDO $pdo): array
    {
        return $pdo->query('SELECT name FROM startups WHERE is_active = 1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Nilai lama mahasiswa tetap boleh dipertahankan saat data lainnya diedit. */
    public static function isAllowed(PDO $pdo, string $value, ?string $current): bool
    {
        return $value === '' || $value === $current || in_array($value, self::activeNames($pdo), true);
    }

    public static function create(PDO $pdo, string $rawName): void
    {
        $name = self::validName($rawName);
        if (self::findByKey($pdo, self::key($name))) {
            throw new DomainException('Startup dengan nama ini sudah ada.');
        }
        self::insert($pdo, $name, null);
    }

    public static function rename(PDO $pdo, int $id, string $rawName): void
    {
        $startup = self::find($pdo, $id);
        if ($startup['portal_id'] !== null) {
            throw new DomainException('Nama startup dari portal hanya bisa diubah di portal.');
        }
        $name = self::validName($rawName);
        $other = self::findByKey($pdo, self::key($name));
        if ($other && (int) $other['id'] !== $id) {
            throw new DomainException('Startup dengan nama ini sudah ada. Gunakan Gabungkan.');
        }
        self::transaction($pdo, fn () => self::applyName($pdo, $startup, $name));
    }

    public static function setActive(PDO $pdo, int $id, bool $active): void
    {
        self::find($pdo, $id);
        $pdo->prepare('UPDATE startups SET is_active = ?, updated_at = NOW() WHERE id = ?')->execute([(int) $active, $id]);
    }

    /** @return array{moved: int, summary: string, undo: array} */
    public static function merge(PDO $pdo, int $sourceId, int $targetId): array
    {
        if ($sourceId === $targetId) {
            throw new DomainException('Pilih startup tujuan yang berbeda.');
        }
        $source = self::find($pdo, $sourceId);
        $target = self::find($pdo, $targetId);
        if ($source['portal_id'] !== null) {
            throw new DomainException('Startup dari portal tidak bisa digabungkan ke startup lain.');
        }

        return self::transaction($pdo, function () use ($pdo, $source, $target) {
            $changes = self::moveTracked($pdo, $source['name'], $target['name']);
            $pdo->prepare('DELETE FROM startups WHERE id = ?')->execute([$source['id']]);

            return [
                'moved' => count($changes),
                'summary' => "Menggabungkan startup {$source['name']} ke {$target['name']}",
                'undo' => ['type' => 'startup', 'changes' => $changes, 'restore_startup' => ['name' => $source['name'], 'is_active' => (int) $source['is_active']]],
            ];
        });
    }

    /**
     * @param  array<int, array{name: string, startup_id: int}>  $mapping
     */
    public static function applyCleanup(PDO $pdo, array $mapping): array
    {
        if ($mapping === [] || count($mapping) > self::MAX_CLEANUP_ROWS) {
            throw new DomainException('Pilih minimal satu nama untuk disesuaikan.');
        }

        return self::transaction($pdo, function () use ($pdo, $mapping) {
            $changes = [];
            foreach ($mapping as $row) {
                $target = self::find($pdo, (int) ($row['startup_id'] ?? 0));
                if (! $target['is_active']) {
                    throw new DomainException("Startup {$target['name']} sedang nonaktif.");
                }
                array_push($changes, ...self::moveTracked($pdo, (string) ($row['name'] ?? ''), $target['name']));
            }

            return [
                'moved' => count($changes),
                'summary' => 'Merapikan '.count($mapping).' nama startup lama ('.count($changes).' mahasiswa)',
                'undo' => $changes === [] ? null : ['type' => 'startup', 'changes' => $changes],
            ];
        });
    }

    /** @return array<int, array{id: string, name: string}> */
    public static function parsePortal(mixed $payload): array
    {
        if (! is_array($payload) || ! array_is_list($payload) || $payload === []) {
            throw new DomainException('Data startup dari portal tidak valid.');
        }

        $items = [];
        foreach ($payload as $item) {
            $id = is_array($item) && is_string($item['id'] ?? null) ? trim($item['id']) : '';
            $name = is_array($item) && is_string($item['name'] ?? null) ? self::cleanName($item['name']) : '';
            if ($id === '' || strlen($id) > 50 || self::key($name) === '' || mb_strlen($name) > self::MAX_NAME) {
                throw new DomainException('Data startup dari portal tidak valid.');
            }
            $items[] = ['id' => $id, 'name' => $name];
        }

        return $items;
    }

    public static function fetchPortal(): array
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get(rtrim((string) config('services.portal.url'), '/').'/api/data/startups');
        } catch (Throwable $e) {
            throw new DomainException('Portal tidak dapat dihubungi. Coba lagi nanti.');
        }
        if (! $response->successful()) {
            throw new DomainException('Portal tidak dapat dihubungi. Coba lagi nanti.');
        }

        return self::parsePortal($response->json());
    }

    /**
     * Startup yang hanya ada di aplikasi ini tidak disentuh.
     *
     * @param  array<int, array{id: string, name: string}>  $items
     * @return array{added: int, updated: int, conflicts: int}
     */
    public static function syncPortal(PDO $pdo, array $items): array
    {
        return self::transaction($pdo, function () use ($pdo, $items) {
            $result = ['added' => 0, 'updated' => 0, 'conflicts' => 0];
            foreach ($items as ['id' => $portalId, 'name' => $name]) {
                $byPortal = self::one($pdo, 'SELECT * FROM startups WHERE portal_id = ?', [$portalId]);
                $byKey = self::findByKey($pdo, self::key($name));
                $row = $byPortal ?: $byKey;

                if (! $row) {
                    self::insert($pdo, $name, $portalId);
                    $result['added']++;
                } elseif (($byPortal && $byKey && $byPortal['id'] !== $byKey['id']) || ($row['portal_id'] !== null && $row['portal_id'] !== $portalId)) {
                    $result['conflicts']++;
                } elseif ($row['name'] !== $name || $row['portal_id'] !== $portalId) {
                    self::applyName($pdo, $row, $name);
                    $pdo->prepare('UPDATE startups SET portal_id = ? WHERE id = ?')->execute([$portalId, $row['id']]);
                    $result['updated']++;
                }
            }

            return $result;
        });
    }

    private static function validName(string $rawName): string
    {
        $name = self::cleanName($rawName);
        if (self::key($name) === '' || mb_strlen($name) > self::MAX_NAME) {
            throw new DomainException('Nama startup wajib diisi, maksimal 100 karakter.');
        }

        return $name;
    }

    private static function applyName(PDO $pdo, array $startup, string $name): void
    {
        self::moveMembers($pdo, $startup['name'], $name);
        $pdo->prepare('UPDATE startups SET name = ?, name_key = ?, updated_at = NOW() WHERE id = ?')->execute([$name, self::key($name), $startup['id']]);
    }

    /**
     * Sama seperti moveMembers(), tetapi mencatat siapa saja yang dipindah agar bisa dibatalkan.
     *
     * @return list<array{0: int, 1: string, 2: string}>
     */
    private static function moveTracked(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'pegawai' AND CAST(startup AS BINARY) = CAST(? AS BINARY) FOR UPDATE");
        $stmt->execute([$from]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        self::moveMembers($pdo, $from, $to);

        return array_map(fn ($id) => [(int) $id, $from, $to], $ids);
    }

    private static function moveMembers(PDO $pdo, string $from, string $to): int
    {
        $stmt = $pdo->prepare("UPDATE users SET startup = ? WHERE role = 'pegawai' AND CAST(startup AS BINARY) = CAST(? AS BINARY)");
        $stmt->execute([$to, $from]);

        return $stmt->rowCount();
    }

    private static function insert(PDO $pdo, string $name, ?string $portalId): void
    {
        $pdo->prepare('INSERT INTO startups (name, name_key, portal_id, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())')
            ->execute([$name, self::key($name), $portalId]);
    }

    private static function find(PDO $pdo, int $id): array
    {
        return self::one($pdo, 'SELECT * FROM startups WHERE id = ?', [$id]) ?: throw new DomainException('Startup tidak ditemukan.');
    }

    private static function findByKey(PDO $pdo, string $key): ?array
    {
        return self::one($pdo, 'SELECT * FROM startups WHERE name_key = ?', [$key]);
    }

    private static function one(PDO $pdo, string $sql, array $params): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function transaction(PDO $pdo, callable $work): mixed
    {
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }
}
