<?php

namespace SequelMongo;

class ArrayLength
{
	public $name;
	public $alias;

	public function __construct(string $arrayProperty, ?string $alias = null)
	{
		$this->name  = $arrayProperty;
		$this->alias = $alias === null ? $arrayProperty . "_length" : $alias;
	}

	public function asArray(): array
	{
		return [$this->alias => ["\$size" => "\$" . $this->name]];
	}

}
