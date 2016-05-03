<?php

namespace AceOugi;

class ORMEntity
{
    /** @var ORMSkeleton $_SKELETON */
    protected $_SKELETON;
    /** @var array $_DATA */
    protected $_DATA = array();
    /** @var array $_UNIQUE */
    protected $_UNIQUE = array();
    /** @todo optimisation: si pas unique, utiliser un index dans l'ordre du where, suivi des elements restant */

    function __construct($skeleton, array $data, array $unique = array())
    {
        $this->_SKELETON = $skeleton;
        $this->_DATA = $data;
        foreach ($unique as $column_name)
            if (isset($this->_DATA[$column_name]) || array_key_exists($column_name, $this->_DATA))
                $this->_UNIQUE[$column_name] =& $this->_DATA[$column_name];
    }

    // Getter
    /**
     * @param $name
     * @return null|ORMEntity|ORMEntity[]
     */
    public function __get($name)
    {
        $tname = $name;
        /*
        // Found link
        if ($links = $this->_SKELETON->getLink($name))
        {
            $multi  = false;
            $results= null;

            foreach ($links as $link)
            {
                $target = \ORMD::database($link['database_id'])->invoke($link['table_name']);
                $tmp = array();

                /** @var \ORMD\Skeleton[] $link * /
                if ($link['multi'])
                {
                    if ($multi)
                    {
                        foreach($results as $result)
                        {
                            $params = array();
                            foreach ($link['compares'] as $k => $v)
                                $params[$v] = $result->__get($k);

                            $tmp = array_merge($tmp, $target->selects($params));
                        }
                    }
                    else
                    {
                        $current = ($results)?$results:$this;
                        $params = array();
                        foreach ($link['compares'] as $k => $v)
                            $params[$v] = $current->__get($k);

                        $tmp = $target->selects($params);
                    }

                    $multi = true;
                }
                else
                {
                    if ($multi)
                    {
                        foreach($results as $result)
                        {
                            $params = array();
                            foreach ($link['compares'] as $k => $v)
                                $params[$v] = $result->__get($k);

                            $tmp[]= $target->select($params);
                        }
                    }
                    else
                    {
                        $current = ($results)?$results:$this;
                        $params = array();
                        foreach ($link['compares'] as $k => $v)
                            $params[$v] = $current->__get($k);

                        $tmp = $target->select($params);
                    }
                }

                $results= $tmp;
            }

            return $results;
        }
        */

        // Found
        $name = $this->_SKELETON->sql_column_name($name);
        if (isset($this->_DATA[$name]) || array_key_exists($name, $this->_DATA))
            return $this->_DATA[$name];

        // Error (not found)
        foreach (debug_backtrace() as $trace)
            trigger_error(
                '<br />'."\r\n".
                '<b>Notice</b>:  Undefined property: '.__CLASS__.'::$'.$tname.'-'.$name.
                ' in <b>'.$trace['file'].'</b>'.
                ' on line <b>'.$trace['line'].'</b><br />'."\r\n".
                '<br />',
                E_USER_NOTICE
            );

        return null;
    }

    // Setter
    public function __set($name, $value)
    {
        $this->update(array($name => $value));
    }

    /**
     * Warning: care if have unique or not
     * @todo secure duplicate with unique
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function duplicate()
    {
        return $this->_SKELETON->insert($this->_DATA);
    }

    /**
     * @param array $data
     */
    public function update($data)
    {
        if ($this->_SKELETON->update($data, ((count($this->_UNIQUE))?$this->_UNIQUE:$this->_DATA) ))
            foreach ($data as $k => $v)
                if ($column = $this->_SKELETON->sql_column_name($k))
                    $this->_DATA[$column] = $v;
    }

    /**
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function delete()
    {
        return $this->_SKELETON->delete($this->_DATA);
    }
}
