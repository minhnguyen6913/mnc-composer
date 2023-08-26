<?php


namespace Sannomiya\Util;


class LanguagesDefault implements LanguagesInterface
{
    public function label(string $text, $module = null): ?string
    {
        return $text;
    }

    public function message(string $text, $module = null): ?string
    {
        return $text;
    }
}
