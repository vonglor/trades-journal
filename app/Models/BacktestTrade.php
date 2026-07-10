<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BacktestTrade extends Model
{
    //
    use HasApiTokens, HasFactory, Notifiable;

    // 1. 📢 ບອກ Laravel ວ່າຕາຕະລາງໃນ Postgres ຂອງເຮົາຊື່ "users"
    protected $table = 'backtest_trades';

    // 2. 🔑 ບອກວ່າມັນໃຊ້ UUID ເປັນ Key (ບໍ່ແມ່ນຕົວເລກ 1, 2, 3)
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    // 3. 📝 ກໍານົດ Field ທີ່ຍອມໃຫ້ກອກຂໍ້ມູນໄດ້
    protected $fillable = [
        'id',
        'strategy_id',
        'action',
        'status',
        'risk_amount',
        'pnl_amount',
        'balance_after',
        'note'
    ];

    public function strategy()
    {
        return $this->belongsTo(Strategy::class, 'strategy_id');
    }


}
