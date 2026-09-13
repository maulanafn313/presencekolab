<?php

namespace App\Services;

final class SafeResponse
{
    public static function payload(array $data, int $status): array
    {
        if ($status >= 500) {
            return ['ok' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.'];
        }
        foreach (['debug', 'debug_error', 'trace', 'raw_output', 'exit_code'] as $key) {
            unset($data[$key]);
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::payload($value, $status);
            }
        }

        return $data;
    }
}
