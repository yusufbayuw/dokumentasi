<?php

namespace App\Services;

use App\Models\NetworkQualityMeasurement;

class NetworkQualityAdvice
{
    /**
     * Suggestions are hypotheses/operational checks, never a verified root
     * cause or an instruction to reconfigure network infrastructure.
     *
     * @return array{summary:string,steps:list<string>}
     */
    public static function forMeasurement(NetworkQualityMeasurement $measurement): array
    {
        if ($measurement->status === 'error' || $measurement->quality === 'unknown') {
            return [
                'summary' => 'Pengukuran tidak tersedia atau tidak dapat dipercaya; ini bukan bukti perangkat offline.',
                'steps' => [
                    'Periksa Cron, worker queue network-quality, dan log error job.',
                    'Pastikan target termasuk allowlist dan dapat dijangkau dari server monitoring.',
                    'Periksa kode error, alat ping, timeout, dan izin jaringan sebelum menilai kondisi layanan.',
                ],
            ];
        }

        if ($measurement->status === 'down') {
            $specific = match ($measurement->probe_type) {
                'dns' => 'Periksa resolver DNS yang digunakan server dan catatan DNS target.',
                'tcp' => 'Periksa service/port tujuan dan kebijakan firewall dari arah server monitoring.',
                'https' => 'Periksa endpoint HTTPS, sertifikat, dan kode respons yang diharapkan.',
                default => 'Periksa keterjangkauan IP, gateway, VLAN, dan kebijakan ICMP.',
            };

            return [
                'summary' => 'Target tidak terjangkau dari server monitoring pada pemeriksaan terakhir.',
                'steps' => [
                    'Uji ulang dari titik jaringan lain untuk membedakan gangguan target dan jalur monitor.',
                    $specific,
                    'Periksa histori hasil sebelum menentukan eskalasi atau menyatakan perangkat benar-benar offline.',
                ],
            ];
        }

        $loss = (float) ($measurement->packet_loss_pct ?? 0);
        $latency = (float) ($measurement->latency_ms ?? 0);
        $variation = (float) ($measurement->rtt_variation_ms ?? 0);
        $limits = config('network_quality.quality', []);

        if ($measurement->probe_type === 'icmp'
            && $measurement->packet_loss_pct !== null
            && $loss >= (float) ($limits['loss_degraded_pct'] ?? 1)) {
            return [
                'summary' => 'Ada packet loss pada sampel ICMP; indikasi stabilitas koneksi menurun.',
                'steps' => [
                    'Bandingkan hasil ping ke gateway lokal dan ke target lain pada jaringan yang sama.',
                    'Periksa utilisasi dan error port switch/router; pada Wi-Fi periksa interferensi serta kualitas sinyal.',
                    'Amati beberapa interval agar tidak mengambil keputusan dari sampel yang terlalu sedikit.',
                ],
            ];
        }

        if ($measurement->latency_ms !== null
            && $latency >= (float) ($limits['latency_degraded_ms'] ?? 150)) {
            return [
                'summary' => 'Waktu respons tinggi dibanding ambang monitoring yang dikonfigurasi.',
                'steps' => [
                    'Bandingkan waktu respons ke gateway, server internal, dan tujuan eksternal.',
                    'Periksa utilisasi link, perubahan jalur routing, dan kondisi service target.',
                    'Uji kembali pada periode trafik berbeda sebelum menyimpulkan sumber masalah.',
                ],
            ];
        }

        if ($measurement->probe_type === 'icmp'
            && $measurement->rtt_variation_ms !== null
            && $variation >= (float) ($limits['variation_degraded_ms'] ?? 50)) {
            return [
                'summary' => 'Variasi RTT antar-sampel meningkat; ini bukan ukuran jitter satu arah.',
                'steps' => [
                    'Periksa pola trafik sesaat, antrean link, dan kualitas sambungan.',
                    'Bandingkan beberapa interval dan target lain untuk memastikan pola berulang.',
                ],
            ];
        }

        return [
            'summary' => 'Hasil pemeriksaan terakhir berada dalam batas kualitas yang dikonfigurasi.',
            'steps' => [
                'Lanjutkan pemantauan tren; status baik pada satu interval tidak menjamin semua jalur selalu baik.',
                'Bandingkan hasil dengan jam sibuk dan target terkait bila pengguna melaporkan keluhan.',
            ],
        ];
    }

    /** @return array{heading:string,description:string,steps:list<string>} */
    public static function forCategory(string $quality): array
    {
        return match (NetworkQualitySnapshot::validQuality($quality)) {
            'bad' => [
                'heading' => 'Prioritaskan target berkualitas buruk',
                'description' => 'Lihat hasil terakhir dan gunakan rekomendasi per baris sebagai langkah diagnosis, bukan kesimpulan otomatis.',
                'steps' => ['Periksa loss dan RTT, lalu bandingkan titik gateway serta layanan lain.', 'Verifikasi berulang sebelum melakukan eskalasi atau perubahan konfigurasi.'],
            ],
            'degraded' => [
                'heading' => 'Amati degradasi yang berulang',
                'description' => 'Kualitas menurun belum tentu berarti layanan terputus.',
                'steps' => ['Bandingkan tren saat sibuk dan sepi.', 'Periksa kemungkinan kenaikan utilisasi jaringan atau latensi layanan.'],
            ],
            'unknown' => [
                'heading' => 'Pastikan monitoring sehat',
                'description' => 'Belum diketahui berbeda dengan perangkat mati; beberapa probe mungkin gagal dieksekusi.',
                'steps' => ['Periksa scheduler dan worker network-quality.', 'Periksa allowlist, kode error, serta konektivitas dari host pengukur.'],
            ],
            'good' => [
                'heading' => 'Pertahankan kondisi baik',
                'description' => 'Hasil terbaru di bawah ambang masalah; tetap pantau tren dan keluhan pengguna.',
                'steps' => ['Periksa kestabilan pada jam sibuk.', 'Gunakan histori untuk membandingkan perubahan kualitas.'],
            ],
            default => [
                'heading' => 'Ringkasan seluruh target',
                'description' => 'Menampilkan satu pengukuran terbaru untuk setiap target yang memiliki data dalam jendela aktif.',
                'steps' => ['Fokuskan penanganan pada kualitas buruk dan belum diketahui.', 'Buka aksi Rekomendasi per baris untuk langkah pemeriksaan yang relevan.'],
            ],
        };
    }
}
