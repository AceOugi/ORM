<?php

namespace AceOugi\CODAddon;

use AceOugi\ORM;
use AceOugi\Renderer;
use Zend\Diactoros\Response\HtmlResponse;

class Generator
{
    protected $shared = [];
    public function share($key, $val)
    {
        $this->shared[$key] = $val;
    }

    /** @var string */
    protected $file_path = APP_DIR.'/database/generator.json';
    /** @var \stdClass */
    protected $file_data;

    /** @var \PDO */
    protected $pdo;
    /** @var bool */
    protected $error = FALSE;
    /** @var string */
    protected $error_message = 'ERROR_MESSAGE_DEFAULT';

    /** @var string */
    protected $url_base = '';
    /** @var string */
    protected $url = '';

    use GeneratorGenerateTrait;

    /**
     * GeneratorController constructor.
     */
    public function __construct()
    {
        global $pdo;

        // Load
        $this->file_data = json_decode(file_get_contents( $this->file_path ));

        // PDO
        try
        {
            $pdo = $this->pdo = new \PDO
            (
                $this->file_data->settings->database->dsn ?? '',
                $this->file_data->settings->database->username ?? '',
                $this->file_data->settings->database->password ?? '',
                array
                (
                    \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_TIMEOUT => '1',
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    //\PDO::ATTR_PERSISTENT => true,
                )
            );

            // ORM
            \AceOugi\ORM::setDefaultConnection($this->pdo);
            ORM::setDefaultDatabase('generator');

        }
        catch (\PDOException $e)
        {
            $this->error = TRUE;
            $this->error_message = $e->getMessage();
        }
    }

    function main()
    {
        $this->url_base = 'generator.html';
        $this->url = $this->url_base.'?module='. ($_GET['module']??'explorer');
        if (FALSE AND isset($_GET['filter']))
            $this->url.= '&filter='.$_GET['filter'];

        $this->settings();
        if (!$this->error)
        {
            $this->explorer();
            $this->entity();
        }

        if (isset($_GET['module']) AND $_GET['module'] == 'generate')
        {
            $this->generate();
        }

        $html = Renderer::render(__DIR__ . '/GeneratorTemplate.php', $this->shared+[
            'data' => $this->file_data,
            'error' => $this->error,
            'error_message' => $this->error_message,
            'module' => $_GET['module'] ?? 'explorer',
            'url_base' => $this->url_base,
            'url' => $this->url
        ]);
        $response = new HtmlResponse($html);

        echo $html;

        return $response;
    }

