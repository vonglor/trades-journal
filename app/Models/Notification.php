<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
     protected $table = 'notifications';
    
    protected $keyType = 'string';
    public $incrementing = false;

    public $timestamps = false;
    protected $fillable = [
        'id',
        'type',
        'title',
        'content',
        'data',
        'is_read',
        'user_id',
    ];
}
