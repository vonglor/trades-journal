<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trade;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
class TradeController extends Controller
{
    //

    public function index(Request $request)
    {
        $trades = Trade::with(['user'])->where('user_id', $request->user()->id)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $trades
        ], 200);
    }

    public function userIndex($id)
    {
        // 1. ດຶງລາຍການ Trades ທັງໝົດ
        $trades = Trade::where('user_id', $id)->orderBy('created_at', 'desc')->get();

        // 2. ຄິດໄລ່ສະຖິຕິ ລວມທັງ Gross Profit R ແລະ Gross Loss R
        $stats = Trade::where('user_id', $id)
            ->selectRaw("
            COUNT(CASE WHEN status = 'win' THEN 1 END) as total_win,
            COUNT(CASE WHEN status = 'loss' THEN 1 END) as total_loss,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as total_pending,
            
            -- 🟢 ລວມ R ຂອງໄມ້ຊະນະທັງໝົດ
            SUM(CASE WHEN status = 'win' THEN risk_reward ELSE 0 END) as gross_profit_r,
            
            -- 🔴 ລວມ R ຂອງໄມ້ແພ້ທັງໝົດ (ຄິດເປັນຄ່າບວກເພື່ອເອົາໄປເປັນຕົວຫານ)
            SUM(CASE WHEN status = 'loss' THEN 1 ELSE 0 END) as gross_loss_r
        ")
            ->first();

        // 3. ຄຳນວນ Profit Factor (ປ້ອງກັນຕົວຫານເປັນ 0 ຖ້າບໍ່ເຄີຍແພ້)
        $grossProfit = (float) ($stats->gross_profit_r ?? 0);
        $grossLoss = (float) ($stats->gross_loss_r ?? 0);

        $profitFactor = $grossLoss > 0 ? ($grossProfit / $grossLoss) : $grossProfit;

        return response()->json([
            'status' => 'success',
            'total_win' => (int) ($stats->total_win ?? 0),
            'total_loss' => (int) ($stats->total_loss ?? 0),
            'total_pending' => (int) ($stats->total_pending ?? 0),
            'total_trades' => $trades->count(),

            // 📊 ສົ່ງຄ່າເຫຼົ່ານີ້ໄປສະແດງຜົນຢູ່ React
            'net_risk_reward' => $grossProfit - $grossLoss, // R ສຸດທິ
            'profit_factor' => round($profitFactor, 2),   // Profit Factor ທົດສະນິຍົມ 2 ຕຳແໜ່ງ

            'data' => $trades
        ], 200);
    }
    public function store(Request $request)
    {
        if ($request->has('pair')) {
            $request->merge([
                'pair' => strtoupper(trim($request->pair))
            ]);
        }

        // 🎯 ເອົາ strategy_id ອອກ ແລະ ປ່ຽນ exit_price ເປັນ nullable (ເພາະບາງໄມ້ອາດຈະຍັງບໍ່ທັນປິດ)
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'pair' => 'required|string|max:255',
            'action' => 'required|string|max:50',
            'entry_price' => 'required|numeric',
            'exit_price' => 'required|numeric',
            'risk_reward' => 'required|numeric',
        ]);

        if (auth()->check()) {
            $userId = auth()->id();
        } else {
            $userId = $request->user_id;
        }

        // 📸 ຈັດການເລື່ອງຮູບພາບ
        $path = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $path = $image->store('screenshots', 'public');
        }

        // 💾 ບັນທຶກຂໍ້ມູນໂດຍບໍ່ມີ strategy_id
        $trades = Trade::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'pair' => $request->pair,
            'action' => $request->action,
            'entry_price' => $request->entry_price,
            'exit_price' => $request->exit_price,
            'risk_reward' => $request->risk_reward,
            'screenshot_url' => $path ? asset('storage/' . $path) : null,
            'notes' => $request->notes
        ]);

        return response()->json([
            'message' => 'ເພີ່ມລາຍການເທຣດສຳເລັດ',
            'data' => $trades
        ], 201);
    }

    public function update(Request $request, $id)
    {
        // 🔍 ຄົ້ນຫາຂໍ້ມູນ Trade ຖ້າບໍ່ພົບຈະ Return 404 ອັດຕະໂນມັດ
        $trade = Trade::findOrFail($id);

        if ($request->has('pair')) {
            $request->merge([
                'pair' => strtoupper(trim($request->pair))
            ]);
        }

        // 🎯 1. ປັບ Validation ໃຫ້ຮອງຮັບທັງການແກ້ໄຂໃນ Modal ແລະ ການກົດ WIN/LOSS ຈາກໜ້າຕາຕະລາງ
        $request->validate([
            'pair' => 'required|string|max:255',
            'action' => 'required|string|max:50',
            'entry_price' => 'required|numeric',
            'exit_price' => 'nullable|numeric',
            'risk_reward' => 'nullable|numeric',
            'notes' => 'nullable|string',        // 👈 ເພີ່ມ notes ເຂົ້າໃນ validation
        ]);

        // 🎯 2. ກວດເຊັກເລື່ອງຮູບພາບ (ຕັ້ງຄ່າເລີ່ມຕົ້ນເປັນຄ່າເກົ່າໃນ DB ກ່ອນ)
        $path = $trade->screenshot_url;

        if ($request->hasFile('image')) {
            // 🗑️ ຖ້າມີການອັບໂຫຼດຮູບໃໝ່ ໃຫ້ລຶບຮູບເກົ່າອອກຈາກ Storage ກ່ອນ
            if ($trade->screenshot_url) {
                // ດຶງເອົາສະເພາະ path ທາງຫຼັງ (ກັນພາດທັງແບບ Full URL ແລະ ພາດສັ້ນ)
                $oldPath = str_replace(asset('storage/'), '', $trade->screenshot_url);
                $oldPath = str_replace('http://localhost:8000/storage/', '', $oldPath);
                $oldPath = str_replace('http://127.0.0.1:8000/storage/', '', $oldPath);

                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // 💾 ເກັບຮູບພາບໃໝ່
            $image = $request->file('image');
            $uploadedPath = $image->store('screenshots', 'public');

            // 💡 ແນະນຳໃຫ້ເກັບເປັນ Full URL ຜ່ານ asset() ເພື່ອໃຫ້ React ເອົາໄປໃຊ້ງານງ່າຍໆບໍ່ຕ້ອງ replace domain ຄືນ
            $path = asset('storage/' . $uploadedPath);
        }

        // 🎯 3. ອັບເດດຂໍ້ມູນທັງໝົດລົງ Database
        $trade->update([
            'pair' => $request->pair,
            'action' => $request->action,
            'entry_price' => $request->entry_price,
            'exit_price' => $request->exit_price,
            'risk_reward' => $request->risk_reward,
            'screenshot_url' => $path, // 👈 ປ່ຽນມາໃຊ້ $path ທີ່ຜ່ານການກວດເຊັກແລ້ວ (ແກ້ Bug ຮູບແຕກ/Crash)
            'notes' => $request->notes
        ]);

        return response()->json([
            'message' => 'ອັບເດດລາຍການເທຣດສຳເລັດ',
            'data' => $trade
        ], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $trades = Trade::findOrFail($id);
        $trades->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'success',
            'data' => $trades
        ], 200);
    }

    public function destroy($id)
    {
        $trades = Trade::findOrFail($id);
        $trades->delete();
        return response()->json([
            'message' => 'success'
        ], 200);
    }
}
