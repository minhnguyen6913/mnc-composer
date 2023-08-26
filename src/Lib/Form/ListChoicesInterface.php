<?php
namespace Sannomiya\Form;

use Sannomiya\Database\Database;

interface ListChoicesInterface
{
    /**
     * Get query to get list choices data.
     * <br>Query is like that select field1_id from map_table where field2_id=$rec['id']
     * @param array $rec
     * @return string
     */
    function getDataQuery(array $rec): ?string;

    /**
     * Get query to delete list choices data before insert.
     * <br>Query is like that delete from map_table where field2_id=$rec['id']
     * @param array $rec
     * @return string
     */
    function getDeleteQuery(array $rec): ?string;

    /**
     * Get query to insert list choices data.
     * <br>If values contain a string, maybe it is auto complete. Register value to database and get returned id, assign back to $values
     * <br>Queries is a list that item like that insert into map_table ($field1, $field2) values ($rec['field1'], $value)
     * @param Database $db
     * @param array $rec
     * @param array $values
     * @return array
     */
    function getInsertQueries(Database $db, array $rec, array &$values): array;

    /**
     * Get query condition to search list choices.
     * <br>$values is string or null
     * <br>Query condition is like that field1 in (select field1_id from table_map where field2_id not in ($values))
     * @param bool $notIn
     * @param ?string $values
     * @return string
     */
    function getSearchQueryCondition(bool $notIn, ?string $values): ?string;


}
