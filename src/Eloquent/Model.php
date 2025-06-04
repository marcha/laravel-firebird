<?php

namespace Firebird\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Support\Facades\DB;

class Model extends BaseModel
{

  protected static function booted()
  {
    static::creating(function ($model) {
      if (empty($model->{$model->primaryKey}) && property_exists($model, 'sequence')) {
        $generator = $model->sequence;
        $model->{$model->primaryKey} = DB::connection($model->connection)->selectOne("SELECT GEN_ID($generator, 1) AS ID from RDB\$DATABASE")->ID;
      }
    });
  }
}
