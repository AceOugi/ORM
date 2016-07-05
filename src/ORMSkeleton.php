<?php

namespace AceOugi;

class ORMSkeleton
{
    /** @var \PDO $pdo */
    protected $pdo;
    /** @var string $name */
    protected $name;
    /** @var string $name_table */
    protected $name_table;
    /** @var string $name_database */
    protected $name_database;

    protected $aliases = [];
    protected $columns = [];
    protected $indexes = [];

    protected $links = [];

    protected $error = null;

    protected $lastInsertId = null;


    /**
     * ORMSkeleton constructor.
     * @param string $table
     * @param \PDO $pdo
     * @param string $database
     */
    public function __construct(string $table, \PDO $pdo, string $database = '')
    {
        $this->pdo = $pdo;
        $this->name = strlen($database) ? "`{$database}`.`$table`" : "`$table`";
        $this->name_table = $table;
        $this->name_database = $database;

        $this->xxx();
        $this->yyy();
    }

    public function xxx()
    {
        if ($req = $this->pdo->query("SHOW COLUMNS FROM $this->name"))
            foreach($req->fetchAll() as $column)
                $this->columns[ $column['Field'] ] = $column['Default'];
        else
            $this->debug($this->pdo->errorInfo());
    }

    public function yyy()
    {
        if ($req = $this->pdo->query("SHOW INDEXES FROM $this->name"))
            foreach($req->fetchAll() as $index)
                if (!isset($this->indexes[$index['Key_name']]))
                    $this->indexes[$index['Key_name']] = ['columns' => [$index['Column_name'] => []], 'multi' => ($index['Non_unique'] ? true : false)];
                else
                    $this->indexes[$index['Key_name']]['columns'][$index['Column_name']] = [];
        else
            $this->debug($this->pdo->errorInfo());
    }

    public function setAlias(string $name, string $column)
    {
        $this->aliases[$name] = $column; // TODO CHECK !!!
    }

    public function setIndex(string $name, array $data)
    {
        $this->indexes[$name] = $data; // TODO CHECK !!!
    }

    public function setIndexEase(string $name, bool $multi, string $column, string ...$columns)
    {
        $this->indexes[$name] = ['multi' => $multi, 'columns' => [[$column => []]]];

        foreach ($columns as $column)
            $this->indexes[$name]['columns'][$column] = [];
    }

    public function setLink($name)
    {
    }

    protected function debug(array $error = null)
    {
        if ($error) $this->error = $error;

        foreach (debug_backtrace() as $trace)
            trigger_error(
                'PDO Query error occurred via '.__METHOD__.' (columns)'.
                ' in ' . $trace['file'].
                ' on line ' . $trace['line'],
                E_USER_WARNING);

        trigger_error(
            'PDO Query error#'.$this->error[0].': '.$this->error[2].
            ' ( ' . $this->error[1] . ' ) ',
            E_USER_ERROR);
    }

    public function error() { return $this->error; }
    public function lastInsertId() { return $this->lastInsertId; }


    /**
     * @param string $name
     * @return string|null
     */
    protected function column(string $name)
    {
        if (isset($this->aliases[$name]))
            $name = $this->aliases[$name];

        return (isset($this->columns[$name]) OR array_key_exists($name, $this->columns)) ? $name : null;
    }

    /**
     * @param array $list
     * @return array
     */
    protected function columns(array $list)
    {
        $columns = [];
        foreach ($list as $name => $value)
            if ($column = $this->column($name))
                $columns[$column] = $value;

        return $columns;
    }

    /**
     * @param array $list
     * @return array
     */
    protected function where(array $list, &$data_bind, &$data_data)
    {
        $where = [];
        foreach ($list as $key => $value)
        {
            if ($column = $this->column($key))
            {
                if ($value === null)
                    $where[] = "`{$column}` IS NULL";
                else
                {
                    $where[] = "`{$column}` = ?";
                    $data_data[] = $value;
                }
            }
        }

        return count($where) ? ' WHERE '.implode(' AND ', $where) : '';
    }















