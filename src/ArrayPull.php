<?php

namespace SequelMongo;

class ArrayPull implements SpecialFunction
{
    protected $name;
    protected $value;

    public function __construct(string $property, $value)
    {
        $this->name  = $property;
        $this->value = $value;
    }

    public function asArray(): array
    {
        return [
            "\$pull" => [
                $this->name => $this->value
            ]
        ];
    }
}
