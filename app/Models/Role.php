<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';
    
    protected $keyType = 'string';
    public $incrementing = false;

    public $timestamps = false;
    protected $fillable = [
        'id',
        'name',
        'description',
    ];


    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
        
    }

}