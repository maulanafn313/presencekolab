<?php

return [
    'keep' => max(2, (int) env('BACKUP_KEEP', 7)),
    'auto_enabled' => env('BACKUP_AUTO_ENABLED', true),
];
