<?php

namespace App\Services;

class PrivateMedia
{
    public static function path(string $name): ?string
    {
        if ($name === '' || basename($name) !== $name || str_contains($name, '\\')) return null;
        foreach ([storage_path('app/private/media/users'), storage_path('app/public/users'), storage_path('app/private/public/users'), public_path('storage/users')] as $directory) {
            $root = realpath($directory);
            $path = $root ? realpath($root.DIRECTORY_SEPARATOR.$name) : false;
            if ($path && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path) && !is_link($root.DIRECTORY_SEPARATOR.$name)) return $path;
        }
        return null;
    }

    public static function url(string $name): string
    {
        return '/api/media/users/'.rawurlencode(basename($name));
    }
}
