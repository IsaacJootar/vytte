<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionType extends Model
{
    protected $primaryKey = 'type_id';

    public $timestamps = false;

    protected $fillable = ['type_code'];
}
