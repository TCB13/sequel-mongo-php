<?php

namespace SequelMongo;

class Max implements SpecialFunction
{
	public $name;
	public $alias;

	public function __construct(string $property, ?string $alias = null)
	{
		$this->name  = $property;
		$this->alias = $alias === null ? $property . "_max" : $alias;
	}

	public function asArray(): array
	{
		return [
			"\$group" => [
				"_id"        => null,
				$this->alias => ["\$max" => "\$" . $this->name]
			]
		];
	}

}
