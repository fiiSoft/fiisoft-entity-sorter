<?php

namespace FiiSoft\EntitySorter;

use Closure;

final class EntitySorter
{
    /**
     * @param SortableEntity[] $entities collection of entities to sort
     * @param Closure $entitiesComparator function used to compare entities during sorting
     * @param Closure|null $canBeSorted predicated to decide if place of particular entity can be changed; null means TRUE always
     * @param int $defaultStart default start value of sort number
     * @param int $defaultIncrement default increment of sort number
     * @return array sorted entities - be aware this array can have less elements than input!
     */
    public function sortEntities(
        array $entities, 
        Closure $entitiesComparator,
        Closure $canBeSorted = null,
        $defaultStart = 1,
        $defaultIncrement = 1
    ){
        if (empty($entities)) {
            return [];
        }
        
        $this->assertIsParamValid($entities, $defaultIncrement, $defaultStart);
    
        if ($canBeSorted === null) {
            $canBeSorted = function ($entity) { return true; };
            $canNotBeSorted = function ($entity) { return false; };
        } elseif ($this->isThereAtLeastOneEntityThat($canBeSorted, $entities)) {
            $canNotBeSorted = function ($entity) use ($canBeSorted) { return !$canBeSorted($entity); };
        } else {
            return [];
        }
    
        if ($this->isThereAtLeastOneEntityThat($canNotBeSorted, $entities)) {
            
            $notToSort = $this->getEntitiesThat($canNotBeSorted, $entities);
            $this->orderEntitiesByTheirSortNumbers($notToSort);
        
            $sortNumbers = $this->getSortNumbersOfEntities($notToSort);
            
            $this->orderEntitiesByComparator($entities, $entitiesComparator);
    
            if ($this->isItPossibleToPutSortableEntitiesBetweenUnsortable($entities, $canBeSorted, $sortNumbers)) {
                $entities = $this->putSortableEntitiesBetweenUnsortable(
                    $entities, $canBeSorted, $sortNumbers, $defaultIncrement
                );
            } else {
                $entities = $this->getEntitiesThat($canBeSorted, $entities);
                $maxSortNumber = $this->getMaxSortNumberFrom($sortNumbers);
                $this->changeSortNumbersInEntities($entities, $maxSortNumber + $defaultIncrement, $defaultIncrement);
            }
        } else {
            $this->orderEntitiesByComparator($entities, $entitiesComparator);
            $this->changeSortNumbersInEntities($entities, $defaultStart, $defaultIncrement);
        }
        
        return $entities;
    }
    
    /**
     * @param SortableEntity[] $entities
     * @param Closure $canBeSorted
     * @param int[] $sortNumbers
     * @return bool
     */
    private function isItPossibleToPutSortableEntitiesBetweenUnsortable(
        array $entities,
        Closure $canBeSorted,
        array $sortNumbers
    ){
        $totalNotSortable = count($sortNumbers);
        $countSortable = $countNotSortable = 0;
        
        foreach ($entities as $entity) {
            if ($canBeSorted($entity)) {
                ++$countSortable;
            } else {
                if ($countSortable > 0) {
                    if ($countNotSortable === 0) {
                        $prevSort = 0;
                        $nextSort = $sortNumbers[0];
                    } else {
                        $prevSort = $sortNumbers[$countNotSortable - 1];
                        $nextSort = $this->findNextSortNumber($sortNumbers, $countNotSortable);
                    }
                    
                    if ($prevSort >= $nextSort) {
                        return false;
                    }
                    
                    $gap = $nextSort - $prevSort - 1;
                    
                    if ($countSortable > $gap) {
                        return false;
                    }
                    
                    $countSortable = 0;
                }
                
                if (++$countNotSortable === $totalNotSortable) {
                    break;
                }
            }
        }
        
        return true;
    }
    
