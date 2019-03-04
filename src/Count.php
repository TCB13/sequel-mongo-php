<?php

namespace SequelMongo;

class Count implements SpecialFunction
{
	public $name;
	public $alias;

	public function __construct(string $property, ?string $alias = null)
	{
		$this->name  = $property;
		$this->alias = $alias === null ? $property . "_size" : $alias;
	}

	public function asArray(): array
	{
		return ["\$count" => $this->alias];
	}

}
