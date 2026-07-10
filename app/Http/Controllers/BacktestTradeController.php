<?php

namespace App\Http\Controllers;
use App\Models\BacktestTrade;
use App\Models\Strategy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BacktestTradeController extends Controller
{
    //

    public function store(Request $request){
        $request->validate([
            'strategy_id' => 'required',
            'action' => 'required|string',
            'status' => 'required|string'
        ]);
        $strategy = Strategy::findOrFail($request->strategy_id);
        $risk_amount = $strategy->initial_capital * ($strategy->risk_percent / 100);
        if($request->status == 'win'){
            $pnl_amount = $risk_amount * $strategy->rr_ratio;
        }else{
            $pnl_amount = -$risk_amount;
        }
        
        $balance_after = $strategy->initial_capital + $pnl_amount;

        $data = BacktestTrade::create([
            'id' => (string) Str::uuid(),
            'strategy_id' => $request->strategy_id,
            'action' => $request->action,
            'status' => $request->status,
            'risk_amount' => $risk_amount,
            'pnl_amount' => $pnl_amount,
            'balance_after' => $balance_after 
        ]);
        $strategy->update(['initial_capital' => $balance_after]);

        return response()->json([
            'message' => 'ເພີ່ມການເທຣດສຳເລັດ',
            'data' => $data
        ], 201);
    }

    public function show($id){
        $strategy = Strategy::findOrFail($id);

        $backtests = BacktestTrade::where('strategy_id', $id)->orderByDesc('created_at')->get();
        return response()->json([
            'message', 'success',
            'data' => $backtests,
            'strategy_name' => $strategy->name
        ], 200);
    }

}
