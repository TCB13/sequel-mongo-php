<?php

namespace SequelMongo;

use \MongoDB\Collection;
use \MongoDB\Database;
use \MongoDB\DeleteResult;
use \MongoDB\InsertManyResult;
use \MongoDB\UpdateResult;

class QueryBuilder
{

	/** @var \MongoDB\Database $con */
	private $connection;
	/** @var \MongoDB\Collection */
	private $collection;
	/** @var \MongoDB\Driver\Cursor */
	private $result;

	private $fields = [];
	private $limit = 0;
	private $skip = 0;
	private $order = [];
	private $filters = [];
	private $lookup = [];
	private $unwind = [];

	private $expectedMultipleResults = true;

	protected static $mongoOperatorMap = [
		"="     => "\$eq",
		"!="    => "\$ne",
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
	public $considerMongoIds = true;

	public function __construct(?Database $connection = null)
	{
		$this->connection = $connection;
		if ($this->considerMongoIds)
			$this->order = ["_id" => 1];
	}

	/**
	 * @return static
	 */
	public static function instance()
	{
		return new static();
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
			throw new Exception("Collections can only be set with strings you provide a MongoDB connection at the constructor.");

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

		if ($this->skip > 0)
			$pipeline[] = ["\$skip" => $this->skip];
		if ($this->limit > 0)
			$pipeline[] = ["\$limit" => $this->limit];

		if (!empty($this->order))
			$pipeline[] = ["\$sort" => $this->order];

		if (!empty($this->fields))
			$pipeline[] = ["\$project" => $this->fields];

		// @todo: check if the pipeline has enough information to run a query!

		$this->result = $this->collection->aggregate($pipeline);

		return $this;
	}

	public function select($fields): self
	{
		$fields       = is_array($fields) ? $fields : func_get_args();
		$this->fields = array_fill_keys(array_values($fields), 1);

		// Exclude built in _id if not set in fields - MongoDB always returns this by default
		if ($this->considerMongoIds && !in_array("_id", $fields))
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

	public function whereContains(string $key, $value): self
	{
		$this->buildWhere("\$and", $key, "regx", ".*" . $value . ".*");
		return $this;
	}

	public function whereStartsWith(string $key, $value): self
	{
		$this->buildWhere("\$and", $key, "regx", "^" . $value . ".*");
		return $this;
	}

	public function whereEndsWith(string $key, $value): self
	{
		$this->buildWhere("\$and", $key, "regx", ".*" . $value . "$");
		return $this;
	}

	public function orWhereContains(string $key, $value): self
	{
		$this->buildWhere("\$or", $key, "regx", ".*" . $value . ".*");
		return $this;
	}

	public function orWhereStartsWith(string $key, $value): self
	{
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
		if ($value === null) {
			$value    = $operator;
			$operator = "=";
		}

		// Convert SQL operators to Mongo
		if (!array_key_exists($operator, self::$mongoOperatorMap))
			throw new Exception("Invalid Operator.");

		$operator = self::$mongoOperatorMap[$operator];

		// If we're passing a nested/sub/() where query
		if (is_callable($key)) {
			$innerQb = new self;
			$key($innerQb);
			$key = RawFilter::fromCollectionOfRawFilters($innerQb->getNormalizedFilters());
		}

		// Allow users to pass RawFilter or the parameters
		if ($key instanceof RawFilter) {
			$this->filters[] = [
				"prefix" => $prefix,
				"filter" => $key->asArray()
			];
			return;
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
		$this->order = [$field => is_numeric($sort) ? (int)$sort : ($sort === "DESC" ? 1 : -1)];
		return $this;
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
		return $this->collection->updateMany($this->getNormalizedFilters(), ["\$set" => $update]);
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

		if ($this->considerMongoIds && $this->deserializeMongoIds && array_key_exists("_id", $this->fields) && $this->fields["_id"] === 1) {
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

	public function toArray(): ?array
	{
		return $this->deserializeResult();
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
