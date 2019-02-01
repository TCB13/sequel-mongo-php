<?php

namespace SequelMongo;

class Count
{
	public $name;
	public $alias;

	public function __construct(string $field, ?string $alias = null)
	{
		$this->name  = $field;
		$this->alias = $alias === null ? $field . "_size" : $alias;
	}

	public function asArray(): array
	{
		return ["\$count" => $this->alias];
	}

}
