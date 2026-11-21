<?php
/**
 * ÜNİVERSİTE WEB SERVİSİ SİMÜLASYONU (DEMO VERİ)
 *
 * Bu dosyadaki TÜM kayıtlar kurgusaldır; gerçek öğrenci/ders/AKTS bilgisi DEĞİLDİR.
 * Ders kodları ve "gereken AKTS = 240" değeri yalnızca örnektir; bölümden teyit edilmelidir.
 * Bilgi İşlem'den gerçek servis alındığında yalnızca bu dosyadaki ogrenci_bilgisi_getir() değiştirilir
 * (cURL ile); sistemin geri kalanı etkilenmez. Örnek:
 *   $ch = curl_init('https://servis.universite.edu.tr/ogrenci/' . $no);
 *   curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ...']]);
 *   $json = json_decode(curl_exec($ch), true);
 *
 * Dönüş biçimi: ad, soyad, bolum, program, danisman_no,
 *   akademik (gano, tamamlanan_akts, gereken_akts) veya null,
 *   stajlar (yaz1|yaz2|ime => durum), dersler[], ek_kosullar[]
 */
function mock_dersler(string $bitirme = 'gecti'): array {
    return [
        ['kod'=>'BLM201', 'ad'=>'Veri Yapıları (DEMO)',       'akts'=>6,  'zorunlu'=>1, 'durum'=>'gecti'],
        ['kod'=>'BLM301', 'ad'=>'Yazılım Mühendisliği (DEMO)', 'akts'=>6,  'zorunlu'=>1, 'durum'=>'gecti'],
        ['kod'=>'BLM401', 'ad'=>'Bitirme Projesi (DEMO)',      'akts'=>10, 'zorunlu'=>1, 'durum'=>$bitirme],
    ];
}

function mock_kayit(string $ad, string $soyad, string $danisman, ?array $akademik, array $stajlar, array $dersler): array {
    return ['ad'=>$ad, 'soyad'=>$soyad, 'bolum'=>'Bilgisayar Mühendisliği', 'program'=>'Lisans', 'danisman_no'=>$danisman,
            'akademik'=>$akademik, 'stajlar'=>$stajlar, 'dersler'=>$dersler, 'ek_kosullar'=>[]];
}

function mock_ogrenci_listesi(): array {
    $tam = ['yaz1'=>'onaylandi', 'yaz2'=>'onaylandi', 'ime'=>'onaylandi'];
    $ak = fn(float $g, int $t, int $r = 240) => ['gano'=>$g, 'tamamlanan_akts'=>$t, 'gereken_akts'=>$r];
    return [
        // Danışman D001
        '2021001' => mock_kayit('Ahmet',  'Yılmaz', 'D001', $ak(3.12, 240), $tam, mock_dersler()),                       // tüm koşullar tamam
        '2021002' => mock_kayit('Elif',   'Kaya',   'D001', $ak(2.45, 240), $tam, mock_dersler()),                       // yalnızca GANO < 2.50
        '2021003' => mock_kayit('Mehmet', 'Demir',  'D001', $ak(1.85, 225),                                              // GANO + AKTS + staj + ders eksik
                                ['yaz1'=>'onaylandi', 'yaz2'=>'tamamlanmadi', 'ime'=>'tamamlandi_onay_bekliyor'], mock_dersler('kaldi')),
        '2021004' => mock_kayit('Zeynep', 'Arslan', 'D001', $ak(2.90, 240), $tam, mock_dersler()),                       // tüm koşullar tamam
        '2021005' => mock_kayit('Can',    'Öztürk', 'D001', $ak(3.40, 240), $tam, mock_dersler()),                       // tüm koşullar tamam
        // Danışman D002
        '2021006' => mock_kayit('Selin',  'Aydın',  'D002', $ak(3.05, 240),                                              // yalnızca İME onay bekliyor
                                ['yaz1'=>'onaylandi', 'yaz2'=>'onaylandi', 'ime'=>'tamamlandi_onay_bekliyor'], mock_dersler()),
        '2021007' => mock_kayit('Burak',  'Çelik',  'D002', $ak(2.75, 232), $tam, mock_dersler('alinmadi')),             // AKTS eksik + zorunlu ders eksik
        '2021008' => mock_kayit('Deniz',  'Koç',    'D002', null, [], []),                                               // akademik bilgi hiç aktarılmamış
        '2021009' => mock_kayit('Ece',    'Polat',  'D002', $ak(3.20, 240), $tam, mock_dersler()),                       // tüm koşullar tamam
    ] + mock_ek_ogrenciler() + mock_toplu_ogrenciler();
}

