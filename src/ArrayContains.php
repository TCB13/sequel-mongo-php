<?php

namespace SequelMongo;

class ArrayContains
{
	public $name;
	public $needle;

	public function __construct(string $arrayField, $needles)
	{
		$this->name   = $arrayField;
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
