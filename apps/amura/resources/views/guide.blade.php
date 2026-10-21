<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FBF7F1">
    <title>Cara buka Kejutan Kado {{ $theme['name'] }}</title>
    <meta name="description" content="Cara membuka Kejutan Kado {{ $theme['name'] }}: masuk dengan nomor resi, pilih 3 kado, ambil 1 hadiah, lalu redeem lewat WhatsApp.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/guide.css') }}?v={{ filemtime(public_path('css/guide.css')) }}">
    <style>
        :root { --p: {{ $theme['p'] }}; --d: {{ $theme['d'] }}; --tint: {{ $theme['t'] }}; --stage: {{ $theme['st2'] }}; }
    </style>
</head>
<body>
<div class="lp">
    <div class="wrap">
        <header class="top">
            <span class="brand">
                <span class="tile" aria-hidden="true">{{ $theme['initial'] }}</span>
                <span><b>Kejutan Kado {{ $theme['name'] }}</b><span>Poin &amp; Hadiah</span></span>
            </span>
            <a class="signin" href="{{ route('customer.login') }}">Sudah punya PIN? Masuk</a>
        </header>

        <main>
        <section class="hero">
            <div>
                <h1>Paket {{ $theme['name'] }} sudah {{ $theme['call'] }} terima? Ini cara buka kadonya.</h1>
                <p class="lead">Setiap pesanan {{ $theme['name'] }} lewat WhatsApp dapat 2 kesempatan buka kado. Siapkan paketnya dan HP yang {{ $theme['call'] }} pakai waktu memesan. Semuanya di browser, tanpa unduh aplikasi.</p>
                <a class="cta" href="{{ route('play.form') }}">Buka kado sekarang</a>
                <p class="fine">@if ($startsOn)Berlaku untuk pesanan mulai {{ $startsOn }}. @endif Kado baru bisa dibuka setelah paket tercatat diterima.</p>
            </div>
            <div class="hero-shot">
                <div class="phone lg"><img src="{{ $shots('papan') }}" alt="Layar Gatcha {{ $theme['name'] }}: 24 kado, pilih 3" width="300" height="649" fetchpriority="high"></div>
            </div>
        </section>
    </div>

    <section class="chap tone">
        <div class="wrap">
            <div class="chap-head">
                <span class="chap-no">Bagian 1</span>
                <div>
                    <h2>Masuk dengan resi, sekali saja</h2>
                    <p>Pertama kali butuh nomor resi. Setelah PIN dibuat, pesanan berikutnya cukup nomor HP dan PIN.</p>
                </div>
            </div>
            <div class="pair">
                <div class="step">
                    <div class="phone sm"><img src="{{ $shots('resi') }}" alt="Halaman isi nomor resi dan nomor HP" width="228" height="493" loading="lazy"></div>
                    <div>
                        <span class="num">1</span>
                        <h3>Isi nomor resi dan nomor HP</h3>
                        <p>Nomor resi ada di label paket, di bawah barcode. Nomor HP harus sama dengan nomor waktu memesan.</p>
                        <div class="label" aria-hidden="true">
                            <small>LABEL PAKET</small>
                            <div class="bars"></div>
                            <span class="awb">{{ $theme['awb_example'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="step">
                    <div class="phone sm"><img src="{{ $shots('pin') }}" alt="Halaman buat PIN 4 angka" width="228" height="493" loading="lazy"></div>
                    <div>
                        <span class="num">2</span>
                        <h3>Buat PIN 4 angka</h3>
                        <p>Hindari angka kembar atau berurutan seperti 1234. Simpan sendiri ya, CS {{ $theme['name'] }} tidak pernah menanyakan PIN.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="chap stage">
        <div class="wrap">
            <div class="chap-head">
                <span class="chap-no">Bagian 2</span>
                <div>
                    <h2>Pilih 3 kado, ambil 1 hadiah</h2>
                    <p>Diulang 2 kali, satu untuk tiap kesempatan. Semua kado berisi hadiah.</p>
                </div>
            </div>
            <p class="swipe">Geser ke samping untuk langkah berikutnya</p>
            <div class="reel">
                <div class="step">
                    <div class="phone sm"><img src="{{ $shots('pilih-3') }}" alt="Tiga kado dipilih dari 24" width="228" height="493" loading="lazy"></div>
                    <div>
                        <span class="num">3</span>
                        <h3>Ketuk 3 kado</h3>
                        <p>Bebas pilih yang mana saja, lalu tekan Buka 3 kado. Isinya baru diundi saat dibuka.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="phone sm"><img src="{{ $shots('pilih-1') }}" alt="Tiga kado terbuka, satu dipilih" width="228" height="493" loading="lazy"></div>
                    <div>
                        <span class="num">4</span>
                        <h3>Ambil 1 yang paling {{ $theme['call'] }} mau</h3>
                        <p>Dua kado lainnya hangus. Pilihan tidak bisa diubah setelah diambil.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="phone sm"><img src="{{ $shots('hasil') }}" alt="Hasil kesempatan pertama dan tombol lanjut ke kesempatan kedua" width="228" height="493" loading="lazy"></div>
                    <div>
                        <span class="num">5</span>
                        <h3>Lanjut ke kesempatan kedua</h3>
                        <p>Papan diisi 24 kado baru. Mau main nanti juga bisa, kesempatannya tersimpan.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="chap">
        <div class="wrap">
            <div class="chap-head">
                <span class="chap-no">Bagian 3</span>
                <div>
                    <h2>Ambil hadiahnya lewat WhatsApp</h2>
                    <p>Semua hadiah tersimpan di menu Hadiahku. Tidak ada yang dikirim otomatis sebelum {{ $theme['call'] }} redeem.</p>
                </div>
            </div>

            <div class="spot">
                <div class="phone"><img src="{{ $shots('redeem') }}" alt="Detail hadiah dengan tombol Redeem sekarang" width="260" height="562" loading="lazy"></div>
                <div>
                    <span class="num">6</span>
                    <p class="big">Tekan Redeem sekarang, lalu kirim pesannya.</p>
                    <p class="body">WhatsApp terbuka dengan pesan yang sudah terisi kode hadiah. Kirim dari nomor HP pemesan supaya CS {{ $theme['name'] }} bisa mencocokkan.</p>
                    <div class="deadline">
                        <div><b>{{ $redeemDays }} hari</b><span>batas redeem hadiah biasa</span></div>
                        <div><b>{{ $bigPrizeDays }} hari</b><span>batas redeem hadiah utama</span></div>
                    </div>
                    <p class="fine">Dihitung sejak hadiah didapat. Lewat dari batas, hadiah hangus.</p>
                </div>
            </div>

            <div class="spot flip">
                <div class="phone"><img src="{{ $shots('katalog') }}" alt="Katalog tukar poin dengan saldo dan harga poin" width="260" height="562" loading="lazy"></div>
                <div>
                    <span class="num">7</span>
                    <p class="big">Kado poin masuk saldo. Kumpulkan, lalu tukar.</p>
                    <p class="body">Nilai kado poin mengikuti nilai belanja {{ $theme['call'] }}. Mulai {{ number_format($minRedeem, 0, ',', '.') }} poin, saldo bisa ditukar dengan hadiah di katalog. Hadiah dari tukar poin juga di-redeem lewat WhatsApp dalam {{ $catalogDays }} hari.</p>
                    <p class="fine">Poin berlaku {{ $validityMonths }} bulan sejak didapat.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="chap tone">
        <div class="wrap">
            <div class="lists">
                <div>
                    <h2 class="list-title">Yang bisa keluar dari kado</h2>
                    @if ($prizes)
                        <p class="sub">Hadiah utama jumlahnya terbatas tiap bulan.</p>
                        <ul class="rows">
                            @foreach ($prizes as $prize)
                                <li @class(['main' => $prize['main']])>{{ $prize['name'] }}<span>{{ $prize['note'] }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="sub">Daftar hadiah sedang disiapkan. Cek lagi nanti, atau tanya BA {{ $theme['name'] }}.</p>
                    @endif
                </div>
                <div>
                    @if ($catalog && $catalogItems->isNotEmpty())
                        <h2 class="list-title">Katalog tukar poin, {{ $catalogMonth }}</h2>
                        <p class="sub">Isi katalog berganti tiap bulan. Ongkir hadiah ditanggung penerima.</p>
                        <ul class="rows">
                            @foreach ($catalogItems as $item)
                                <li>{{ $item->name }}<span class="pts">{{ number_format($item->points_cost, 0, ',', '.') }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <h2 class="list-title">Katalog tukar poin</h2>
                        <p class="sub">Katalog bulan ini belum terbit. Poin {{ $theme['call'] }} tetap tersimpan sampai katalog berikutnya.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="chap">
        <div class="wrap">
            <div class="chap-head">
                <span class="chap-no">Aturan</span>
                <div><h2>Yang perlu {{ $theme['call'] }} tahu</h2></div>
            </div>
            <dl class="rules">
                <div><dt>Siapa yang ikut</dt><dd>Pesanan {{ $theme['name'] }} lewat WhatsApp. Pesanan dari marketplace belum bisa ikut.</dd></div>
                @if ($period)
                    <div><dt>Periode</dt><dd>Pesanan yang dibuat {{ $period }}. Pesanan di luar periode itu tidak ikut.</dd></div>
                @endif
                <div><dt>Kapan bisa main</dt><dd>Setelah paket tercatat diterima. Kurir kadang butuh beberapa jam untuk memperbarui status.</dd></div>
                <div><dt>Kesempatan</dt><dd>2 per pesanan. Tiap kesempatan: pilih 3 kado, ambil 1.</dd></div>
                <div><dt>Batas redeem</dt><dd>{{ $redeemDays }} hari sejak didapat, hadiah utama {{ $bigPrizeDays }} hari.</dd></div>
                <div><dt>Poin</dt><dd>Berlaku {{ $validityMonths }} bulan. Minimal tukar {{ number_format($minRedeem, 0, ',', '.') }} poin.</dd></div>
            </dl>
        </div>
    </section>

    <section class="chap flush">
        <div class="wrap">
            <div class="chap-head">
                <span class="chap-no">Kendala</span>
                <div><h2>Kalau ada yang tidak jalan</h2></div>
            </div>
            <div class="help">
                <div>
                    <h3>Paket sudah sampai, tapi ditolak</h3>
                    <p>Status paket belum diperbarui kurir. Coba lagi beberapa jam kemudian, atau chat BA {{ $theme['name'] }} yang mengirim link.</p>
                </div>
                <div>
                    <h3>Lupa PIN</h3>
                    <p>Di halaman masuk, ketuk Lupa PIN. CS {{ $theme['name'] }} mengirim PIN sementara lewat WhatsApp, lalu {{ $theme['call'] }} buat PIN baru.</p>
                </div>
                <div>
                    <h3>Pesan lewat marketplace</h3>
                    <p>Belum bisa ikut. Kejutan Kado hanya untuk pesanan lewat WhatsApp {{ $theme['name'] }}.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="wrap">
        <section class="end">
            <div>
                <h2>Paketnya sudah di tangan?</h2>
                <p>Siapkan nomor resi, lalu buka 2 kado {{ $theme['call'] }}. Belum pesan bulan ini? Chat BA {{ $theme['name'] }} yang biasa melayani {{ $theme['call'] }}.</p>
            </div>
            <a class="cta light" href="{{ route('play.form') }}">Buka kado sekarang</a>
        </section>
    </div>
        </main>
</div>
</body>
</html>
