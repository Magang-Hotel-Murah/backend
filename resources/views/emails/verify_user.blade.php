<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Verifikasi Email</title>
</head>

<body>
    <p>Halo {{ $name }},</p>
    <p>Terima kasih telah mendaftar. Klik tombol di bawah ini untuk memverifikasi email Anda:</p>

    <p style="text-align:center;">
        <a href="{{ $verifyUrl }}"
            style="display:inline-block;background-color:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;">
            Verifikasi Email
        </a>
    </p>

    <p>Atau salin URL berikut ke browser Anda:</p>
    <p>{{ $verifyUrl }}</p>

    <p>Link ini akan kadaluarsa dalam 60 menit.</p>
</body>

</html>
