<?php

namespace Modules\BusinessManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\BusinessManagement\Database\factories\QuestionAnswerFactory;
use Modules\Gateways\Traits\HasUuid;

class QuestionAnswer extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'question',
        'answer',
        'question_answer_for',
        'is_active',
    ];

    protected static function newFactory(): QuestionAnswerFactory
    {
        //return QuestionAnswerFactory::new();
    }
}
