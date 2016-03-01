<?php
/**
 * Copyright (c) 2016 Filip Sedlacek <filsedla@gmail.com>
 */

namespace Filsedla\Hyperrow;

use Nette;

/**
 *
 */
final class Extension extends Nette\DI\CompilerExtension
{

    /** @var array Defaults loaded from NEON file */
    protected $defaults;


    public function loadConfiguration()
    {
        $this->defaults = Nette\Neon\Neon::decode(file_get_contents(__DIR__ . '/defaults.neon'))['hyperrow'];
        $config = $this->getConfig($this->defaults);
//        dump($config); exit;

        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('hyperSelectionFactory'))
            ->setClass(HyperSelectionFactory::class, [$config['classes']['selection']['*']]);

        $builder->addDefinition($this->prefix('hyperRowFactory'))
            ->setClass(HyperRowFactory::class, [$config['classes']['row']['*']]);

        $builder->addDefinition($this->prefix('generator'))
            ->setClass(Generator::class, [$config])
            ->setAutowired(FALSE);
    }
}