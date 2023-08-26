<?php
namespace Minhnhc\Util;

interface LanguagesInterface
{
    public function label(string $text, $module=null): ?string;
    public function message(string $text, $module=null): ?string;
}
