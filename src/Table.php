<?php

namespace App\DatabaseImporter;

use Illuminate\Support\Facades\DB;

class Table
{
    /** @var  string $database */
    protected $database;

    /** @var  string $tableName */
    protected $tableName;

    /** @var  string $model */
    protected $model;

    /** @var  string $identifier */
    protected $identifier;

    /** @var  array $tableContent */
    protected $tableContent;

    /** @var  array $mappedAttributes */
    protected $mappedAttributes;

    /** @var array $foreignKeys */
    protected $foreignKeys = [];

    /**
     * Table constructor.
     *
     * @param string $database
     * @param        $tableName
     */
    public function __construct($database, $tableName)
    {
        $this->database = $database;
        $this->tableName = $tableName;
    }

    /**
     * Sets a <code>Model</code> that this table should be imported to.
     *
     * @param string $model Class name of a <code>Model</code>
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * Gets the <code>Model</code> that this table should be imported to.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Sets a field that can be used for pairing an entry with the database it will
     * be imported to.
     *
     * @param $identifier
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * Gets the identifier in the <code>Table</code> that can be used to connect
     * existing rows between the tables without using ID.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Gets the <code>Model</code>'s attribute name of the identifier.
     *
     * @return string
     */
    public function getMappedIdentifier()
    {
        return $this->mapAttribute($this->getIdentifier());
    }

    /**
     * Sets the mapped attributes to be used when importing from this table.
     *
     * @param array $mappedAttributes Key = Table field name, Value =
     *                                <code>Model</code> attribute name
     */
    public function setMappedAttributes(array $mappedAttributes)
    {
        $this->mappedAttributes = $mappedAttributes;
    }

    /**
     * Gets the mapped attribute for the specified <code>$field</code>.
     *
     * @param $field
     * @return string
     */
    public function mapAttribute($field)
    {
        return $this->mappedAttributes[$field] ?: $field;
    }

    /**
     * Gets a new array with mapped key names.
     * Does not retain id.
     *
     * @param array $attributes
     * @return array
     */
    public function getMappedAttributes(array $attributes)
    {
        $newAttributes = [];

        foreach($attributes as $key => $value)
        {
            if($key == 'id')
            {
                continue;
            }

            $newAttributes[$this->mapAttribute($key)] = $value;
        }

        return $newAttributes;
    }

    /**
     * Gets the table content.
     *
     * @return array
     */
    public function getTableContent()
    {
        if(! is_array($this->tableContent))
        {
            $this->loadTableContent();
        }

        return $this->tableContent;
    }

    /**
     * Adds a foreign key.
     *
     * @param string $key
     * @param string $model
     */
    public function addForeignKey($key, $model)
    {
        $this->foreignKeys[$key] = $model;
    }

    /**
     * Gets any foreign keys.
     *
     * @return array
     */
    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    /**
     * Loads the content of the table.
     */
    public function loadTableContent()
    {
        $this->tableContent = DB::connection($this->database)->table($this->tableName)->select('*')->get();
    }

}