<?php
/**
 * Copyright (c) 2016 Filip Sedlacek <filsedla@gmail.com>
 */

namespace Filsedla\Hyperrow;

use Nette\Database\IStructure;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

/**
 *
 */
class Generator
{
    /** @var IStructure */
    protected $structure;

    /** @var array */
    protected $config;

    /** @var bool */
    protected $changed = FALSE;

    /** @var array Array of FQNs to exclude from generation - currently applies only to classes that are generated empty */
    protected $excludedClasses = [];


    /**
     * @param array $config
     * @param IStructure $structure
     */
    public function __construct(array $config, IStructure $structure)
    {
        $this->config = $config;
        $this->structure = $structure;
    }


    /**
     * @return array
     */
    protected function getTables()
    {
        $tables = [];
        foreach ($this->structure->getTables() as $table) {
            if ($table['view'] === FALSE) {
                foreach ($this->structure->getColumns($table['name']) as $column) {
                    $tables[$table['name']][$column['name']] = \Nette\Database\Helpers::detectType($column['nativetype']);
                }
            }
        }
        return $tables;
    }


    /**
     * @return boolean
     */
    public function isChanged()
    {
        return $this->changed;
    }


    /**
     * @return array
     */
    public function getExcludedClasses()
    {
        return $this->excludedClasses;
    }


    /**
     * @param array $excludedClasses
     */
    public function setExcludedClasses(array $excludedClasses)
    {
        $this->excludedClasses = $excludedClasses;
    }

    /**
     * @return void
     */
    protected function generateGeneratedDatabase()
    {
        $classFqn = $this->config['classes']['database']['generated'];
        $className = Helpers::extractClassName($classFqn);
        $classNamespace = Helpers::extractNamespace($classFqn);

        $class = new ClassType($className);
        $class->setExtends('\Filsedla\Hyperrow\Database');

        // Generate methods.database.table
        foreach ((array)$this->config['methods']['database']['table'] as $methodTemplate) {

            foreach ($this->getTables() as $tableName => $columns) {

                if (is_array($this->config['tables']) && !in_array($tableName, $this->config['tables'])) {
                    continue;
                }

                $methodName = Helpers::substituteMethodWildcard($methodTemplate, $tableName);
                $returnType = $this->getTableClass('selection', $tableName, $classNamespace);

                $class->addMethod($methodName)
                    ->addBody('return $this->table(?);', [$tableName])
                    ->addComment("@return $returnType");

                if (Strings::startsWith($methodName, 'get')) {

                    // Add property annotations
                    $property = Strings::firstLower(Strings::substring($methodName, 3));
                    $correspondingHyperSelectionTableClass = $this->getTableClass('selection', $tableName, $classNamespace);
                    $class->addComment("@property-read $correspondingHyperSelectionTableClass \$$property");
                }
            }
        }

        $code = implode("\n\n", [
            '<?php',
            "/**\n * This is a generated file. DO NOT EDIT. It will be overwritten.\n */",
            "namespace {$classNamespace};",
            $class
        ]);
        $file = $this->config['dir'] . '/' . $className . '.php';

        $this->writeIfChanged($file, $code);
    }

    /**
     * @param string $file
     * @param string $code
     */
    protected function writeIfChanged($file, $code)
    {
        $content = @file_get_contents($file); // @ file may not exist
        if (!$content || $content != $code) {
            FileSystem::createDir(dirname($file));
            FileSystem::write($file, $code, NULL);
            $this->changed = TRUE;
        }
    }

    /**
     * @return void
     */
    public function generate()
    {
        $this->generateGeneratedDatabase();
        $this->generateTables();
    }


    /**
     * @param string $namespace
     * @param string $className
     * @param string $extends
     * @param string $dir
     */
    protected function generateEmptyClass($namespace, $className, $extends, $dir)
    {
        $classFqn = $namespace . '\\' . $className;

        if (in_array($classFqn, $this->excludedClasses)) {
            return;
        }

        $file = $dir . '/' . $className . '.php';

        if (is_file($file)) {
            return;
        }

        $class = new ClassType($className);
        $class->setExtends($extends);

        $code = implode("\n\n", [
            '<?php',
            "/**\n * This is a generated file. You CAN EDIT it, it was generated only once. It will not be overwritten.\n */",
            "namespace {$namespace};",
            $class
        ]);

        $this->writeIfChanged($file, $code);
    }


