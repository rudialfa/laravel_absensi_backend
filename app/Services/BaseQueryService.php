<?php

namespace App\Services;

class BaseQueryService
{
    protected static function flash(string $key, string $message): void
    {
        session()->flash($key, $message);
    }

    public static function flashSuccess(string $message): void
    {
        static::flash('success', $message);
    }

    public static function flashError(string $message): void
    {
        static::flash('error', $message);
    }
}

// ini versi salma
