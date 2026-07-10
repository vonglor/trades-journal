<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Strategy extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    // 1. 📢 ບອກ Laravel ວ່າຕາຕະລາງໃນ Postgres ຂອງເຮົາຊື່ "users"
    protected $table = 'strategies';

    // 2. 🔑 ບອກວ່າມັນໃຊ້ UUID ເປັນ Key (ບໍ່ແມ່ນຕົວເລກ 1, 2, 3)
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    // 3. 📝 ກໍານົດ Field ທີ່ຍອມໃຫ້ກອກຂໍ້ມູນໄດ້
    protected $fillable = [
        'id',
        'user_id',
        'name',
        'symbol',
        'rr_ratio',
        'timeframe',
        'description',
        'start_date',
        'end_date',
        'initial_capital',
        'risk_percent'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function backtestTrades(){
        return $this->hasMany(BacktestTrade::class, 'strategy_id');
    }

}
