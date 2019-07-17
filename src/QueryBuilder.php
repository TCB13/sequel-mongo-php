<?php

namespace SequelMongo;

use MongoDB\BSON\Serializable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\DeleteResult;
use MongoDB\InsertManyResult;
use MongoDB\UpdateResult;

class QueryBuilder
{

    /** @var MongoDB\Database */
    private static $globalConnection;
    /** @var MongoDB\Database */
    private $connection;
    /** @var MongoDB\Collection */
    private $collection;
    /** @var MongoDB\Driver\Cursor */
    private $result;

    private $fields = [];
    private $addFields = [];
    private $limit = 0;
    private $skip = 0;
    private $count = [];
    private $order = ["_id" => 1];
    private $filters = [];
    private $lookup = [];
    private $unwind = [];
    private $group = [];
    private $indexBy = null;

    private $customPipeline = [];

    private $expectedMultipleResults = true;

    protected static $mongoOperatorMap = [
        "="     => "\$eq",
        "!="    => "\$ne",
        "<>"    => "\$ne",
        ">"     => "\$gt",
        ">="    => "\$gte",
        "<"     => "\$lt",
        "<="    => "\$lte",
        "in"    => "\$in",
        "notIn" => "\$nin",
        "regx"  => "\$regex"
    ];

    /*
     * QB Configuration
     */
    public static $refuseInsertsOfMixedClassTypes = true;

    public function __construct(?Database $connection = null)
    {
        if (isset(self::$globalConnection)) {
            $this->connection = self::$globalConnection;
        }
        if ($connection !== null) {
            $this->connection = $connection;
        }
    }

    public static function setGlobalConnection(Database $connection)
    {
        self::$globalConnection = $connection;
    }

    /**
     * @param $collection
     *
     * @return $this
     * @throws Exception
     */
    public function collection($collection)
    {

        if ($collection instanceof Collection) {
            $this->collection = $collection;
            return $this;
        }

        if (!isset($this->connection)) {
            throw new Exception("Collections can only be set with strings if you provide a MongoDB connection at the constructor.");
        }

        // if is_array() => collection alias on queries?

        $this->collection = $this->connection->selectCollection($collection);
        return $this;
    }

    public function find(): self
    {
        $this->expectedMultipleResults = false;
        $this->limit(1);
        $this->findAll();
        return $this;
    }

    public function findAll(): self
    {
        // Build the pipeline
        if (is_object($this->customPipeline) && !empty($this->customPipeline->pipeline) && $this->customPipeline->beforeQb) {
            $pipeline = $this->customPipeline->pipeline;
        } else {
            $pipeline = [];
        }

        if (!empty($this->lookup)) // Lookups should ALWAYS be the first ones
        {
            $pipeline[] = $this->lookup;
        }

        if (!empty($this->unwind)) {
            $pipeline[] = $this->unwind;
        }

        if (!empty($this->addFields)) {
            $pipeline[] = ["\$addFields" => $this->addFields];
        }

        if (empty(!$this->filters)) {
            $pipeline[] = [
                "\$match" => $this->getNormalizedFilters()
            ];
        }

        if (!is_object($this->customPipeline) || empty($this->customPipeline->pipeline)) {
            $pipeline[] = ["\$sort" => $this->order];
        }

        if ($this->skip > 0) {
            $pipeline[] = ["\$skip" => $this->skip];
        }
        if ($this->limit > 0) {
            $pipeline[] = ["\$limit" => $this->limit];
        }

        if (!empty($this->count)) {
            $pipeline[] = $this->count;
        }

        if (!empty($this->fields)) {
            $pipeline[] = ["\$project" => $this->fields];
        }

        if (!empty($this->group)) {
            $pipeline[] = $this->group;
        }

        if (is_object($this->customPipeline) && !empty($this->customPipeline->pipeline) && !$this->customPipeline->beforeQb) {
            $pipeline = array_merge($pipeline, $this->customPipeline->pipeline);
        }

        // @todo: check if the pipeline has enough information to run a query!
        if (!isset($this->collection)) {
            throw new Exception("You must set a collection!");
        }

        $this->result = $this->collection->aggregate($pipeline);
        return $this;
    }

