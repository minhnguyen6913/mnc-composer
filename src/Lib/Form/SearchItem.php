<?php

namespace Minhnhc\Form;

class SearchItem
{
    public ?string $value1;
    public ?string $value2;
    public ?array $values;
    public ?bool $searchNull = false;
    public ?int $type = Constant::SearchTypeEqual;
    public ?string $name = null;
}
