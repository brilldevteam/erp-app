<?php

namespace Workdo\Appointment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_name',
        'question_type',
        'available_answers',
        'required_answer',
        'enabled',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'question_type' => 'string',
            'required_answer' => 'boolean',
            'enabled' => 'boolean'
        ];
    }
}
