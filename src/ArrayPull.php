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
        // Remove individual value from array
        // new ArrayPull("list", "user.123")
        if (!is_array($this->value)) {
            return [
                "\$pull" => [
                    $this->name => $this->value
                ]
            ];
        }

        // Remove array values from property
        // new ArrayPull("list", ["user.123", "user.456"])
        if (is_int(array_keys($this->value)[0])) {
            return [
                "\$pull" => [
                    $this->name => [
                        "\$in" => $this->value
                    ]
                ]
            ];
        }

        // Remove objects from array with conditions
        // new ArrayPull("list", ["id" => ["user.123", "user.456"]])
        // new ArrayPull("list", ["id" => "user.123"])
        // new ArrayPull("list", ["id" => "user.123", "name" => "joe"])
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
