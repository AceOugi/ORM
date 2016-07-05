<?php

if (!function_exists('orm'))
{
    /**
     * @param string $table
     * @param string $database
     * @param string $connection
     * @return \AceOugi\ORMSkeleton
     */
    function orm(string $table, string $database = '', string $connection = '__default')
    {
        return \AceOugi\ORM::getSkeleton($table, $database, $connection);
    }
}
