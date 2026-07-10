<?php
// routes/api.php
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\VerificationController;
use App\Http\Controllers\{
    AuthApiController,
    StrategyController,
    BacktestTradeController,
    TradeController,
    UserController,
    RoleController
};

// Route ສໍາລັບໃຫ້ React ສົ່ງ URL Params ມາເຊັກຄວາມຖືກຕ້ອງ
Route::get('/auth/verify', [VerificationController::class, 'verify'])
    ->name('api.verification.verify');

// // Route ສໍາລັບກົດສົ່ງ Email ໃໝ່ (Resend Link) ຖ້າ Link ເກົ່າໝົດອາຍຸ
Route::post('/email/verification-notification', [VerificationController::class, 'resend']);

Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword']);
Route::post('/reset-password',  [AuthApiController::class, 'resetPassword']);

// // ── PUBLIC ROUTES ─────────────────────────────────────────────
Route::post('/check-identity', [AuthApiController::class, 'checkIdentity']);
Route::post('/register', [AuthApiController::class, 'register']);
Route::post('/login',    [AuthApiController::class, 'login']);
// Route::get('/auth/verify', [VerificationController::class, 'verify'])->name('verification.verify'); // ⚠️ ຕ້ອງຕັ້ງຊື່ name ໃຫ້ຕົງກັບໃນ AuthServiceProvider

// ── AUTH REQUIRED (ທຸກຄົນທີ່ Login ຕ້ອງຜ່ານກຸ່ມນີ້ກ່ອນ) ────────────────
Route::middleware('auth:sanctum')->group(function () {
    
    // Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthApiController::class, 'me']);

     // User profile
    Route::put('/users/{id}/profile',     [UserController::class, 'updateProfile']);
    Route::post('/users/password_change', [UserController::class, 'passwordChange']);
    Route::put('/users/upgrade-role', [UserController::class, 'upgradeToSuperUser']);

    // 🟢 1. Route ສຳລັບດຶງແຈ້ງເຕືອນທີ່ຍັງບໍ່ໄດ້ອ່ານ (GET)
    Route::get('/notifications/unread', function (Request $request) {
        // ດຶງແຈ້ງເຕືອນຫຼ້າສຸດ 10 ອັນ ທີ່ is_read ເປັນ false
        $notifications = Notification::where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json($notifications);
    });

    // 🟢 2. Route ສຳລັບອັບເດດວ່າອ່ານແລ້ວ (PUT)
    Route::put('/notifications/{id}/read', function ($id) {
        $notification = Notification::find($id);
        
        if ($notification) {
            $notification->update(['is_read' => true]);
            return response()->json(['status' => 'success', 'message' => 'ໝາຍເປັນອ່ານແລ້ວ']);
        }

        return response()->json(['status' => 'error', 'message' => 'ບໍ່ພົບຂໍ້ມູນ'], 404);
    });

    // ──  2. OWNER & ADMIN ROUTES (Token ມີສິດ role:owner ຫຼື role:admin) ──
    //  ໃຊ້ 'abilities' (ເຕີມ s) ເພື່ອໝາຍເຖິງ "ມີສິດອັນໃດອັນໜຶ່ງໃນນີ້ກໍໄດ້"
    Route::middleware('role:user,super_user,admin')->prefix('user')->group(function () {

        Route::get('/strategies', [StrategyController::class, 'userIndex']);
        Route::post('/strategies', [StrategyController::class, 'store']);
        Route::put('/strategies/{id}/', [StrategyController::class, 'update']);
        Route::delete('/strategies/{id}/', [StrategyController::class, 'destroy']);
        Route::get('/strategies/{id}/', [StrategyController::class, 'show']);
        Route::put('/strategy_update_capital/{id}', [StrategyController::class, 'updateCapital']);
        Route::put('/strategy_update_timespan/{id}', [StrategyController::class, 'updateTimespan']);

        // Backtest trades
        Route::post('/backtest_trades', [BacktestTradeController::class, 'store']);
        Route::get('/backtest_trades/{id}', [BacktestTradeController::class, 'show']);

        // forward testing
        Route::get('/trades', [TradeController::class, 'index']);
        Route::get('/trades/{id}/', [TradeController::class, 'userIndex']);
        Route::put('/trades/{id}/', [TradeController::class, 'update']);
        Route::put('/trades/update_status/{id}/', [TradeController::class, 'updateStatus']);
        Route::post('/trades', [TradeController::class, 'store']);
        Route::delete('/trades/{id}/', [TradeController::class, 'destroy']);

        // Route Roles
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

        // Route Users
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
    });
    

    // ── ⚡ 3. ADMIN ONLY ROUTES (ສະເພາະ Token ທີ່ມີສິດ role:admin ເທົ່ານັ້ນ) ──
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        
    });
});