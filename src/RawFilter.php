<?php

namespace SequelMongo;

class RawFilter
{
	public $operator = "\$and";
	public $conditions = [];

	public function __construct($filter = null)
	{
		if ($filter === null)
			return;

		// Extract the filter if we're proving another filter
		if ($filter instanceof self) {
			$this->conditions = $filter->getConditions();
			$this->operator   = $filter->getOperator();
			return;
		}

		$this->push($filter);
	}

	public static function fromCollectionOfRawFilters($filters): self
	{
		return (new self)->pushMany($filters);
	}

	public function push($filter): self
	{
		return $this->pushMany([$filter]);
	}

	public function pushMany($filters): self
	{

		$filters = array_map(function($filter) {
			return $filter instanceof self ? $filter->asArray() : $filter;
		}, $filters);

		$this->conditions = \array_merge($this->conditions, $filters);
		return $this;
	}

	public function asArray(): array
	{
		return [$this->operator => [$this->conditions]];
	}

	public function getOperator(): string
	{
		return $this->operator;
	}

	public function getConditions(): array
	{
		return $this->conditions;
	}

}
