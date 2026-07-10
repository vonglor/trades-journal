<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        // ດຶງຂໍ້ມູນທັງໝົດຈາກຕາຕະລາງ roles
        $roles = Role::latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'ດຶງຂໍ້ມູນບົດບາດ/ໜ້າທີ່ສໍາເລັດ',
            'data' => $roles
        ]);
    }

    public function store(Request $request)
    {
        // ກວດສອບຂໍ້ມູນທີ່ສົ່ງມາ
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string',
        ],
        [
            'name.required' => 'ກະລຸນາປ້ອນຊື່ບົດບາດ',
            'name.unique' => 'ຊື່ບົດບາດນີ້ໄດ້ຖືກນໍາໃຊ້ແລ້ວ',
        ]
        ); 
        $formattedName = Str::snake($request->name);
        // ສ້າງ Role ໃໝ່
        $role = Role::create([
            'name' => $formattedName,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ເພີ່ມບົດບາດໃໝ່ສໍາເລັດ',
            'data' => $role
        ]);
    }

    public function update(Request $request, $id)
    {
        // ກວດສອບຂໍ້ມູນທີ່ສົ່ງມາ
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $id,
            'description' => 'nullable|string',
        ],
        [
            'name.required' => 'ກະລຸນາປ້ອນຊື່ບົດບາດ',
            'name.unique' => 'ຊື່ບົດບາດນີ້ໄດ້ຖືກນໍາໃຊ້ແລ້ວ',
        ]
        );

        // ດຶງ Role ເພື່ອ Update
        $role = Role::findOrFail($id);
        $formattedName = Str::snake($request->name);
        $role->update([
            'name' => $formattedName,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ແກ້ໄຂບົດບາດສໍາເລັດ',
            'data' => $role
        ]);
    }

    public function destroy($id)
    {
        // ດຶງ Role ເພື່ອ Delete
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'ລຶບບົດບາດສໍາເລັດ'
        ]);
    }
}
