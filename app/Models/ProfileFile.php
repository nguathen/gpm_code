<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Group;

class ProfileFile extends Model
{
    use HasFactory;

    protected $table = 'profile_files';

    protected $fillable = [
        'group_id',
        'file_name',
        'google_drive_file_id',
        'file_path',
        'file_size',
        'md5_checksum',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
