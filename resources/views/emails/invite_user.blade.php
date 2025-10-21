<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Undangan Bergabung</title>
</head>

<body
    style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f8; margin: 0; padding: 30px;">

    <div
        style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); overflow: hidden;">
        <div style="background-color: #4CAF50; padding: 20px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 22px;">Undangan Bergabung</h1>
        </div>

        <div style="padding: 30px;">
            <p style="font-size: 16px; color: #333;">Halo <strong>{{ $email }}</strong>,</p>

            <p style="font-size: 15px; color: #555; line-height: 1.6;">
                Terima kasih telah bergabung dengan <strong>{{ config('app.name') }}</strong>!
                Kami senang Anda menjadi bagian dari tim kami.
                Klik tombol di bawah ini untuk menerima undangan Anda:
            </p>

            <div style="text-align:center; margin: 30px 0;">
                <a href="{{ $inviteUrl }}"
                    style="display:inline-block;background-color:#4CAF50;color:#fff;font-weight:bold;padding:12px 28px;text-decoration:none;border-radius:6px;transition: background-color 0.3s;">
                    ✅ Terima Undangan
                </a>
            </div>

            <p style="font-size: 14px; color: #777; line-height: 1.6;">
                Jika tombol di atas tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:
            </p>

            <div
                style="background-color:#f9f9f9; border-left:4px solid #4CAF50; padding:10px 15px; word-break: break-all; font-size:14px; color:#333;">
                {{ $inviteUrl }}
            </div>

            <p style="font-size: 13px; color: #888; margin-top: 25px;">
                ⏰ Link ini akan kadaluarsa dalam <strong>60 menit</strong>.
            </p>

            <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;">

            <p style="font-size: 13px; color: #999; text-align: center;">
                Email ini dikirim secara otomatis oleh sistem {{ config('app.name') }}.
                Mohon untuk tidak membalas email ini.
            </p>
        </div>
    </div>
</body>

</html>
