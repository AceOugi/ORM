<?php

namespace AceOugi\CODAddon;

trait GeneratorGenerateTrait
{
    protected $datag;

    public function delTree($dir)
    {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    public function generate()
    {
        global $pdo;

        $loader = new \Twig_Loader_Array([
            'hello' => 'Hello {{ name }}',
            //'test'  => \ORMD::database('old')->template_tpl->selectByPRIMARY( $_REQUEST['id'] ?? 1 )->tpl_content,
            'f_orme' => $this->template_file(1),
            'c_orme' => $this->template(1),
            'f_orm'  => $this->template_file(2),
            'c_orm'  => $this->template(2),
        ]);
        $twig = new \Twig_Environment($loader); 

        $this->data();

        $templates = [
            'f_orme' => 'c_orme',
            'f_orm' => 'c_orm',
        ];

        $dir = ROOT_DIR.'\\inc\\';

        if (file_exists($dir.'ORM'))
            $this->delTree($dir.'ORM');
        if (file_exists($dir.'ORME'))
            $this->delTree($dir.'ORME');


        //echo $twig->render('f_orme', $this->datag[0]);
        //exit;

        foreach ($this->datag ?? [] as $data)
        {
            foreach ($templates as $tf => $tc)
            {
                $file = $dir . $twig->render($tf, $data);
                $code = $twig->render($tc, $data);

                if (!is_dir( dirname($file) ))
                {
                    mkdir( dirname($file), 0777, true );
                }

                file_put_contents($file, $code);
            }
        }

    }

    public function template_file($id_template)
    {
        return orm('template')->select(['id' => $id_template])->file;
    }

    public function template($id_template)
    {
        $code = '';

        $this->template_code($code, $id_template);

        return $code;
    }

    public function template_code(&$code, $id_template, $id_parent = NULL, $times = 0)
    {
        foreach (orm('template_code')->selects(['id_template' => $id_template, 'id_parent' => $id_parent], 'ORDER BY `order`') as $tc)
        {
            $c = $tc->code;
            //$c = preg_replace('/\v/i', "\n", $c);
            //$c = preg_replace('/\n{2}/i', "\n--", $c);
            //$c = preg_replace('/([-]{2,}.*[-]{2,})/i', "~~", $c);
            foreach (explode("\r\n", $c) as $line)
                if (!isset($line[1]) OR (isset($line[1]) AND $line[0] != '{' AND $line[1] != '%'))
                    $code.= str_repeat(' ', $times*4) . $line . PHP_EOL;
                else
                    $code.= $line.PHP_EOL;
            //$code.= $c.PHP_EOL;

            //return;
            $this->template_code($code, $id_template, $tc->id, $times+1);
        }
    }

    public function data()
    {
        global $pdo;

        // List database
        foreach($pdo->query("SHOW DATABASES")->fetchAll(\PDO::FETCH_COLUMN) as $database)
        {
            $data_db = (isset($this->file_data->data->$database)) ? $this->file_data->data->$database : null;
            // If database hide
            if ( ($data_db AND isset($data_db->disabled) AND $data_db->disabled) OR
                in_array($database, explode('|', $this->file_data->settings->hide_database))
            )
                continue;

            // List table
            foreach($pdo->query("SHOW TABLES FROM `$database`")->fetchAll(\PDO::FETCH_COLUMN) as $table)
            {
                $data_tb = (isset($data_db->tables->$table)) ? $data_db->tables->$table : null;
                // If table hide
                if ( $data_tb AND isset($data_tb->disabled) AND $data_tb->disabled )
                    continue;

                // Columns
                $columns = [];
                $columns_all = [];
                foreach($pdo->query("SHOW COLUMNS FROM `$database`.`$table`")->fetchAll() as $column)
                {
                    $column_key = $column['Field'];
                    $data_cl = (isset($data_tb->columns->$column_key)) ? $data_tb->columns->$column_key : null;

                    preg_match('/([\w]+)\(?([\d]*)\)?\s?([\w\s]*)/', $column['Type'], $type_info);

                    $attrs = [];
                    if ($data_cl AND isset($data_cl->properties))
                    foreach ($data_cl->properties as $attr_name => $attr_value)
                        $attrs[$attr_name] = $attr_value;

                    $columns[$column['Field']] = [
                        //'name' => $column['Field'],
                        'name'     => $this->wordOO ($data_cl->name2 ?? $column['Field']),
                        'column'   => $data_cl->name3 ?? $column['Field'],
                        'id'       => $data_cl->name3 ?? $column['Field'],
                        'name_sql' => $data_cl->name3 ?? $column['Field'],
                        'sql'      => $data_cl->name3 ?? $column['Field'],
                        'name_oo'  => $this->wordOO ($data_cl->name2 ?? $column['Field']),
                        'name_ool' => $this->wordOOL($data_cl->name2 ?? $column['Field']),
                        'name_oou' => $this->wordOOU($data_cl->name2 ?? $column['Field']),

                        'type' => 'XXX',
                        'type_name' => $type_info[1],
                        'type_size' => $type_info[2],
                        'type_attr' => $type_info[3],
                        'type_full' => $column['Type'],

                        'is_null' => ($column['Null'] == 'YES') ? 1 : 0,

                        'key' => $column['Key'],
                        'is_primary' => ($column['Key'] == 'PRI') ? 1 : 0,
                        'is_unique' => ($column['Key'] == 'PRI' OR $column['Key'] == 'UNI') ? 1 : 0,
                        'is_unique_only' => ($column['Key'] == 'UNI') ? 1 : 0,

                        'default' =>$column['Default'],
                        'extra' => $column['Extra'],
                        'arguments' => $attrs,
                        'args' => $attrs,
                        'attrs' => $attrs,
                        'attributes' => $attrs,
                    ];

                    foreach ($attrs as $k => $v)
                        $columns[$column['Field']][$k] = $v;

                    // COPY IN COLUMNS_ALL
                    $columns_all[$column_key] = $columns[$column_key];

                    // If column hide
                    if ( $data_cl AND isset($data_cl->disabled) AND $data_cl->disabled )
                        unset( $columns[$column_key] );
                }

                // Alias
                $aliases = [];
                /* DISABLE * /
                foreach(\ORMD::database()->alias->selects(['database' => $database, 'table' => $table]) as $alias)
                {
                    if (!array_key_exists($alias->alias, $columns) AND array_key_exists($alias->column, $columns))
                    {
                        $aliases[$alias->alias] = $columns[$alias->column];
                        $aliases[$alias->alias]['id'] = $alias->alias;
                        $aliases[$alias->alias]['name'] = $this->wordOO($alias->alias);
                        $aliases[$alias->alias]['name_oo'] = $this->wordOO($alias->alias);
                        $aliases[$alias->alias]['name_ool'] = $this->wordOOL($alias->alias);
                        $aliases[$alias->alias]['name_oou'] = $this->wordOOU($alias->alias);
                    }
                }
                /* ALIAS I/O */

                // Link
                $links = [];
                if (isset($data_tb->links))
                foreach($data_tb->links as $link_key => $link)
                {
                    $links[$link_key] = [
                        'name' => $this->wordOO($link->name),
                        'id' => $link->name,
                        'name_sql' => $link->name,
                        'sql' => $link->name,
                        'name_oo' => $this->wordOO($link->name),
                        'name_ool' => $this->wordOOL($link->name),
                        'name_oou' => $this->wordOOU($link->name),

                        'database' => $link->to_db,
                        'table' => $link->to_tb,

                        'namespace' => $this->getTableGroup($link->to_db, $link->to_tb),
                        'class' => $this->getTableName($link->to_db, $link->to_tb),

                        'group' => $this->getTableGroup($link->to_db, $link->to_tb),
                        'object' => $this->getTableName($link->to_db, $link->to_tb),

                        'from_cl' => $link->from_cl,
                        'to_db' => $link->to_db,
                        'to_tb' => $link->to_tb,
                        'to_cl' => $link->to_cl,

                        'multiple' => $link->multiple,
                        'check' => [$link->from_cl => $link->to_cl]
                    ];

                    //if (strlen($link->check1_from) AND strlen($link->check1_to))
                    //    $links[$link->link]['check'][$link->check1_from] = $link->check1_to;
                    //2...3...
                }

                // Index
                $indexes = [];
                foreach($pdo->query("SHOW INDEXES FROM `$database`.`$table`")->fetchAll() as $elem)
                {
                    if (array_key_exists($elem['Key_name'], $indexes))
                        $indexes[ $elem['Key_name'] ][ 'columns' ][ $elem['Seq_in_index'] ] = $columns[$elem['Column_name']];
                    else
                        $indexes[ $elem['Key_name'] ] = [
                            //'name' => $elem['Key_name'],
                            'name' => $this->wordOO(($elem['Key_name'] == 'PRIMARY') ? 'Primary' : $elem['Key_name']),
                            'name_sql' => $elem['Key_name'],
                            'sql' => $elem['Key_name'],
                            'index' => $elem['Key_name'],
                            'name_oo' => $this->wordOO($elem['Key_name']),
                            'name_ool' => $this->wordOOL($elem['Key_name']),
                            'name_oou' => $this->wordOOU($elem['Key_name']),

                            'type' => ($elem['Key_name'] == 'PRIMARY') ? 'PRI' : ($elem['Non_unique'] ? 'MUL' : 'UNI'),
                            'columns' => [ $elem['Seq_in_index'] => $columns_all[$elem['Column_name']] ],
                            'is_null' => ($elem['Null'] == 'YES') ? 1 : 0,
                            'comment' => $elem['Comment'],
                            'index_comment' => $elem['Index_comment'],
                        ];
                }

                foreach ($indexes as &$index)
                    $index['properties'] = $index['columns'];

                $indexes_mul = [];
                $indexes_uni = [];
                $uni = null;
                $pri = null;

                foreach ($indexes as &$index)
                {
                    switch ($index['type'])
                    {
                        case 'PRI':
                            $pri = $index;
                            $uni = $index;
                            $indexes_uni[] = $index;
                            break;
                        case 'UNI':
                            if ($uni == null) $uni = $index;
                            $indexes_uni[] = $index;
                            break;
                        case 'MUL':
                            $indexes_mul[] = $index;
                            break;
                    }
                }
                if ($pri == null AND $uni != null)
                    $pri = $uni;



                /* START * /
                foreach ($indexes_uni as &$index)
                {
                    unset($index['columns']);
                    unset($index['properties']);
                }
                //print_r ($indexes);
                print_r ($indexes_uni);
                exit;
                /* END */



                // Data
                $this->datag[] = [
                    'database' => $database,
                    'namespace' => $this->getTableGroup($database, $table),
                    'group' => $this->getTableGroup($database, $table),
                    'table' => $table,
                    'class' => $this->getTableName($database, $table),
                    'object' => $this->getTableName($database, $table),
                    'columns' => $columns,
                    'properties' => $columns,
                    'aliases' => $aliases,
                    'indexs' => $indexes,
                    'indexes' => $indexes,
                    'pri' => $pri,
                    'uni' => $uni,
                    'indexes_uni' => $indexes_uni,
                    'indexes_mul' => $indexes_mul,
                    'links' => $links,
                ];
            }
        }
    }

    public function getTableName($database, $table)
    {
        return $this->file_data->data->$database->tables->$table->name ?? $this->wordOOU($table);
    }
    public function getTableGroup($database, $table)
    {
        return $this->file_data->data->$database->tables->$table->group ?? $this->file_data->data->$database->group ?? $this->wordOOU($database);
    }

    public function wordOO($words)
    {
        $result = '';
        foreach (explode('_', $words) as $word)
            $result.= ucfirst($word);
        return $result;
    }
    public function wordOOU($words)
    {
        return ucfirst($this->wordOO($words));
    }
    public function wordOOL($words)
    {
        return lcfirst($this->wordOO($words));
    }

}
