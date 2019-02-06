<?php

namespace SequelMongo;

class Min
{
	public $name;
	public $alias;

	public function __construct(string $property, ?string $alias = null)
	{
		$this->name  = $property;
		$this->alias = $alias === null ? $property . "_min" : $alias;
	}

	public function asArray(): array
	{
		return [
			"\$group" => [
				"_id"        => null,
				$this->alias => ["\$min" => "\$" . $this->name]
			]
		];
	}

}
