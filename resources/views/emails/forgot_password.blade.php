<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Reset Password - Kode OTP</title>
</head>

<body style="font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background-color:#f4f6f8;margin:0;padding:30px;">

    <div
        style="max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.08);overflow:hidden;">
        <div style="background-color:#4CAF50;padding:20px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:22px;">Reset Password</h1>
        </div>

        <div style="padding:30px;">
            <p style="font-size:16px;color:#333;">Halo,</p>

            <p style="font-size:15px;color:#555;line-height:1.6;">
                Kami menerima permintaan untuk mengatur ulang password akun Anda di
                <strong>{{ config('app.name') }}</strong>.
            </p>

            <p style="font-size:15px;color:#555;">Gunakan kode OTP berikut untuk melanjutkan proses reset password:</p>

            <div style="text-align:center;margin:25px 0;">
                <div
                    style="display:inline-block;background-color:#4CAF50;color:#fff;font-size:28px;font-weight:bold;padding:14px 35px;border-radius:8px;letter-spacing:4px;">
                    {{ $otp }}
                </div>
            </div>

            <p style="font-size:14px;color:#777;line-height:1.6;">
                Kode ini hanya berlaku selama <strong>5 menit</strong>.
                Jika Anda tidak merasa meminta reset password, abaikan email ini — password Anda tetap aman.
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
