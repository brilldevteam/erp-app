<?php

namespace Workdo\PettyCashManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashAttachment extends Model
{
    use SoftDeletes;

    public const REQUEST = 'request';
    public const FUND = 'fund';

    protected $fillable = ['attachable_type', 'attachable_id', 'file_name', 'file_path', 'file_type', 'file_size', 'uploaded_by', 'removed_by', 'created_by'];

    protected $hidden = ['file_path'];

    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
