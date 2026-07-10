<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification; // 🟢 ໃຊ້ຕົວນີ້ເປັນຫຼັກໃນການ Queue

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // 1. 📢 ບອກ Laravel ວ່າຕາຕະລາງໃນ Postgres ຂອງເຮົາຊື່ "users"
    protected $table = 'users';

    // 2. 🔑 ບອກວ່າມັນໃຊ້ UUID ເປັນ Key (ບໍ່ແມ່ນຕົວເລກ 1, 2, 3)
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    // 3. 📝 ກໍານົດ Field ທີ່ຍອມໃຫ້ກອກຂໍ້ມູນໄດ້
    protected $fillable = [
        'id',
        'fullname',
        'status',
    ];

    public function strategies()
    {
        return $this->hasMany(Strategy::class, 'user_id');
    }

    // 4. 🔗 ຜູກຄວາມສຳພັນກັບຕາຕະລາງ user_providers (ຖ້າຕ້ອງການດຶງຂໍ້ມູນໃນອະນາຄົດ)
    public function providers()
    {
        return $this->hasMany(UserProvider::class, 'user_id', 'id');
    }

    // 🔗 ຜູກວ່າ User 1 ຄົນ ສາມາດມີໄດ້ຫຼາຍ Role
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    // 💡 Helper ສໍາລັບເຊັກສິດໃນ Middleware / Gates
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    // 🟢 1. ແກ້ໄຂ: ບອກ Laravel ວ່າ ອີເມວສຳລັບສົ່ງ Link ປ່ຽນລະຫັດ ຫຼື ຢືນຢັນ ແມ່ນຟີວໃດ
    public function getEmailForVerification()
    {
        // ປ່ຽນຈາກ account_value ມາເປັນ provider_name
        $emailProvider = $this->providers()->where('provider_type', 'email')->first();
        return $emailProvider ? $emailProvider->provider_name : null;
    }

    // 🟢 2. ແກ້ໄຂ: ບອກໃຫ້ mark ອີເມວເປັນ verified
    public function markEmailAsVerified()
    {
        $emailAccount = $this->providers()->where('provider_type', 'email')->first();
        if ($emailAccount) {
            $emailAccount->is_verified = true;
            return $emailAccount->save();
        }
        return false;
    }

    // 🟢 3. ແກ້ໄຂ: virtual property 'email' ໃຫ້ Laravel ເຫັນ
    public function getEmailAttribute()
    {
        // ປ່ຽນຈາກ account_value ມາເປັນ provider_name ເຊັ່ນກັນ
        $emailAccount = $this->providers()->where('provider_type', 'email')->first();
        return $emailAccount ? $emailAccount->provider_name : null;
    }

    // 🎯 ໄມ້ຕາຍສຸດທ້າຍ: ບັງຄັບໃຫ້ລະບົບ Notification ສົ່ງອີເມວໄປຫາຄ່ານີ້ໂດຍກົງ
    public function routeNotificationForMail($notification)
    {
        return $this->getEmailForVerification();
    }

    /**
     * 🎯 ສົ່ງອີເມວຢືນຢັນຕົວຕົນຜ່ານລະບົບ Queue (ຕໍ່ຍອດຈາກລະບົບເດີມຂອງທ່ານ)
     * ຈະຊ່ວຍໃຫ້ໜ້າເວັບ React ຕອບສະໜອງໄດ້ໄວຂຶ້ນ ໂດຍບໍ່ຕ້ອງລໍຖ້າການສົ່ງອີເມວ
     */
    public function sendEmailVerificationNotification()
    {
        // 💡 ສັ່ງໃຫ້ໂຍນ Notification ນີ້ເຂົ້າ Queue ໂດຍໃຫ້ດີເລ (Delay) 2 ວິນາທີ ເພື່ອຄວາມສະຖຽນ
        $notification = new VerifyEmailNotification;

        // ສັ່ງໃຫ້ Notify ຕາມປົກກະຕິ (Laravel ຈະຈັບເຂົ້າ Queue ເອງອັດຕະໂນມັດ ຖ້າ QUEUE_CONNECTION=database)
        $this->notify($notification);
    }
}