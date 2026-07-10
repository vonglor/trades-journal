<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\UserProvider;
use Illuminate\Support\Facades\Validator;
use App\Models\Role;
use App\Mail\VerifyEmailMail; // 👈 ຢ່າລືມ Import ໄຟລ໌ນີ້ເຂົ້າມາດ້ານເທິງສຸດ
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Mail\ResetPasswordMail;
use App\Models\Notification;

class AuthApiController extends Controller
{



    public function checkIdentity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identity' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $identity = trim($request->identity);

        // ເຊັກໃນຕາຕະລາງ user_providers ວ່າຄ່ານີ້ຖືກໃຊ້ໄປແລ້ວບໍ
        $exists = DB::table('user_providers')
            ->where('provider_name', $identity)
            ->exists();

        return response()->json(['exists' => $exists]);
    }
    // 🔑 1. API ສຳລັບສະໝັກສະມາຊິກ (Register)
    public function register(Request $request)
    {
        // ກວດສອບຄວາມຖືກຕ້ອງຂອງຂໍ້ມູນທີ່ສົ່ງມາ
        $request->validate([
            'fullname' => 'required|string|max:255',
            'identity' => 'required|email', // ເປັນໄດ້ທັງ email ຫຼື phone
            'password' => 'required|string|min:6',
        ]);

        $identity = trim($request->identity);
        $providerType = '';

        // ດັກເຊັກຮູບແບບວ່າເປັນ Email ຫຼື ເບີໂທ
        if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
            $providerType = 'email';
        } else {
            return response()->json([
                'errors' => ['identity' => ['ຮູບແບບອີເມວບໍ່ຖືກຕ້ອງ']]
            ], 422);
        }

        // ເຊັກຂໍ້ມູນຊ້ຳ
        $isDuplicate = UserProvider::where('provider_type', $providerType)
            ->where('provider_name', $identity)
            ->exists();

        if ($isDuplicate) {
            return response()->json(['errors' => ['identity' => ['ອີເມວນີ້ມີຢູ່ໃນລະບົບແລ້ວ']]], 422);
        }

        // 🚀 ເລີ່ມໃຊ້ DB Transaction
        DB::beginTransaction();

        try {
            // 1. ບັນທຶກລົງຕາຕະລາງ users
            // ✨ ສ້າງ UUID ໄວ້ຖ້າສຳລັບ User ໃໝ່
            $userId = (string) Str::uuid();
            $user = User::create([
                'id' => $userId,
                'fullname' => $request->fullname,
                'status' => 'inactive',
            ]);

            // 2. ບັນທຶກລົງຕາຕະລາງ user_accounts
            // 3. ບັນທຶກລົງຕາຕະລາງ user_providers (ເກັບທັງ email ແລະ ລະຫັດຜ່ານທີ່ hash ແລ້ວ)
            UserProvider::create([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'provider_type' => $providerType,
                'provider_name' => $identity, // ເກັບ email
                'provider_value' => Hash::make($request->password), // ເກັບ ລະຫັດຜ່ານ
                'is_verified' => false,
            ]);

            // 3. ຄົ້ນຫາ ຫຼື ສ້າງ Role 'customer'
            $role = Role::firstOrCreate(
                ['name' => 'user'],
                [
                    'id' => (string) Str::uuid(),
                    'description' => 'ລູກຄ້າທົ່ວໄປ'
                ]
            );

            // 4. ຜູກສິດລົງຕາຕະລາງ user_roles
            $user->roles()->attach($role->id);

            $notification = Notification::create([
                'id' => (string) Str::uuid(),
                'type' => 'registration',
                'title' => 'ສະມາຊິກໃໝ່: ' . $user->fullname . ' ໄດ້ລົງທະບຽນເຂົ້າຮ່ວມລະບົບ',
                'content' => 'ມີຜູ້ໃຊ້ງານໃໝ່ສະໝັກເຂົ້າມາໃນລະບົບ ກະລຸນາກວດສອບບົດບາດ.',
                'data' => json_encode([
                    'user_id' => $user->id,
                    'action_url' => '/dashboard/users' // ສົ່ງ url ໄປໃຫ້ React ໃຊ້ navigate
                ]),
                'is_read' => false
            ]);

            // ຢືນຢັນການບັນທຶກທັງໝົດລົງ Database
            DB::commit();

            $user->load(['roles', 'providers']); // 💡 ຕ້ອງມີບັນທັດນີ້!
            
            event(new \App\Events\UserRegisteredEvent($user, $notification)); // 🔔 ເປີດ Event ສົ່ງໄປໃຫ້ React ທີ່ກວດເຊັກ

           // 🟢 ປ່ຽນການສ້າງ Link ໃຫ້ມີໝົດອາຍຸພາຍໃນ 30 ນາທີ
            $verificationLink = URL::temporarySignedRoute(
                'api.verification.verify', // 👈 ຊື່ຂອງ Route (ເຮົາຕ້ອງໄປຕັ້ງຊື່ນີ້ໃນ api.php)
                now()->addMinutes(30),  // 👈 ກຳນົດເວລາໝົດອາຍຸ (30 ນາທີ)
                ['token' => base64_encode($identity)] // 👈 Parameter ທີ່ຕ້ອງການສົ່ງໄປ
            );
            // 🟢 2. ສັ່ງສົ່ງອີເມວຢືນຢັນຕົວຕົນເຂົ້າ Queue (ແລ່ນເບື້ອງຫຼັງ ບໍ່ເຮັດໃຫ້ໜ້າເວັບຊ້າ)
            Mail::to($identity)->send(new VerifyEmailMail($request->fullname, $verificationLink));

            return response()->json([
                'status' => 'success',
                'message' => 'ລົງທະບຽນສຳເລັດແລ້ວ',
                'user_id' => $user->id
            ], 201);

        } catch (\Exception $e) {
            // ຖ້າມີບ່ອນໃດບ່ອນໜຶ່ງພັງ ໃຫ້ຍົກເລີກທັງໝົດ
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'ເກີດຂໍ້ຜິດພາດໃນການບັນທຶກຂໍ້ມູນ: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'identity' => 'required',
            'password' => 'required',
        ]);

        // 🔍 1. ຄົ້ນຫາ Provider ຈາກອີເມວ ຫຼື ຕົວຕົນທີ່ສົ່ງມາ
        $provider = UserProvider::where('provider_name', $request->identity)->first();

        if (!$provider) {
            return response()->json(['message' => 'ບໍ່ພົບຜູ້ໃຊ້ນີ້ໃນລະບົບ'], 401);
        }

        // 🔍 2. ດຶງຂໍ້ມູນ User ພ້ອມກັບ Roles ທີ່ຜູກໄວ້
        $user = User::with('roles')->find($provider->user_id);

        // 🔒 3. ກວດສອບຄວາມຖືກຕ້ອງຂອງລະຫັດຜ່ານ (ທຽບກັບ provider_value)
        if (!$user || !Hash::check($request->password, $provider->provider_value)) {
            return response()->json(['message' => 'ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ'], 401);
        }

        // ⚠️ 4. ກວດສອບການຢືນຢັນອີເມວ (ປ່ຽນຈາກຫັກມາໃຊ້ $provider->is_verified ໂດຍກົງ)
        // ດຶງແຖວ 'email' ຂອງ user ຄົນນີ້ມາເຊັກວ່າ verify ຫຼືຍັງ
        $emailProvider = UserProvider::where('user_id', $user->id)
            ->where('provider_type', 'email')
            ->first();

        if ($emailProvider && !$emailProvider->is_verified) {
            return response()->json([
                'status' => 'unverified', // 🎯 ປ່ຽນໃຫ້ຕົງກັບ React ທີ່ກວດເຊັກຄຳວ່າ 'unverified'
                'message' => 'ບັນຊີຂອງທ່ານຍັງບໍ່ທັນໄດ້ຢືນຢັນອີເມວ ກະລຸນາຢືນຢັນກ່ອນເຂົ້າສູ່ລະບົບ',
                'email' => $emailProvider->provider_name
            ], 403);
        }

        // 🚫 5. ກວດສອບວ່າຖືກບລັອກ ຫຼື ລະງັບການໃຊ້ງານຫຼືບໍ່
        if ($user->status !== 'active') {
            return response()->json(['message' => 'ບັນຊີຂອງທ່ານຖືກລະງັບການໃຊ້ງານ'], 403);
        }

        // 🎟️ 6. ດຶງຊື່ສິດ (Role) ແລະ ສ້າງ Sanctum Token
        $roleName = $user->roles->first()?->name ?? 'user';
        $token = $user->createToken('auth_token', ["role:{$roleName}"])->plainTextToken;

        // 🎉 7. ສົ່ງຂໍ້ມູນກັບໄປຝັ່ງ Frontend (ປ່ຽນ $user->name ເປັນ $user->fullname)
        return response()->json([
            'status' => 'success',
            'message' => 'ເຂົ້າສູ່ລະບົບສຳເລັດ',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'fullname' => $user->fullname, // 🎯 ແກ້ໄຂບ່ວງນີ້ໃຫ້ຖືກຕາມ DB
                'role' => $roleName,
                'provider_type' => $provider->provider_type
            ]
        ], 200);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('providers');

        $emailAccount = $user->providers->where('provider_type', 'email')->first();
        $phoneAccount = $user->providers->where('provider_type', 'phone')->first();

        return response()->json([
            'id' => $user->id,
            'fullname' => $user->fullname,
            'status' => $user->status,
            'email' => $emailAccount ? $emailAccount->provider_name : '',
            'phone' => $phoneAccount ? $phoneAccount->provider_name : '',
        ]);
    }

    // 🔗 2. API ສຳລັບກົດລິ້ງຢືນຢັນຕົວຕົນ (Verify)
    // public function verify(Request $request)
    // {
    //     if (!$request->hasValidSignature()) {
    //         // ❌ ຖ້າ Link ໝົດອາຍຸ ໃຫ້ເຕະ (Redirect) ໄປໜ້າ Login ຂອງ React ພ້ອມແນບ status ໄປເຕືອນ
    //         return redirect()->to('http://localhost:5173/login?error=expired');
    //     }

    //     $token = $request->query('token');

    //     if (!$token) {
    //         return response()->json(['status' => 'error', 'message' => 'Token ບໍ່ຖືກຕ້ອງ'], 400);
    //     }

    //     // ຖອດລະຫັດ email ອອກມາຈາກ Token
    //     $email = base64_decode($token);

    //     // 🔍 ກວດສອບຂໍ້ມູນໃນ user_providers
    //     $provider = UserProvider::where('provider_name', $email)
    //         ->where('provider_type', 'email')
    //         ->first();

    //     if (!$provider) {
    //         return response()->json(['status' => 'error', 'message' => 'ບໍ່ພົບຂໍ້ມູນຜູ້ໃຊ້ໃນລະບົບ'], 404);
    //     }

    //     // 🔒 ອັບເດດສະຖານະໃຫ້ Active ແລະ Verified ພ້ອມກັນ
    //     DB::beginTransaction();
    //     try {
    //         // ອັບເດດຕາຕະລາງ users
    //         User::where('id', $provider->user_id)->update(['status' => 'active']);

    //         // ອັບເດດຕາຕະລາງ user_providers
    //         $provider->update(['is_verified' => true]);

    //         DB::commit();

    //         // 🎯 ພໍຢືນຢັນສຳເລັດ ໃຫ້ Redirect ເດັ້ງໄປຫາໜ້າ Login ຂອງຝັ່ງ React Frontend (Port 3000)
    //         return redirect()->to('http://localhost:5173/login?verified=true');
    //         // return redirect()->away('http://localhost:3000/login?verified=true');

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['status' => 'error', 'message' => 'ການ Verify ຜິດພາດ: ' . $e->getMessage()], 500);
    //     }
    // }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($request->email));

        // ເຊັກກ່ອນວ່າມີ Email ນີ້ໃນ user_accounts ບໍ່
        $provider = UserProvider::where('provider_type', 'email')
            ->where('provider_name', $email)
            ->first();

        if (!$provider) {
            return response()->json(['message' => 'ບໍ່ພົບອີເມວນີ້ໃນລະບົບ'], 404);
        }

        $user = User::find($provider->user_id);

        // ສ້າງ Token ແບບສຸ່ມ ແລະ ບັນທຶກລົງຕາຕະລາງ password_reset_tokens ມາດຕະຖານຂອງ Laravel
        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $token,
                'created_at' => now()
            ]
        );

        // 🎯 ສົ່ງອີເມວແບບ Queue ເບື້ອງຫຼັງ ບໍ່ຕ້ອງໃຫ້ User ນັ່ງລໍຖ້າ
        Mail::to($user)->queue(new ResetPasswordMail($user, $token));

        return response()->json(['message' => 'ລະບົບໄດ້ສົ່ງ Link ສຳລັບປ່ຽນລະຫັດຜ່ານໄປຫາອີເມວຂອງທ່ານແລ້ວທ່ານ!']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $email = strtolower(trim($request->email));

        // ກວດເຊັກ Token ວ່າຖືກຕ້ອງ ແລະ ກົງກັບ Email ບໍ່
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $request->token)
            ->first();

        if (!$resetRecord) {
            return response()->json(['message' => 'Link ປ່ຽນລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ ຫຼື ໝົດອາຍຸແລ້ວ'], 400);
        }

        // ອັບເດດລະຫັດຜ່ານໃໝ່ໃຫ້ User
        $account = UserProvider::where('provider_type', 'email')->where('provider_name', $email)->first();
        $user = User::find($account->user_id);
        $account->provider_value = Hash::make($request->password);
        $user->save();

        // ລຶບ Token ຖິ້ມເມື່ອໃຊ້ງານແລ້ວ
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['message' => 'ປ່ຽນລະຫັດຜ່ານໃໝ່ສຳເລັດແລ້ວ ທ່ານສາມາດເຂົ້າສູ່ລະບົບໄດ້ເລີຍ!']);
    }
}