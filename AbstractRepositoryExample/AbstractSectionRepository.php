<?php

namespace Project\Abstracts\Repository;

abstract class AbstractSectionRepository extends AbstractRepository
{
    /**
     * @return void
     */
    protected function setQuery(): void
    {
        /** @noinspection PhpUndefinedMethodInspection  */
        $entity = $this->modelSectionClass::compileEntityByIblock($this->ibId);
        $this->query = $entity::query();
    }
}
