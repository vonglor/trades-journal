<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class UserController extends Controller
{
    //
    public function index()
    {
        // ດຶງຂໍ້ມູນທັງໝົດຈາກຕາຕະລາງ users ພ້ອມກັ່ນຕອງ provider ພາຍໃນ
        $users = User::with([
            'roles',
            'providers'
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'ດຶງຂໍ້ມູນຜູ້ໃຊ້ສໍາເລັດ',
            'data' => $users
        ]);
    }

    public function updateProfile(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // 1. Validate ຂໍ້ມູນທີ່ສົ່ງມາ
        $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => 'required|email'
        ]);

        if ($request->has('phone') && !empty($request->phone)) {
            $request->validate([
                'phone' => [
                    'required',
                    'regex:/^(20[2579]\d{7})$/'
                ],
            ]);

            // ກວດເຊັກ ເບີໂທຊ້ຳ (ທີ່ບໍ່ແມ່ນຂອງໂຕເອງ)
            $phoneCheck = UserProvider::where('provider_type', 'phone')
                ->where('provider_name', $request->phone)
                ->where('user_id', '!=', $user->id)
                ->exists();
            if ($phoneCheck) {
                return response()->json(['message' => 'ເບີໂທລະສັບນີ້ຖືກໃຊ້ໃນລະບົບແລ້ວ'], 422);
            }
        }

        // ກວດເຊັກ Email ຊ້ຳ (ທີ່ບໍ່ແມ່ນຂອງໂຕເອງ)
        $emailCheck = UserProvider::where('provider_type', 'email')
            ->where('provider_name', $request->email)
            ->where('user_id', '!=', $user->id)
            ->exists();
        if ($emailCheck) {
            return response()->json(['message' => 'ອີເມວນີ້ຖືກໃຊ້ໃນລະບົບແລ້ວ'], 422);
        }

        // 🚀 ເປີດ Transaction ຕອນ Save
        DB::beginTransaction();
        try {
            // ອັບເດດຊື່ໃນຕາຕະລາງ users
            $user->update([
                'fullname' => $request->fullname
            ]);

            // ອັບເດດ ຫຼື ສ້າງຂໍ້ມູນ Email ໃນ user_providers
            UserProvider::updateOrCreate(
                ['user_id' => $user->id, 'provider_type' => 'email'],
                ['provider_name' => $request->email]
            );

            // ອັບເດດ ຫຼື ສ້າງຂໍ້ມູນ ເບີໂທ ໃນ user_providers
            if ($request->has('phone') && !empty($request->phone)) {
                // 🟢 ແກ້ໄຂ: ຮວມຂໍ້ມູນທີ່ຈະອັບເດດໄວ້ໃນ Array ທີ 2 ນຳກັນ
                UserProvider::updateOrCreate(
                    ['user_id' => $user->id, 'provider_type' => 'phone'], // 1. ເງື່ອນໄຂຄົ້ນຫາ
                    [
                        'provider_name' => $request->phone,               // 2. ຄ່າທີ່ຈະອັບເດດ/Insert
                        'provider_value' => null
                    ]
                );
            }

            DB::commit();
            return response()->json(['message' => 'ອັບເດດຂໍ້ມູນໂປຣໄຟລ໌ສຳເລັດ']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()], 500);
        }
    }
    public function passwordChange(Request $request)
    {
        try {
            // 1. ກວດສອບຄວາມຖືກຕ້ອງຂອງຂໍ້ມູນທີ່ສົ່ງມາ (Validation)
            // ⚠️ ຫ້າມຫຼົງພິມຍ່າງຫວ່າງ (Space) ທາງໜ້າຊື່ຟີວເດີ້ທ່ານ
            $request->validate([
                'new_password' => 'required|string|min:6|confirmed',
                // 'confirmed' ຈະໄປເຊັກໂດຍອັດຕະໂນມັດວ່າຕົງກັບຟີວ 'new_password_confirmation' ທີ່ສົ່ງມາຈາກ React ຫຼືບໍ່
            ], [
                'new_password.required' => 'ກະລຸນາກອກລະຫັດຜ່ານໃໝ່',
                'new_password.min' => 'ລະຫັດຜ່ານໃໝ່ຕ້ອງມີຢ່າງໜ້ອຍ 6 ຕົວອັກສອນ',
                'new_password.confirmed' => 'ລະຫັດຜ່ານໃໝ່ ແລະ ຢືນຢັນລະຫັດຜ່ານບໍ່ຕົງກັນ',
            ]);

            // 2. ດຶງຂໍ້ມູນ User ທີ່ກຳລັງ Login ຢູ່ໃນລະບົບດຽວນີ້
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // 4. ທຳການ Hash ລະຫັດຜ່ານໃໝ່ໃຫ້ປອດໄພ ແລະ ບັນທຶກລົງ Database
            $user->providers()->where('user_id', $user->id)->update([
                'provider_value' => Hash::make($request->new_password)
            ]);
            $user->save();

            // 5. ສົ່ງຜົນລ່ວງສຳເລັດກັບໄປຫາ React (Front-end)
            return response()->json([
                'status' => 'success',
                'message' => 'ປ່ຽນລະຫັດຜ່ານສຳເລັດຮຽບຮ້ອຍແລ້ວ'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ດັກ Error ກໍລະນີ Validation ບໍ່ຜ່ານ (ເຊັ່ນ ລະຫັດຜ່ານບໍ່ເຖິງ 6 ຕົວ ຫຼື ບໍ່ຕົງກັນ)
            return response()->json([
                'status' => 'error',
                'message' => collect($e->errors())->first()[0] // ດຶງເອົາຂໍ້ຄວາມ Error ຕົວທຳອິດອອກມາສະແດງ
            ], 422);

        } catch (\Exception $e) {
            // ດັກ Error ອື່ນໆ ທີ່ອາດເກີດຂຶ້ນກັບລະບົບ/Database
            return response()->json([
                'status' => 'error',
                'message' => 'ເກີດຂໍ້ຜິດພາດໃນລະບົບ: ' . $e->getMessage()
            ], 500);
        }
    }

    public function upgradeToSuperUser(Request $request)
    {
        // 1. Validate ຄ່າ ID ທີ່ສົ່ງມາຈາກ Frontend
        $request->validate([
            'id' => 'required|exists:users,id'
        ]);

        // ດຶງຂໍ້ມູນ User ທີ່ຕ້ອງການອັບເກຣດ
        $user = User::findOrFail($request->id);

        // 2. ເຊັກກ່ອນວ່າ User ເປັນ customer ແທ້ ຫຼື ບໍ່
        if (!$user->hasRole('user')) {
            return response()->json([
                'status' => 'error',
                'message' => 'ບັນຊີນີ້ບໍ່ໄດ້ຢູ່ໃນສະຖານະ User ບໍ່ສາມາດອັບເກຣດໄດ້'
            ], 400);
        }

        // 3. ເຊັກວ່າໃນລະບົບມີ Role 'owner' ຢູ່ແທ້ບໍ່
        $superUserRole = Role::where('name', 'super_user')->first();
        $userRole = Role::where('name', 'user')->first();
        if (!$superUserRole || !$userRole) {
            return response()->json([
                'status' => 'error',
                'message' => 'ບໍ່ພົບຂໍ້ມູນ Role ໃນລະບົບ (ກະລຸນາກວດສອບ Seeders ຂອງທ່ານ)'
            ], 500);
        }

        // 4. ເຮັດການອັບເກຣດສະຖານະ (ໃຊ້ Database Transaction ເພື່ອຄວາມປອດໄພ)
        DB::beginTransaction();
        try {
            // ລຶບ Role 'user' ອອກ
            $user->roles()->detach($userRole->id);

            // ເພີ່ມ Role 'super_user' ເຂົ້າໄປແທນ
            $user->roles()->attach($superUserRole->id);

            // 🟢 ລຶບ Token ເກົ່າທັງໝົດຂອງ User ອອກກ່ອນ ເພື່ອຄວາມປອດໄພ (Optional)
            $user->tokens()->delete();

            // 🟢 ສ້າງ Token ໃໝ່ ທີ່ມີ Scope ຂອງ Role 'super_user'
            $roleName = 'super_user';
            $token = $user->createToken('auth_token', ["role:{$roleName}"])->plainTextToken;

            // 🟢 ດຶງຂໍ້ມູນ Account Type ໃຫ້ຄືກັນກັບຕອນ Login 
            // (ສົມມຸດວ່າ User 1 ຄົນມີ 1 Account ຫຼັກ, ຖ້າມີຫຼາຍອັນ ໃຫ້ປ່ຽນເປັນ ->first() ຕາມຄວາມເໝາະສົມ)
            $provider = UserProvider::where('user_id', $user->id)->first();
            $providerType = $provider ? $provider->provider_type : null;

            DB::commit();

            // 5. ສົ່ງ Response ກັບໄປ Format ດຽວກັນກັບຕອນ Login ເລີຍ
            return response()->json([
                'status' => 'success',
                'message' => 'ອັບເກຣດເປັນເຈົ້າຂອງຫ້ອງແຖວສຳເລັດແລ້ວ',
                'token' => $token, // ສົ່ງ Token ໃໝ່ໄປນຳ
                'user' => [
                    'id' => $user->id,
                    'fullname' => $user->fullname,
                    'role' => $roleName, // 'super_user'
                    'provider_type' => $providerType
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'ເກີດຂໍ້ຜິດພາດໃນລະບົບ: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'fullname' => 'required|string|max:255',
            'role_id' => 'required|string|exists:roles,id',
            'email' => 'required|email'
        ]);

        if ($request->has('phone') && !empty($request->phone)) {
            $request->validate([
                'phone' => [
                    'required',
                    'regex:/^(20[2579]\d{7})$/'
                ],
            ]);

            // ກວດເຊັກ ເບີໂທຊ້ຳ (ທີ່ບໍ່ແມ່ນຂອງໂຕເອງ)
            $phoneCheck = UserProvider::where('provider_type', 'phone')
                ->where('provider_name', $request->phone)
                ->where('user_id', '!=', $user->id)
                ->exists();
            if ($phoneCheck) {
                return response()->json(['message' => 'ເບີໂທລະສັບນີ້ຖືກໃຊ້ໃນລະບົບແລ້ວ'], 422);
            }
        }

        // ກວດເຊັກ Email ຊ້ຳ (ທີ່ບໍ່ແມ່ນຂອງໂຕເອງ)
        $emailCheck = UserProvider::where('provider_type', 'email')
            ->where('provider_name', $request->email)
            ->where('user_id', '!=', $user->id)
            ->exists();
        if ($emailCheck) {
            return response()->json(['message' => 'ອີເມວນີ້ຖືກໃຊ້ໃນລະບົບແລ້ວ'], 422);
        }


        DB::beginTransaction();
        try {
            // ອັບເດດຊື່
            $user->update([
                'fullname' => $request->fullname
            ]);

            // ອັບເດດ Role (ລຶບອັນເກົ່າອອກ ແລ້ວໃສ່ອັນໃໝ່ເຂົ້າໄປ)
            $newRole = Role::where('id', $request->role_id)->first();
            $user->roles()->sync([$newRole->id]); // sync ຈະຈັດການລຶບອັນເກົ່າ ແລະ ເພີ່ມອັນໃໝ່ໃຫ້ເອງ

             // ອັບເດດ ຫຼື ສ້າງຂໍ້ມູນ Email ໃນ user_providers
            UserProvider::updateOrCreate(
                ['user_id' => $user->id, 'provider_type' => 'email'],
                ['provider_name' => $request->email]
            );

            // ອັບເດດ ຫຼື ສ້າງຂໍ້ມູນ ເບີໂທ ໃນ user_providers
            if ($request->has('phone') && !empty($request->phone)) {
                // 🟢 ແກ້ໄຂ: ຮວມຂໍ້ມູນທີ່ຈະອັບເດດໄວ້ໃນ Array ທີ 2 ນຳກັນ
                UserProvider::updateOrCreate(
                    ['user_id' => $user->id, 'provider_type' => 'phone'], // 1. ເງື່ອນໄຂຄົ້ນຫາ
                    [
                        'provider_name' => $request->phone,               // 2. ຄ່າທີ່ຈະອັບເດດ/Insert
                        'provider_value' => null
                    ]
                );
            }
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'ອັບເດດຂໍ້ມູນຜູ້ໃຊ້ງານສຳເລັດແລ້ວ'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'ເກີດຂໍ້ຜິດພາດ: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'status' => 'required|in:active,inactive,suspended'
        ]);

        $user->status = $request->status;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'ອັບເດດສະຖານະຜູ້ໃຊ້ງານສຳເລັດແລ້ວ'
        ]);
    }

    // 4. ຟັງຊັນລຶບຜູ້ໃຊ້ງານ (Delete)
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        DB::beginTransaction();
        try {
            // ລຶບຄວາມສຳພັນໃນຕາຕະລາງ user_roles ກ່ອນ (foreign key)
            $user->roles()->detach();

            // ລຶບຂໍ້ມູນໃນ user_providers (ອີເມວ/ເບີໂທ)
            $user->providers()->delete();

            // ລຶບຂໍ້ມູນຜູ້ໃຊ້
            $user->delete();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'ລຶບຂໍ້ມູນຜູ້ໃຊ້ງານຮຽບຮ້ອຍແລ້ວ'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'ບໍ່ສາມາດລຶບຂໍ້ມູນໄດ້: ' . $e->getMessage()
            ], 500);
        }
    }

}
