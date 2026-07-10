<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ຕັ້ງຄ່າລະຫັດຜ່ານໃໝ່</title>
</head>
<body style="font-family: 'Noto Sans Lao', sans-serif; background-color: #f3f4f6; padding: 20px;">
    <div style="max-width: 500px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <h2 style="color: #0f2d37; text-align: center;">🔒 ປ່ຽນລະຫັດຜ່ານຂອງທ່ານ</h2>
        <p>ສະບາຍດີທ່ານ <strong>{{ $user->fullname }}</strong>,</p>
        <p>ລະບົບໄດ້ຮັບຄຳຮ້ອງຂໍປ່ຽນລະຫັດຜ່ານສຳລັບບັນຊີແອັບຈັດການການເທຣດຂອງທ່ານ. ກະລຸນາກົດປຸ່ມດ້ານລຸ່ມນີ້ເພື່ອຕັ້ງຄ່າລະຫັດຜ່ານໃໝ່:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="http://localhost:5173/reset-password/{{ $token }}?email={{ urlencode($user->getEmailForVerification()) }}" 
               style="background-color: #0a7067; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">
               ປ່ຽນລະຫັດຜ່ານ
            </a>
        </div>

        <p style="color: #ef4444; font-size: 13px;">* ໝາຍເຫດ: Link ນີ້ຈະມີອາຍຸການໃຊ້ງານ 60 ນາທີ. ຖ້າທ່ານບໍ່ໄດ້ເປັນຄົນຮ້ອງຂໍ ທ່ານສາມາດປ່ອຍຜ່ານອີເມວນີ້ໄດ້ເລີຍ.</p>
        <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0;">
        <p style="font-size: 12px; color: #6b7280; text-align: center;">© 2026 ລະບົບຈັດການຫ້ອງແຖວ. All rights reserved.</p>
    </div>
</body>
</html>