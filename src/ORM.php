<?php

namespace AceOugi;

class ORM
{
    /** @var \PDO */
    protected static $default_connection;
    /** @var string */
    protected static $default_database;
    /** @var string */
    protected static $default_table;

    /**
     * @return \PDO
     */
    public static function getDefaultConnection()
    {
        return self::$default_connection;
    }

    /**
     * @param \PDO $default_connection
     */
    public static function setDefaultConnection(\PDO $default_connection)
    {
        self::$default_connection = $default_connection;
    }

    /**
     * @return string
     */
    public static function getDefaultDatabase()
    {
        return self::$default_database;
    }

    /**
     * @param string $default_database
     */
    public static function setDefaultDatabase(string $default_database)
    {
        self::$default_database = $default_database;
    }

    /**
     * @return string
     */
    public static function getDefaultTable()
    {
        return self::$default_table;
    }

    /**
     * @param string $default_table
     */
    public static function setDefaultTable(string $default_table)
    {
        self::$default_table = $default_table;
    }

    /**
     * @param string $table
     * @param string $database
     * @param \PDO $connection
     * @return ORMSkeleton
     */
    public static function reach(string $table = null, string $database = null, \PDO $connection = null)
    {
        return new ORMSkeleton(
            $connection ?? static::$default_connection,
            $database ?? static::$default_database,
            $table ?? static::$default_table
        );
    }
}
