<?php

/**
 * This is a generated file. DO NOT EDIT. It will be overwritten.
 */

namespace Example\Model\Database;

/**
 * @property-read int $id
 * @property-read string $name
 */
class EmptyGeneratedRow extends BaseRow
{

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->activeRow->id;
	}


	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->activeRow->name;
	}

}
