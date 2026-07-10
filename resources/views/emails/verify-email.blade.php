<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ຢືນຢັນອີເມວ</title>
</head>
<body style="font-family: 'Phetsarath OT', sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px;">
        <h2 style="color: #333;">ສະບາຍດີ, {{ $fullname }}!</h2>
        <p>ຂອບໃຈທີ່ສະໝັກສະມາຊິກກັບເຮົາ. ກະລຸນາກົດປຸ່ມດ້ານລຸ່ມນີ້ເພື່ອຢືນຢັນອີເມວ ແລະ ເປີດໃຊ້ງານ Account ຂອງທ່ານ:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $url }}" style="background-color: #4f46e5; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
                🔗 ກົດຢືນຢັນອີເມວຢູ່ບ່ອນນີ້
            </a>
        </div>

        <p style="color: #666; font-size: 12px;">ຖ້າປຸ່ມດ້ານເທິງບໍ່ເຮັດວຽກ, ທ່ານສາມາດ Copy Link ນີ້ໄປວາງໃສ່ Browser ໄດ້ຕົງໆ:</p>
        <p style="color: #4f46e5; font-size: 12px; word-break: break-all;">{{ $url }}</p>
    </div>
</body>
</html>