<?php namespace FMLaravel\Database;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class RecordRelation extends Relation
{

    protected $name;
    protected $foreignTable;
    protected $type;
    protected $fieldKeys;

    public function __construct($name, Model $parent, Model $related, $foreignTable, $type)
    {
        $this->name = $name;
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignTable = $foreignTable;
        $this->type = $type;
    }

    public function addConstraints()
    {
        // TODO: Implement addConstraints() method.
    }

    public function addEagerConstraints(array $models)
    {
        // TODO: Implement addEagerConstraints() method.
    }

    public function initRelation(array $models, $relation)
    {

        if (count($models)) {
            $model = array_first($models, function ($key, Model $model) use ($relation) {
                return (bool)count($model->getFileMakerMetaData(Model::FILEMAKER_RELATED_RECORDS)[$relation]);
            });

            if (!empty($model)) {
                $keys = array_keys($model->getFileMakerMetaData(Model::FILEMAKER_RELATED_RECORDS)[$relation][0]);

                $prefix = $this->parent->getRelatedRecordsInfo($relation)['table'] . '::';
                $this->fieldKeys = str_replace($prefix, '', $keys);
            }
        }
        return $models;
    }

    /**
     * Get the relationship for eager loading.
     *
     * FMLaravel: Override. No query is necessary, so for the flow of Builder pretend there is a result.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEager()
    {
        return Collection::make();
    }

    public function match(array $models, Collection $results, $relation)
    {
        $models = array_map(function (Model $model) use ($relation) {

            $records = $model->getFileMakerMetaData(Model::FILEMAKER_RELATED_RECORDS)[$relation];
            $records = array_map(function ($v) {
                    return array_combine($this->fieldKeys, $v);
            }, $records);

            $records = $this->related->hydrate($records, $this->parent->getConnection());
            $records->each(function (Model $model) {
                $model->setRelatedTable($this->foreignTable);
            });

            if ($this->type == 'many') {
                $model->setRelation($relation, $records);
            } else {
                $model->setRelation($relation, $records->first());
            }

            // unset related array entry (no need for duplicates)
            unset($model->getFileMakerMetaData()->{Model::FILEMAKER_RELATED_RECORDS}[$relation]);

            return $model;
        }, $models);

        return $models;
    }

    public function getResults()
    {
        // If the related records where not eagerly loaded, we just do another request to load a new copy of the
        // parent model with the related records loaded.
        // NOTE this means the model requires a primary key!
        $modelId = $this->parent->getAttributeValue($this->parent->getQualifiedKeyName());
        $modelWithRelation = $this->parent->with($this->name)->find($modelId);

        return $modelWithRelation->{$this->name};
    }
}
