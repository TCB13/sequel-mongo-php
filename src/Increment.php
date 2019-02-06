<?php

namespace SequelMongo;

class Increment
{
	protected $name;
	protected $by;

	public function __construct(string $property, int $incrementBy = 1)
	{
		$this->name = $property;
		$this->by   = $incrementBy;
	}

	public function asArray(): array
	{
		return [
			"\$inc" => [$this->name => $this->by]
		];
	}
}
