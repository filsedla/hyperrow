<?php
/**
 * Copyright (c) 2015 Filip Sedlacek <filsedla@gmail.com>
 */

namespace Filsedla\Hyperrow;

use Nette\Database\Table\Selection;
use Nette\Object;

/**
 *
 */
class HyperSelection extends Object implements \Iterator
{

    /** @var Selection */
    private $selection;

    /** @var HyperRowFactory */
    private $hyperRowFactory;


    /**
     * @param Selection $selection
     */
    public function setSelection(Selection $selection)
    {
        $this->selection = $selection;
    }


    /**
     * @param HyperRowFactory $hyperRowFactory
     */
    public function setHyperRowFactory(HyperRowFactory $hyperRowFactory)
    {
        $this->hyperRowFactory = $hyperRowFactory;
    }


    /**
     * @return HyperRow|FALSE
     */
    public function fetch()
    {
        $activeRow = $this->selection->fetch();
        if ($activeRow === FALSE) {
            return FALSE;
        }
        return $this->hyperRowFactory->create($activeRow, $this->selection->getName());
    }


    /**
     * Returns row specified by primary key.
     *
     * @param  mixed $key Primary key
     * @return HyperRow|FALSE
     */
    public function get($key)
    {
        $activeRow = $this->selection->get($key);
        if ($activeRow === FALSE) {
            return FALSE;
        }
        return $this->hyperRowFactory->create($activeRow, $this->selection->getName());
    }


    /**
     * Adds where condition, more calls appends with AND
     *
     * @param  string $condition condition possibly containing ?
     * @param  mixed $parameters ...
     * @return self
     */
    public function where($condition, $parameters = [])
    {
        call_user_func_array([$this->selection, 'where'], func_get_args());
        return $this;
    }


    /**
     * Counts number of rows
     *
     * @param  string $column if it is not provided returns count of result rows, otherwise runs new sql counting query
     * @return int
     */
    public function count($column = NULL)
    {
        return $this->selection->count($column);
    }


    /**
     * @return HyperRow|FALSE
     */
    public function current()
    {
        $activeRow = $this->selection->current();
        if ($activeRow === FALSE) {
            return FALSE;
        }
        return $this->hyperRowFactory->create($activeRow, $this->selection->getName());
    }


    /**
     * @return void
     */
    public function next()
    {
        $this->selection->next();
    }


    /**
     * @return string row ID
     */
    public function key()
    {
        return $this->selection->key();
    }


    /**
     * @return boolean
     */
    public function valid()
    {
        return $this->selection->valid();
    }


    /**
     * @return void
     */
    public function rewind()
    {
        $this->selection->rewind();
    }


} 