    public function insert(array $data)
    {
        // Resolve column name
        $data = $this->columns($data);

        $data_name = [];
        $data_link = [];
        $data_bind = [];
        $data_data = [];

        foreach ($data as $key => $value)
        {
            $data_name[] = $key;
            $data_link[] = '?';
            $data_data[] = $value;
        }

        $req = $this->pdo->prepare('INSERT INTO '.$this->name.' ('.implode(',', $data_name).') VALUES ('.implode(', ', $data_link).')');

        $result = $req->execute($data_data);

        if (!$result)
            $this->debug($req->errorInfo());
        $this->lastInsertId = $this->pdo->lastInsertId();

        return $result;
    }

    public function inserts(array ...$list)
    {
        $inc = 0;

        foreach ($list as &$data)
            $inc+= $this->insert($data);

        return $inc;
    }

    public function select(array $where = [], ...$extras)
    {
        $extras[] = 'LIMIT 1';
        $list = $this->selects($where, ...$extras);
        return array_shift($list);
    }

    public function selects(array $where = [], ...$extras)
    {
        $data_bind = [];
        $data_data = [];

        $req = $this->pdo->prepare($sql = 'SELECT * FROM '.$this->name.' '.$this->where($where, $data_bind, $data_data).' '.implode(' ', $extras));

        $result = $req->execute($data_data);
        $this->error = $req->errorInfo();

        $list = [];
        while ($data = $req->fetch())
            $list[] = new ORMEntity($this, $data);

        return $list;
    }

    public function count($where = array(), ...$extras)
    {
        $data_bind = [];
        $data_data = [];

        $req = $this->pdo->prepare($sql = 'SELECT COUNT(*) FROM '.$this->name.' '.$this->where($where, $data_bind, $data_data).' '.implode(' ', $extras));

        $result = $req->execute($data_data);
        $this->error = $req->errorInfo();

        return ($result) ? $req->fetchColumn() : 0;
    }

    public function delete(array $where = [], ...$extras)
    {
        $extras[] = 'LIMIT 1';
        return $this->deletes($where, ...$extras);
    }

    public function deletes(array $where = [], ...$extras)
    {
        $data_bind = [];
        $data_data = [];

        $req = $this->pdo->prepare($sql = 'DELETE FROM '.$this->name.' '.$this->where($where, $data_bind, $data_data).' '.implode(' ', $extras));

        $result = $req->execute($data_data);
        $this->error = $req->errorInfo();

        return $result;
    }

    public function update(array $data, array $where = [], ...$extras)
    {
        $extras[] = 'LIMIT 1';
        return $this->updates($data, $where, ...$extras);
    }

    public function updates(array $data, array $where = [], ...$extras)
    {
        // Resolve column name
        $data = $this->columns($data);

        $data_link = [];
        $data_bind = [];
        $data_data = [];

        foreach ($data as $key => $value)
        {
            $data_link[] = "`$key` = ?";
            $data_data[] = $value;
        }

        $req = $this->pdo->prepare($sql = 'UPDATE '.$this->name.' SET '.implode(', ', $data_link).' '.$this->where($where, $data_bind, $data_data).' '.implode(' ', $extras));

        $result = $req->execute($data_data);
        $this->error = $req->errorInfo();

        return $result;
    }

    public function replace(array $data)
    {
        // Resolve column name
        $data = $this->columns($data);

        $data_name = [];
        $data_link = [];
        $data_bind = [];
        $data_data = [];

        foreach ($data as $key => $value)
        {
            $data_name[] = $key;
            $data_link[] = '?';
            $data_data[] = $value;
        }

        $req = $this->pdo->prepare('REPLACE INTO '.$this->name.' ('.implode(',', $data_name).') VALUES ('.implode(', ', $data_link).')');

        $result = $req->execute($data_data);

        if (!$result)
            $this->debug($req->errorInfo());
        $this->lastInsertId = null;
        $this->lastInsertId = $this->pdo->lastInsertId();

        return $result;
    }


    public function replaceSoft(array $data)
    {
        // Resolve column name
        $data = $this->columns($data);

        $data_name = [];
        $data_link = [];
        $data_bind = [];
        $data_data = [];

        foreach ($data as $key => $value)
        {
            $data_name[] = $key;
            $data_link[] = '?';
            $data_data[] = $value;
        }

        $data_link2= [];
        foreach ($data as $key => $value)
        {
            $data_link2[] = "`$key` = ?";
            $data_data[] = $value;
        }

        $req = $this->pdo->prepare('INSERT INTO '.$this->name.' ('.implode(',', $data_name).') VALUES ('.implode(', ', $data_link).') ON DUPLICATE KEY UPDATE '.implode(', ', $data_link2));

        $result = $req->execute($data_data);

        if (!$result)
            $this->debug($req->errorInfo());
        $this->lastInsertId = null;
        $this->lastInsertId = $this->pdo->lastInsertId();

        return $result;
    }