    function entityPrep()
    {
        $this->explorerTb();

        if (isset($_GET['database']) AND $database = $_GET['database'])
            if (isset($_GET['table']) AND $table = $_GET['table'])
                if (isset($_GET['column']) AND $column = $_GET['column'])
                {
                    if (!isset($this->file_data->data->$database->tables->$table->columns))
                        $this->file_data->data->$database->tables->$table->columns = new \stdClass();

                    if (!isset($this->file_data->data->$database->tables->$table->columns->$column))
                        $this->file_data->data->$database->tables->$table->columns->$column = new \stdClass();
                }
    }
    function entityPrepToggle()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'disable')
        {
            $this->entityPrep();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                if (isset($_GET['table']) AND $table = $_GET['table'])
                    if (isset($_GET['column']) AND $column = $_GET['column'])
                        $this->file_data->data->$database->tables->$table->columns->$column->disabled = true;
        }

        if (isset($_GET['action']) AND $_GET['action'] == 'enable')
        {
            $this->entityPrep();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                if (isset($_GET['table']) AND $table = $_GET['table'])
                    if (isset($_GET['column']) AND $column = $_GET['column'])
                {
                    unset($this->file_data->data->$database->tables->$table->columns->$column->disabled);
                    if (count( (array) $this->file_data->data->$database->tables->$table->columns->$column ) == 0) // TODO TEMPORARY
                        unset($this->file_data->data->$database->tables->$table->columns->$column);
                }
        }
    }
    function entityPrepReset()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'reset')
        {
            $this->entityPrep();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                if (isset($_GET['table']) AND $table = $_GET['table'])
                    if (isset($_GET['column']) AND $column = $_GET['column'])
                    unset($this->file_data->data->$database->tables->$table->columns->$column);
        }
    }
    function entityPrepEdit()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'edit')
        {
            $this->entityPrep();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                if (isset($_GET['table']) AND $table = $_GET['table'])
                    if (isset($_GET['column']) AND $column = $_GET['column'])
                {
                    if (isset($_POST['name']) AND strlen($_POST['name']))
                        $this->file_data->data->$database->tables->$table->columns->$column->name = $_POST['name'];
                    elseif (isset($_POST['name']) AND strlen($_POST['name']) == 0)
                        unset($this->file_data->data->$database->tables->$table->columns->$column->name);

                    if (isset($_POST['name1']) AND strlen($_POST['name1']))
                        $this->file_data->data->$database->tables->$table->columns->$column->name1 = $_POST['name1'];
                    elseif (isset($_POST['name1']) AND strlen($_POST['name1']) == 0)
                        unset($this->file_data->data->$database->tables->$table->columns->$column->name1);

                    if (isset($_POST['name2']) AND strlen($_POST['name2']))
                        $this->file_data->data->$database->tables->$table->columns->$column->name2 = $_POST['name2'];
                    elseif (isset($_POST['name2']) AND strlen($_POST['name2']) == 0)
                        unset($this->file_data->data->$database->tables->$table->columns->$column->name2);

                    // Properties create if no exist
                    if (!isset($this->file_data->data->$database->tables->$table->columns->$column->properties))
                        $this->file_data->data->$database->tables->$table->columns->$column->properties = new \stdClass();

                    // Properties insert
                    if (isset($_POST['regex']) AND strlen($_POST['regex']))
                        $this->file_data->data->$database->tables->$table->columns->$column->properties->regex = $_POST['regex'];
                    elseif (isset($_POST['regex']) AND strlen($_POST['regex']) == 0)
                        unset($this->file_data->data->$database->tables->$table->columns->$column->properties->regex);

                    if (isset($_POST['type']) AND strlen($_POST['type']))
                        $this->file_data->data->$database->tables->$table->columns->$column->properties->type = $_POST['type'];
                    elseif (isset($_POST['type']) AND strlen($_POST['type']) == 0)
                        unset($this->file_data->data->$database->tables->$table->columns->$column->properties->type);

                    // Properties custom

                    // Properties delete if empty
                    if (count( (array) $this->file_data->data->$database->tables->$table->columns->$column->properties ) == 0) // TODO TEMPORARY
                        unset($this->file_data->data->$database->tables->$table->columns->$column->properties);
                }
        }
    }

    function entityLink()
    {
        if (isset($_GET['action']) AND ($_GET['action'] == 'link_add' OR $_GET['action'] == 'link_del'))
        {
            $this->entityPrep();

            if (isset($_GET['database']) AND $database = $_GET['database'])
                if (isset($_GET['table']) AND $table = $_GET['table'])
                {
                    if (!isset($this->file_data->data->$database->tables->$table->links))
                        $this->file_data->data->$database->tables->$table->links = new \stdClass();

                    // ADD
                    if ($_GET['action'] == 'link_add')
                    {
                        $name = $_POST['name'];
                        $this->file_data->data->$database->tables->$table->links->$name = new \stdClass();
                        $this->file_data->data->$database->tables->$table->links->$name->name = $_POST['name'];
                        $this->file_data->data->$database->tables->$table->links->$name->from_cl = $_POST['from_cl'];
                        $this->file_data->data->$database->tables->$table->links->$name->to_db = $_POST['to_db'];
                        $this->file_data->data->$database->tables->$table->links->$name->to_tb = $_POST['to_tb'];
                        $this->file_data->data->$database->tables->$table->links->$name->to_cl = $_POST['to_cl'];
                        $this->file_data->data->$database->tables->$table->links->$name->multiple = (isset($_POST['multiple'])) ? true : false;
                    }

                    // DEL
                    if ($_GET['action'] == 'link_del' AND $name = $_GET['link'] AND isset($this->file_data->data->$database->tables->$table->links->$name))
                    {
                        unset($this->file_data->data->$database->tables->$table->links->$name);
                    }

                    if (count( (array) $this->file_data->data->$database->tables->$table->links ) == 0) // TODO TEMPORARY
                        unset($this->file_data->data->$database->tables->$table->links);
                }
        }
    }

    function entity()
    {
        if (!isset($_GET['module']) OR $_GET['module'] != 'manager')
            return;

        $this->entityPrepToggle();
        $this->entityPrepReset();
        $this->entityPrepEdit();
        $this->entityLink();

        /*
        $databases = $this->get('databases');
        if (isset($databases[$_GET['database']]['tables'][$_GET['table']]))
        {
            var_dump($databases[$_GET['database']]['tables'][$_GET['table']]);
            exit;
        }
        if (isset($this->file_data->data->{$_GET['database']}->tables->{$_GET['table']}))
        {
            var_dump($this->file_data->data->{$_GET['database']}->tables->{$_GET['table']});
            exit;
        }
        */

        $hide_disabled = $this->file_data->settings->hide_disabled;

        $database_key = $_GET['database'];
        $table_key = $_GET['table'];

        $columns = [];

        // Columns PDO
        foreach ($this->pdo->query("SHOW COLUMNS FROM `$database_key`.`$table_key`")->fetchAll(\PDO::FETCH_COLUMN) as $column_key)
        {
            if (!isset($columns[$column_key]))
                $columns[$column_key] = ['in_data' => false];

            $columns[$column_key]['sql'] = $column_key;
            $columns[$column_key]['in_pdo'] = true;
        }

        // Columns Data
        if (isset($this->file_data->data->$database_key->tables->$table_key->columns))
        foreach ($this->file_data->data->$database_key->tables->$table_key->columns as $column_key => &$column)
        {
            if (FALSE AND count( (array) $column ) == 0) // TODO TEMPORARY
            {
                unset($column);
                continue;
            }

            if (!isset($columns[$column_key]))
                $columns[$column_key] = ['in_pdo' => false];

            $columns[$column_key]['sql'] = $column_key;
            $columns[$column_key]['in_data'] = true;
            $columns[$column_key]['disabled'] = (isset($column->disabled) AND $column->disabled) ? true : false;

            if (isset($column->name) AND $column->name)
                $columns[$column_key]['name'] = $column->name;
            if (isset($column->name1) AND $column->name1)
                $columns[$column_key]['name1'] = $column->name1;
            if (isset($column->name2) AND $column->name2)
                $columns[$column_key]['name2'] = $column->name2;
            if (isset($column->properties->type) AND $column->properties->type)
                $columns[$column_key]['type'] = $column->properties->type;
            if (isset($column->properties->regex) AND $column->properties->regex)
                $columns[$column_key]['regex'] = $column->properties->regex;

            if ($hide_disabled AND isset($column->disabled) AND $column->disabled)
                $columns[$column_key]['hide'] = true;
        }

        // Columns Name
        foreach ($columns as $column_key => &$column)
        {
            $column['name_default'] = $this->wordOO($column_key);
            $column['name1_default'] = $column['sql'];
            $column['name2_default'] = $this->wordOO($column_key);
        }

        // Links
        $links = [];
        if (isset($this->file_data->data->$database_key->tables->$table_key->links))
        foreach ($this->file_data->data->$database_key->tables->$table_key->links as $link_key => &$link)
        {
            if (FALSE AND count( (array) $link ) == 0) // TODO TEMPORARY
            {
                unset($link);
                continue;
            }

            $links[$link_key]['name'] = $link->name;
            $links[$link_key]['from_cl'] = $link->from_cl;
            $links[$link_key]['to_db'] = $link->to_db;
            $links[$link_key]['to_tb'] = $link->to_tb;
            $links[$link_key]['to_cl'] = $link->to_cl;
            $links[$link_key]['multiple'] = $link->multiple;
        }

        $this->share('links', $links);
        $this->share('columns', $columns);
        $this->share('database', $database_key);
        $this->share('table', $table_key);
    }

    function explorerDb()
    {
        if (isset($_GET['database']) AND $database = $_GET['database'])
            if (!isset($this->file_data->data->$database))
                $this->file_data->data->$database = new \stdClass();
    }
    function explorerTb()
    {
        $this->explorerDb();

        if (isset($_GET['database']) AND $database = $_GET['database'])
            if (isset($_GET['table']) AND $table = $_GET['table'])
            {
                if (!isset($this->file_data->data->$database->tables))
                    $this->file_data->data->$database->tables = new \stdClass();

                if (!isset($this->file_data->data->$database->tables->$table))
                    $this->file_data->data->$database->tables->$table = new \stdClass();
            }
    }

    function explorerDbReset()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'db_reset')
        {
            $this->explorerDb();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                unset($this->file_data->data->$database);
        }
    }
    function explorerTbReset()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'tb_reset')
        {
            $this->explorerTb();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                if (isset($_GET['table']) AND $table = $_GET['table'])
                    unset($this->file_data->data->$database->tables->$table);
        }
    }

    function explorerDbDisable()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'db_disable')
        {
            $this->explorerDb();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                $this->file_data->data->$database->disabled = true;
        }
    }
    function explorerTbDisable()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'tb_disable')
        {
            $this->explorerTb();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                if (isset($_GET['table']) AND $table = $_GET['table'])
                    $this->file_data->data->$database->tables->$table->disabled = true;
        }
    }

    function explorerDbEnable()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'db_enable')
        {
            $this->explorerDb();
            if (isset($_GET['database']) AND $database = $_GET['database'])
            {
                unset($this->file_data->data->$database->disabled);
                if (count( (array) $this->file_data->data->$database ) == 0) // TODO TEMPORARY
                    unset($this->file_data->data->$database);
            }
        }
    }
    function explorerTbEnable()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'tb_enable')
        {
            $this->explorerTb();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                if (isset($_GET['table']) AND $table = $_GET['table'])
                {
                    unset($this->file_data->data->$database->tables->$table->disabled);
                    if (count( (array) $this->file_data->data->$database->tables->$table ) == 0) // TODO TEMPORARY
                        unset($this->file_data->data->$database->tables->$table);
                }
        }
    }

    function explorerDbEdit()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'db_edit')
        {
            $this->explorerDb();
            if (isset($_GET['database']) AND $database = $_GET['database'])
            {
                if (isset($_POST['name']) AND $_POST['name'])
                    $this->file_data->data->$database->name = $_POST['name'];
                else
                    unset($this->file_data->data->$database->name);

                if (isset($_POST['group']) AND $_POST['group'])
                    $this->file_data->data->$database->group = $_POST['group'];
                else
                    unset($this->file_data->data->$database->group);

                if (isset($_POST['pdo']) AND $_POST['pdo'])
                    $this->file_data->data->$database->pdo = $_POST['pdo'];
                else
                    unset($this->file_data->data->$database->pdo);
            }
        }
    }
    function explorerTbEdit()
    {
        if (isset($_GET['action']) AND $_GET['action'] == 'tb_edit')
        {
            $this->explorerTb();
            if (isset($_GET['database']) AND $database = $_GET['database'])
                if (isset($_GET['table']) AND $table = $_GET['table'])
                {
                    if (isset($_POST['name']) AND $_POST['name'])
                        $this->file_data->data->$database->tables->$table->name = $_POST['name'];
                    else
                        unset($this->file_data->data->$database->tables->$table->name);

                    if (isset($_POST['group']) AND $_POST['group'])
                        $this->file_data->data->$database->tables->$table->group = $_POST['group'];
                    else
                        unset($this->file_data->data->$database->tables->$table->group);

                    if (isset($_POST['pdo']) AND $_POST['pdo'])
                        $this->file_data->data->$database->tables->$table->pdo = $_POST['pdo'];
                    else
                        unset($this->file_data->data->$database->tables->$table->pdo);
                }
        }
    }

    function explorer()
    {
        $all = (isset($this->file_data->settings->view) AND $this->file_data->settings->view == 'all') ? true : false;

        $hide_disabled = $this->file_data->settings->hide_disabled;

        // Action
        $this->explorerDbReset();
        $this->explorerDbDisable();
        $this->explorerDbEnable();
        $this->explorerDbEdit();

        $this->explorerTbReset();
        $this->explorerTbDisable();
        $this->explorerTbEnable();
        $this->explorerTbEdit();

        // Databases
        $databases = [];

        // Databases PDO
        foreach ($this->pdo->query('SHOW DATABASES')->fetchAll(\PDO::FETCH_COLUMN) as $database)
        {
            if (!isset($databases[$database]))
                $databases[$database] = ['in_data' => false];

            $databases[$database]['sql'] = $database;
            $databases[$database]['in_pdo'] = true;
        }

        // Database Data
        if (!isset($this->file_data->data)) $this->file_data->data = new \stdClass();
        foreach ($this->file_data->data as $database => $data)
        {
            if (FALSE AND count( (array) $data ) == 0) // TODO TEMPORARY
            {
                unset($this->file_data->data->$database);
                continue;
            }

            if (!isset($databases[$database]))
                $databases[$database] = ['in_pdo' => false];

            $databases[$database]['sql'] = $database;
            $databases[$database]['in_data'] = true;
            $databases[$database]['disabled'] = (isset($data->disabled) AND $data->disabled) ? true : false;

            if (isset($data->name) AND $data->name)
                $databases[$database]['name'] = $data->name;
            if (isset($data->group) AND $data->group)
                $databases[$database]['group'] = $data->group;
            if (isset($data->pdo) AND $data->pdo)
                $databases[$database]['pdo'] = $data->pdo;

            // DEFAULT_NAME

            // DEFAULT_GROUP

            if ($hide_disabled AND isset($data->disabled) AND $data->disabled)
                $databases[$database]['hide'] = true;
        }

        // Database Filtrer
        if (isset($_GET['filter']))
        {
            foreach ($databases as $database => $data)
                $databases[$database]['hide'] = ($_GET['filter'] != $data['sql']) ? true : false;
            $this->url.= '&filter='.$_GET['filter'];
        }

        // Database Hide
        foreach(explode('|', $this->file_data->settings->hide_database) as $database)
        {
            if (!isset($databases[$database]))
                $databases[$database] = ['in_data' => false, 'in_pdo' => false];

            $databases[$database]['sql'] = $database;
            $databases[$database]['hide'] = true;
        }

        // Database Name
        foreach ($databases as $database => $data)
        {
            $databases[$database]['group_default'] = $this->wordOO($database);
        }

        // Table
        foreach ($databases as $database_key => &$database)
        {
            $database['tables'] = [];
            $tables =& $database['tables'];

            if (!$database['in_pdo'])
                continue;
            if (!$all)
                if (!isset($_GET['filter']) OR
                    (isset($_GET['filter']) AND $_GET['filter']) != $database_key
                )
                    continue;

            // Table PDO
            foreach ($this->pdo->query("SHOW TABLES FROM `$database_key`")->fetchAll(\PDO::FETCH_COLUMN) as $table_key)
            {
                if (!isset($tables[$table_key]))
                    $tables[$table_key] = ['in_data' => false];

                $tables[$table_key]['sql'] = $table_key;
                $tables[$table_key]['in_pdo'] = true;
            }

            // Table Data
            if (isset($this->file_data->data->$database_key->tables))
            foreach ($this->file_data->data->$database_key->tables as $table_key => &$data)
            {
                if (FALSE AND count( (array) $data ) == 0) // TODO TEMPORARY
                {
                    unset($this->file_data->data->$database_key->tables->$table_key);
                    continue;
                }

                if (!isset($tables[$table_key]))
                    $tables[$table_key] = ['in_pdo' => false];

                $tables[$table_key]['sql'] = $table_key;
                $tables[$table_key]['in_data'] = true;
                $tables[$table_key]['disabled'] = (isset($data->disabled) AND $data->disabled) ? true : false;

                if (isset($data->name) AND $data->name)
                    $tables[$table_key]['name'] = $data->name;
                if (isset($data->group) AND $data->group)
                    $tables[$table_key]['group'] = $data->group;
                if (isset($data->pdo) AND $data->pdo)
                    $tables[$table_key]['pdo'] = $data->pdo;

                // DEFAULT_NAME

                // DEFAULT_GROUP

                if ($hide_disabled AND isset($data->disabled) AND $data->disabled)
                    $tables[$table_key]['hide'] = true;
            }

            // Table Name
            foreach ($tables as $table_key => &$table)
            {
                $table['name_default'] = $this->wordOO($table_key);
            }
        }

        // <==========>
        $this->share('databases', $databases);
    }

    function settings()
    {
        if (!isset($_GET['module']) OR (isset($_GET['module']) AND $_GET['module'] != 'settings'))
            return;

        if (isset($_GET['action']) AND $_GET['action'] == 'reset')
        {
            $this->file_data = json_decode(self::$file_data_default);
        }

        if (isset($_POST['settings']))
        {
            if(!isset($this->file_data->settings)) $this->file_data->settings = new \stdClass();
            if(!isset($this->file_data->settings->database)) $this->file_data->settings->database = new \stdClass();
            $this->file_data->settings->database->dsn      = $_POST['settings']['database']['dsn'];
            $this->file_data->settings->database->username = $_POST['settings']['database']['username'];
            $this->file_data->settings->database->password = $_POST['settings']['database']['password'];
            $this->file_data->settings->hide_database = $_POST['settings']['hide_database'];
            $this->file_data->settings->hide_disabled = $_POST['settings']['hide_disabled'] ?? false;
            $this->file_data->settings->view = $_POST['settings']['view'];
        }
    }

    function __destruct()
    {
        // Save
        file_put_contents($this->file_path, json_encode($this->file_data, JSON_PRETTY_PRINT));
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

    static protected $file_data_default = '{
    "settings":
    {
        "database":
        {
            "dsn": "mysql:host=localhost;port=3306;charset=UTF8",
            "username": "root",
            "password": ""
        },
        "hide_database": "information_schema|mysql|performance_schema|phpmyadmin|test",
        "hide_disabled": true,
        "view": "all"
    },
    "data":
    {
    }
}';

}
