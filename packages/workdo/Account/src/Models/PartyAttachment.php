<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartyAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = ['file_name', 'file_path', 'file_type', 'file_size', 'uploaded_by', 'removed_by'];
    protected $hidden = ['file_path'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
