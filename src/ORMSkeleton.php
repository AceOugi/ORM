<?php

namespace AceOugi;

class ORMSkeleton
{

    /** @var \PDO $pdo */
    protected $pdo;
    /** @var string $database */
    protected $database;
    /** @var string $table */
    protected $table;
    /** @var string $table_full_name Table full name: `$database`.`$table` */
    protected $table_full_name;

    /** @var array $columns */
    protected $columns = array();
    /** @var array $indexu */
    protected $indexu = array();
    /** @var array $indexm */
    protected $indexm = array();

    /** @var array $aliases */
    protected $aliases = array();
    /** @var array $links */
    protected $links = array();
    /** @var array $links_m */
    protected $links_m = array();

    /**
     * @param string $name
     * @param array ...$list
     */
    public function setLink($name, ...$list)
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
    public function __construct($pdo, $database, $table)
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


    /**
     * @param array $params
     * @param ...$extra
     * @return ORMEntity|null
     */
    public function select($params = array(), ...$extra)
    {
        $extra[] = 'LIMIT 1';
        return ( $temp = $this->selects($params, ...$extra) ) ? reset($temp) : null;
    }

    /**
     * @param array $params
     * @param ...$extra
     * @return ORMEntity[]
     */
    public function selects($params = array(), ...$extra)
    {
        $input = $this->sql_encode($params, $sql);

        if (strlen($sql))
            $sql = " WHERE $sql ";

        foreach ($extra as $extra_elem)
            $sql.= " $extra_elem ";

        $req = $this->pdo->prepare("SELECT * FROM $this->table_full_name $sql");

        $req->execute($input);

        $result = array();
        while($data = $req->fetch())
            $result[] = new ORMEntity($this, $data, ((count($this->indexu))?reset($this->indexu):array()) );

        return $result;
    }

    /**
     * @param array $params
     * @param ...$extra
     * @return int
     */
    public function count($params = array(), ...$extra)
    {
        $input = $this->sql_encode($params, $sql);

        if (strlen($sql))
            $sql = " WHERE $sql ";

        foreach ($extra as $extra_elem)
            $sql.= " $extra_elem ";

        $req = $this->pdo->prepare("SELECT COUNT(*) FROM $this->table_full_name $sql");

        return ($req->execute($input)) ? $req->fetchColumn() : 0;
    }

    /**
     * @param array $params
     * @return bool
     */
    public function insert(array $params)
    {
        $data = array();

        foreach ($params as $k => $v)
            if ($column = $this->sql_column_name($k))
                $data[$k] = $v;

        $sql_k = '';
        $sql_v = '';
        $sql_d = array();

        foreach ($data as $k => $v)
        {
            if (strlen($sql_k))
                $sql_k.= ', ';
            $sql_k.= "`$k`";

            if (strlen($sql_v))
                $sql_v.= ', ';
            $sql_v.= '?';

            $sql_d[] = $v;
        }

        $req = $this->pdo->prepare("INSERT INTO $this->table_full_name ($sql_k) VALUES ($sql_v)");

        return $req->execute($sql_d);
    }

    /**
     * @param array ...$params_list
     * @return int nb de succes
     */
    public function inserts(array ...$params_list)
    {
        $success = 0;

        foreach ($params_list as &$params)
            $success+= $this->insert($params);

        return $success;
    }

    /**
     * @param array $sets
     * @param array $params
     * @param ...$extra
     * @return bool
     */
    public function update($sets, $params = array(), ...$extra)
    {
        $extra[] = 'LIMIT 1';
        return $this->updates($sets, $params, ...$extra);
    }

    /**
     * @param array $sets
     * @param array $params
     * @param ...$extra
     * @return bool
     */
    public function updates($sets, $params = array(), ...$extra)
    {
        // Set
        $input = array();
        $sql = ' SET ';
        foreach ($sets as $k => $v)
            if ($column = $this->sql_column_name($k))
            {
                $sql.= " `$column`=?,";
                $input[] = $v;
            }
        $sql = rtrim($sql, ',');

        // Where
        $input = array_merge($input, $this->sql_encode($params, $sqlw));

        if (strlen($sqlw))
            $sql.= " WHERE $sqlw ";

        // Extra
        foreach ($extra as $extra_elem)
            $sql.= " $extra_elem ";

        // Exec
        return $this->pdo->prepare("UPDATE $this->table_full_name $sql ")->execute($input);
    }

    /**
     * @param array $params
     * @param ...$extra
     * @return bool
     */
    public function delete($params = array(), ...$extra)
    {
        $extra[] = 'LIMIT 1';
        return $this->deletes($params, ...$extra);
    }

    /**
     * @param array $params
     * @param ...$extra
     * @return bool
     */
    public function deletes($params = array(), ...$extra)
    {
        $input = $this->sql_encode($params, $sql);

        if (strlen($sql))
            $sql = " WHERE $sql ";

        foreach ($extra as $extra_elem)
            $sql.= " $extra_elem ";

        return $this->pdo->prepare("DELETE FROM $this->table_full_name $sql")->execute($input);
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

            // Merge index (unique and not unique)
            foreach (array_merge($this->indexu, $this->indexm) as $index_name => $index_columns)
            {
                $index_name_oo = explode('_', $index_name);
                foreach ($index_name_oo as &$tn)
                {
                    $tn = strtolower($tn);
                    $tn = ucfirst($tn);
                }
                $index_name_oo = implode('', $index_name_oo);
                if ($i == $index_name OR $i == $index_name_oo)
                {
                    if ($m == 'update' OR $m == 'updates')
                        $tmp = array_shift($arguments);

                    $new_index_columns = array();
                    foreach ($index_columns as $k => $v)
                    {
                        $new_index_columns[$v] = array_shift($arguments);
                    }

                    array_unshift($arguments, $new_index_columns);
                    if ($m == 'update' OR $m == 'updates')
                        array_unshift($arguments, $tmp);

                    return call_user_func_array(array($this, $m), $arguments);
                }
            }
        }

        echo 'FAIL';
        return null;
    }






    // ALIAS
    public function setAlias($name, $column)
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
