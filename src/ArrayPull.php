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
        foreach ($this->value as $key => &$value) {
            if (is_array($value)) {
                $value = [
                    "\$in" => $value
                ];
            }
        }
        return [
            "\$pull" => [
                $this->name => $this->value
            ]
        ];
    }
}
