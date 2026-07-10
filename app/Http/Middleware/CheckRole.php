<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
          // 1. ເຊັກວ່າຜູ້ໃຊ້ໄດ້ Login ຫຼືຖື Token ມາບໍ
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 2. ດຶງ Token ທີ່ກຳລັງໃຊ້ງານຢູ່ອອກມາ
        $currentToken = $user->currentAccessToken();

        // 3. ໄລ່ເຊັກວ່າໃນ Token ມີສິດ (Ability) ທີ່ກົງກັບ $roles ທີ່ເຮົາກຳນົດໄວ້ໃນ Route ບໍ
        foreach ($roles as $role) {
            // ເຊັກຮູບແບບ "role:admin" ຫຼື "role:owner" ທີ່ເຮົາຝັງໄວ້ໃນ Token
            if ($currentToken && $currentToken->can("role:{$role}")) {
                return $next($request); // 🟢 ຖ້າມີສິດອັນໃດອັນໜຶ່ງກົງ ໃຫ້ຜ່ານໄດ້ເລີຍ!
            }
        }

        // ❌ ຖ້າໄລ່ເຊັກທັງໝົດແລ້ວບໍ່ມີສິດ ກໍ Block ທັນທີ
        return response()->json([
            'message' => 'ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນສ່ວນນີ້ (Forbidden).'
        ], 403);
    }
}
