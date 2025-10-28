<?php

declare(strict_types=1);

namespace App\Support;

final class Tuti
{
    public static function projectRoot(): ?string
    {
        $path = getcwd().'/.tuti';

        return file_exists($path) ? dirname($path) : null;
    }

    public static function isInsideProject(): bool
    {
        return (bool) self::projectRoot();
    }
}