    /**
     * @param SortableEntity[] $entities
     * @param Closure $canBeSorted
     * @param int[] $sortNumbers
     * @param int $defaultIncrement
     * @return SortableEntity[]
     */
    private function putSortableEntitiesBetweenUnsortable(
        array $entities,
        Closure $canBeSorted,
        array $sortNumbers,
        $defaultIncrement
    ){
        $totalNotSortable = count($sortNumbers);
        $countNotSortable = $lastSort = 0;
        $increaseSortNormal = false;
        $keys = [];
        
        foreach ($entities as $key => $entity) {
            if ($increaseSortNormal) {
                if ($canBeSorted($entity)) {
                    $lastSort += $defaultIncrement;
                    $entity->changeSort($lastSort);
                }
            } elseif ($canBeSorted($entity)) {
                $keys[] = $key;
            } else {
                if (!empty($keys)) {
                    if ($countNotSortable === 0) {
                        $prevSort = 0;
                        $nextSort = $sortNumbers[0];
                    } else {
                        $prevSort = $sortNumbers[$countNotSortable - 1];
                        $nextSort = $this->findNextSortNumber($sortNumbers, $countNotSortable);
                    }
                    
                    $increment = (float) ($nextSort - $prevSort) / (count($keys) + 1.0);
                    $sort = $prevSort;
                    
                    foreach ($keys as $k) {
                        $sort += $increment;
                        $entities[$k]->changeSort((int) round($sort, 0));
                    }
                    
                    $keys = [];
                }
    
                if (++$countNotSortable === $totalNotSortable) {
                    $increaseSortNormal = true;
                    $lastSort = $entity->sortNumber();
                }
            }
        }
        
        return $this->getEntitiesThat($canBeSorted, $entities);
    }
    
    /**
     * @param int[] $sortNumbers
     * @param int $num
     * @return int
     */
    private function findNextSortNumber(array $sortNumbers, $num)
    {
        $lastSort = $sortNumbers[$num - 1];
        
        for ($i = $num, $j = count($sortNumbers); $i < $j; ++$i) {
            $next = $sortNumbers[$i];
            if ($next !== $lastSort) {
                return $next;
            }
        }
        
        return $lastSort;
    }
    
    /**
     * @param Closure $predicate
     * @param array $entities
     * @return bool
     */
    public function isThereAtLeastOneEntityThat(Closure $predicate, array $entities)
    {
        foreach ($entities as $entity) {
            if ($predicate($entity)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * @param int[] $sortNumbers
     * @return int
     */
    private function getMaxSortNumberFrom(array $sortNumbers)
    {
        return (int) max($sortNumbers);
    }
    
    /**
     * @param Closure $predicate
     * @param SortableEntity[] $entities
     * @return SortableEntity[]
     */
    private function getEntitiesThat(Closure $predicate, array $entities)
    {
        $filtered = array_filter($entities, $predicate);
        
        if (empty($filtered)) {
            throw new \RuntimeException('Unexpected empty list of entities after filtering');
        }
        
        return $filtered;
    }
    
    /**
     * @param SortableEntity[] $entities
     * @return int[]
     */
    private function getSortNumbersOfEntities(array $entities)
    {
        return array_values(array_map(function (SortableEntity $entity) {
            return $entity->sortNumber();
        }, $entities));
    }
    
    /**
     * @param SortableEntity[] $entities REFERENCE
     * @param int $sortNumber starting sort number
     * @param int $increment
     * @return void
     */
    private function changeSortNumbersInEntities(array &$entities, $sortNumber, $increment)
    {
        foreach ($entities as $entity) {
            $entity->changeSort($sortNumber);
            $sortNumber += $increment;
        }
    }
    
    /**
     * @param SortableEntity[] $entities REFERENCE
     * @param Closure $entitiesComparator
     * @return void
     */
    private function orderEntitiesByComparator(array &$entities, Closure $entitiesComparator)
    {
        if (count($entities) > 1) {
            usort($entities, $entitiesComparator);
        }
    }
    
    /**
     * @param SortableEntity[] $entities REFERENCE
     * @return void
     */
    private function orderEntitiesByTheirSortNumbers(array &$entities)
    {
        if (count($entities) > 1) {
            usort($entities, function (SortableEntity $first, SortableEntity $second) {
                return ($first->sortNumber() - $second->sortNumber()) ?: $first->id()->compare($second->id());
            });
        }
    }

    /**
     * @param SortableEntity[] $entities
     * @param int $defaultIncrement
     * @param int $defaultStart
     * @throws \InvalidArgumentException when any argument is invalid
     * @return void
     */
    private function assertIsParamValid(array $entities, $defaultIncrement, $defaultStart)
    {
        if (!is_int($defaultIncrement) || $defaultIncrement < 1) {
            throw new \InvalidArgumentException('Invalid value of defaultIncrement passed to EntitySorter::sortEntities');
        }
        
        if (!is_int($defaultStart) || $defaultStart < 1) {
            throw new \InvalidArgumentException('Invalid value of defaultStart passed to EntitySorter::sortEntities');
        }
        
        foreach ($entities as $entity) {
            if (! $entity instanceof SortableEntity) {
                throw new \InvalidArgumentException(
                    'Invalid value of element in array passed to EntitySorter::sortEntities'
                );
            }
        }
    }
}