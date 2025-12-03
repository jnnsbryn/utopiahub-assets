### ✨ Fitur Utama

- 🔐 **Login Manual Aman**  
  Masukkan Public Key & password akun `crp.is` langsung di browser (data tidak disimpan di server).

- 💳 **Saldo Semua Aset**  
  Tampilkan saldo lengkap: **CRP, USDT, UUSD, DAI** — termasuk aset dengan saldo 0.

- 📈 **Harga Real-time vs USDT**  
  Ambil data langsung dari `/market/ticker` — tampilkan `Last`, `Ask`, dan `Bid` untuk semua pasangan berbasis USDT.

- 🆔 **Public Key Lengkap**  
  Alamat akun ditampilkan utuh (tidak dipotong), mudah disalin untuk verifikasi atau integrasi.

- 🚪 **Logout Bersih**  
  Session diakhiri via `/user/logout` — aman dan sesuai alur resmi API.

---

### 🛠️ Teknologi

- **Bahasa**: PHP 7.4+ (tanpa framework — ringan & mudah di-deploy)
- **API**: Integrasi penuh dengan [`crp.is`](https://crp.is) (Utopia Exchange)
- **UI**: HTML + CSS minimalis — tema **Matrix-green** (`#00FF41`) di latar hitam, responsif untuk mobile & desktop
- **Host**: Bisa dijalankan di **localhost** (XAMPP, PHP built-in server) atau shared hosting

---
