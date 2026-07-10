<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Strategy;
use Illuminate\Support\Str;

class StrategyController extends Controller
{


    public function userIndex(Request $request)
    {
         $strategy = Strategy::with(['user'])->where('user_id', $request->user()->id)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $strategy
        ], 200);
    }

    public function store(Request $request){
        if ($request->has('symbol')) {
            $request->merge([
                'symbol' => strtoupper(trim($request->symbol))
            ]);
        }
         $request->validate([
            'user_id'   => 'required|exists:users,id',
            'name'      => 'required|string|max:255',
            'symbol'    => 'required|string|max:50',
            'rr_ratio'  => 'required|numeric',
            'timeframe' => 'required|string|max:20',
        ]);

        if (auth()->check()) {
            $userId = auth()->id(); 
        } else {
            // 💡 ຖ້າທ່ານຍັງບໍ່ທັນໄດ້ຄອບ Middleware Auth (ຊົ່ວຄາວ) ໃຫ້ດຶງຈາກ request ທີ່ React ສົ່ງມາ
            $userId = $request->user_id; 
        }

        $strategy = Strategy::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'name' => $request->name,
            'symbol' => $request->symbol,
            'rr_ratio' => $request->rr_ratio,
            'timeframe' => $request->timeframe,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'ເພີ່ມກົນລະຍຸດສຳເລັດ',
            'data' => $strategy
        ], 201);
    }

    public function update(Request $request, $id){

        $strategy = Strategy::findOrFail($id);
         if ($request->has('symbol')) {
            $request->merge([
                'symbol' => strtoupper(trim($request->symbol))
            ]);
        }
        $request->validate([
            'name'      => 'required|string|max:255',
            'symbol'    => 'required|string|max:50',
            'rr_ratio'  => 'required|numeric',
            'timeframe' => 'required|string|max:20',
        ]);

        $strategy->update([
            'name' => $request->name,
            'symbol' => $request->symbol,
            'rr_ratio' => $request->rr_ratio,
            'timeframe' => $request->timeframe,
            'description' => $request->description,
        ]);

          return response()->json([
            'message' => 'ແກ້ໄຂຂໍ້ມູນກົນລະຍຸດສຳເລັດ',
            'data' => $strategy
        ], 201);
    }

public function show($id)
{
    $strategy = Strategy::withCount([
            'backtestTrades as total_trades',
            'backtestTrades as win_count' => function ($query) {
                $query->where('status', 'win'); 
            },
            'backtestTrades as loss_count' => function ($query) {
                $query->where('status', 'loss');
            }
        ])
        // 🛠️ ແກ້ໄຂ Syntax withSum ໃຫ້ຖືກຕ້ອງຕາມມາດຕະຖານ Laravel:
        ->withSum([
            // 'ຊື່Relation as Alias' => function($query) { ... }
            'backtestTrades as winnings' => function ($query) {
                $query->where('status', 'win');
            },
            'backtestTrades as losses' => function ($query) {
                $query->where('status', 'loss');
            },
            'backtestTrades as total_profit' => function ($query) {
                // ປ່ອຍຫວ່າງໄວ້ ບໍ່ຕ້ອງມີ where ເພື່ອໃຫ້ມັນລວມທັງໝົດ
            }
        ], 'pnl_amount') // 🔑 ຕ້ອງໃສ່ 'pnl_amount' ໄວ້ເປັນ Parameter ໂຕທີ 2 ຢູ່ບ່ອນນີ້!
        ->with(['backtestTrades' => function($query) {
            $query->orderBy('created_at', 'asc'); // ລຽງຈາກໄມ້ທຳອິດໄປຫາໄມ້ຫຼ້າສຸດ
        }])
        ->findOrFail($id);

        $grossProfit = (float) ($strategy->winnings ?? 0);
        $grossLoss = (float) ($strategy->losses ?? 0);
        // 🔄 ປ່ຽນຄ່າໃຫ້ເປັນຄ່າບວກສະເໝີ ດ້ວຍ abs() ປ້ອງກັນກໍລະນີ Backend ສົ່ງຄ່າມາເປັນຕິດລົບ
        $absoluteGrossLoss = abs($grossLoss);
        $profitFactor = $absoluteGrossLoss > 0 ? ($grossProfit / $absoluteGrossLoss) : $grossProfit;

    return response()->json([
        'data' => $strategy,
        'profit_factor' => round($profitFactor, 2)
    ], 200);
}

    public function updateCapital(Request $request, $id){
        $str = Strategy::findOrFail($id);
        $request->validate([
            'initial_capital' => 'required|numeric',
            'risk_percent' => 'required|numeric',
        ]);
        $str->update([
            'initial_capital' => $request->initial_capital,
            'risk_percent' => $request->risk_percent
        ]);

        return response()->json(['data' => $str], 200);
    }

    public function updateTimespan (Request $request, $id){
        $str = Strategy::findOrFail($id);
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);
        $str->update([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

        return response()->json(['data' => $str], 200);
    }


    public function destroy($id){
        Strategy::findOrFail($id)->delete();
        return response()->json(['message' => 'ລຶບສຳເລັດ']);
    }
}
