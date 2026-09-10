<?php
namespace Workdo\Account\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class JournalEntryAttachment extends Model
{
    use SoftDeletes;
    protected $fillable = ['file_name', 'file_path', 'file_type', 'file_size', 'uploaded_by', 'removed_by'];
    protected $hidden = ['file_path'];
    public function uploader() { return $this->belongsTo(\App\Models\User::class, 'uploaded_by'); }
}
