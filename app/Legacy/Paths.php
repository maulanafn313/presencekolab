<?php

namespace App\Legacy;

final class Paths
{
    /**
     * Keep installations with existing backups readable during migration.
     * New installations write runtime data under storage, never beside code.
     */
    public static function backups(): string
    {
        $existing = resource_path('views/pages/database_backup');

        return is_dir($existing)
            ? $existing
            : storage_path('app/private/database-backups');
    }
}
