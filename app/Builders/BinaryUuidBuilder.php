<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Ramsey\Uuid\Uuid;

class BinaryUuidBuilder extends Builder
{
    /**
     * Cache of binary UUID columns per model class.
     *
     * @var array<string, array<string>>
     */
    protected static array $binaryUuidColumnsCache = [];

    /**
     * Determine if a column is cast as a binary UUID.
     *
     * @param  mixed  $column
     * @return bool
     */
    protected function isBinaryUuidColumn($column): bool
    {
        if (!is_string($column) || !$this->model) {
            return false;
        }

        $modelClass = get_class($this->model);

        if (!isset(self::$binaryUuidColumnsCache[$modelClass])) {
            self::$binaryUuidColumnsCache[$modelClass] = [];
            
            foreach ($this->model->getCasts() as $col => $cast) {
                if ($cast === \App\Casts\UuidBinaryCast::class || 
                    (is_string($cast) && class_exists($cast) && is_subclass_of($cast, \App\Casts\UuidBinaryCast::class))) {
                    self::$binaryUuidColumnsCache[$modelClass][] = $col;
                }
            }
        }

        $columnName = last(explode('.', $column));

        return in_array($columnName, self::$binaryUuidColumnsCache[$modelClass], true);
    }

    /**
     * Convert a UUID string value to raw binary bytes if valid.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function convertToBinary($value)
    {
        if (is_string($value) && Uuid::isValid($value)) {
            try {
                return Uuid::fromString($value)->getBytes();
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  \Closure|string|array|\Illuminate\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_array($column)) {
            foreach ($column as $col => $val) {
                if ($this->isBinaryUuidColumn($col)) {
                    $column[$col] = $this->convertToBinary($val);
                }
            }
        } elseif (is_string($column) && $this->isBinaryUuidColumn($column)) {
            if (func_num_args() === 2) {
                $operator = $this->convertToBinary($operator);
            } elseif (func_num_args() >= 3) {
                $value = $this->convertToBinary($value);
            }
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if ($this->isBinaryUuidColumn($column)) {
            $values = collect($values)->map(fn($v) => $this->convertToBinary($v))->all();
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        if ($this->isBinaryUuidColumn($column)) {
            $values = collect($values)->map(fn($v) => $this->convertToBinary($v))->all();
        }

        return parent::whereNotIn($column, $values, $boolean);
    }

    /**
     * Explicitly query by UUID column.
     *
     * @param  string  $value
     * @param  string  $column
     * @return $this
     */
    public function whereUuid($value, $column = 'uuid')
    {
        return $this->where($column, '=', $value);
    }

    /**
     * Explicitly query by UUID column (OR).
     *
     * @param  string  $value
     * @param  string  $column
     * @return $this
     */
    public function orWhereUuid($value, $column = 'uuid')
    {
        return $this->where($column, '=', $value, 'or');
    }

    /**
     * Explicitly query in UUID column values.
     *
     * @param  array  $values
     * @param  string  $column
     * @return $this
     */
    public function whereUuidIn($values, $column = 'uuid')
    {
        return $this->whereIn($column, $values);
    }

    /**
     * Explicitly query in UUID column values (OR).
     *
     * @param  array  $values
     * @param  string  $column
     * @return $this
     */
    public function orWhereUuidIn($values, $column = 'uuid')
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Explicitly query not in UUID column values.
     *
     * @param  array  $values
     * @param  string  $column
     * @return $this
     */
    public function whereUuidNotIn($values, $column = 'uuid')
    {
        return $this->whereNotIn($column, $values);
    }

    /**
     * Explicitly query not in UUID column values (OR).
     *
     * @param  array  $values
     * @param  string  $column
     * @return $this
     */
    public function orWhereUuidNotIn($values, $column = 'uuid')
    {
        return $this->whereNotIn($column, $values, 'or');
    }
}
