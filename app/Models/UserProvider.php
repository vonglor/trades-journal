<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProvider extends Model
{
    protected $table = 'user_providers';
    
    protected $keyType = 'string';
    public $incrementing = false;

    public $timestamps = false;
    protected $fillable = [
        'id',
        'user_id',
        'provider_type',
        'provider_value',
        'provider_name',
        'is_verified',
    ];

    // ແປງຄ່າສະຖານະໃຫ້ເປັນ Boolean ອັດຕະໂນມັດ
    protected $casts = [
        'is_verified' => 'boolean',
    ];

     public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}