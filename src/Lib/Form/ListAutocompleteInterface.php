<?php
namespace Sannomiya\Form;

use Sannomiya\Database\Database;

interface ListAutocompleteInterface
{
    /**
     * Get map of id=>name
     * <br>Params is array of id
     * @param Database $db
     * @param array $params
     * @return array
     */
    function getData(Database $db, array $params): array;

    /**
     * Get array of id from master that name matched with $value.
     * @param Database $db
     * @param string $value
     * @param bool $partialMatch
     * @return array
     */
    function search(Database $db, string $value, bool $partialMatch = true): array;

    /**
     * Insert value into master and return inserted id
     * @param Database $db
     * @param string $value
     * @return int|null
     */
    function insert(Database $db, string $value): ?int;

}
