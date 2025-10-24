<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Profile;

class Group extends Model
{
    use HasFactory;

    protected $table = 'groups';

    protected $fillable = [
        'name',
        'sort',
        'created_by',
        'auto_backup',
        'google_drive_folder_id'
    ];

    protected $casts = [
        'auto_backup' => 'boolean',
    ];

    public function profiles(){
        return $this->hasMany(Profile::class);
    }
}
