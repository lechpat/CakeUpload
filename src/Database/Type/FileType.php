<?php
namespace Upload\Database\Type;

use Cake\Database\Type;

class FileType extends Type
{
    /**
     * Prevent the marhsaller changing the upload array into a string
     *
     * @param mixed $value Passed upload array
     * @return mixed
     */
    public function marshal($value)
    {
        return $value;
    }
}
