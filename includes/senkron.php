<?php
/**
 * Üniversite servisinden (şimdilik mock) gelen öğrenci verisini yerel tablolara yazar. Tekrar çalıştırmak güvenlidir
 * (UPSERT): yinelenen kayıt oluşmaz. Dönüş: [true, ''] veya [false, 'profil_yok'|'danisman_yok'|'teknik_hata'].
 * Öğrenci bu verileri arayüzden DEĞİŞTİREMEZ; yalnızca bu fonksiyon yazar.
 */
function ogrenci_senkronize(PDO $pdo, int $kullaniciId, string $no, string $kaynak = 'mock'): array {
    $ws = ogrenci_bilgisi_getir($no);
    if (!$ws) return [false, 'profil_yok'];
    $d = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_no = ? AND rol = 'danisman'");
    $d->execute([$ws['danisman_no']]);
    $danismanId = $d->fetchColumn();
    if (!$danismanId) return [false, 'danisman_yok'];

    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO ogrenciler (kullanici_id, ogrenci_no, ad_soyad, gano, bolum, program, danisman_id) VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE ad_soyad=VALUES(ad_soyad), gano=VALUES(gano), bolum=VALUES(bolum), program=VALUES(program), danisman_id=VALUES(danisman_id)')
            ->execute([$kullaniciId, $no, $ws['ad'] . ' ' . $ws['soyad'], $ws['akademik']['gano'] ?? null, $ws['bolum'], $ws['program'] ?? null, $danismanId]);
        $s = $pdo->prepare('SELECT id FROM ogrenciler WHERE kullanici_id = ?');
        $s->execute([$kullaniciId]);
        $ogId = (int)$s->fetchColumn();

        if (!empty($ws['akademik'])) {
            $a = $ws['akademik'];
            $pdo->prepare('INSERT INTO akademik_kayitlari (ogrenci_id, gano, tamamlanan_akts, gereken_akts, kaynak, guncelleme_tarihi) VALUES (?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE gano=VALUES(gano), tamamlanan_akts=VALUES(tamamlanan_akts), gereken_akts=VALUES(gereken_akts), kaynak=VALUES(kaynak), guncelleme_tarihi=NOW()')
                ->execute([$ogId, $a['gano'] ?? null, $a['tamamlanan_akts'] ?? null, $a['gereken_akts'] ?? null, $kaynak]);
        }
        $st = $pdo->prepare('INSERT INTO staj_kayitlari (ogrenci_id, tur, durum, kaynak) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE durum=VALUES(durum), kaynak=VALUES(kaynak)');
        foreach ($ws['stajlar'] ?? [] as $tur => $durum) $st->execute([$ogId, $tur, $durum, $kaynak]);
        $de = $pdo->prepare('INSERT INTO ders_kayitlari (ogrenci_id, ders_kodu, ders_adi, akts, zorunlu, durum, kaynak) VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE ders_adi=VALUES(ders_adi), akts=VALUES(akts), zorunlu=VALUES(zorunlu), durum=VALUES(durum), kaynak=VALUES(kaynak)');
        foreach ($ws['dersler'] ?? [] as $x) $de->execute([$ogId, $x['kod'], $x['ad'], $x['akts'] ?? null, $x['zorunlu'] ?? 1, $x['durum'], $kaynak]);
        $ek = $pdo->prepare('INSERT INTO ek_kosullar (ogrenci_id, kosul_kodu, ad, durum, kaynak) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE ad=VALUES(ad), durum=VALUES(durum), kaynak=VALUES(kaynak)');
        foreach ($ws['ek_kosullar'] ?? [] as $x) $ek->execute([$ogId, $x['kod'], $x['ad'], $x['durum'], $kaynak]);
        $pdo->commit();
        return [true, ''];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] Senkronizasyon hatası (' . $no . '): ' . $ex->getMessage());
        return [false, 'teknik_hata'];
    }
}