    public function select($fields): self
    {
        $fields = is_array($fields) && count(func_get_args()) === 1 && is_int(key($fields)) ? $fields : func_get_args();
        $fields = array_map(function ($field) {
            // Simple field
            if (is_string($field)) {
                return [$field => 1];
            }
            // Create an alias for a field
            if (is_array($field)) {
                return [reset($field) => "\$" . key($field)];
            }
            // ArrayLength Function or COUNT feature
            if ($field instanceof ArrayLength /*|| $field instanceof Count*/) {
                $arrayLen          = $field->asArray();
                $this->addFields[] = $arrayLen;
                return [key($arrayLen) => 1];
            }
            return $field;
        }, $fields);

        $this->fields = array_filter($fields, function ($field) {
            // Max / Min
            if ($field instanceof Max || $field instanceof Min) {
                $this->group = $field->asArray();
                return false;
            }
            return true;
        });

        if (count($this->fields)) {
            $this->fields = call_user_func_array("array_merge", $this->fields);
        }

        if (count($this->addFields)) {
            $this->addFields = call_user_func_array("array_merge", $this->addFields);
        }

        // Exclude built in _id if not set in fields - MongoDB always returns this by default
        if (!array_key_exists("_id", $this->fields)) {
            $this->fields["_id"] = 0;
        }

        return $this;
    }

    public function where($key, $operatorOrValue = null, $value = null): self
    {
        $this->buildWhere("\$and", $key, $operatorOrValue, $value);
        return $this;
    }

    public function orWhere($key, $operatorOrValue = null, $value = null): self
    {
        $this->buildWhere("\$or", $key, $operatorOrValue, $value);
        return $this;
    }

    public function whereIn(string $key, array $values): self
    {
        $this->buildWhere("\$and", $key, "in", $values);
        return $this;
    }

    public function whereNotIn(string $key, array $values): self
    {
        $this->buildWhere("\$and", $key, "notIn", $values);
        return $this;
    }

    public function orWhereIn(string $key, array $values): self
    {
        $this->buildWhere("\$or", $key, "in", $values);
        return $this;
    }

    public function orWhereNotIn(string $key, array $values): self
    {
        $this->buildWhere("\$or", $key, "notIn", $values);
        return $this;
    }

    public function whereRegex(string $key, string $expression): self
    {
        $this->buildWhere("\$and", $key, "regx", $expression);
        return $this;
    }

    public function whereContains(string $key, $value, bool $caseSensitive = true): self
    {
        $value = preg_quote($value);
        if (!$caseSensitive) {
            $value = "(?i)" . $value;
        }
        $this->buildWhere("\$and", $key, "regx", ".*" . $value . ".*");
        return $this;
    }

    public function whereStartsWith(string $key, $value, bool $caseSensitive = true): self
    {
        $value = preg_quote($value);
        if (!$caseSensitive) {
            $value = "(?i)" . $value;
        }
        $this->buildWhere("\$and", $key, "regx", "^" . $value . ".*");
        return $this;
    }

    public function whereEndsWith(string $key, $value): self
    {
        $this->buildWhere("\$and", $key, "regx", ".*" . preg_quote($value) . "$");
        return $this;
    }

    public function orWhereContains(string $key, $value, bool $caseSensitive = true): self
    {
        $value = preg_quote($value);
        if (!$caseSensitive) {
            $value = "(?i)" . $value;
        }
        $this->buildWhere("\$or", $key, "regx", ".*" . $value . ".*");
        return $this;
    }

    public function orWhereStartsWith(string $key, $value, bool $caseSensitive = true): self
    {
        $value = preg_quote($value);
        if (!$caseSensitive) {
            $value = "(?i)" . $value;
        }
        $this->buildWhere("\$or", $key, "regx", "^" . $value . ".*");
        return $this;
    }

    public function orWhereEndsWith(string $key, $value): self
    {
        $this->buildWhere("\$or", $key, "regx", ".*" . preg_quote($value) . "$");
        return $this;
    }

    private function buildWhere(string $prefix, $key, $operator = null, $value = null): void
    {
        // Assume that the operator is =
        if ($value === null && !in_array((string)$operator, array_keys(self::$mongoOperatorMap))) {
            $value    = $operator;
            $operator = "=";
        }

        // Convert SQL operators to Mongo
        if (!array_key_exists($operator, self::$mongoOperatorMap)) {
            throw new Exception("Invalid Operator.");
        }

        $operator = self::$mongoOperatorMap[$operator];

        // If we're passing a nested/sub/() where query
        //if (is_callable($key)) {
        if ($key instanceof \Closure) {
            $innerQb = new self;
            $key($innerQb);
            $key = RawFilter::fromCollectionOfRawFilters($innerQb->getNormalizedFilters());
        }

        // Allow users to pass RawFilter or the parameters
        if ($key instanceof RawFilter || $key instanceof ArrayContains) {
            $this->filters[] = [
                "prefix" => $prefix,
                "filter" => $key->asArray()
            ];
            return;
        }

        // Convert PHP Date Object to MongoFormat
        if ($value instanceof \DateTime) {
            $value = new UTCDateTime($value->format("Uv"));
        }

        // User called ->where with SQL parameters
        $this->filters[] = [
            "prefix" => $prefix,
            "filter" => [
                "\$and" => [[$key => [$operator => $value]]]
            ]
        ];

    }

