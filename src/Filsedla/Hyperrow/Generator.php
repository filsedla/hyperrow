<?php
/**
 * Copyright (c) 2015 Filip Sedláček <filsedla@gmail.com>
 */

namespace Filsedla\Hyperrow;

use Nette\Database\Context;
use Nette\Database\Helpers;
use Nette\Database\IStructure;
use Nette\Object;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

/**
 *
 */
final class Generator extends Object
{

    /** @var Context */
    private $context;

    /** @var string */
    private $dir;

    /** @var array */
    private $config;

    /** @var HyperRowFactory */
    private $hypeRowFactory;


    /**
     * @param array $config
     * @param $dir
     * @param Context $context
     * @param HyperRowFactory $hyperRowFactory
     */
    function __construct(array $config, $dir, Context $context, HyperRowFactory $hyperRowFactory)
    {
        $this->config = $config;
        $this->dir = $dir;
        $this->context = $context;
        $this->hypeRowFactory = $hyperRowFactory;
    }


    public function generate()
    {
        FileSystem::createDir($this->dir);

        $tables = [];
        foreach ($this->context->getStructure()->getTables() as $table) {
            if ($table['view'] === FALSE) {
                foreach ($this->context->getStructure()->getColumns($table['name']) as $column) {
                    $tables[$table['name']][$column['name']] = Helpers::detectType($column['nativetype']);
                }
            }
        }

        // Create base row classes
        $classes = [];
        foreach ($tables as $table => $columns) {

            $className = $table . '_BaseRowClass';
            $class = new ClassType($className);
            $class->setExtends('\Filsedla\Hyperrow\BaseHyperRow');

            foreach ($columns as $column => $type) {
                if ($type === IStructure::FIELD_DATETIME) {
                    $type = '\Nette\Utils\DateTime';
                }
                $class->addDocument("@property-read $type \$$column");
            }

            foreach ($this->context->getStructure()->getBelongsToReference($table) as $referencingColumn => $referencedTable) {
                $methodName = 'referenced' . Strings::firstUpper($referencedTable);
                $returnType = $this->hypeRowFactory->tableNameToClassName($referencedTable);
                if (!Strings::startsWith($returnType, '\\')) {
                    $returnType = '\\' . $returnType;
                }
                $class->addMethod($methodName)
                    ->addBody('return $this->ref(?, ?);', [$referencedTable, $referencingColumn])
                    ->addDocument("@return $returnType");
            }

            foreach ($this->context->getStructure()->getHasManyReference($table) as $relatedTable => $referencingColumns) {
                foreach ($referencingColumns as $referencingColumn) {
                    $methodName = 'related' . Strings::firstUpper($relatedTable) . 's'
                        . (count($referencingColumns) > 1 ? 'As' . Strings::firstUpper(Strings::replace($referencingColumn, '~_id$~')) : '');
                    $returnType = '\Filsedla\Hyperrow\BaseHyperSelection'; //$this->hyperRowFactory->tableNameToClassName($relatedTable);
//                    if (!Strings::startsWith($returnType, '\\')) {
//                        $returnType = '\\' . $returnType;
//                    }
                    $class->addMethod($methodName)
                        ->addBody('return $this->related(?, ?);', [$relatedTable, $referencingColumn])
                        ->addDocument("@return $returnType");
                }
            }

            $classes[] = $class;
        }
        $code = implode("\n\n", array_merge(['<?php', 'namespace Filsedla\Hyperrow;'], $classes));
        file_put_contents($this->dir . "/row_classes_base_generated.php", $code);

        // Create database class
        $className = 'SystemDatabase';
        $class = new ClassType($className);
        $class->setExtends('\Filsedla\Hyperrow\BaseDatabase');
        foreach ($tables as $table => $columns) {
            $methodName = 'table' . Strings::firstUpper($table);
            $returnType = '\Filsedla\Hyperrow\BaseHyperSelection';
            $class->addMethod($methodName)
                ->addBody('return $this->table(?);', [$table])
                ->addDocument("@return $returnType");
        }
        $code = implode("\n\n", array_merge(['<?php', 'namespace Filsedla\Hyperrow;'], [$class]));
        file_put_contents($this->dir . "/systemdatabase_generated.php", $code);
    }

} 