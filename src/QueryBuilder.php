<?php

namespace SequelMongo;

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
	private $limit = 0;
	private $skip = 0;
	private $count = [];
	private $order = ["_id" => 1];
	private $filters = [];
	private $lookup = [];
	private $unwind = [];
	private $group = [];

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
	public $deserializeMongoIds = true;

	public function __construct(?Database $connection = null)
	{
		if (isset(self::$globalConnection))
			$this->connection = self::$globalConnection;
		if ($connection !== null)
			$this->connection = $connection;
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

		if (!isset($this->connection))
			throw new Exception("Collections can only be set with strings if you provide a MongoDB connection at the constructor.");

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
		$pipeline = [];

		if (!empty($this->lookup)) // Lookups should ALWAYS be the first ones
			$pipeline[] = $this->lookup;

		if (!empty($this->unwind))
			$pipeline[] = $this->unwind;

		if (empty(!$this->filters)) {
			$pipeline[] = [
				"\$match" => $this->getNormalizedFilters()
			];
		}

		$pipeline[] = ["\$sort" => $this->order];

		if ($this->skip > 0)
			$pipeline[] = ["\$skip" => $this->skip];
		if ($this->limit > 0)
			$pipeline[] = ["\$limit" => $this->limit];

		if (!empty($this->count))
			$pipeline[] = $this->count;

		if (!empty($this->fields))
			$pipeline[] = ["\$project" => $this->fields];

		if (!empty($this->group))
			$pipeline[] = $this->group;

		// @todo: check if the pipeline has enough information to run a query!

		$this->result = $this->collection->aggregate($pipeline);
		return $this;
	}

	public function select($fields): self
	{
		$fields = is_array($fields) && count(func_get_args()) === 1 && is_int(key($fields)) ? $fields : func_get_args();
		$fields = array_map(function ($field) {
			// Simple field
			if (is_string($field))
				return [$field => 1];
			// Create an alias for a field
			if (is_array($field))
				return [reset($field) => "\$" . key($field)];
			// ArrayLength Function or COUNT feature
			if ($field instanceof ArrayLength /*|| $field instanceof Count*/)
				return $field->asArray();
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

		if (count($this->fields))
			$this->fields = call_user_func_array("array_merge", $this->fields);

		// Exclude built in _id if not set in fields - MongoDB always returns this by default
		if (!in_array("_id", $fields))
			$this->fields["_id"] = 0;

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
		if (!$caseSensitive)
			$value = "(?i)" . $value;
		$this->buildWhere("\$and", $key, "regx", ".*" . $value . ".*");
		return $this;
	}

	public function whereStartsWith(string $key, $value, bool $caseSensitive = true): self
	{
		if (!$caseSensitive)
			$value = "(?i)" . $value;
		$this->buildWhere("\$and", $key, "regx", "^" . $value . ".*");
		return $this;
	}

	public function whereEndsWith(string $key, $value): self
	{
		$this->buildWhere("\$and", $key, "regx", ".*" . $value . "$");
		return $this;
	}

	public function orWhereContains(string $key, $value, bool $caseSensitive = true): self
	{
		if (!$caseSensitive)
			$value = "(?i)" . $value;
		$this->buildWhere("\$or", $key, "regx", ".*" . $value . ".*");
		return $this;
	}

	public function orWhereStartsWith(string $key, $value, bool $caseSensitive = true): self
	{
		if (!$caseSensitive)
			$value = "(?i)" . $value;
		$this->buildWhere("\$or", $key, "regx", "^" . $value . ".*");
		return $this;
	}

	public function orWhereEndsWith(string $key, $value): self
	{
		$this->buildWhere("\$or", $key, "regx", ".*" . $value . "$");
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
		if (!array_key_exists($operator, self::$mongoOperatorMap))
			throw new Exception("Invalid Operator.");

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
		if ($value instanceof \DateTime)
			$value = new UTCDateTime($value->format("Uv"));

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

	public function join($collection, $localField, $operatorOrForeignField, $foreignField = null): self
	{
		// Allow users to skip the JOIN operator
		if ($foreignField === null) {
			$foreignField = $operatorOrForeignField;
			$operator     = "=";
		}
		else {
			$operator = $operatorOrForeignField;
		}

		// Automatically add an alias to the collection if not set
		if (!\is_array($collection))
			$collection = [$collection => $collection . "#joined"];

		// Automatically add an alias to the foreign field if not set
		if (!\is_array($foreignField))
			$foreignField = [$foreignField => $foreignField . "SEQUELMONGOForeignFieldAlias"];

		$filter = [
			"\$and" => [
				self::$mongoOperatorMap[$operator] => [
					"\$" . $localField,
					"\$\$" . reset($foreignField)
				]
			]
		];

		$this->lookup = [
			"\$lookup" => [
				"from"     => key($collection),
				"let"      => [reset($foreignField) => "\$" . key($foreignField)],
				"pipeline" => [["\$match" => ["\$expr" => $filter]]],
				"as"       => reset($collection)
			],
		];

		$this->unwind = [
			"\$unwind" => ["path" => "\$" . reset($collection)]
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
		if (empty($result))
			return 0;
		return $result[0]["count"];
	}

	/*
	 * Record Manipulation
	 */
	public function insert($documents): InsertManyResult
	{
		$fel = reset($documents);
		if (!is_object($fel) && !\is_array($fel))
			$documents = [$documents];
		return $this->collection->insertMany(array_values($documents));
	}

	public function update($update): UpdateResult
	{
		if (empty($this->filters))
			throw new Exception("You must set a filter (where query) to update records.");

		$pipeline = [];

		$inc = [];
		$set = \array_filter($update, function($value) use (&$inc) {
			// Filter out Increments
			if ($value instanceof Increment) {
				$inc[] = $value->asArray();
				return false;
			}
			return true;
		});

		// Add set to the pipeline
		if (!empty($set))
			$pipeline["\$set"] = $set;

		// Add all increments to the pipline
		if (!empty($inc))
			$pipeline["\$inc"] = array_merge(...array_column($inc, "\$inc"));

		return $this->collection->updateMany($this->getNormalizedFilters(), $pipeline);
	}

	public function delete(): DeleteResult
	{
		if (empty($this->filters))
			throw new Exception("You must set a filter (where query) to delete records.");
		return $this->collection->deleteMany($this->getNormalizedFilters());
	}

	/*
	 * Result Output Methods
	 */
	protected function deserializeResult($array = "array", $document = "array", $root = "array")
	{
		$this->result->setTypeMap(compact("array", "document", "root"));
		$results = $this->result->toArray();

		if ($this->deserializeMongoIds && array_key_exists("_id", $this->fields) && $this->fields["_id"] === 1) {
			$results = \array_map(function ($result) {
				if (is_object($result))
					$result->_id = (string)$result->_id;
				else
					$result["_id"] = (string)$result["_id"];
				return $result;
			}, $results);
		}

		if ($this->expectedMultipleResults)
			return $results;

		return empty($results) ? null : $results[0];
	}

	public function toArray(bool $fullArray = false): ?array
	{
		return $this->deserializeResult("array", ($fullArray ? "array" : null), "array");
	}

	public function toObject($className = \stdClass::class)
	{
		return $this->deserializeResult($className, $className, $className);
	}

	public function toJson(): string
	{
		$result = $this->toArray();
		return json_encode($result);
	}

}