    /**
     * @param $method
     * @param $arguments
     * @return mixed|null
     */
    public function __call($method, $arguments)
    {
        if (preg_match('/^(select|selects|update|updates|delete|delete|count)By([a-zA-Z_]+)$/', $method, $matches))
        {
            $m = $matches[1]; // method name (select, selects, update, ...)
            $i = $matches[2]; // index name (PRIMARY, ...)

            if (isset($this->indexes[$i]))
            {
                $list = []; // TODO CHECK NUMBER ARGUMENTS IF VALID

                // If update, add data in list
                if ($m == 'update' OR $m == 'updates')
                    $list[] = array_shift($arguments);

                // Where
                $where = [];
                foreach ($this->indexes[$i]['columns'] as $column_name => $column_data)
                    $where[$column_name] = array_shift($arguments);
                $list[] = $where;

                // Extras
                foreach ($arguments as $argument)
                    $list[] = array_shift($arguments);

                return call_user_func_array(array($this, $m), $list);
            }
        }

        echo 'FAIL';
        return null;
    }














    /** @var \PDO $pdo */
    //protected $pdo;
    /** @var string $database */
    protected $database;
    /** @var string $table */
    protected $table;
    /** @var string $table_full_name Table full name: `$database`.`$table` */
    protected $table_full_name;

    /** @var array $columns */
    //protected $columns = array();
    /** @var array $indexu */
    protected $indexu = array();
    /** @var array $indexm */
    protected $indexm = array();

    /** @var array $aliases */
   // protected $aliases = array();
    /** @var array $links */
    //protected $links = array();
    /** @var array $links_m */
    protected $links_m = array();

    /**
     * @param string $name
     * @param array ...$list
     */
    public function setLinkOLD($name, ...$list)
    {
        if (count($list))
            $this->links[$name] = $list;
    }

    public function setLinkMulti($name, ...$list)
    {
        if (count($list))
            $this->links_m[$name] = $list;
    }

    /**
     * If exist return the link, else null
     * @param $name
     * @return array|null
     */
    public function getLink($name)
    {
        if (isset($this->links[$name]) || array_key_exists($name, $this->links))
            return $this->links[$name];
        else
            return null;
    }
    public function getLinkMulti($name)
    {
        if (isset($this->links_m[$name]) || array_key_exists($name, $this->links_m))
            return $this->links_m[$name];
        else
            return null;
    }

    /**
     * @param \PDO $pdo
     * @param string $database
     * @param string $table
     */
    public function __constructOLD($pdo, $database, $table)
    {
        if (!($pdo instanceof \PDO))
        {
            $trace = end(debug_backtrace());
            trigger_error(
                'Invalid property type via '.__METHOD__.' ($pdo)'.
                ' in ' . $trace['file'].
                ' on line ' . $trace['line'],
                E_USER_ERROR);
        }

        $this->pdo = $pdo;
        $this->database = $database;
        $this->table = $table;
        $this->table_full_name = "`$this->database`.`$this->table`";

        // Columns
        if ($req = $this->pdo->query("SHOW COLUMNS FROM $this->table_full_name"))
            foreach($req->fetchAll() as $data)
                $this->columns[ $data['Field'] ] = $data['Default'];
        else
        {
            foreach (debug_backtrace() as $trace)
                trigger_error(
                    'PDO Query error occurred via '.__METHOD__.' (columns)'.
                    ' in ' . $trace['file'].
                    ' on line ' . $trace['line'],
                    E_USER_WARNING);
            trigger_error(
                'PDO Query error#'.$pdo->errorInfo()[0].': '.$pdo->errorInfo()[2].
                ' ( ' . $pdo->errorInfo()[1] . ' ) ',
                E_USER_WARNING);
        }


        // Indexs
        if ($req = $this->pdo->query("SHOW INDEXES FROM $this->table_full_name"))
            foreach($req->fetchAll() as $data)
            {
                if (!$data['Non_unique'])
                    $this->indexu[$data['Key_name']][] = $data['Column_name'];
                else
                    $this->indexm[$data['Key_name']][] = $data['Column_name'];
            }
        else
        {
            foreach (debug_backtrace() as $trace)
                trigger_error(
                    'PDO Query error occurred via '.__METHOD__.' (columns)'.
                    ' in ' . $trace['file'].
                    ' on line ' . $trace['line'],
                    E_USER_WARNING);
            trigger_error(
                'PDO Query error#'.$pdo->errorInfo()[0].': '.$pdo->errorInfo()[2].
                ' ( ' . $pdo->errorInfo()[1] . ' ) ',
                E_USER_WARNING);
        }
    }