    public function getNormalizedFilters(): array
    {
        $prefixes      = array_column($this->filters, "prefix");
        $this->filters = [
            count(array_unique($prefixes)) === 1 ? reset($this->filters)["prefix"] : "\$or" => array_column($this->filters, "filter")
        ];
        return $this->filters;
    }

    public function pipeline(array $pipeline, bool $beforeQueryBuilderPipeline = false): self
    {
        $this->customPipeline           = new \stdClass();
        $this->customPipeline->beforeQb = $beforeQueryBuilderPipeline;
        $this->customPipeline->pipeline = $pipeline;

        return $this;
    }

    public function join($collection, $localField, $operatorOrForeignField, $foreignField = null): self
    {
        // Allow users to skip the JOIN operator
        if ($foreignField === null) {
            $foreignField = $operatorOrForeignField;
            $operator     = "=";
        } else {
            $operator = $operatorOrForeignField;
        }

        // Automatically add an alias to the collection if not set
        if (!\is_array($collection)) {
            $collection = [$collection => $collection . "#joined"];
        }

        // Automatically add an alias to the foreign field if not set
        if (!\is_array($foreignField)) {
            $foreignField = [$foreignField => $foreignField . "SEQUELMONGOFieldAlias"];
        }

        $filter = [
            "\$and" => [
                self::$mongoOperatorMap[$operator] => [
                    "\$" . key($foreignField),
                    "\$\$" . reset($foreignField)
                ]
            ]
        ];

        $this->lookup = [
            "\$lookup" => [
                "from"     => key($collection),
                "let"      => [reset($foreignField) => "\$" . $localField],
                "pipeline" => [["\$match" => ["\$expr" => $filter]]],
                "as"       => reset($collection)
            ],
        ];

        $this->unwind = [
            "\$unwind" => ["path" => "\$" . reset($collection)]
        ];

        return $this;
    }

    public function unwind(string $path, ?string $includeArrayIndex = null, bool $preserveNullAndEmptyArrays = false): self
    {
        $unwind = [
            "path"                       => "\$" . $path,
            "preserveNullAndEmptyArrays" => $preserveNullAndEmptyArrays,
        ];

        if ($includeArrayIndex !== null) {
            $unwind["includeArrayIndex"] = $includeArrayIndex;
        }

        $this->unwind = [
            "\$unwind" => $unwind
        ];

        return $this;
    }

    /*
     * Sorting Methods
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->skip = $offset;
        return $this;
    }

    public function order(string $field, $sort = "DESC"): self
    {
        $this->order = [$field => is_numeric($sort) ? (int)$sort : ($sort === "DESC" ? -1 : 1)];
        return $this;
    }

    public function count(): int
    {
        $this->count = ["\$count" => "count"];
        $result      = $this->findAll()->toArray();
        if (empty($result) || (isset($result[0]) && empty($result[0]))) {
            return 0;
        }

        return $result[0]["count"];
    }

    /*
     * Record Manipulation
     */
    public function insert($documents): InsertManyResult
    {

        if (is_array($documents) && empty($documents)) {
            throw new Exception("Insert: Expecting at least one document to insert.");
        }

        // Single Mongo Seriazable object
        if ($documents instanceof Serializable) {
            return $this->collection->insertMany([$documents]);
        }

        // Multiple Mongo Seriazable object
        if (is_array($documents) && $documents[0] instanceof Serializable) {
            if (self::$refuseInsertsOfMixedClassTypes) {
                $proprosedClass = get_class($documents[0]);
                foreach ($documents as $document) {
                    if (!$document instanceof $proprosedClass) {
                        throw new Exception("Insert: All documents must be instances of the same class.");
                    }
                }
            }
            return $this->collection->insertMany($documents);
        }

        // Single document (as array) or multiple documents (as arrays of arrays)
        $fel = reset($documents);
        if (!is_object($fel) && !is_array($fel)) {
            $documents = [$documents];
        }
        return $this->collection->insertMany(array_values($documents));
    }

