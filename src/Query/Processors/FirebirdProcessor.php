<?php

namespace Firebird\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;

class FirebirdProcessor extends Processor
{

  /**
   * Process an "insert get ID" query.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $sql
   * @param array $values
   * @param string $sequence
   * @return int
   */
  public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
  {
    $results = $query->getConnection()->selectFromWriteConnection($sql, $values);

    $sequence = $sequence ?: 'ID';

    $result = (array)$results[0];

    $id = $result[$sequence];

    return is_numeric($id) ? (int)$id : $id;
  }

  /**
   * Process an "next sequence value" query.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $sql
   * @return int
   */
  public function processNextSequenceValue(Builder $query, $sql)
  {
    $results = $query->getConnection()->selectFromWriteConnection($sql);

    $result = (array)$results[0];

    $id = $result['ID'];

    return is_numeric($id) ? (int)$id : $id;
  }

  /**
   * Process an "get context variable value" query.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $sql
   * @return int
   */
  public function processGetContextValue(Builder $query, $sql)
  {
    $result = $query->getConnection()->selectOne($sql);

    return $result['VAL'];
  }

  /**
   * Process an "execute function" query.
   *
   * @param \Illuminate\Database\Query\Builder $query
   * @param string $sql
   * @param array $values
   *
   * @return mixed
   */
  public function processExecuteFunction(Builder $query, $sql, $values)
  {
    $result = $query->getConnection()->selectOne($sql, $values);

    return $result['VAL'];
  }

  /**
   * Process the results of a column listing query.
   *
   * @param array $results
   * @return array
   */
  public function processColumnListing($results)
  {
    $mapping = function ($r) {
      $r = (object)$r;

      return trim($r->{'RDB$FIELD_NAME'});
    };

    return array_map($mapping, $results);
  }


  /**
   * Process the results of a columns query.
   *
   * @param  array  $results
   * @return array
   */
  public function processColumns($results)
  {
    return array_map(function ($result) {
      $result = (object) $result;

      return [
        'name' => trim($result->name),
        'type_name' => $result->type_name,
        'type' => $result->type,
        'collation' => $result->collation,
        'nullable' => $result->nullable === 'YES',
        'default' => $result->default,
        'auto_increment' => $result->extra === 'auto_increment',
        'comment' => $result->comment ?: null,
        'generation' => $result->expression ? [
          'type' => match ($result->extra) {
            'STORED GENERATED' => 'stored',
            'VIRTUAL GENERATED' => 'virtual',
            default => null,
          },
          'expression' => $result->expression,
        ] : null,
      ];
    }, $results);
  }
}
