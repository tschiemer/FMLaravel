<?php namespace FMLaravel\Database;

use FMLaravel\Database\ContainerField\ContainerField;
use FMLaravel\Database\Model;
use FMLaravel\Database\Helpers;

use Illuminate\Database\Query\Builder;
//use Illuminate\Database\Eloquent\Builder;
use \stdClass;
use FileMaker;
use Exception;
use Illuminate\Support\Str;

class QueryBuilder extends Builder
{
    protected $operators = [
        '=', '==', '<', '>', '<=', '>=', '<>', '!',
        '~', '""', '*""', 'like'
    ];

    /**
     * @var Model
     */
    protected $model;

    /**
     * @var RecordExtractor
     */
    protected $recordExtractor;

    protected $find;

    public $skip;

    public $limit;

    public $sorts = [];

    public $compoundWhere = 1;

    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->recordExtractor = RecordExtractor::forModel($model);

        return $this;
    }

    public function setEagerLoad($eagerLoad = [])
    {
        $this->recordExtractor->setEagerLoad($eagerLoad);

        return $this;
    }

    //this should be the method to get the results
    public function get($columns = [])
    {
        if ($this->containsOr()) {
            $this->find = $this->connection->filemaker('read')->newCompoundFindCommand($this->model->getLayoutName());
            $find_type = 'compound';
        } else {
            $this->find = $this->connection->filemaker('read')->newFindCommand($this->model->getLayoutName());
            $find_type = 'basic';
        }

        $this->parseWheres($this->wheres, $this->find, $find_type);
        $this->addSortRules();
        $this->setRange();

        $result = $this->find->execute();

        /* check if error occurred.
         * This wonderful FileMaker API considers no found entries as an error with code 401 which is why we have
         * to make this ridiculous exception. Shame on them, really.
         */
        if (FileMaker::isError($result) && !in_array($result->getCode(), ['401'])) {
            throw FileMakerException::newFromError($result);
        }

        return $this->recordExtractor->processResult($result);
    }

    public function skip($skip)
    {
        $this->skip = $skip;

        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;

        return $this;
    }


    /** Where statement
     *
     * NOTE
     * This is pretty much a copy of the original/parent where method, but the default operator (=) was replaced
     * with the semantically identical operator (==)
     *
     * NOTE 2
     * There are effectively more where clauses that use the normal = as default operator. Ideally we should change
     * this everywhere. Well, it's a bit unfortunate that Builder does not have a property for the default operator.
     *
     * @todo simplify as far as possible (strip unsupported functionality)
     *
     * @param array|\Closure|string $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return $this|Builder|static
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        if (func_num_args() == 2) {
            list($value, $operator) = [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if (! in_array(strtolower($operator), $this->operators, true) &&
            ! in_array(strtolower($operator), $this->grammar->getOperators(), true)) {
            list($value, $operator) = [$operator, '=='];
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator != '==');
        }

        // Now that we are working with just a simple query we can put the elements
        // in our array and add the query binding to our array of bindings that
        // will be bound to each SQL statements when it is finally executed.
        $type = 'Basic';

        if (Str::contains($column, '->') && is_bool($value)) {
            $value = new Expression($value ? 'true' : 'false');
        }

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (! $value instanceof Expression) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    private function parseWheres($wheres, $find, $find_type)
    {
        if (!$wheres) {
            return;
        }

        foreach ($wheres as $where) {
            if ($find_type == 'compound') {
                $request = $this->connection->filemaker('read')->newFindRequest($this->model->getLayoutName());
                $this->parseWheres([$where], $request, 'basic');
                $find->add($this->compoundWhere, $request);
                $this->compoundWhere++;
            } elseif ($where['type'] == 'Nested') {
                    $this->parseWheres($where['query']->wheres, $find, $find_type);
            } else {
                if ($where['operator'] == 'like') {
                    $whereValue = $where['value'];
                } else {
                    $whereValue = $where['operator'] . Helpers::escape($where['value']);
                }
                $find->AddFindCriterion(
                    $where['column'],
                    $whereValue
                );
            }
        }
    }



    public function setRange()
    {
        $this->find->setRange($this->skip, $this->limit);

        return $this;
    }

    public function sortBy($fields, $order = 'asc')
    {
        if (!is_array($fields)) {
            $this->sorts[$fields] = $order;
        } else {
            foreach ($fields as $field) {
                $this->sorts[$field] = 'asc';
            }
        }

        return $this;
    }

    private function addSortRules()
    {
        $i = 1;
        foreach ($this->sorts as $field => $order) {
            $order = $order == 'desc' ? FILEMAKER_SORT_DESCEND : FILEMAKER_SORT_ASCEND;
            $this->find->addSortRule($field, $i, $order);
            $i++;
        }
    }

    /**
     * Check to see if the wheres array contains any "or" type wheres
     * @return boolean
     */
    private function containsOr()
    {
        if (!$this->wheres) {
            return false;
        }

        return in_array('or', array_pluck($this->wheres, 'boolean'));
    }


    public function delete($id = null)
    {
        if (! is_null($id)) {
            throw new FileMakerException("this delete mode is not supported!");
        }

        $command = $this->connection->filemaker('write')->newDeleteCommand(
            $this->model->getLayoutName(),
            $this->model->getFileMakerMetaData(Model::FILEMAKER_RECORD_ID)
        );
        $result = $command->execute();

        if (\FileMaker::isError($result)) {
            throw FileMakerException::newFromError($result);
        }

        return true;
    }

    public function update(array $values)
    {
        /**
         * separate container fields from other fields
         * Container fields that are set to an empty value will delete the current data
         */
        $cfValues = array_filter($values, function ($v) {
            return $v instanceof ContainerField;
        });
        $values = array_diff_key($values, $cfValues);

        // first update any non-ContainerFields
        if (!empty($values)) {
            $command = $this->connection->filemaker('write')->newEditCommand(
                $this->model->getLayoutName(),
                $this->model->getFileMakerMetaData(Model::FILEMAKER_RECORD_ID),
                $values
            );
            $result = $command->execute();

            if (\FileMaker::isError($result)) {
                throw FileMakerException::newFromError($result);
            }

            $record = reset($result->getRecords());

            // because setRawAttributes overwrites the whole array, we have to save the meta data before.
            $meta = (array)$this->model->getFileMakerMetaData();

            $this->model->setRawAttributes($this->recordExtractor->extractRecordFields($record));

            $meta[Model::FILEMAKER_MODIFICATION_ID] = $record->getModificationId();
            $this->model->setFileMakerMetaDataArray($meta);
        }

        // now also save container fields
        if (!empty($cfValues)) {
            $this->model->updateContainerFields($cfValues);
        }


        return true;
    }


    public function insert(array $values)
    {
        return !empty($this->insertGetId($values));
    }


    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        /**
         * separate container fields from other fields
         * Container fields that are set to an empty value will delete the current data
         */
        $cfValues = array_filter($values, function ($v) {
            return $v instanceof ContainerField;
        });
        $values = array_diff_key($values, $cfValues);

        // first update any non-ContainerFields (even if no attributes set!)
        $command = $this->connection->filemaker('write')->newAddCommand(
            $this->model->getLayoutName(),
            $values
        );
        $result = $command->execute();

        if (\FileMaker::isError($result)) {
            throw FileMakerException::newFromError($result);
        }

        $record = reset($result->getRecords());

        $this->model->setRawAttributes($this->recordExtractor->extractRecordFields($record));

        $meta = [
            Model::FILEMAKER_RECORD_ID            => $record->getRecordId(),
            Model::FILEMAKER_MODIFICATION_ID    => $record->getModificationId()
        ];
        $this->model->setFileMakerMetaDataArray($meta);

        // now also save container fields
        if (!empty($cfValues)) {
            $this->model->updateContainerFields($cfValues);
        }

        return $record->getField($this->model->getKeyName());
    }
}
