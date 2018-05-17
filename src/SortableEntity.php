<?php

namespace FiiSoft\EntitySorter;

use FiiSoft\Tools\Entity\Entity;

interface SortableEntity extends Entity
{
    /**
     * @return int
     */
    public function sortNumber();
    
    /**
     * @param int $sort
     * @return void
     */
    public function changeSort($sort);
}