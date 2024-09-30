<?php

namespace Project\Abstracts\Repository;

abstract class AbstractElementRepository extends AbstractRepository
{
    private int $id = 0;
    private array $data = [];

    /**
     * @param array $data
     * @return void
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @param int $id
     */
    protected function getObjectById(int $id)
    {
        return ($this->getClassName())::getByPrimary($id)->fetchObject();
    }

    /**
     * @return void
     */
    protected function setQuery(): void
    {
        $this->query = $this->getCurEntityQuery();
    }

    /**
     * @return int
     */
    protected function add(): int
    {
        $object = $this->getClassName()::createObject();

        return $this->setObject($this->data, $object);
    }

    /**
     * @param int $id
     * @param array $data
     * @return int
     */
    protected function update(): int
    {
        $object = $this->getObjectById($this->id);

        return $this->setObject($this->data, $object);
    }

    /**
     * @param int $id
     */
    protected function delete(): int
    {
        $object = $this->getObjectById($this->id);

        return  $object->delete();
    }

    /**
     * @param array $data
     * @param $object
     * @return int
     */
    private function setObject(array $data, $object): int
    {
        $updateValues = [];
        foreach ($data as $field => $item) {
            if (empty($item)) {
                continue;
            }

            if (count($item) == 1) {
                $object->set($field, $item);
            } else {
                //массив множественных свойств
                $updateValues[$field] = $item;
            }
        }

        $object->save();
        $id = $object->getId();

        if (!empty($updateValues)) {
            $multipleValuesObject = $this->getObjectById($id);
            foreach ($updateValues as $field => $item) {
                $multipleValuesObject->removeAll($field);
                foreach ($item as $curValue) {
                    $multipleValuesObject->addTo($field, $curValue);
                }
            }

            $multipleValuesObject->save();
        }

        return $id;
    }
}
