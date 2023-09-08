<?php


namespace Minhnhc\Form;


abstract class Constant
{
    const SearchTypeEqual = 0;
    const SearchTypeNotEqual = 1;
    const SearchTypeIn = 2;
    const SearchTypeNotIn = 3;
    const SearchTypeContains = 4;
    const SearchTypeStartWith = 5;
    const SearchTypeEndWith = 6;
    const SearchTypeNotContains = 7;
    const SearchTypeIsNull = 8;
    const SearchTypeIsNotNull = 9;

    const SortTypeAsc = 0;
    const SortTypeDesc = 1;


    const QueryParamParam = "'{param}'";

    const QueryParamParam1 = "'{param1}'";
    const QueryParamParam2 = "'{param2}'";
    const QueryParamParam3 = "'{param3}'";
    const QueryParamParam4 = "'{param4}'";
    const QueryParamParam5 = "'{param5}'";

    const QueryParamValue = "'{value}'";
    const QueryParamIn = "'{in}'";
    const QueryParamFilter = "'{filter}'";
    const QueryParamCondition = "'{condition}'";

    public static function createQueryParamParam($i = null): string
    {
        if (is_numeric($i)){
            return  "'{param$i}'";
        }else{
            return self::QueryParamParam;
        }
    }

    public static function createFieldQueryParam($fieldName): string
    {
        return "[$fieldName]";
    }
}
