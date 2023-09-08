<?php

namespace Minhnhc\Form;

abstract class ListFunctionAbstract
{
    public ?array $params = null;
    public ?string $filter;
    public ?int $max = 0;
    public ?array $fixParams = null;
    public ?array $ids = null;

    public abstract function query(): string;
}
