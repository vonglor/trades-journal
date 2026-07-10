<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProvider;
use Illuminate\Http\Request;
use App\Mail\VerifyEmailMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB; // 👈 Import DB ເຂົ້າມານຳທ່ານ

class VerificationController extends Controller
{
    // 🟢 1. ແກ້ໄຂຟັງຊັນ verify ໃໝ່ໃຫ້ຮັບແຕ່ $request ເພາະເຮົາໃຊ້ Query String (?token=...)
    public function verify(Request $request)
    {
        // ເຊັກຄວາມຖືກຕ້ອງຂອງ Signed URL (ຖ້າໝົດອາຍຸ ຫຼື ຖືກແກ້ໄຂ ໃຫ້ເຕະລົງໜ້າ Login ຂອງ React ພ້ອມແນບ error ໄປ)
        if (!$request->hasValidSignature()) {
            return redirect()->to('http://localhost:5173/login?error=expired');
        }

        $token = $request->query('token');
        if (!$token) {
            return redirect()->to('http://localhost:5173/login?error=invalid');
        }

        // ຖອດລະຫັດ Email ຈາກ Base64 Token
        $email = base64_decode($token);

        // ຄົ້ນຫາໃນ user_providers
        $provider = UserProvider::where('provider_type', 'email')
            ->where('provider_name', $email)
            ->first();

        if (!$provider) {
            return redirect()->to('http://localhost:5173/login?error=notfound');
        }

        // ຖ້າເຄີຍ Verify ໄປແລ້ວ ໃຫ້ສົ່ງໄປໜ້າ Login ໂລດ
        if ($provider->is_verified) {
            return redirect()->to('http://localhost:5173/login?verified=already');
        }

        // ເລີ່ມການອັບເດດສະຖານະ
        DB::beginTransaction();
        try {
            // ອັບເດດໃນ user_providers
            $provider->update([
                'is_verified' => true
            ]);

            // ອັບເດດໃນ users ຫຼັກ ໃຫ້ເປັນ active
            $user = User::find($provider->user_id);
            if ($user) {
                $user->update([
                    'status' => 'active'
                ]);
            }

            DB::commit();

            // 🟢 ຢືນຢັນສຳເລັດ! ເຕະລົງໜ້າ Login ຂອງ React ພ້ອມແນບ verified=true ໄປສະແດງ SweetAlert
            return redirect()->to('http://localhost:5173/login?verified=true');

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()], 500);
        }
    }

    // 🟢 2. ຟັງຊັນ resend (ຖືກຕ້ອງ ແລະ ຄົບຖ້ວນແລ້ວ)
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = strtolower(trim($request->email));

        $provider = UserProvider::where('provider_type', 'email')
            ->where('provider_name', $email)
            ->first();

        if (!$provider) {
            return response()->json([
                'message' => 'ບໍ່ພົບຂໍ້ມູນບັນຊີອີເມວນີ້ໃນລະບົບ ກະລຸນາກວດສອບອີກຄັ້ງ'
            ], 404);
        }

        if ($provider->is_verified) {
            return response()->json([
                'message' => 'ອີເມວນີ້ໄດ້ຮັບການຢືນຢັນແລ້ວ ບໍ່ຈໍາເປັນຕ້ອງສົ່ງອີກ'
            ], 400);
        }

        $user = User::find($provider->user_id);
        if (!$user) {
            return response()->json(['message' => 'ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້ຫຼັກ'], 404);
        }

        // ສ້າງ Temporary Signed URL
        $verificationLink = URL::temporarySignedRoute(
            'api.verification.verify', 
            now()->addMinutes(30),  
            ['token' => base64_encode($provider->provider_name)] 
        );

        $name = $user->fullname ?? 'ລູກຄ້າທົ່ວໄປ';

        // 💡 ຖ້າທົດສອບແລ້ວເມວບໍ່ທັນເຂົ້າ Mailtrap ທັນທີ ໃຫ້ປ່ຽນ ->queue() ເປັນ ->send() ເດີ້ທ່ານ
        Mail::to($provider->provider_name)->send(new VerifyEmailMail($name, $verificationLink));

        return response()->json([
            'message' => 'ລະບົບໄດ້ສົ່ງ Link ຢືນຢັນໃໝ່ໄປຫາອີເມວຂອງທ່ານແລ້ວທ່ານ!'
        ], 200);
    }
}