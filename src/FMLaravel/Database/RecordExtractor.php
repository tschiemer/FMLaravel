<?php namespace FMLaravel\Database;

use FMLaravel\Database\FileMaker\Record;
use FMLaravel\Database\FileMaker\RecordInterface;
use FMLaravel\Database\Model;
use FileMaker;
use FileMaker_Result;

class RecordExtractor
{

    protected $metaKey;
    protected $relatedRecordsInfo;
    protected $eagerLoad = [];

    public function __construct($metaKey, $relatedRecordsInfo = [])
    {
        $this->metaKey = $metaKey;
        $this->relatedRecordsInfo = $relatedRecordsInfo;
    }

    /** Shortcut-Instantiator for defined model classes
     * @param $model
     * @return RecordExtractor
     * @throws \Exception
     */
    public static function forModel($model)
    {
        if (is_string($model)) {
            $model = new $model();
        }

        if (!($model instanceof Model)) {
            throw new \Exception("Model is not a FMLaravel\\Data\\Model class!");
        }

        return new RecordExtractor($model->getFileMakerMetaKey(), $model->getRelatedRecordsInfo());
    }

    /** Set list of related records to load.
     * @param array $eagerLoad
     * @return $this
     */
    public function setEagerLoad(array $eagerLoad = [])
    {
        $this->eagerLoad = $eagerLoad;
        return $this;
    }

    /** Processes FileMaker Result
     * @param FileMaker_Result|\FileMaker_Error $result Result as returned from filemaker command
     * @return array
     */
    public function processResult($result)
    {
        if (FileMaker::isError($result) || $result->getFetchCount() == 0) {
            return [];
        }

        return $this->processArray($result->getRecords());
    }

    /** Processes array of FileMaker records.
     * @param array $records
     * @return array
     */
    public function processArray(array $records)
    {
        return array_map(function (RecordInterface $record) {

            $row = $record->getAllFields();

            $meta = [
                Model::FILEMAKER_RECORD_ID          => $record->getRecordId(),
                Model::FILEMAKER_MODIFICATION_ID    => $record->getModificationId(),
                Model::FILEMAKER_RELATED_RECORDS    => array_combine(
                    $this->eagerLoad,
                    array_map(function ($relation) use ($record) {

                        $info = $this->relatedRecordsInfo[$relation];

                        $array = $record->getRelatedSet($info['table']);

                        $extractor = self::forModel($info['class']);

                        if (FileMaker::isError($array)) {
                            // If there is not yet any related record, the FileMaker API returns an error,
                            // which does not have a specific code set but only a message.
                            // Sadly this case is indistuingishable from a record not have the given relation at all
                            // The design decision here is that this error does not throw an exception, but just
                            // returns an empty set of related records.
                            if (strpos($array->getMessage(), 'Related set "'.$info['table'].'" not present.') == 0) {
                                return [];
                            }
                            throw FileMakerException::newFromError($array);
                        }

                        return $extractor->processArray($array);

                    }, $this->eagerLoad)
                )
            ];

            $row[$this->metaKey] = (object)$meta;

            return $row;
        }, $records);
    }

// This method become obsolete when the use of RecordInterface was introduced.
//    public function extractRecordFields($record)
//    {
//        $attributes = [];
//        foreach ($record->getFields() as $field) {
//            if ($field) {
//                $attributes[$field] = $record->getFieldUnencoded($field);
//            }
//        }
//        return $attributes;
//    }
}
