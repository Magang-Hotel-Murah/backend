# 🏨 Magang Hotel Murah — Backend

Backend service untuk proyek **Magang Hotel Murah**, dibangun dengan **Laravel 12** dan **MySQL**.  
Menyediakan API untuk manajemen pengguna, reservasi hotel, transaksi, dan fitur pendukung lainnya.

---

## ⚡️ Quick Start

Ikuti langkah-langkah berikut untuk menjalankan proyek secara lokal:

```bash
# 1. Clone repository
git clone https://github.com/Magang-Hotel-Murah/backend.git
cd backend

# 2. Install dependency PHP
composer install

# 3. Copy file .env (buat salinan dari .env.example)
cp .env.example .env

# 4. Generate APP_KEY
php artisan key:generate

# 5. Buat database MySQL (ubah sesuai user/password lokal)
mysql -u root -p -e "CREATE DATABASE db_magang;"

# 6. Jalankan migrasi & seeder
php artisan migrate --seed

# 7. Jalankan server development
php artisan serve
