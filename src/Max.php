<?php

namespace SequelMongo;

class Max
{
	public $name;
	public $alias;

	public function __construct(string $field, ?string $alias = null)
	{
		$this->name  = $field;
		$this->alias = $alias === null ? $field . "_max" : $alias;
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