    public function update($update): UpdateResult
    {
        if (empty($this->filters)) {
            throw new Exception("You must set a filter (where query) to update records.");
        }

        // Build the pipeline
        if (is_object($this->customPipeline) && !empty($this->customPipeline->pipeline) && $this->customPipeline->beforeQb) {
            $pipeline = $this->customPipeline->pipeline;
        } else {
            $pipeline = [];
        }

        if (is_array($update)) {
            $inc    = [];
            $pushes = [];
            $pulls  = [];
            $set    = \array_filter($update, function ($value) use (&$inc, &$pushes, &$pulls) {
                // Filter out Increments
                if ($value instanceof Increment) {
                    $inc[] = $value->asArray();
                    return false;
                }
                // Filter out ArrayPush
                if ($value instanceof ArrayPush) {
                    $pushes[] = $value->asArray();
                    return false;
                }
                // Filter out ArrayPull
                if ($value instanceof ArrayPull) {
                    $pulls[] = $value->asArray();
                    return false;
                }
                return true;
            });

            // Add set to the pipeline
            if (!empty($set)) {
                $pipeline["\$set"] = $set;
            }

            // Add all increments to the pipline
            if (!empty($inc)) {
                $pipeline["\$inc"] = array_merge(...array_column($inc, "\$inc"));
            }

            // Add all ArrayPushes to the pipline
            if (!empty($pushes)) {
                $pipeline["\$push"] = array_merge(...array_column($pushes, "\$push"));
            }

            // Add all ArrayPulls to the pipline
            if (!empty($pulls)) {
                $pipeline["\$pull"] = array_merge(...array_column($pulls, "\$pull"));
            }

        } else {
            if (is_object($update) && $update instanceof Serializable) {
                $pipeline["\$set"] = $update;
            } else {
                throw new Exception("Collection Update: You must provide an array of fields or an instance of a class that implements 'MongoDB\BSON\Serializable'");
            }
        }

        if (is_object($this->customPipeline) && !empty($this->customPipeline->pipeline) && !$this->customPipeline->beforeQb) {
            $pipeline = array_merge($pipeline, $this->customPipeline->pipeline);
        }

        // Run the update query
        return $this->collection->updateMany($this->getNormalizedFilters(), $pipeline);
    }

    public function delete(): DeleteResult
    {
        if (empty($this->filters)) {
            throw new Exception("You must set a filter (where query) to delete records.");
        }
        return $this->collection->deleteMany($this->getNormalizedFilters());
    }

    /*
     * Result Output Methods
     */
    public function deserializeResult($array = "array", $document = "array", $root = "array")
    {
        $this->result->setTypeMap(compact("array", "document", "root"));
        $results = $this->result->toArray();
        if ($this->expectedMultipleResults) {
            if ($this->indexBy !== null) {
                $results = array_combine(array_column($results, $this->indexBy), $results);
            }
            return $results;
        }

        return empty($results) ? null : $results[0];
    }

    public function toArray(bool $fullArray = false): ?array
    {
        return $this->deserializeResult("array", ($fullArray ? "array" : null), "array");
    }

    public function toObject($className = \stdClass::class, bool $fullObject = false)
    {
        // return $this->deserializeResult(($fullObject ? $className : "array"), $className, $className);
        return $this->deserializeResult($className, $className, $className);
    }

    public function toUnidimensionalArray(string $key, bool $unique = false)
    {
        $arr = array_column($this->toArray(), $key);

        return $unique ? array_unique($arr) : $arr;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function indexBy(string $property)
    {
        if (!$this->expectedMultipleResults) {
            throw new Exception("You can only use indexBy with findAll.");
        }

        if (!empty($this->fields) && !array_key_exists($property, $this->fields)) {
            throw new Exception("Property '{$property}' not selected.");
        }

        $this->indexBy = $property;
        return $this;
    }

    /**
     * Takes a variable number of QueryBuilder instances and merges them into one.
     *
     * @param \SequelMongo\QueryBuilder ...$queryBuilderInstances
     *
     * @return \SequelMongo\QueryBuilder
     */
    public static function merge(QueryBuilder ...$queryBuilderInstances): self
    {
        $merge = [
            "fields",
            "filters",
            "lookup",
            "unwind"
        ];

        $state = array_shift($queryBuilderInstances)->getState();
        foreach ($queryBuilderInstances as $qb) {
            $newState = $qb->getState();
            foreach ($newState as $key => $value) {
                if (!isset($state[$key])) {
                    $state[$key] = $value;
                } else {
                    // Properties that should be merged : Properties that should be assigned / replace existing values
                    $state[$key] = in_array($key, $merge) ? array_merge($state[$key], $newState[$key]) : $newState[$key];
                }
            }
        }

        return self::__set_state($state);
    }

    public function getState(): array
    {
        $reflectionClass = new \ReflectionClass(self::class);
        $defaults        = $reflectionClass->getDefaultProperties();

        $excludedProperties = [
            "globalConnection",
            "mongoOperatorMap",
            "expectedMultipleResults"
        ];

        $state    = [];
        $defaults = array_diff_key($defaults, array_flip($excludedProperties));
        foreach ($defaults as $key => $value) {
            if ($this->$key !== $value) {
                $state[$key] = $this->$key;
            }
        }

        return $state;
    }

    public static function __set_state($array)
    {
        $qb = new self;
        foreach ($array as $key => $value) {
            $qb->$key = $value;
        }

        return $qb;
    }

}
