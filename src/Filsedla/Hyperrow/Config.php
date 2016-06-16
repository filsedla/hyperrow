<?php
/**
 * Copyright (c) 2016 Filip Sedlacek <filsedla@gmail.com>
 */

namespace Filsedla\Hyperrow;

use Nette\Object;
use Nette\Utils\Arrays;


class Config extends Object
{

    /** @var bool */
    protected $nestedTransactions;


    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->nestedTransactions = Arrays::get($config, 'nestedTransactions');
    }

    /**
     * @return boolean
     */
    public function isNestedTransactions()
    {
        return $this->nestedTransactions;
    }


}