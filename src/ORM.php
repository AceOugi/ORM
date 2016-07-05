<?php

namespace AceOugi;

class ORM
{
    /** @var \PDO */
    protected static $instance = [];
    /** @var array */
    protected static $registry = [];
    /** @var ORMSkeleton[] */
    protected static $cache = [];

    /**
     * @param \PDO $instance
     * @param string $key
     */
    public static function set(\PDO $instance, string $key = '__default')
    {
        self::$instance[$key] = $instance;
    }

    /**
     * @param array $connection
     * @param string $key
     * @throws \UnderflowException
     */
    public static function setConnection(array $connection, string $key = '__default')
    {
        if (!isset($connection['dsn']))
            throw new \UnderflowException('Missing DSN on the connection array');

        self::$registry[$key] = $connection;
    }

    /**
     * @param string $key
     * @return mixed
     * @throws \OutOfBoundsException
     */
    public static function get(string $key = '__default')
    {
        if (isset(self::$instance[$key]))
            return self::$instance[$key];

        if (isset(self::$registry[$key]))
        {
            return self::$instance[$key] = new \PDO(
                self::$registry[$key]['dsn'],
                self::$registry[$key]['username'] ?? '',
                self::$registry[$key]['password'] ?? '',
                self::$registry[$key]['options'] ?? []
            );
        }

        if ($key == '__default' AND isset($GLOBALS['pdo']) AND $GLOBALS['pdo'] instanceof \PDO)
        {
            trigger_error('ORM PDO Instance not found, but found on $GLOBALS', E_USER_DEPRECATED);
            return $GLOBALS['pdo'];
        }

        throw new \OutOfBoundsException('Undefined key "'.$key.'"');
    }

    /**
     * @param string $table
     * @param string $database
     * @param string $connection
     * @return ORMSkeleton
     */
    public static function getSkeleton(string $table, string $database = '', string $connection = '__default')
    {
        $key = md5($connection.$database.$table); // TODO: if database empty, use "SELECT DATABASE();" before MD5, for compatiblity orm(tb, db) with orm(tb)

        if (isset(self::$cache[$key]))
            return self::$cache[$key];

        return self::$cache[$key] = new ORMSkeleton($table, self::get($connection), $database);
    }
}
