<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Trade extends Model
{
    //
    use HasApiTokens, HasFactory, Notifiable;
    protected $table = 'trades';

    // 2. 🔑 ບອກວ່າມັນໃຊ້ UUID ເປັນ Key (ບໍ່ແມ່ນຕົວເລກ 1, 2, 3)
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    // 3. 📝 ກໍານົດ Field ທີ່ຍອມໃຫ້ກອກຂໍ້ມູນໄດ້
    protected $fillable = [
        'id',
        'user_id',
        'pair',
        'action',
        'entry_price',
        'exit_price',
        'risk_reward',
        'status',
        'screenshot_url',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
