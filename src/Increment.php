<?php

namespace SequelMongo;

class Increment
{
	protected $name;
	protected $by;

	public function __construct(string $propertyName, int $incrementBy = 1)
	{
		$this->name = $propertyName;
		$this->by   = $incrementBy;
	}

	public function asArray(): array
	{
		return [
			"\$inc" => [$this->name => $this->by]
		];
	}
}
