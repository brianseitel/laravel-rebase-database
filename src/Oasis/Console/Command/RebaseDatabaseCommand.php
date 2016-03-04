<?php

namespace Oasis\Console\Commands;

use DB;

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Filesystem\Filesystem;

class RebaseDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:rebase {database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebase the database';

    protected $database = null;
    protected $files = null;
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->files = new Filesystem($app['files']);
        $this->database = $this->argument('database');

        if (!$this->database) {
            throw new Exception('You need to specify a database.');
        }

        $tables_list = collect(DB::connection($this->database)->select(DB::raw('SHOW TABLES')))
        ->pluck('Tables_in_' . $this->database)
        ->toArray();

        $migration_name = 'RebaseDatabase';
        $stub           = $this->getCreateMigrationStub($migration_name);

        $tables = [];
        $drops  = [];
        foreach ($tables_list as $table) {
            if ($table == 'migrations') {
                continue;
            }
        
            $schema      = $this->getTableSchema($table);
            $create_stub = $this->getCreateTableStub($table);

            $columns = [];
            $keys    = [];
            foreach ($schema as $s) {
                $columns[] = $this->parseColumn($s);

                if ($key = $this->parseKey($s->Key, $s->Field)) {
                    $keys[] = $key;
                }
            }
            $items = array_merge($columns, $keys);
            $create_stub = str_replace('OtherColumnsGoHere', implode("\n        ", $items), $create_stub);

            $tables[] = $create_stub;

            $drops[] = "       Schema::connection('DummyDatabase')->drop('{$table}');";
        }

        $stub = str_replace('CreateTableStubGoesHere', implode("\n", $tables), $stub);
        $stub = str_replace('DropTableCommandsGoHere', implode("\n", $drops), $stub);
        $stub = str_replace('DummyDatabase', $this->database, $stub);
        $this->writeMigration($stub, $migration_name);

        echo "Done!\n";
    }

    private function parseColumn($schema)
    {
        $column = '';

        $type_info = $this->parseColumnType($schema);
        $type      = $type_info['type'];
        $size      = $type_info['size'];
        $unsigned  = $type_info['unsigned'];

        if ($schema->Extra == 'auto_increment') {
            $column = '$table->increments("{{name}}");';
        } else {
            switch ($type) {
                case 'bigIncrements':
                    $column = '$table->bigIncrements("{{name}}");';
                    break;
                case 'bigint':
                    $column = '$table->bigInteger("{{name}}");';
                    break;
                case 'blob':
                    $column = '$table->binary("{{name}}");';
                    break;
                case 'boolean':
                    $column = '$table->boolean("{{name}}");';
                    break;
                case 'char':
                    $column = '$table->char("{{name}}", {{size}});';
                    break;
                case 'date':
                    $column = '$table->date("{{name}}");';
                    break;
                case 'datetime':
                    $column = '$table->dateTime("{{name}}");';
                    break;
                case 'decimal':
                    $column = '$table->decimal("{{name}}", {{size}});';
                    break;
                case 'double':
                    $column = '$table->double("{{name}}", {{size}});';
                    break;
                case 'float':
                    $column = '$table->float("{{name}}");';
                    break;
                case 'int':
                    $column = '$table->integer("{{name}}");';
                    break;
                case 'json':
                    $column = '$table->json("{{name}}");';
                    break;
                case 'jsonb':
                    $column = '$table->jsonb("{{name}}");';
                    break;
                case 'longtext':
                    $column = '$table->longText("{{name}}");';
                    break;
                case 'mediumint':
                    $column = '$table->mediumInteger("{{name}}");';
                    break;
                case 'mediumtext':
                    $column = '$table->mediumText("{{name}}");';
                    break;
                case 'smallint':
                    $column = '$table->smallInteger("{{name}}");';
                    break;
                case 'mediumint':
                    $column = '$table->mediumInteger("{{name}}");';
                    break;
                case 'varchar':
                    $column = '$table->string("{{name}}", {{size}});';
                    break;
                case 'text':
                    $column = '$table->text("{{name}}");';
                    break;
                case 'time':
                    $column = '$table->time("{{name}}");';
                    break;
                case 'tinyint':
                    $column = '$table->tinyInteger("{{name}}");';
                    break;
                case 'timestamp':
                    $column = '$table->timestamp("{{name}}");';
                    break;
                case 'enum':
                    $column = '$table->timestamp("{{name}}", {{choices}});';
                    break;
                default:
                    pp($type);
                    return null;
            }
        }

        if ($schema->Null == 'NO') {
            $column = substr($column, 0, -1).'->notNull();';
        } else {
            $column = substr($column, 0, -1).'->nullable();';
        }

        if ($schema->Default) {
            $column = substr($column, 0, -1)."->defaultsTo('{$schema->Default}');";
        }

        if ($unsigned) {
            $column = substr($column, 0, -1)."->unsigned();";
        }

        $column = str_replace('{{name}}', $schema->Field, $column);
        $column = str_replace('{{size}}', $size, $column);
        return $column;
    }

    private function parseColumnType($schema)
    {
        $type = fetch($schema, 'Type');
        preg_match('/^(\w+)(\(\d+\)?)/', $type, $matches);

        $size = null;
        if (count($matches)) {
            list($full, $type, $size) = $matches;
        } elseif (preg_match('/^(\w+)(\(\d+,\d+\))/', $type, $matches)) {
            list($full, $type, $size) = $matches;
        }
        $size = preg_replace('/\((.+)\)/', '$1', $size);

        $unsigned = false;
        if (preg_match('/(.+)\sunsigned/', $type, $matches)) {
            list($full, $type) = $matches;
            $unsigned = true;
        }

        return [
        'type' => $type,
        'size' => $size,
        'unsigned' => $unsigned
        ];
    }

    private function parseKey($key, $name)
    {
        $line = '';
        switch ($key) {
            case 'PRI':
                break;
            case 'UNI':
                $line = '$table->unique("{{name}}");';
                break;
            case 'MUL':
                $line = '$table->index("{{name}}");';
                break;
            default:
                break;
        }

        if ($line) {
            $line = str_replace('{{name}}', $name, $line);
            return $line;
        }
        return null;
    }

    private function getCreateMigrationStub($name)
    {
        $stub = $this->getStub('blank_migration.stub');
        return str_replace('DummyClass', $name, $stub);
    }

    private function getCreateTableStub($table)
    {
        $create_stub = $this->getStub('create_table.stub');
        $create_stub = str_replace('DummyTable', $table, $create_stub);

        return $create_stub;
    }

    private function getTableSchema($table)
    {
        return collect(DB::connection($this->database)->select('DESCRIBE '. $table))->toArray();
    }

    public function getStub($name)
    {
        $stub = $this->files->get(__DIR__.'/stubs/'.$name);
        return $stub;
    }

    public function writeMigration($stub, $migration_name)
    {
        $filename = date('Y_m_d_his').'_'.snake_case($migration_name).'_'.$this->database.'.php';
        $this->files->put(database_path().'/migrations/'.$filename, $stub);
    }
}
