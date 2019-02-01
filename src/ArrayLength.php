<?php

namespace SequelMongo;

class ArrayLength
{
	public $name;
	public $alias;

	public function __construct(string $arrayField, ?string $alias = null)
	{
		$this->name  = $arrayField;
		$this->alias = $alias === null ? $arrayField . "_length" : $alias;
	}

	public function asArray(): array
	{
		return [$this->alias => ["\$size" => "\$" . $this->name]];
	}

}
