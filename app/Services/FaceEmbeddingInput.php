<?php

namespace App\Services;

use InvalidArgumentException;

final class FaceEmbeddingInput
{
    public static function validate(mixed $embedding, mixed $landmarks): void
    {
        if (! is_string($embedding) || strlen($embedding) > 16384) {
            throw new InvalidArgumentException('Embedding wajah tidak valid.');
        }
        $values = json_decode($embedding, true);
        if (! is_array($values) || ! array_is_list($values) || count($values) !== 128) {
            throw new InvalidArgumentException('Embedding wajib berisi 128 angka.');
        }
        foreach ($values as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('Embedding wajib berisi angka yang valid.');
            }
        }
        if ($landmarks === null || $landmarks === '') {
            return;
        }
        if (! is_string($landmarks) || strlen($landmarks) > 32768) {
            throw new InvalidArgumentException('Landmark wajah tidak valid.');
        }
        $points = json_decode($landmarks, true);
        if (! is_array($points) || ! array_is_list($points) || count($points) !== 68) {
            throw new InvalidArgumentException('Landmark wajib berisi 68 titik.');
        }
        foreach ($points as $point) {
            foreach (['x', 'y'] as $axis) {
                $value = is_array($point) ? ($point[$axis] ?? $point['_'.$axis] ?? null) : null;
                if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                    throw new InvalidArgumentException('Koordinat landmark tidak valid.');
                }
            }
        }
    }
}