    /**
     * @param string $column_or_alias
     * @return string|null
     */
    public function sql_column_name($column_or_alias)
    {
        if (array_key_exists($column_or_alias, $this->aliases))
            return $this->aliases[$column_or_alias];
        if (array_key_exists($column_or_alias, $this->columns))
            return $column_or_alias;

        return NULL;
        /** @todo METTRE ERREUR SI ERREUR */

        foreach (debug_backtrace() as $trace)
            trigger_error(
                'Invalid column/alias name <b>'.$column_or_alias.'</b> via '.__METHOD__.' ($column_or_alias)'.
                ' in ' . $trace['file'].
                ' on line ' . $trace['line'],
                E_USER_WARNING);

        return NULL;
    }

    /**
     * @param array $where
     * @param string $sql
     * @return array Data list for the PDO Execute
     */
    private function sql_encode(array &$where, &$sql)
    {
        $text = array();
        $data = array();

        foreach ($where as $column => &$value)
        {
            if ($column = $this->sql_column_name($column))
            {
                //if ($value instanceof ORMVariant)
                //    $text[] = '`'.$column.'` '.$value->toSql();
                //else
                if ($value === null)
                    $text[] = "`$column` IS NULL";
                else
                {
                    $text[] = "`$column` = ?";
                    $data[] = $value;
                }
            }
        }

        $sql = implode(' AND ', $text);

        return $data;
    }








    // ALIAS
    public function setAliasOLD($name, $column)
    {
        if ($column = $this->sql_column_name($column))
            $this->aliases[$name] = $column;
    }
























    /*
     * column (nom correcte de la column)
     * columns (list column name)
     * aliases (list alias name)
     * links   (list link name)
     * isset    (check if exist)
     * filter   (return)
     * filtrate (fix ref)
     * encode
     *
     */

    /**
     * Retourn columns list
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Check if have column
     * @param $name Column name
     * @return bool
     */
    public function haveColumn($name)
    {
        if (isset($this->columns[$name]) || array_key_exists($name, $this->columns))
            return true;
        else
            return false;
    }

    /**
     * @param array $list
     * @return array
     */
    public function filter(&$list)
    {
        $result = array();

        foreach($list as $key => $val)
        {
            if (array_key_exists($key, $this->columns))
                $result[$key] = $val;
        }

        $list = $result;
        return $result;
    }

    /**
     * @param array $list
     * @return array
     */
    public function filterNull($list)
    {
        $new = array();

        foreach($list as $k => $v)
        {
            if ($v === null)
                continue;
            else
                $new[$k] = $v;
        }

        return $new;
    }

    /**
     * @param string $column Column name or alias of the column
     * @return string|null
     */
    private function sqlColumn($column)
    {
        return $column;
    }

    /** @todo alias on alias on alias (multi alias) */
    private function sqlWhere(array &$where, &$sql, &$data)
    {
        $text = array();
        //$data = array();

        foreach ($where as $column => &$value)
        {
            if ($column = $this->sqlColumn($column))
            {
                //if ($value instanceof ORMVariant)
                //    $text[] = '`'.$column.'` '.$value->toSql();
                //else
                if ($value === null)
                    $text[] = "`$column` IS NULL";
                else
                {
                    $text[] = "`$column` = ?";
                    $data[] = $value;
                }
            }
        }

        $sql = implode(', ', $text);

        //return array('text' => implode(', ', $text), 'data' => $data);
    }
}
