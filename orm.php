<?php

require 'vendor/autoload.php';
require __DIR__.'/orm_generator.php';

if (is_file('orm.json'))
{
    $config = json_decode(file_get_contents('orm.json'), true);
}
elseif (is_file('orm.yaml'))
{
    $config = \Spyc::YAMLLoad('orm.yaml');
}
else
    exit('Undefined config file');

$settings = $config['settings'] ?? [];
$database = $config['database'];
$data     = $config['data'] ?? [];

$pdo = new \PDO(
    $database['dsn'],
    $database['username'] ?? '',
    $database['password'] ?? '',
    $database['options'] ?? [
        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_TIMEOUT => '1',
        \PDO::ATTR_PERSISTENT => false,
        //\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ]
);

$title1 = 'ORME/{{ namespace }}/{{ class }}.php';
$title2 = 'ORM/{{ namespace }}/{{ class }}.php';
$source1= file_get_contents(__DIR__.'/template/php1.twig');
$source2= file_get_contents(__DIR__.'/template/php2.twig');

// OLD MODE
$all = [];
$all['settings'] = $settings;
$all['settings']['database'] = $database;
$all['data'] = $data;
$all = array_to_object($all);
$gen = new \AceOugi\CODAddon\Generator();
$gen->generate();
function array_to_object($array) {
    $obj = new stdClass;
    foreach($array as $k => $v) {
        if(strlen($k)) {
            if(is_array($v)) {
                $obj->{$k} = array_to_object($v); //RECURSION
            } else {
                $obj->{$k} = $v;
            }
        }
    }
    return $obj;
}

/*

foreach (\ORQ\Connection::setPDO($pdo)->databases() as $database)
{
    foreach ($database->skeletons() as $skeleton)
    {
        // Columns
        $columns = [];
        foreach ($skeleton->elements() as $data)
        {
            preg_match('/([\w]+)\(?([\d]*)\)?\s?([\w\s]*)/', $column['Type'], $type);
        }
    }
}


function __dataToColumn($data)
{
    $column = $data;
    $column_key = $data['Field'];
    $data_cl = new stdClass();
    //$data_cl = (isset($data_tb->columns->$column_key)) ? $data_tb->columns->$column_key : null;

    preg_match('/([\w]+)\(?([\d]*)\)?\s?([\w\s]*)/', $column['Type'], $type_info);

    //$attrs = [];
    //if ($data_cl AND isset($data_cl->properties))
    //    foreach ($data_cl->properties as $attr_name => $attr_value)
    //        $attrs[$attr_name] = $attr_value;

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
*/
