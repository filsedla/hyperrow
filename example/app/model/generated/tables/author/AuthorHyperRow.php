<?php

/**
 * This is a generated file. You CAN EDIT it, it was generated only once. It will not be overwritten.
 */

namespace Example\Model\Generated;

class AuthorHyperRow extends AuthorBaseHyperRow
{


    /**
     * @return int
     */
    public function bookCount()
    {
        return $this->relatedBooksAsAuthor()->count();
    }

}
