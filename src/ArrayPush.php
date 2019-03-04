<?php

namespace SequelMongo;

class ArrayPush implements SpecialFunction
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
            "\$push" => [
                $this->name => $this->value
            ]
        ];
    }
}