    /**
     * For each table generate:
     *  - "Table"GeneratedHyperSelection (fully generated)
     *  - "Table"HyperSelection (generated once, empty)
     *  - "Table"GeneratedHyperRow (fully generated)
     *  - "Table"HyperRow (generated once, empty)
     *
     * @return void
     */
    protected function generateTables()
    {
        foreach ($this->getTables() as $tableName => $columns) {

            if (is_array($this->config['tables']) && !in_array($tableName, $this->config['tables'])) {
                continue;
            }

            $this->generateTableClass('selection', $tableName);
            $this->generateTableClass('row', $tableName);

            $this->generateTableGeneratedHyperSelection($tableName, $columns);
            $this->generateTableGeneratedHyperRow($tableName, $columns);
        }
    }


    /**
     * @param string $type selection|row
     * @param string $tableName
     * @param string $contextClassNamespace
     * @return string
     */
    protected function getTableClass($type, $tableName, $contextClassNamespace)
    {
        $classFqn = $this->config['classes'][$type]['mapping'];
        $classFqn = Helpers::substituteClassWildcard($classFqn, $tableName);

        return Helpers::formatClassName($classFqn, $contextClassNamespace);
    }


    /**
     * @param string $tableName
     * @param array $columns
     * @return void
     */
    protected function generateTableGeneratedHyperSelection($tableName, $columns)
    {
        $classFqn = $this->config['classes']['selection']['generated'];
        $classFqn = Helpers::substituteClassWildcard($classFqn, $tableName);

        $className = Helpers::extractClassName($classFqn);
        $classNamespace = Helpers::extractNamespace($classFqn);

        $extendsFqn = $this->config['classes']['selection']['base'];
        $extends = Helpers::formatClassName($extendsFqn, $classNamespace);

        $class = new ClassType($className);
        $class->setExtends($extends);

        // Add annotations for methods returning specific row class
        $correspondingHyperRowTableClass = $this->getTableClass('row', $tableName, $classNamespace);
        $correspondingHyperSelectionTableClass = $this->getTableClass('selection', $tableName, $classNamespace);
        $noRowReturnValue = strtoupper(var_export($this->config['noRowReturnValue'], TRUE));
        $methods = [
            'fetch()' => $correspondingHyperRowTableClass . '|' . $noRowReturnValue,
            'get($key)' => $correspondingHyperRowTableClass . '|' . $noRowReturnValue,
            'current()' => $correspondingHyperRowTableClass . '|' . $noRowReturnValue,
            'select($columns)' => $correspondingHyperSelectionTableClass,
            'where($condition, $parameters = [])' => $correspondingHyperSelectionTableClass,
            'whereOr(array $parameters)' => $correspondingHyperSelectionTableClass,
            'wherePrimary($key)' => $correspondingHyperSelectionTableClass,
            'group($columns)' => $correspondingHyperSelectionTableClass,
            'having($having)' => $correspondingHyperSelectionTableClass,
            'insert($data)' => $correspondingHyperRowTableClass,
            'fetchAll()' => $correspondingHyperRowTableClass . '[]',
            'order($columns)' => $correspondingHyperSelectionTableClass,
            'limit($limit, $offset = NULL)' => $correspondingHyperSelectionTableClass,
            'page($page, $itemsPerPage, & $numOfPages = NULL)' => $correspondingHyperSelectionTableClass,
            'offsetGet($key)' => $correspondingHyperRowTableClass,
        ];

        foreach ($methods as $methodName => $returnType) {
            $class->addComment("@method $returnType $methodName");
        }

        // Generate methods.selection.where
        foreach ((array)$this->config['methods']['selection']['where'] as $methodTemplate) {

            // Add where methods based on columns
            foreach ($columns as $column => $type) {

                // withFuture*, withPast*
                if (in_array($type, [IStructure::FIELD_DATETIME, IStructure::FIELD_UNIX_TIMESTAMP])) {

                    $methodName = Helpers::substituteMethodWildcard($methodTemplate, 'Future' . Strings::firstUpper($column));
                    $method = $class->addMethod($methodName);
                    $method->addBody("return \$this->where('$column > NOW()');");
                    $method->addComment("@return $correspondingHyperSelectionTableClass");

                    $methodName = Helpers::substituteMethodWildcard($methodTemplate, 'Past' . Strings::firstUpper($column));
                    $method = $class->addMethod($methodName);
                    $method->addBody("return \$this->where('$column < NOW()');");
                    $method->addComment("@return $correspondingHyperSelectionTableClass");
                }

                if (in_array($type, [IStructure::FIELD_DATETIME, IStructure::FIELD_TIME, IStructure::FIELD_DATE, IStructure::FIELD_UNIX_TIMESTAMP])) {
                    $type = '\Nette\Utils\DateTime';
                }

                $methodName = Helpers::substituteMethodWildcard($methodTemplate, $column);
                $method = $class->addMethod($methodName);

                if ($type == 'bool') {
                    $method->addParameter('value', 'TRUE');

                } else {
                    $method->addParameter('value');
                }

                $method->addBody('return $this->where(?, $value);', [$column]);
                $method->addComment("@param $type \$value");
                $method->addComment("@return $correspondingHyperSelectionTableClass");
            }
        }

        // generate methods.selection.whereRelated
        foreach ((array)$this->config['methods']['selection']['whereRelated'] as $methodTemplate) {

            // Generate methods
            foreach ($this->structure->getHasManyReference($tableName) as $relatedTable => $referencingColumns) {

                // Check excluded tables
                if (is_array($this->config['tables']) && !in_array($relatedTable, $this->config['tables'])) {
                    continue;
                }

                foreach ($referencingColumns as $referencingColumn) {

                    // Omit longest common prefix between $relatedTable and (this) $tableName
                    $result = Helpers::underscoreToCamelWithoutPrefix($relatedTable, $tableName);


                    // Discover suffix if any
                    if (count($referencingColumns) > 1) {
                        $suffix = 'As' . Helpers::underscoreToCamel(Strings::replace($referencingColumn, '~_id$~'));

                    } else {
                        $suffix = NULL;
                    }

                    $methodName = Helpers::substituteMethodWildcard($methodTemplate, $result, $suffix);

                    $returnType = $correspondingHyperSelectionTableClass;

                    $parameterName = Strings::firstLower(Helpers::underscoreToCamel($relatedTable)) . 'Id';
                    $method = $class->addMethod($methodName);
                    $method->addParameter($parameterName);
                    $method->addBody("return \$this->where(':$relatedTable($referencingColumn).id', $?);", [$parameterName])
                        ->addComment("@return $returnType");

                    // Add property annotations
                    if (Strings::startsWith($methodName, 'get')) {
                        $property = Strings::firstLower(Strings::substring($methodName, 3));
                        $class->addComment("@property-read $returnType \$$property");
                    }
                }
            }
        }

        // generate methods.selection.whereRelatedWith
        foreach ((array)$this->config['methods']['selection']['whereRelatedWith'] as $methodTemplate) {

            // Generate methods
            foreach ($this->structure->getHasManyReference($tableName) as $relatedTable => $referencingColumns) {

                // Check excluded tables
                if (is_array($this->config['tables']) && !in_array($relatedTable, $this->config['tables'])) {
                    continue;
                }

                foreach ($referencingColumns as $referencingColumn) {

//                    if ($tableName == 'tag') {
//                        dump($tableName); // author | tag (M:N)
//                        dump($relatedTable); // book | book_tagging (M:N)
//                        dump($referencingColumns); // [author_id, translator_id] | [tag_id]
//                        // related (on 1:N): relatedBooksAsAuthor, relatedBooksAsTranslator - result BookSelection
//                        // whereRelated (on 1:N): inBook(bookId), inBookAsAuthor(bookId), inBookAsTranslator(bookId)
//
//                        // whereRelated (on M:N): inTaggingWithBook(As...)(bookId)
//
//                        $furtherReferences = $this->structure->getBelongsToReference($relatedTable);
//                        unset($furtherReferences[$referencingColumn]);
//                        dump($furtherReferences);
//
//                        if (Strings::startsWith($relatedTable, $tableName)) {
//                            dump('TRUE');
//                        }
//                    }

                    foreach ($this->structure->getBelongsToReference($relatedTable) as $furtherReferencingColumn => $furtherReferencedTable) {

                        // Do not return where I came from
                        if ($furtherReferencingColumn == $referencingColumn) {
                            continue;
                        }

                        if (!Strings::endsWith($relatedTable, 'ing') && in_array($methodTemplate, ['*', 'get*'])) {
                            continue;
                        }

                        // Omit longest common prefix between $relatedTable and (this) $tableName
                        $relatedTableResult = Helpers::underscoreToCamelWithoutPrefix($relatedTable, $tableName);

                        $end = NULL;
                        if (Strings::endsWith($relatedTable, 'ing') && in_array($methodTemplate, ['*', 'get*'])) {

                            // TODO problem:
                            // couse_tagging, couse
                            // couse_tagging, course_tag
                            if (Strings::startsWith($relatedTable, $tableName)) {
                                // taggedByBook

                                $relatedTableResult = Strings::replace($relatedTableResult, '~ing$~', 'ed');

                                // Add 'With' suffix (not configurable in method template - 2 * needed for that (TODO))
                                $end = 'By' . Helpers::underscoreToCamelWithoutPrefix(Strings::replace($furtherReferencingColumn, '~_id$~'), $tableName);

                            } else {
                                // taggingBook

                                // Omit longest common prefix between $relatedTable and (this) $tableName
                                $relatedTableResult = Helpers::underscoreToCamelWithoutPrefix($relatedTable, $furtherReferencedTable);

                                $end = Helpers::underscoreToCamelWithoutPrefix(Strings::replace($furtherReferencingColumn, '~_id$~'), $tableName);
                            }

                        } else {

                            // Add 'With' suffix (not configurable in method template - 2 * needed for that (TODO))
                            $end = 'With' . Helpers::underscoreToCamelWithoutPrefix(Strings::replace($furtherReferencingColumn, '~_id$~'), $tableName);
                        }

//                        if ($tableName == 'tag') {
//                            dump($relatedTableResult);
//                        }

                        // Discover 'As' suffix if any
                        $suffix = NULL;
                        if (count($referencingColumns) > 1) {
                            $suffix = 'As' . Helpers::underscoreToCamel(Strings::replace($referencingColumn, '~_id$~'));
                        }

                        $methodName = Helpers::substituteMethodWildcard($methodTemplate, $relatedTableResult, $suffix || $end ? $suffix . $end : NULL);

                        $returnType = $correspondingHyperSelectionTableClass;

                        $parameterName = Strings::firstLower(Helpers::underscoreToCamelWithoutPrefix(Strings::replace($furtherReferencingColumn, '~_id$~'), $tableName))
                            . 'Id';

                        $method = $class->addMethod($methodName);
                        $method->addParameter($parameterName);
                        $method->addBody("return \$this->where(':$relatedTable($referencingColumn).$furtherReferencingColumn', $?);", [$parameterName])
                            ->addComment("@return $returnType");

                        // Add property annotations
                        if (Strings::startsWith($methodName, 'get')) {
                            $property = Strings::firstLower(Strings::substring($methodName, 3));
                            $class->addComment("@property-read $returnType \$$property");
                        }
                    }
                }
            }
        }

        $code = implode("\n\n", [
            '<?php',
            "/**\n * This is a generated file. DO NOT EDIT. It will be overwritten.\n */",
            "namespace {$classNamespace};",
            $class
        ]);

        $dir = $this->config['dir'] . '/' . 'tables' . '/' . $tableName;
        $file = $dir . '/' . $className . '.php';

        $this->writeIfChanged($file, $code);
    }


