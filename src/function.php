<?php

if (!function_exists('orm'))
{
    /**
     * @param string $table
     * @param string $database
     * @param \PDO $connection
     * @return \AceOugi\ORMSkeleton
     */
    function orm(string $table = null, string $database = null, \PDO $connection = null)
    {
        return \AceOugi\ORM::reach($table, $database, $connection);
    }
}
