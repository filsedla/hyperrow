<?php
/**
 * Copyright (c) 2015 Filip Sedláček <filsedla@gmail.com>
 */

namespace Filsedla\CustomRowClass;

use Nette\Database\Table\Selection;
use Nette\Object;

/**
 *
 */
class SelectionWrapper extends Object implements \Iterator
{

    /** @var Selection */
    private $selection;

    /** @var ActiveRowWrapperFactory */
    private $activeRowWrapperFactory;


    function __construct(Selection $selection, ActiveRowWrapperFactory $activeRowWrapperFactory)
    {
        $this->selection = $selection;
        $this->activeRowWrapperFactory = $activeRowWrapperFactory;
    }


    /**
     * @return ActiveRowWrapper|FALSE
     */
    public function fetch()
    {
        $activeRow = $this->selection->fetch();
        if ($activeRow === FALSE) {
            return FALSE;
        }
        return $this->activeRowWrapperFactory->create($activeRow, $this->selection->getName());
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
     * @return ActiveRowWrapper|FALSE
     */
    public function current()
    {
        $activeRow = $this->selection->current();
        if ($activeRow === FALSE) {
            return FALSE;
        }
        return $this->activeRowWrapperFactory->create($activeRow, $this->selection->getName());
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