    /**
     * @param string $tableName
     * @param array $columns
     * @return void
     */
    protected function generateTableGeneratedHyperRow($tableName, $columns)
    {
        $classFqn = $this->config['classes']['row']['generated'];
        $classFqn = Helpers::substituteClassWildcard($classFqn, $tableName);

        $className = Helpers::extractClassName($classFqn);
        $classNamespace = Helpers::extractNamespace($classFqn);

        $extendsFqn = $this->config['classes']['row']['base'];
        $extends = Helpers::formatClassName($extendsFqn, $classNamespace);

        $class = new ClassType($className);
        $class->setExtends($extends);

        // Add property annotations based on columns
        foreach ($columns as $column => $type) {

            if (in_array($type, [IStructure::FIELD_DATETIME, IStructure::FIELD_TIME, IStructure::FIELD_DATE, IStructure::FIELD_UNIX_TIMESTAMP])) {
                $type = '\Nette\Utils\DateTime';
            }

            $class->addComment("@property-read $type \$$column");
        }

        // Generate methods.row.getter
        foreach ((array)$this->config['methods']['row']['getter'] as $methodTemplate) {

            // Generate column getters
            foreach ($columns as $column => $type) {

                if (in_array($type, [IStructure::FIELD_DATETIME, IStructure::FIELD_TIME, IStructure::FIELD_DATE, IStructure::FIELD_UNIX_TIMESTAMP])) {
                    $type = '\Nette\Utils\DateTime';
                }

                $methodName = Helpers::substituteMethodWildcard($methodTemplate, $column);

                $returnType = $type;

                $class->addMethod($methodName)
                    ->addBody('return $this->activeRow->?;', [$column])
                    ->addComment("@return $returnType");


                // Add property annotation
                if (Strings::startsWith($methodName, 'get')) {
                    $property = Strings::firstLower(Strings::substring($methodName, 3));
                    if ($property != $column) {
                        $class->addComment("@property-read $type \$$property");
                    }
                }
            }
        }

        // Generate methods.row.ref
        foreach ((array)$this->config['methods']['row']['ref'] as $methodTemplate) {

            // Generate 'ref' methods
            foreach ($this->structure->getBelongsToReference($tableName) as $referencingColumn => $referencedTable) {

                if (is_array($this->config['tables']) && !in_array($referencedTable, $this->config['tables'])) {
                    continue;
                }

                $result = Helpers::underscoreToCamelWithoutPrefix(Strings::replace($referencingColumn, '~_id$~'), $tableName);

                $methodName = Helpers::substituteMethodWildcard($methodTemplate, $result);

                $returnType = $this->getTableClass('row', $referencedTable, $classNamespace);

                $class->addMethod($methodName)
                    ->addBody('return $this->ref(?, ?);', [$referencedTable, $referencingColumn])
                    ->addComment("@return $returnType");

                // Add property annotations
                if (Strings::startsWith($methodName, 'get')) {
                    $property = Strings::firstLower(Strings::substring($methodName, 3));
                    $class->addComment("@property-read $returnType \$$property");
                }
            }
        }

        // Generate methods.row.related
        foreach ((array)$this->config['methods']['row']['related'] as $methodTemplate) {

            // Generate 'related' methods
            foreach ($this->structure->getHasManyReference($tableName) as $relatedTable => $referencingColumns) {

                if (is_array($this->config['tables']) && !in_array($relatedTable, $this->config['tables'])) {
                    continue;
                }

                foreach ($referencingColumns as $referencingColumn) {

                    // Omit longest common prefix between $relatedTable and (this) $tableName
                    $result = Helpers::underscoreToCamelWithoutPrefix($relatedTable, $tableName);

                    if (count($referencingColumns) > 1) {
                        $suffix = 'As' . Helpers::underscoreToCamel(Strings::replace($referencingColumn, '~_id$~'));

                    } else {
                        $suffix = NULL;
                    }

                    $methodName = Helpers::substituteMethodWildcard($methodTemplate, $result, $suffix);

                    $returnType = $this->getTableClass('selection', $relatedTable, $classNamespace);

                    $class->addMethod($methodName)
                        ->addBody('return $this->related(?, ?);', [$relatedTable, $referencingColumn])
                        ->addComment("@return $returnType");

                    // Add property annotations
                    if (Strings::startsWith($methodName, 'get')) {
                        $property = Strings::firstLower(Strings::substring($methodName, 3));
                        $class->addComment("@property-read $returnType \$$property");
                    }
                }
            }
        }

        $code = implode("\n\n", [
            '<?php',
            "/**\n * This is a generated file. DO NOT EDIT. It will be overwritten.\n */",
            "namespace {$classNamespace};",
            $class
        ]);

        $dir = $this->config['dir'] . '/' . 'tables' . '/' . $tableName;
        $file = $dir . '/' . $className . '.php';

        $this->writeIfChanged($file, $code);
    }


    /**
     * @param string $type selection|row
     * @param string $tableName
     */
    protected function generateTableClass($type, $tableName)
    {
        $classFqn = $this->config['classes'][$type]['mapping'];
        $classFqn = Helpers::substituteClassWildcard($classFqn, $tableName);

        $className = Helpers::extractClassName($classFqn);
        $classNamespace = Helpers::extractNamespace($classFqn);

        $extendsFqn = $this->config['classes'][$type]['generated'];
        $extendsFqn = Helpers::substituteClassWildcard($extendsFqn, $tableName);
        $extends = Helpers::formatClassName($extendsFqn, $classNamespace);

        $dir = $this->config['dir'] . '/' . 'tables' . '/' . $tableName;

        $this->generateEmptyClass($classNamespace, $className, $extends, $dir);
    }

}
