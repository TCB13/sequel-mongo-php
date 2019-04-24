<?php

namespace SequelMongo;

class ArrayPush implements SpecialFunction
{
    protected $name;
    protected $values;

    public function __construct(string $property, ...$values)
    {
        $this->name  = $property;
        $this->values = $values;
    }

    public function asArray(): array
    {
        return [
            "\$push" => [
                $this->name => ["\$each" => $this->values]
            ]
        ];
    }
}
