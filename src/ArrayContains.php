<?php

namespace SequelMongo;

class ArrayContains implements SpecialFunction
{
	public $name;
	public $needle;

	public function __construct(string $arrayProperty, $needles)
	{
		$this->name   = $arrayProperty;
		$this->needle = is_array($needles) ? $needles : [$needles];
	}

	public function asArray(): array
	{
		return [
			$this->name => [
				"\$exists" => true,
				"\$elemMatch" => [
					"\$in" => $this->needle
				]
			]
		];
	}
}
