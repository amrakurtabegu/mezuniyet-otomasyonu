<?php
/** Başvuru dönemleri: her ayın 1–15'i (1. dönem) ve 16'sı–ay sonu (2. dönem). */
const AY_ADLARI = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];

/** Verilen tarihin hangi başvuru dönemine düştüğünü hesaplar (ay uzunlukları ve artık yıl dahil). */
function donem_hesapla(DateTimeInterface $t): array {
    $y = (int)$t->format('Y'); $m = (int)$t->format('n'); $g = (int)$t->format('j'); $sonGun = (int)$t->format('t');
    if ($g <= 15) {
        return ['yil'=>$y, 'ay'=>$m, 'donem'=>1, 'baslangic'=>sprintf('%04d-%02d-01', $y, $m), 'bitis'=>sprintf('%04d-%02d-15', $y, $m)];
    }
    return ['yil'=>$y, 'ay'=>$m, 'donem'=>2, 'baslangic'=>sprintf('%04d-%02d-16', $y, $m), 'bitis'=>sprintf('%04d-%02d-%02d', $y, $m, $sonGun)];
}

/** Dönem satırını döndürür; yoksa otomatik ve "açık" olarak oluşturur (tohum verisi eksik olsa da sistem kullanılabilir kalır). */
function donem_getir(PDO $pdo, ?DateTimeInterface $t = null): array {
    $h = donem_hesapla($t ?? new DateTime('now'));
    $pdo->prepare('INSERT IGNORE INTO basvuru_donemleri (yil, ay, donem, baslangic, bitis, durum) VALUES (?,?,?,?,?,?)')
        ->execute([$h['yil'], $h['ay'], $h['donem'], $h['baslangic'], $h['bitis'], 'acik']);
    $s = $pdo->prepare('SELECT * FROM basvuru_donemleri WHERE yil=? AND ay=? AND donem=?');
    $s->execute([$h['yil'], $h['ay'], $h['donem']]);
    return $s->fetch();
}

/** Örn: "Ekim 2026 · 2. Dönem (16–31 Ekim)" */
function donem_etiketi(array $d): string {
    $ay = AY_ADLARI[(int)$d['ay']] ?? (string)$d['ay'];
    return $ay . ' ' . $d['yil'] . ' · ' . (int)$d['donem'] . '. Dönem (' . (int)substr($d['baslangic'], 8, 2) . '–' . (int)substr($d['bitis'], 8, 2) . ' ' . $ay . ')';
}
