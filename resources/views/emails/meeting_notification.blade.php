<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Notifikasi Meeting</title>
</head>

<body style="font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background-color:#f4f6f8;margin:0;padding:30px;">

    <div
        style="max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.08);overflow:hidden;">

        <div style="background-color:#4CAF50;padding:20px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:22px;">Notifikasi Meeting</h1>
        </div>

        <div style="padding:30px;">
            <p style="font-size:16px;color:#333;">Halo,</p>

            <p style="font-size:15px;color:#555;line-height:1.6;">
                {{ $messageText }}
            </p>

            <p style="font-size:14px;color:#777;margin-top:25px;line-height:1.6;">
                Mohon periksa jadwal meeting Anda melalui sistem <strong>{{ config('app.name') }}</strong>.
                Pastikan Anda hadir sesuai waktu yang telah ditentukan.
            </p>

            <hr style="border:none;border-top:1px solid #eee;margin:25px 0;">

            <p style="font-size:13px;color:#999;text-align:center;">
                Email ini dikirim secara otomatis oleh sistem {{ config('app.name') }}.
                Mohon untuk tidak membalas email ini.
            </p>
        </div>

    </div>

</body>

</html>
