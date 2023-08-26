<?php
namespace Sannomiya\Form;

class Bag
{

    protected $bag = [];

    /**
     * Array of field name by key. Use for caching getFields function
     * @var array
     */
    protected $fields = [];


    /**
     * Set value of a key for field.
     * @param mixed $fields Maybe array or string delimited by commas
     * @param int $key
     * @param mixed $value It will replace {FIELD} by $field
     */
    public function setBag($fields, $key, $value)
    {
        if ($fields == '*') {
            $fields = array_keys($this->bag);
        }else{
            if (!is_array($fields)) {
                $fields = explode(',', $fields);
            }
        }
        foreach ($fields as $field) {
            $field = trim($field);
            if (!isset ($this->bag [$field])) {
                $this->bag [$field] = [];
            }
            if (isset ($value)) {
                if (is_string($value)) {
                    $this->bag[$field][$key] = str_replace("{FIELD}", $field, $value);
                } else {
                    $this->bag[$field][$key] = $value;
                }
            } else {
                unset ($this->bag [$field] [$key]);
            }
        }
    }

    /**
     * Get value of key of field
     * @param string $field
     * @param int $key
     * @param mixed $def
     * @return mixed
     */
    public function getBag($field, $key, $def = null)
    {
        if ($this->exists($field, $key)) {
            return $this->bag [$field] [$key];
        } else {
            return $def;
        }
    }

    public function getField($field){
        return @$this->bag [$field];
    }

    /**
     * Check if field or key of and field exists in bag
     * @param mixed $field
     * @param null $key If null, only check field exists
     * @return bool
     */
    protected function exists($field, $key = null)
    {
        return isset ($this->bag [$field]) && (is_null($key) || isset ($this->bag [$field] [$key]));
    }


    /**
     * Remove fields or key of field from bag
     * @param mixed $fields
     * @param null $key
     */
    protected function delete($fields, $key = null){
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }
        foreach ( $fields as $field ) {
            $field = trim ( $field );
            if (isset($key)){
                if (isset($this->bag [$field]) && isset($this->bag [$field][$key])){
                    unset($this->bag [$field][$key]);
                }
            }else{
                if (isset($this->bag [$field])){
                    unset ( $this->bag [$field] );
                }
            }
        }
    }

    /**
     * Get array of field name exists in bag.
     * @param null $key If not null, get array of field name exists
     * in bag which exists this key and value of this key is not false.
     * @return array
     */
    public function getFields($key = null)
    {
        $fields = array_keys($this->bag);
        if (is_null($key)) {
            return $fields;
        } else {
            if (!isset ($this->fields [$key])) {
                $ret = [];
                foreach ($fields as $field) {
                    if ($this->exists($field, $key) && $this->bag [$field] [$key] !== false) {
                        $ret [] = $field;
                    }
                }
                $this->fields [$key] = $ret;
            }
            return $this->fields [$key];
        }
    }
}
