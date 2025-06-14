<?php

namespace Firebird\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

class Firebird25Grammar extends Grammar
{

  /**
   * All of the available clause operators.
   *
   * @var array
   */
  protected $operators = [
    '=',
    '<',
    '>',
    '<=',
    '>=',
    '<>',
    '!=',
    'like',
    'not like',
    'between',
    'containing',
    'starting with',
    'similar to',
    'not similar to',
  ];

  /**
   * Compile an aggregated select clause.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param array $aggregate
   * @return string
   */
  protected function compileAggregate(Builder $query, $aggregate)
  {
    $column = $this->columnize($aggregate['columns']);

    // If the query has a "distinct" constraint and we're not asking for all columns
    // we need to prepend "distinct" onto the column name so that the query takes
    // it into account when it performs the aggregating operations on the data.
    if ($query->distinct && $column !== '*') {
      $column = 'distinct ' . $column;
    }

    return 'select ' . $aggregate['function'] . '(' . $column . ') as "aggregate"';
  }

  /**
   * Compile SQL statement for get context variable value
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $namespace
   * @param string $name
   * @return string
   */
  public function compileGetContext(Builder $query, $namespace, $name)
  {
    return "SELECT RDB\$GET_CONTEXT('{$namespace}', '{$name}' AS VAL FROM RDB\$DATABASE";
  }

  /**
   * Compile SQL statement for execute function
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $function
   * @param array $values
   * @return string
   */
  public function compileExecFunction(Builder $query, $function, array $values = null)
  {
    $function = $this->wrap($function);

    return "SELECT  {$function} (" . $this->parameterize($values) . ") AS VAL FROM RDB\$DATABASE";
  }

  /**
   * Compile SQL statement for execute procedure
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $procedure
   * @param array $values
   * @return string
   */
  public function compileExecProcedure(Builder $query, $procedure, array $values = null)
  {
    $procedure = $this->wrap($procedure);

    return "EXECUTE PROCEDURE {$$procedure} (" . $this->parameterize($values) . ')';
  }

  /**
   * Compile an insert and get ID statement into SQL.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param array $values
   * @param string $sequence
   * @return string
   */
  public function compileInsertGetId(Builder $query, $values, $sequence)
  {
    if (is_null($sequence)) {
      $sequence = 'ID';
    }

    return $this->compileInsert($query, $values) . ' RETURNING ' . $this->wrap($sequence);
  }

  /**
   * Compile the "limit" portions of the query.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param int $limit
   * @return string
   */
  protected function compileLimit(Builder $query, $limit)
  {
    if ($query->offset) {
      $first = (int)$query->offset + 1;
      return 'ROWS ' . (int)$first;
    } else {
      return 'ROWS ' . (int)$limit;
    }
  }

  /**
   * Compile the lock into SQL.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param bool|string $value
   * @return string
   */
  protected function compileLock(Builder $query, $value)
  {
    if (is_string($value)) {
      return $value;
    }

    return $value ? 'FOR UPDATE' : '';
  }

  /**
   * Compile SQL statement for get next sequence value
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $sequence
   * @param int $increment
   * @return string
   */
  public function compileNextSequenceValue(Builder $query, $sequence = null, $increment = null)
  {
    if (!$sequence) {
      $sequence = $this->wrap(substr('seq_' . $query->from, 0, 31));
    }
    if ($increment) {
      return "SELECT GEN_ID({$sequence}, {$increment}) AS ID FROM RDB\$DATABASE";
    }
    return "SELECT NEXT VALUE FOR {$sequence} AS ID FROM RDB\$DATABASE";
  }

  /**
   * Compile the "offset" portions of the query.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param int $offset
   * @return string
   */
  protected function compileOffset(Builder $query, $offset)
  {
    if ($query->limit) {
      if ($offset) {
        $end = (int)$query->limit + (int)$offset;
        return 'TO ' . $end;
      } else {
        return '';
      }
    } else {
      $begin = (int)$offset + 1;
      return 'ROWS ' . $begin . ' TO 2147483647';
    }
  }

  /**
   * Compile the additional where clauses for updates with joins.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @return string
   */
  protected function compileUpdateWheres(Builder $query)
  {
    $baseWhere = $this->compileWheres($query);

    return $baseWhere;
  }


  /**
   * Wrap a single string in keyword identifiers.
   *
   * @param string $value
   * @return string
   */
  protected function wrapValue($value)
  {
    if ($value === '*') {
      return $value;
    }

    return '"' . str_replace('"', '""', $value) . '"';
  }

  /**
   * Compile an exists statement into SQL.
   *
   * @param  \Illuminate\Database\Query\Builder  $query
   * @return string
   */
  public function compileExists(Builder $query)
  {
    $select = $this->compileSelect($query);
    return "SELECT 1 as \"exists\" FROM RDB\$DATABASE WHERE EXISTS ({$select})";
    //  return "select exists({$select}) as {$this->wrap('exists')}";
  }

  public function getValue($expression)
  {
    $value = parent::getValue($expression);

    // Automatski dodaj quotes oko column names u funkcijama
    $value = preg_replace('/\blower\s*\(\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\)/', 'lower("$1")', $value);

    return $value;
  }

  /**
   * Compile a "where date" clause.
   */
  public function whereDate(Builder $query, $where)
  {
    return $this->dateBasedWhere('DATE', $query, $where);
  }

  /**
   * Compile a "where time" clause.
   */
  public function whereTime(Builder $query, $where)
  {
    return $this->dateBasedWhere('TIME', $query, $where);
  }

  /**
   * Compile a "where day" clause.
   */
  public function whereDay(Builder $query, $where)
  {
    return $this->dateBasedWhere('DAY', $query, $where);
  }

  /**
   * Compile a "where month" clause.
   */
  public function whereMonth(Builder $query, $where)
  {
    return $this->dateBasedWhere('MONTH', $query, $where);
  }

  /**
   * Compile a "where year" clause.
   */
  public function whereYear(Builder $query, $where)
  {
    return $this->dateBasedWhere('YEAR', $query, $where);
  }

  /**
   * Compile a date based where clause.
   */
  protected function dateBasedWhere($type, Builder $query, $where)
  {
    $value = $this->parameter($where['value']);

    switch ($type) {
      case 'DATE':
        return "CAST({$this->wrap($where['column'])} AS DATE) {$where['operator']} {$value}";

      case 'TIME':
        return "CAST({$this->wrap($where['column'])} AS TIME) {$where['operator']} {$value}";

      case 'DAY':
        return "EXTRACT(DAY FROM {$this->wrap($where['column'])}) {$where['operator']} {$value}";

      case 'MONTH':
        return "EXTRACT(MONTH FROM {$this->wrap($where['column'])}) {$where['operator']} {$value}";

      case 'YEAR':
        return "EXTRACT(YEAR FROM {$this->wrap($where['column'])}) {$where['operator']} {$value}";

      default:
        return "CAST({$this->wrap($where['column'])} AS DATE) {$where['operator']} {$value}";
    }
  }
}