/** Sunum için genişletilmiş KURGUSAL öğrenci listesi. Tür: uygun | gano | akts | staj | ime | ders | yok */
function mock_ek_ogrenciler(): array {
    $satirlar = [
        // Danışman D001
        ['2021010','Ayşe','Güneş','D001','uygun',3.28], ['2021011','Murat','Başaran','D001','uygun',2.67], ['2021012','Gamze','Tekin','D001','uygun',3.55],
        ['2021013','Emre','Yalçın','D001','uygun',2.88], ['2021014','Büşra','Erdem','D001','uygun',3.01], ['2021015','Oğuz','Kurt','D001','uygun',2.54],
        ['2021016','Hakan','Sezer','D001','gano',2.31],  ['2021017','Nur','Çakır','D001','staj',2.95],    ['2021018','Kaan','Aksoy','D001','uygun',3.72],
        ['2021019','Ilgın','Özkan','D001','akts',2.80],
        // Danışman D002
        ['2021020','Berk','Yıldız','D002','uygun',3.15], ['2021021','Merve','Çınar','D002','uygun',2.71], ['2021022','Tolga','Doğan','D002','uygun',3.46],
        ['2021023','Sude','Karakaya','D002','ders',2.99], ['2021024','Efe','Bozkurt','D002','uygun',2.62], ['2021025','İrem','Kaplan','D002','uygun',3.33],
        ['2021026','Yusuf','Er','D002','ime',3.08],      ['2021027','Pelin','Acar','D002','yok',null],
        // Danışman D003
        ['2021030','Onur','Tunç','D003','uygun',3.19],   ['2021031','Seda','Uçar','D003','uygun',2.58],   ['2021032','Arda','Kılıç','D003','uygun',3.64],
        ['2021033','Damla','Eren','D003','uygun',2.85],  ['2021034','Cem','Şimşek','D003','uygun',3.02], ['2021035','Tuğçe','Aslan','D003','gano',2.12],
        ['2021036','Barış','Taş','D003','uygun',2.93],   ['2021037','Melis','Korkmaz','D003','uygun',3.50],
    ];
    $tam = ['yaz1'=>'onaylandi', 'yaz2'=>'onaylandi', 'ime'=>'onaylandi'];
    $liste = [];
    foreach ($satirlar as [$no, $ad, $soyad, $dan, $tur, $g]) {
        $ak = $g === null ? null : ['gano'=>$g, 'tamamlanan_akts'=>$tur === 'akts' ? 228 : 240, 'gereken_akts'=>240];
        $staj = match ($tur) {
            'staj' => ['yaz1'=>'onaylandi', 'yaz2'=>'devam_ediyor', 'ime'=>'onaylandi'],
            'ime'  => ['yaz1'=>'onaylandi', 'yaz2'=>'onaylandi', 'ime'=>'tamamlandi_onay_bekliyor'],
            'yok'  => [],
            default => $tam,
        };
        $dersler = $tur === 'yok' ? [] : mock_dersler($tur === 'ders' ? 'kaldi' : 'gecti');
        $liste[$no] = mock_kayit($ad, $soyad, $dan, $ak, $staj, $dersler);
    }
    return $liste;
}

/** Büyük demo için otomatik üretilen KURGUSAL öğrenciler (2021100 ...). Adedi değiştirmek için $adet'i artırın. */
function mock_toplu_ogrenciler(int $adet = 90): array {
    $adlar = ['Ali','Ayşe','Mehmet','Fatma','Mustafa','Zeynep','Emre','Elif','Burak','Esra','Hasan','Merve','Yasin','Seda','Kerem','Derya','Oğuz','Gizem','Tarık','Ceren'];
    $soyadlar = ['Yıldırım','Kılıç','Şahin','Aydoğan','Erdoğan','Polat','Koçak','Arslan','Çetin','Güler','Aktaş','Sarı','Duman','Özdemir','Bulut','Karaca','Ersoy','Tan','Yurt','Bayram'];
    $danismanlar = ['D001', 'D002', 'D003'];
    $turler = ['uygun','uygun','uygun','uygun','uygun','uygun','uygun','uygun','uygun','gano','akts','staj','ime','ders'];
    $tam = ['yaz1'=>'onaylandi', 'yaz2'=>'onaylandi', 'ime'=>'onaylandi'];
    $liste = [];
    for ($i = 0; $i < $adet; $i++) {
        $no = (string)(2021100 + $i);
        $tur = ($i % 30 === 29) ? 'yok' : $turler[$i % count($turler)];
        $g = $tur === 'gano' ? round(1.80 + (($i * 13) % 65) / 100, 2) : round(2.50 + (($i * 37) % 150) / 100, 2);
        $ak = $tur === 'yok' ? null : ['gano'=>$g, 'tamamlanan_akts'=>$tur === 'akts' ? 228 : 240, 'gereken_akts'=>240];
        $staj = match ($tur) {
            'staj' => ['yaz1'=>'onaylandi', 'yaz2'=>'devam_ediyor', 'ime'=>'onaylandi'],
            'ime'  => ['yaz1'=>'onaylandi', 'yaz2'=>'onaylandi', 'ime'=>'tamamlandi_onay_bekliyor'],
            'yok'  => [],
            default => $tam,
        };
        $dersler = $tur === 'yok' ? [] : mock_dersler($tur === 'ders' ? 'kaldi' : 'gecti');
        $liste[$no] = mock_kayit($adlar[$i % 20], $soyadlar[($i * 7) % 20], $danismanlar[$i % 3], $ak, $staj, $dersler);
    }
    return $liste;
}

/** Öğrenci numarası için servis kaydını döndürür; yoksa null. */
function ogrenci_bilgisi_getir(string $no): ?array {
    return mock_ogrenci_listesi()[$no] ?? null;
}