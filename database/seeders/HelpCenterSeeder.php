<?php

namespace Database\Seeders;

use App\Models\AppPolicy;
use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

/**
 * Isi awal Pusat Bantuan:
 *   - Syarat & Ketentuan v1.0 dan Kebijakan Privasi v1.0 (kalau belum ada yang aktif)
 *   - FAQ dasar (kalau tabel help_articles masih kosong)
 *
 * Aman dijalankan berkali-kali (tidak menimpa data yang sudah ada).
 * Jalankan:  php artisan db:seed --class=HelpCenterSeeder
 *
 * PENTING: teks kebijakan ini TEMPLATE. Baca ulang & sesuaikan (nama perusahaan,
 * alamat, email kontak) di panel Super Admin → Kebijakan Aplikasi sebelum dipakai resmi.
 */
class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        $app = config('app.name', 'Aplikasi Absensi');

        $this->policy('terms_of_service', 'Syarat & Ketentuan Penggunaan', $this->terms($app));
        $this->policy('privacy_policy', 'Kebijakan Privasi', $this->privacy($app));

        if (HelpArticle::count() === 0) {
            $order = 0;
            foreach ($this->faqs() as [$category, $title, $content]) {
                HelpArticle::create([
                    'category'     => $category,
                    'title'        => $title,
                    'content'      => $content,
                    'sort_order'   => $order++,
                    'is_published' => true,
                ]);
            }
            $this->command?->info('FAQ dasar ditambahkan: ' . $order . ' artikel');
        } else {
            $this->command?->warn('FAQ dilewati (sudah ada isinya).');
        }
    }

    private function policy(string $type, string $title, string $content): void
    {
        if (AppPolicy::where('type', $type)->where('is_active', true)->exists()) {
            $this->command?->warn("{$title} dilewati (sudah ada versi aktif).");
            return;
        }

        AppPolicy::create([
            'type'         => $type,
            'title'        => $title,
            'content'      => $content,
            'version'      => '1.0',
            'is_active'    => true,
            'published_at' => now(),
        ]);

        $this->command?->info("{$title} v1.0 dibuat & aktif.");
    }

    private function terms(string $app): string
    {
        return <<<TXT
# Syarat & Ketentuan Penggunaan {$app}

Dengan membuat akun atau menggunakan {$app}, Anda menyetujui syarat & ketentuan di bawah ini. Mohon dibaca dengan saksama.

## 1. Definisi
- **Aplikasi**: layanan {$app} berupa aplikasi seluler dan panel web.
- **Perusahaan**: organisasi yang berlangganan dan mendaftarkan penggunanya.
- **HR / Admin**: pengguna yang mengelola data perusahaan dan langganan.
- **Karyawan**: pengguna yang didaftarkan oleh Perusahaan.

## 2. Akun
- Data yang Anda berikan harus benar dan terbaru.
- Anda bertanggung jawab menjaga kerahasiaan kata sandi. Jangan meminjamkan akun kepada orang lain.
- Segera hubungi HR atau buat Tiket Bantuan bila akun Anda digunakan tanpa izin.

## 3. Penggunaan absensi
- Absen hanya boleh dilakukan oleh pemilik akun, pada lokasi dan jam yang ditentukan Perusahaan.
- Dilarang memalsukan lokasi (fake GPS), memakai foto orang lain, atau cara lain untuk mengelabui sistem.
- Pelanggaran dapat dikenai sanksi oleh Perusahaan sesuai aturan internal masing-masing.

## 4. Langganan & pembayaran
- Perusahaan membayar biaya langganan sesuai paket yang dipilih.
- Setelah masa aktif berakhir, tersedia masa toleransi 3 hari. Setelah itu akses aplikasi untuk seluruh pengguna Perusahaan dikunci sampai langganan diperpanjang.
- Data tidak dihapus saat akses dikunci, dan akan kembali bisa dipakai setelah pembayaran berhasil.
- Pembayaran yang sudah berhasil tidak dapat dibatalkan, kecuali diatur lain dalam Kebijakan Pengembalian Dana.

## 5. Data milik Perusahaan
- Data absensi, izin, cuti, lembur, dan laporan adalah milik Perusahaan.
- Kami mengolah data tersebut atas nama Perusahaan sesuai Kebijakan Privasi.

## 6. Larangan
- Mengakses sistem tanpa izin, mengganggu server, atau mencoba membobol keamanan.
- Mengunggah konten yang melanggar hukum, SARA, atau pornografi.
- Menyalahgunakan fitur chat untuk pelecehan atau spam.

## 7. Ketersediaan layanan
Kami berusaha agar layanan selalu tersedia, namun dapat terjadi gangguan atau pemeliharaan. Kami tidak bertanggung jawab atas kerugian tidak langsung akibat gangguan tersebut.

## 8. Perubahan ketentuan
Ketentuan ini dapat diperbarui sewaktu-waktu. Versi terbaru selalu tersedia di menu Bantuan. Dengan tetap menggunakan aplikasi, Anda dianggap menyetujui perubahan tersebut.

## 9. Kontak
Pertanyaan seputar ketentuan ini dapat disampaikan melalui menu **Tiket Bantuan** di aplikasi.
TXT;
    }

    private function privacy(string $app): string
    {
        return <<<TXT
# Kebijakan Privasi {$app}

Kebijakan ini menjelaskan data apa yang kami kumpulkan, untuk apa, dan bagaimana kami melindunginya.

## 1. Data yang kami kumpulkan
- **Data akun**: nama, email, nomor HP, jabatan, foto profil.
- **Data absensi**: waktu masuk/pulang, foto selfie saat absen, dan lokasi (GPS) saat absen.
- **Data pengajuan**: izin, cuti, lembur, laporan harian/bulanan beserta lampirannya.
- **Data perangkat**: token notifikasi, jenis perangkat, dan versi aplikasi.
- **Percakapan**: pesan chat di dalam aplikasi dan isi Tiket Bantuan.

## 2. Penggunaan lokasi & kamera
- Lokasi hanya diambil saat Anda melakukan absen, untuk memastikan Anda berada di area kerja.
- Kamera dipakai untuk foto absen dan lampiran yang Anda pilih sendiri.
- Kami tidak melacak lokasi Anda di luar proses absen.

## 3. Tujuan penggunaan data
- Menjalankan fitur absensi dan pengajuan.
- Mengirim notifikasi (pengingat absen, persetujuan, jadwal, langganan).
- Membantu menyelesaikan kendala lewat Tiket Bantuan.
- Menjaga keamanan dan mencegah penyalahgunaan.

## 4. Siapa yang bisa melihat data
- HR / Admin Perusahaan Anda.
- Tim kami, hanya bila diperlukan untuk dukungan teknis.
- Kami **tidak menjual** data Anda kepada pihak lain.
- Layanan pihak ketiga yang kami pakai (misalnya penyedia notifikasi dan pembayaran) hanya menerima data seperlunya.

## 5. Penyimpanan & keamanan
- Data disimpan di server dengan akses terbatas dan koneksi terenkripsi (HTTPS).
- Data disimpan selama Perusahaan masih memakai layanan, atau sesuai ketentuan hukum yang berlaku.

## 6. Hak Anda
- Melihat dan memperbarui data profil Anda.
- Meminta koreksi data absensi melalui HR Perusahaan.
- Meminta penghapusan akun melalui HR Perusahaan atau Tiket Bantuan.

## 7. Perubahan kebijakan
Kebijakan ini dapat diperbarui. Versi terbaru selalu tersedia di menu Bantuan.

## 8. Kontak
Pertanyaan tentang privasi dapat disampaikan melalui menu **Tiket Bantuan** di aplikasi.
TXT;
    }

    /** @return array<int, array{0:string,1:string,2:string}> */
    private function faqs(): array
    {
        return [
            [
                'Absensi',
                'Kenapa saya tidak bisa absen masuk?',
                "Beberapa penyebab umum:\n\n- Anda berada di luar radius lokasi kantor. Dekati lokasi kantor lalu coba lagi.\n- GPS belum aktif atau akurasinya rendah. Nyalakan GPS mode akurasi tinggi.\n- Hari ini hari libur atau Anda sedang cuti.\n- Langganan perusahaan sedang tidak aktif.\n\nKalau masih gagal, buat Tiket Bantuan dan sertakan tangkapan layar pesan errornya."
            ],
            [
                'Absensi',
                'Saya lupa absen pulang, bagaimana?',
                "Hubungi HR perusahaan Anda untuk koreksi absen. HR bisa memperbaiki data absensi dari aplikasi."
            ],
            [
                'Absensi',
                'Kenapa lokasi saya terbaca salah?',
                "- Pastikan GPS aktif dan izin lokasi aplikasi diatur ke **Izinkan**.\n- Tunggu beberapa detik di tempat terbuka agar lokasi lebih akurat.\n- Matikan aplikasi pemalsu lokasi (fake GPS). Absen dengan lokasi palsu akan ditolak."
            ],
            [
                'Izin, Cuti & Lembur',
                'Bagaimana cara mengajukan izin atau cuti?',
                "1. Buka menu **Izin** atau **Cuti**.\n2. Pilih jenis dan tanggal.\n3. Isi alasan dan lampirkan bukti bila diminta.\n4. Kirim. Anda akan mendapat notifikasi saat HR menyetujui atau menolak."
            ],
            [
                'Izin, Cuti & Lembur',
                'Pengajuan saya ditolak, apa yang harus dilakukan?',
                "Buka detail pengajuan untuk melihat alasan penolakan dari HR. Perbaiki sesuai catatan lalu ajukan kembali, atau diskusikan langsung dengan HR lewat chat."
            ],
            [
                'Akun & Login',
                'Saya lupa kata sandi',
                "Gunakan tombol **Lupa kata sandi** di halaman masuk, atau minta HR perusahaan Anda untuk mengatur ulang kata sandi."
            ],
            [
                'Akun & Login',
                'Bagaimana cara mengganti foto profil atau kata sandi?',
                "Buka menu **Profil** → **Edit Profil** untuk mengganti foto dan data diri, atau **Ganti Password** untuk mengubah kata sandi."
            ],
            [
                'Notifikasi',
                'Saya tidak menerima notifikasi',
                "- Pastikan izin notifikasi untuk aplikasi sudah aktif di pengaturan HP.\n- Matikan mode hemat baterai untuk aplikasi ini.\n- Keluar lalu masuk kembali agar perangkat terdaftar ulang."
            ],
            [
                'Langganan & Pembayaran',
                'Kenapa aplikasi tiba-tiba terkunci?',
                "Aplikasi terkunci bila langganan perusahaan sudah berakhir dan masa toleransi 3 hari sudah lewat.\n\n- **HR**: buka menu Langganan lalu pilih paket untuk memperpanjang.\n- **Karyawan**: hubungi HR perusahaan Anda.\n\nData tidak hilang, dan akses langsung terbuka kembali setelah pembayaran berhasil."
            ],
            [
                'Langganan & Pembayaran',
                'Sudah bayar tapi status belum aktif',
                "Pembayaran Virtual Account biasanya terkonfirmasi dalam beberapa menit. Buka halaman pembayaran lalu tekan **Cek Status Pembayaran**. Kalau lebih dari 1 jam belum aktif, buat Tiket Bantuan kategori **Langganan & Pembayaran** dan sertakan bukti transfer."
            ],
            [
                'Lainnya',
                'Bagaimana cara menghubungi tim dukungan?',
                "Buka **Bantuan** → **Tiket Bantuan** → **Buat Tiket**. Jelaskan kendala Anda selengkapnya. Balasan dari tim akan masuk sebagai notifikasi."
            ],
        ];
    }
}
