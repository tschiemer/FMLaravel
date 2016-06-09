<?php namespace FMLaravel\Database;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;

use Closure;

class Builder extends EloquentBuilder
{

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function with($relations)
    {
        parent::with($relations);

        $eagerLoad = array_keys($this->eagerLoad);
        $eagerLoad = array_intersect($eagerLoad, array_keys($this->model->getRelatedRecordsInfo()));

        $this->query->setEagerLoad($eagerLoad);

        return $this;
    }
}
