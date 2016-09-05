<?php

namespace App\DatabaseImporter;

use App\DatabaseImporter\Exceptions\ForeignKeyFailureException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class DatabaseImporter
{
    /** @var array $tables */
    protected $tables = [];

    /** @var array $modelIdMappings */
    protected $modelIdMappings = [];

    /**
     * Adds a table to include in the import.
     *
     * @param \App\DatabaseImporter\Table $table
     */
    public function addTable(Table $table)
    {
        $this->tables[] = $table;
    }

    /**
     * Import the database into the current environment.
     */
    public function import()
    {
        foreach($this->tables as $table)
        {
            /** @var Table $table */

            if(! $table->getModel())
            {
                continue;
            }

            /** @var string $model */
            $model = $table->getModel();
            /** @var string $tableIdentifier */
            $tableIdentifier = $table->getIdentifier();
            /** @var string $modelIdentifier */
            $modelIdentifier = $table->getMappedIdentifier();

            foreach($table->getTableContent() as $row)
            {
                /** @var Model $storedModel */
                $storedModel = $tableIdentifier ? $model::first([
                    $modelIdentifier => $row[$tableIdentifier],
                ]) : NULL;

                $row = $this->updateForeignKeyValues($table, $row);

                /** @var array $mappedAttributes */
                $mappedAttributes = $table->getMappedAttributes($row);

                if($storedModel)
                {
                    $this->updateModel($storedModel, $mappedAttributes);

                    Log::info('Updated existing ' . get_class($table->getModel()) . ' with ID ' . $storedModel->id);
                } else
                {
                    $storedModel = $model::create($mappedAttributes);

                    Log::info('Created new ' . get_class($table->getModel()) . ' with ID ' . $storedModel->id);
                }

                $this->modelIdMappings[$table->getModel()][$row['id']] = $storedModel->id;

                $storedModel->save();
            }
        }
    }

    /**
     * Updates a <code>Model</code> using the provided attributes.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array                               $attributes
     */
    protected function updateModel(Model $model, array $attributes)
    {
        foreach($model->toArray() as $key => $value)
        {
            $this->checkAndUpdateAttribute($model, $attributes, $key);
        }
    }

    /**
     * Checks if a specific attribute of a <code>Model</code> needs to be updated,
     * and does so if necessary.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array                               $newAttributesArray
     * @param string                              $fieldName
     */
    protected function checkAndUpdateAttribute(
        Model $model, array $newAttributesArray, $fieldName)
    {
        if(key_exists($fieldName,
                $newAttributesArray) && ! empty($newAttributesArray[$fieldName]) && $model->$fieldName != $newAttributesArray[$fieldName]
        )
        {
            Log::notice('Updating ' . get_class($model) . 'field ' . $fieldName . '. Previous value: ' . $model->$fieldName . ', new value: ' . $newAttributesArray[$fieldName]);
            $model->$fieldName = $newAttributesArray[$fieldName];
        }
    }

    /**
     * Updates IDs in foreign keys to previously created <code>Model</code>s'.
     *
     * @param Table $table
     * @param array $row
     * @return array
     */
    protected function updateForeignKeyValues(Table $table, array $row)
    {
        foreach($table->getForeignKeys() as $foreignKey => $foreignModel)
        {
            if(! key_exists($foreignModel, $this->modelIdMappings))
            {
                throw new ForeignKeyFailureException('Model does not have mapped IDs: ' . $foreignModel);
            }

            if(! key_exists($row['id'], $this->modelIdMappings[$foreignModel]))
            {
                throw new ForeignKeyFailureException('Mapped ID does not exist for model ' . $foreignModel . ': ' . $foreignKey);
            }

            $newId = $this->modelIdMappings[$foreignModel][$row['id']];

            $existingForeignModel = $foreignModel::find($newId);

            if(! $existingForeignModel)
            {
                throw new ForeignKeyFailureException(
                    'Tried to create a ' . get_class($table->getModel())
                    . ' with a foreign key ' . get_class($foreignModel)
                    . ' ' . $row[$foreignKey] . ' ' . $foreignKey
                    . ', but it was not found');
            }

            $row[$foreignKey] = $newId;

            Log::debug('Changed foreign key ' . $foreignKey . ' value to ' . $newId);
        }

        return $row;
    }

}