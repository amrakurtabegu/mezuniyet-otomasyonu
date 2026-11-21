<?php
/**
 * İŞ AKIŞI (WORKFLOW) KATMANI
 * Durum akışı:
 *   danisman_bekliyor → (danışman) komisyon_bekliyor | iade | danisman_red
 *   iade → (öğrenci yeniden gönderir) danisman_bekliyor
 *   komisyon_bekliyor → (5 üyeden 3 onay) komisyon_onay | (3 ret) komisyon_red
 *   komisyon_onay → (ilişik kesme: tüm birimler tamam) iliski_kesme_tamam
 *   iliski_kesme_tamam → (bölüm başkanı son onayı) son_onayli
 *   son_onayli → (mezuniyet listesi oluşturulunca) listede
 *   listede → belge_hazirlaniyor → eimza_surecinde → tamamlandi
 * Her durum değişikliği basvuru_gecmisi tablosuna yazılır; önemli işlemler islem_loglari'na da kaydedilir.
 * Tüm çok adımlı işlemler veritabanı işlemi (transaction) içinde yapılır.
 */
const ILISKI_BIRIMLERI = ['kutuphane' => 'Kütüphane', 'diger_birimler' => 'Diğer Birimler (öğrenci işleri vb.)'];
const PASIF_DURUMLAR = ['danisman_red', 'komisyon_red'];

function durum_degistir(PDO $pdo, int $bid, string $yeni, ?int $kid = null, ?string $not = null): void {
    $s = $pdo->prepare('SELECT durum FROM basvurular WHERE id = ?');
    $s->execute([$bid]);
    $eski = $s->fetchColumn();
    $pdo->prepare('UPDATE basvurular SET durum = ? WHERE id = ?')->execute([$yeni, $bid]);
    $pdo->prepare('INSERT INTO basvuru_gecmisi (basvuru_id, eski_durum, yeni_durum, kullanici_id, aciklama) VALUES (?,?,?,?,?)')
        ->execute([$bid, $eski === false ? null : $eski, $yeni, $kid, $not !== null ? mb_substr($not, 0, 500) : null]);
}

/** O anki uygunluk raporunu başvurunun "uygunluk anlık görüntüsü" olarak saklar. */
function basvuru_uygunluk_kaydet(PDO $pdo, int $bid, array $rapor): void {
    $ins = $pdo->prepare('INSERT INTO basvuru_uygunluk (basvuru_id, kosul_kodu, durum, mesaj, kontrol_tarihi) VALUES (?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE durum=VALUES(durum), mesaj=VALUES(mesaj), kontrol_tarihi=NOW()');
    foreach ($rapor['kontroller'] as $c) $ins->execute([$bid, $c['kod'], $c['durum'], mb_substr($c['mesaj'], 0, 500)]);
}

/* ------------------------------------------------------------------ ÖĞRENCİ BAŞVURUSU */

/**
 * Öğrencinin şu an başvurabilip başvuramayacağını ve nedenini döndürür.
 * kod: mezun | devam_ediyor | akademik_yok | iletisim | donem_kapali | eksik_kosul | uygun
 */
function basvuru_on_kosullari(PDO $pdo, array $og): array {
    $donem = donem_getir($pdo);
    $rapor = uygunluk_raporu($pdo, (int)$og['id']);
    $sonuc = fn(bool $izin, string $kod, string $mesaj) => ['izin'=>$izin, 'kod'=>$kod, 'mesaj'=>$mesaj, 'donem'=>$donem, 'rapor'=>$rapor];

    $m = $pdo->prepare('SELECT COUNT(*) FROM mezunlar WHERE ogrenci_no = ?');
    $m->execute([$og['ogrenci_no']]);
    if ((int)$m->fetchColumn() > 0) return $sonuc(false, 'mezun', 'Zaten mezun olarak kayıtlısınız; yeni başvuru yapamazsınız.');

    $a = $pdo->prepare("SELECT durum FROM basvurular WHERE ogrenci_id = ? AND durum NOT IN ('danisman_red','komisyon_red') ORDER BY id DESC LIMIT 1");
    $a->execute([$og['id']]);
    $aktif = $a->fetchColumn();
    if ($aktif) return $sonuc(false, 'devam_ediyor', 'Devam eden bir başvurunuz var (' . durum_etiketi((string)$aktif) . '). Mevcut başvuru sonuçlanmadan yenisi oluşturulamaz.');

    if (!$rapor['akademik_var']) return $sonuc(false, 'akademik_yok', 'Akademik bilgileriniz henüz sisteme aktarılmamış; mezuniyet koşulları doğrulanamıyor. "Akademik Bilgilerimi Yenile" düğmesini deneyin; sorun sürerse bölüm sekreterliğine başvurun.');
    if (!$og['eposta'] || !$og['telefon'] || !$og['calisma_durumu']) return $sonuc(false, 'iletisim', 'Önce iletişim ve çalışma bilgilerinizi kaydedin.');
    if ($donem['durum'] !== 'acik') return $sonuc(false, 'donem_kapali', donem_etiketi($donem) . ' için mezuniyet başvuruları şu anda kapalıdır. Başvurular bir sonraki açık dönemde alınacaktır.');

    if (uygunluk_politikasi($pdo) === 'engelle' && !$rapor['uygun']) {
        return $sonuc(false, 'eksik_kosul', 'Mezuniyet koşulları tamamlanmadığı için başvuru yapılamaz. Eksikler: ' . implode(' ', $rapor['eksikler']));
    }
    return $sonuc(true, 'uygun', $rapor['uygun'] ? 'Tüm koşullar sağlanıyor; başvuru yapabilirsiniz.'
        : 'Eksik koşullarınız var. Mevcut politika gereği başvurunuz alınır ancak danışman eksikler giderilmeden onaylayamaz.');
}

function basvuru_olustur(PDO $pdo, array $og): array {
    $on = basvuru_on_kosullari($pdo, $og);
    if (!$on['izin']) return [false, $on['mesaj']];
    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO basvurular (ogrenci_id, oturum_id, donem_id, mezuniyete_uygun) VALUES (?,NULL,?,?)')
            ->execute([$og['id'], $on['donem']['id'], $on['rapor']['uygun'] ? 1 : 0]);
        $bid = (int)$pdo->lastInsertId();
        basvuru_uygunluk_kaydet($pdo, $bid, $on['rapor']);
        $pdo->prepare('INSERT INTO basvuru_gecmisi (basvuru_id, eski_durum, yeni_durum, kullanici_id, aciklama) VALUES (?,?,?,?,?)')
            ->execute([$bid, null, 'danisman_bekliyor', null, 'Başvuru oluşturuldu: ' . donem_etiketi($on['donem'])]);
        log_islem($bid, 'Mezuniyet incelemesi başlatıldı (' . $og['ogrenci_no'] . ', ' . donem_etiketi($on['donem']) . ')');
        $pdo->commit();
        return [true, 'Mezuniyet incelemeniz başlatıldı. Başvuru dönemi: ' . donem_etiketi($on['donem']) . '.'];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($ex->getCode() === '23000') return [false, 'Bu başvuru dönemi için zaten bir başvurunuz bulunuyor. Reddedilen başvurular için bir sonraki dönemi bekleyin.'];
        error_log('[mezuniyet-otomasyonu] basvuru_olustur: ' . $ex->getMessage());
        return [false, 'Başvuru kaydedilemedi (teknik hata). Lütfen tekrar deneyin.'];
    }
}

function basvuru_tekrar_gonder(PDO $pdo, array $og, int $bid): array {
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT id FROM basvurular WHERE id = ? AND ogrenci_id = ? AND durum = 'iade' FOR UPDATE");
        $s->execute([$bid, $og['id']]);
        if (!$s->fetchColumn()) { $pdo->rollBack(); return [false, 'Bu başvuru yeniden gönderilemez (iade edilmiş bir başvurunuz değil).']; }
        $rapor = uygunluk_raporu($pdo, (int)$og['id']);
        if (uygunluk_politikasi($pdo) === 'engelle' && !$rapor['uygun']) {
            $pdo->rollBack();
            return [false, 'Eksik koşullarınız giderilmeden başvuru yeniden gönderilemez. Eksikler: ' . implode(' ', $rapor['eksikler'])];
        }
        $pdo->prepare('UPDATE basvurular SET mezuniyete_uygun = ? WHERE id = ?')->execute([$rapor['uygun'] ? 1 : 0, $bid]);
        basvuru_uygunluk_kaydet($pdo, $bid, $rapor);
        durum_degistir($pdo, $bid, 'danisman_bekliyor', null, 'Öğrenci iade edilen başvuruyu yeniden gönderdi');
        log_islem($bid, 'İade edilen başvuru yeniden gönderildi');
        $pdo->commit();
        return [true, 'Başvurunuz danışmana yeniden gönderildi.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] basvuru_tekrar_gonder: ' . $ex->getMessage());
        return [false, 'İşlem tamamlanamadı (teknik hata).'];
    }
}

/* ------------------------------------------------------------------ DANIŞMAN KARARLARI */

/**
 * Tek bir başvuru için danışman kararı. Hem bireysel hem toplu onayda KULLANILAN TEK yoldur:
 * sunucu tarafında atama, durum ve (onay için) anlık uygunluk yeniden doğrulanır.
 */
function danisman_karar_uygula(PDO $pdo, int $danismanId, int $bid, string $karar, string $gerekce, ?int $topluId = null): array {
    if (!in_array($karar, ['onay', 'iade', 'red'], true)) return [false, 'Geçersiz karar.'];
    if ($karar !== 'onay' && $gerekce === '') return [false, 'İade/ret için gerekçe zorunludur.'];
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT b.id, b.ogrenci_id FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id
                            WHERE b.id = ? AND o.danisman_id = ? AND b.durum = 'danisman_bekliyor' FOR UPDATE");
        $s->execute([$bid, $danismanId]);
        $b = $s->fetch();
        if (!$b) { $pdo->rollBack(); return [false, 'Başvuru size atanmış değil veya artık danışman onayı beklemiyor.']; }
        if ($karar === 'onay') {
            $r = uygunluk_raporu($pdo, (int)$b['ogrenci_id']);
            if (!$r['uygun']) {   // eksik koşullu başvuru: yalnızca politika "izin_ver" ise ve danışman GEREKÇE yazarsa komisyona gider; eksikler tamamlanmış sayılmaz, komisyonda görünür
                if (uygunluk_politikasi($pdo) !== 'izin_ver') { $pdo->rollBack(); return [false, 'Öğrenci mezuniyet koşullarını sağlamıyor, onaylanamaz. ' . implode(' ', $r['eksikler'])]; }
                if ($gerekce === '') { $pdo->rollBack(); return [false, 'Eksik koşulları olan başvuruyu onaylamak için gerekçe zorunludur (eksikler komisyona görünür kalır). ' . implode(' ', $r['eksikler'])]; }
            }
        }
        $yeni = ['onay' => 'komisyon_bekliyor', 'iade' => 'iade', 'red' => 'danisman_red'][$karar];
        $pdo->prepare('INSERT INTO danisman_kararlari (basvuru_id, danisman_id, karar, gerekce, toplu_islem_id) VALUES (?,?,?,?,?)')
            ->execute([$bid, $danismanId, $karar, $gerekce !== '' ? $gerekce : null, $topluId]);
        durum_degistir($pdo, $bid, $yeni, $danismanId, 'Danışman kararı: ' . $karar . ($gerekce !== '' ? ' — ' . $gerekce : '') . ($topluId ? ' (toplu işlem #' . $topluId . ')' : ''));
        log_islem($bid, 'Danışman kararı: ' . $karar . ' → durum: ' . $yeni . ($topluId ? ' [toplu #' . $topluId . ']' : ''), $danismanId);
        $pdo->commit();
        return [true, 'Karar kaydedildi: ' . durum_etiketi($yeni) . '.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] danisman_karar_uygula: ' . $ex->getMessage());
        return [false, 'Karar kaydedilemedi (teknik hata); başvuruda değişiklik yapılmadı.'];
    }
}

/**
 * Toplu onay: her başvuru kendi işlemi içinde işlenir; hatalı/uygunsuz olanlar atlanır ve raporlanır,
 * yarım kalmış kayıt bırakılmaz.
 */
function toplu_onay_uygula(PDO $pdo, int $danismanId, array $idler): array {
    $idler = array_values(array_unique(array_filter(array_map('intval', $idler), fn($x) => $x > 0)));
    $pdo->prepare('INSERT INTO toplu_islemler (danisman_id, secilen_sayisi) VALUES (?,?)')->execute([$danismanId, count($idler)]);
    $tid = (int)$pdo->lastInsertId();
    $bilgi = $pdo->prepare('SELECT o.ogrenci_no, o.ad_soyad FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE b.id = ? AND o.danisman_id = ?');
    $sonuclar = []; $onay = 0; $atlanan = 0;
    foreach ($idler as $bid) {
        $bilgi->execute([$bid, $danismanId]);
        $o = $bilgi->fetch();   // başka danışmanın öğrencisi ise bilgi sızdırılmaz
        [$ok, $m] = danisman_karar_uygula($pdo, $danismanId, $bid, 'onay', '', $tid);
        $ok ? $onay++ : $atlanan++;
        $sonuclar[] = ['basvuru_id'=>$bid, 'ogrenci_no'=>$o['ogrenci_no'] ?? '-', 'ad_soyad'=>$o['ad_soyad'] ?? '(size atanmamış başvuru)', 'ok'=>$ok, 'mesaj'=>$m];
    }
    $pdo->prepare('UPDATE toplu_islemler SET onaylanan_sayisi = ?, atlanan_sayisi = ? WHERE id = ?')->execute([$onay, $atlanan, $tid]);
    log_islem(null, "Toplu onay #$tid: $onay onaylandı, $atlanan atlandı", $danismanId);
    return ['id'=>$tid, 'sonuclar'=>$sonuclar, 'onaylanan'=>$onay, 'atlanan'=>$atlanan];
}

/* ------------------------------------------------------------------ KOMİSYON OYLAMASI */

function iliski_kesme_olustur(PDO $pdo, int $bid): void {
    $ins = $pdo->prepare('INSERT IGNORE INTO iliski_kesme_kontrolleri (basvuru_id, birim) VALUES (?,?)');
    foreach (array_keys(ILISKI_BIRIMLERI) as $birim) $ins->execute([$bid, $birim]);
}

/** Dönüş: [ok, mesaj, yeniDurum|null] */
function komisyon_oy_ver(PDO $pdo, int $uyeId, int $bid, string $karar, string $aciklama = '', ?int $topluId = null): array {
    if (!in_array($karar, ['onay', 'red'], true)) return [false, 'Geçersiz karar.', null];
    $r = $pdo->prepare("SELECT rol FROM kullanicilar WHERE id = ? AND aktif = 1");
    $r->execute([$uyeId]);
    if ($r->fetchColumn() !== 'komisyon') return [false, 'Yalnızca komisyon üyeleri oy kullanabilir.', null];
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT durum FROM basvurular WHERE id = ? FOR UPDATE');
        $s->execute([$bid]);
        if ($s->fetchColumn() !== 'komisyon_bekliyor') { $pdo->rollBack(); return [false, 'Bu başvuru komisyon incelemesinde değil veya karara bağlanmış; oy değiştirilemez.', null]; }
        $v = $pdo->prepare('SELECT COUNT(*) FROM komisyon_kararlari WHERE basvuru_id = ? AND uye_id = ?');
        $v->execute([$bid, $uyeId]);
        if ((int)$v->fetchColumn() > 0) { $pdo->rollBack(); return [false, 'Bu başvuru için zaten oy kullandınız.', null]; }
        $pdo->prepare('INSERT INTO komisyon_kararlari (basvuru_id, uye_id, karar, aciklama, toplu_islem_id) VALUES (?,?,?,?,?)')
            ->execute([$bid, $uyeId, $karar, $aciklama !== '' ? $aciklama : null, $topluId]);
        $c = $pdo->prepare("SELECT COALESCE(SUM(karar='onay'),0) AS o, COALESCE(SUM(karar='red'),0) AS r FROM komisyon_kararlari WHERE basvuru_id = ?");
        $c->execute([$bid]);
        $c = $c->fetch();
        $yeni = null;
        if ((int)$c['o'] >= ONAY_ESIGI) $yeni = 'komisyon_onay';
        elseif ((int)$c['r'] > KOMISYON_UYE_SAYISI - ONAY_ESIGI) $yeni = 'komisyon_red';   // artık gereken onay sayısına ulaşılamaz
        if ($yeni) {
            durum_degistir($pdo, $bid, $yeni, $uyeId, 'Komisyon kararı kesinleşti (onay: ' . (int)$c['o'] . ', ret: ' . (int)$c['r'] . ')');
            if ($yeni === 'komisyon_onay') iliski_kesme_olustur($pdo, $bid);
        }
        log_islem($bid, 'Komisyon üyesi oyu: ' . $karar . ($topluId ? ' [toplu #' . $topluId . ']' : '') . ($yeni ? ' → durum: ' . $yeni : ''), $uyeId);
        $pdo->commit();
        return [true, 'Oyunuz kaydedildi.' . ($yeni ? ' Başvuru karara bağlandı: ' . durum_etiketi($yeni) : ''), $yeni];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] komisyon_oy_ver: ' . $ex->getMessage());
        return [false, 'Oy kaydedilemedi (teknik hata).', null];
    }
}

/* ------------------------------------------------------------------ İLİŞİK KESME, SON ONAY */

/** Birim sonuçlarını TEK işlemde kaydeder: [birim => ['durum' => bekliyor|tamam|engel, 'aciklama' => ...]]. Dönüş: [ok, mesaj, tamamlandiMi].
 *  Tüm birimler "tamam" olunca başvuru otomatik olarak belge hazırlama aşamasına geçer ve ilişik kesme listesinden çıkar; sonradan yeniden açılmaz. */
function iliski_kesme_kaydet(PDO $pdo, int $bid, array $birimler, int $kid): array {
    foreach ($birimler as $birim => $v) {
        if (!isset(ILISKI_BIRIMLERI[$birim]) || !in_array($v['durum'] ?? '', ['bekliyor', 'tamam', 'engel'], true)) return [false, 'Geçersiz birim veya durum.', false];
        if ($v['durum'] === 'engel' && trim((string)($v['aciklama'] ?? '')) === '') return [false, ILISKI_BIRIMLERI[$birim] . ': Engel kaydı için açıklama zorunludur.', false];
    }
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT durum FROM basvurular WHERE id = ? FOR UPDATE');
        $s->execute([$bid]);
        if ($s->fetchColumn() !== 'komisyon_onay') { $pdo->rollBack(); return [false, 'Bu başvuru ilişik kesme aşamasında değil (daha önce tamamlanmış olabilir).', false]; }
        iliski_kesme_olustur($pdo, $bid);
        $g = $pdo->prepare('UPDATE iliski_kesme_kontrolleri SET durum = ?, aciklama = ?, kontrol_eden_id = ?, kontrol_tarihi = NOW() WHERE basvuru_id = ? AND birim = ?');
        foreach ($birimler as $birim => $v) {
            $a = trim((string)($v['aciklama'] ?? ''));
            $g->execute([$v['durum'], $a !== '' ? mb_substr($a, 0, 255) : null, $kid, $bid, $birim]);
        }
        $b = $pdo->prepare("SELECT COUNT(*) FROM iliski_kesme_kontrolleri WHERE basvuru_id = ? AND durum <> 'tamam'");
        $b->execute([$bid]);
        $tamam = (int)$b->fetchColumn() === 0;
        if ($tamam) durum_degistir($pdo, $bid, 'iliski_kesme_tamam', $kid, 'Tüm ilişik kesme kontrolleri tamamlandı; belge hazırlama aşamasına geçti');
        log_islem($bid, 'İlişik kesme kaydedildi' . ($tamam ? ' (tamamlandı)' : ''), $kid);
        $pdo->commit();
        return [true, $tamam ? 'İlişik kesme tamamlandı; öğrenci belge hazırlama aşamasına geçti.' : 'Kayıt yapıldı. Tamamlanmamış birim var; öğrenci ilişik kesme listesinde kalır.', $tamam];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] iliski_kesme_kaydet: ' . $ex->getMessage());
        return [false, 'Kayıt yapılamadı (teknik hata).', false];
    }
}

/* ------------------------------------------------------------------ TOPLU İŞLEM ALTYAPISI (komisyon, sekreter, Bilgi İşlem) */
function basvuru_ozeti(PDO $pdo, int $bid): ?array {
    $s = $pdo->prepare('SELECT b.id, b.durum, b.ogrenci_id, b.donem_id, o.ogrenci_no, o.ad_soyad FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE b.id = ?');
    $s->execute([$bid]);
    return $s->fetch() ?: null;
}

/**
 * Seçilen her başvuruyu AYRI işler; biri başarısız olsa diğerleri sürer. Her toplu işlem toplu_islemler tablosuna yazılır.
 * $islem(int $bid, array $ozet, int $topluId): [ 'basarili'|'basarisiz'|'atlandi', mesaj ]
 */
function toplu_calistir(PDO $pdo, string $tur, int $kid, array $idler, array $beklenen, callable $islem): array {
    $idler = array_values(array_unique(array_filter(array_map('intval', $idler), fn($x) => $x > 0)));
    $pdo->prepare('INSERT INTO toplu_islemler (danisman_id, secilen_sayisi, tur) VALUES (?,?,?)')->execute([$kid, count($idler), $tur]);
    $tid = (int)$pdo->lastInsertId();
    $sonuclar = []; $say = ['basarili' => 0, 'basarisiz' => 0, 'atlandi' => 0];
    foreach ($idler as $bid) {
        $o = basvuru_ozeti($pdo, $bid);
        if (!$o) { $d = 'atlandi'; $m = 'Başvuru bulunamadı.'; $o = ['ogrenci_no' => '-', 'ad_soyad' => '(bulunamadı)']; }
        elseif (!in_array($o['durum'], $beklenen, true)) { $d = 'atlandi'; $m = 'Bu aşamada değil (' . durum_etiketi($o['durum']) . '); işlem yapılmadı.'; }
        else {
            try { [$d, $m] = $islem($bid, $o, $tid); }
            catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[mezuniyet-otomasyonu] toplu ' . $tur . ': ' . $ex->getMessage());
                $d = 'basarisiz'; $m = 'Teknik hata; bu kayıtta değişiklik yapılmadı.';
            }
        }
        $say[$d]++;
        $sonuclar[] = ['basvuru_id' => $bid, 'ogrenci_no' => $o['ogrenci_no'], 'ad_soyad' => $o['ad_soyad'], 'durum' => $d, 'mesaj' => $m];
    }
    $pdo->prepare('UPDATE toplu_islemler SET onaylanan_sayisi = ?, basarisiz_sayisi = ?, atlanan_sayisi = ? WHERE id = ?')->execute([$say['basarili'], $say['basarisiz'], $say['atlandi'], $tid]);
    log_islem(null, "Toplu işlem #$tid ($tur): {$say['basarili']} başarılı, {$say['basarisiz']} başarısız, {$say['atlandi']} işlem yapılmadı", $kid);
    return ['id' => $tid, 'tur' => $tur, 'sonuclar' => $sonuclar, 'basarili' => $say['basarili'], 'basarisiz' => $say['basarisiz'], 'atlandi' => $say['atlandi']];
}
function ok_sonuc(array $r): array { return [$r[0] ? 'basarili' : 'basarisiz', $r[1]]; }

/** Komisyon toplu onayı: her öğrenci için üyenin AYRI oyu kaydedilir; 3/5 kuralı komisyon_oy_ver içinde aynen işler. Eksik koşullu başvurular toplu onaya alınmaz. */
function komisyon_toplu_onay(PDO $pdo, int $uyeId, array $idler): array {
    return toplu_calistir($pdo, 'komisyon_onay', $uyeId, $idler, ['komisyon_bekliyor'], function (int $bid, array $o, int $tid) use ($pdo, $uyeId) {
        $v = $pdo->prepare('SELECT COUNT(*) FROM komisyon_kararlari WHERE basvuru_id = ? AND uye_id = ?');
        $v->execute([$bid, $uyeId]);
        if ((int)$v->fetchColumn() > 0) return ['atlandi', 'Bu başvuru için daha önce oy kullandınız.'];
        if (!uygunluk_raporu($pdo, (int)$o['ogrenci_id'])['uygun']) return ['atlandi', 'Eksik koşulu olan başvuru toplu onaya alınmaz; ayrıntılı inceleyip tek tek oy verin.'];
        $r = komisyon_oy_ver($pdo, $uyeId, $bid, 'onay', 'Toplu onay', $tid);
        return [$r[0] ? 'basarili' : 'basarisiz', $r[1]];
    });
}

/** Sekreter toplu ilişik kesme: engel kaydı olmayan başvurularda tüm birimler "tamam" işaretlenir ve belge hazırlama aşamasına geçer. */
function iliski_kesme_toplu_tamamla(PDO $pdo, int $kid, array $idler): array {
    return toplu_calistir($pdo, 'iliski_kesme', $kid, $idler, ['komisyon_onay'], function (int $bid) use ($pdo, $kid) {
        iliski_kesme_olustur($pdo, $bid);
        $e = $pdo->prepare("SELECT COUNT(*) FROM iliski_kesme_kontrolleri WHERE basvuru_id = ? AND durum = 'engel'");
        $e->execute([$bid]);
        if ((int)$e->fetchColumn() > 0) return ['atlandi', 'Engel kaydı var; tek tek incelenmelidir.'];
        $b = [];
        foreach (array_keys(ILISKI_BIRIMLERI) as $k) $b[$k] = ['durum' => 'tamam', 'aciklama' => 'Toplu işlem'];
        $r = iliski_kesme_kaydet($pdo, $bid, $b, $kid);
        return [$r[0] ? 'basarili' : 'basarisiz', $r[1]];
    });
}

/* ------------------------------------------------------------------ BELGE: sekreter hazırlar → Bilgi İşlem onaylar */
/** Sekreter: belgeyi öğrenci bilgileriyle otomatik oluşturur ve DOĞRUDAN Bilgi İşlem onayına gönderir (sekreterliğe geri dönüş yoktur). */
function belge_hazirla(PDO $pdo, int $bid, int $kid): array {
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT b.durum, o.ogrenci_no FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE b.id = ? FOR UPDATE');
        $s->execute([$bid]);
        $r = $s->fetch();
        if (!$r || $r['durum'] !== 'iliski_kesme_tamam') { $pdo->rollBack(); return [false, 'Başvuru belge hazırlama aşamasında değil.']; }
        $c = $pdo->prepare("SELECT COUNT(*) FROM iliski_kesme_kontrolleri WHERE basvuru_id = ? AND durum <> 'tamam'");
        $c->execute([$bid]);
        if ((int)$c->fetchColumn() > 0) { $pdo->rollBack(); return [false, 'Tamamlanmamış ilişik kesme kontrolü var.']; }
        $no = belge_no_uret($bid);
        $pdo->prepare('INSERT INTO mezuniyet_belgeleri (basvuru_id, belge_no, hazirlayan_id, dogrulama_kodu, bilgi_islem_gonderim_tarihi) VALUES (?,?,?,?,NOW())
                       ON DUPLICATE KEY UPDATE hazirlayan_id = VALUES(hazirlayan_id), hazirlama_tarihi = NOW(), bilgi_islem_gonderim_tarihi = NOW(), ilk_onay_tarihi = NULL, ilk_onay_id = NULL')
            ->execute([$bid, $no, $kid, belge_dogrulama_kodu($no, $r['ogrenci_no'])]);
        durum_degistir($pdo, $bid, 'belge_hazirlaniyor', $kid, 'Mezuniyet belgesi hazırlandı ve doğrudan Bilgi İşlem onayına gönderildi');
        log_islem($bid, 'Belge hazırlandı: ' . $no, $kid);
        $pdo->commit();
        return [true, 'Belge hazırlandı ve Bilgi İşlem onayına gönderildi.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] belge_hazirla: ' . $ex->getMessage());
        return [false, 'Belge hazırlanamadı (teknik hata).'];
    }
}
function belge_toplu_hazirla(PDO $pdo, int $kid, array $idler): array {
    return toplu_calistir($pdo, 'belge_hazirla', $kid, $idler, ['iliski_kesme_tamam'], fn(int $bid) => ok_sonuc(belge_hazirla($pdo, $bid, $kid)));
}

/** Bilgi İşlem ilk belge onayı: belge onaylanır; öğrenci mezuniyet listesi bekleyenlere geçer. */
function belge_ilk_onay(PDO $pdo, int $bid, int $kid): array {
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT durum FROM basvurular WHERE id = ? FOR UPDATE');
        $s->execute([$bid]);
        $b = $pdo->prepare('SELECT id FROM mezuniyet_belgeleri WHERE basvuru_id = ?');
        $b->execute([$bid]);
        if ($s->fetchColumn() !== 'belge_hazirlaniyor' || !$b->fetchColumn()) { $pdo->rollBack(); return [false, 'Belge onay aşamasında değil veya hazırlanmamış.']; }
        $pdo->prepare('UPDATE mezuniyet_belgeleri SET ilk_onay_tarihi = NOW(), ilk_onay_id = ? WHERE basvuru_id = ?')->execute([$kid, $bid]);
        durum_degistir($pdo, $bid, 'belge_onayli', $kid, 'Bilgi İşlem belge onayı verildi');
        log_islem($bid, 'Bilgi İşlem belge onayı', $kid);
        $pdo->commit();
        return [true, 'Belge onaylandı; öğrenci mezuniyet listesi bekleyenlere geçti.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] belge_ilk_onay: ' . $ex->getMessage());
        return [false, 'Belge onaylanamadı (teknik hata).'];
    }
}
function belge_toplu_onayla(PDO $pdo, int $kid, array $idler): array {
    return toplu_calistir($pdo, 'belge_onay', $kid, $idler, ['belge_hazirlaniyor'], fn(int $bid) => ok_sonuc(belge_ilk_onay($pdo, $bid, $kid)));
}
/** Bilgi İşlem belgeyi reddeder / düzeltme ister: başvuru sekreterliğin belge hazırlama listesine NOTLA geri döner. */
function belge_duzeltme_iste(PDO $pdo, int $bid, int $kid, string $not): array {
    if (trim($not) === '') return [false, 'Düzeltme talebi için açıklama zorunludur.'];
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT durum FROM basvurular WHERE id = ? FOR UPDATE');
        $s->execute([$bid]);
        if ($s->fetchColumn() !== 'belge_hazirlaniyor') { $pdo->rollBack(); return [false, 'Belge onay aşamasında değil.']; }
        durum_degistir($pdo, $bid, 'iliski_kesme_tamam', $kid, 'Bilgi İşlem belge düzeltmesi istedi: ' . trim($not));
        log_islem($bid, 'Belge düzeltme talebi', $kid);
        $pdo->commit();
        return [true, 'Düzeltme talebi sekreterliğe iletildi.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] belge_duzeltme_iste: ' . $ex->getMessage());
        return [false, 'İşlem kaydedilemedi (teknik hata).'];
    }
}

/* ------------------------------------------------------------------ LİSTE: oluştur → incele → kapat (ayrı aşamalar) */
/** Kapalı başvuru dönemi için, belge onayı tamamlanmış öğrencileri SİSTEM seçer. */
function liste_adaylari(PDO $pdo, int $donemId): array {
    $s = $pdo->prepare("SELECT b.id AS bid, o.* FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE b.durum = 'belge_onayli' AND b.donem_id = ? ORDER BY o.ogrenci_no");
    $s->execute([$donemId]);
    return $s->fetchAll();
}

/** Kapalı bir başvuru dönemi + açık bir mezuniyet oturumu için liste oluşturur (durum: olusturuldu). Liste numarasını döndürür. */
function liste_olustur(PDO $pdo, int $donemId, int $oturumId, ?int $kid): int {
    $pdo->beginTransaction();
    try {
        $d = $pdo->prepare('SELECT * FROM basvuru_donemleri WHERE id = ? FOR UPDATE');
        $d->execute([$donemId]);
        $donem = $d->fetch();
        if (!$donem) throw new RuntimeException('Başvuru dönemi bulunamadı.');
        if ($donem['durum'] !== 'kapali') throw new RuntimeException('Liste oluşturmak için önce başvuru dönemi kapatılmalıdır.');
        $s = $pdo->prepare('SELECT * FROM oturumlar WHERE id = ? FOR UPDATE');
        $s->execute([$oturumId]);
        $o = $s->fetch();
        if (!$o || $o['durum'] !== 'acik') throw new RuntimeException('Mezuniyet oturumu bulunamadı veya zaten kapalı.');
        $erken = $o['planlanan_tarih'] > date('Y-m-d') ? 1 : 0;
        $adaylar = liste_adaylari($pdo, $donemId);
        if (!$adaylar) throw new RuntimeException('Bu dönemde listeye alınacak "belge onaylı" öğrenci yok. Önce belge onayları tamamlanmalıdır.');

        $pdo->prepare("UPDATE oturumlar SET durum = 'kapali', gercek_kapanis_tarihi = ? WHERE id = ?")->execute([date('Y-m-d'), $oturumId]);
        $pdo->prepare("INSERT INTO mezuniyet_listeleri (oturum_id, erken_olusturuldu, durum, donem_id) VALUES (?,?,'olusturuldu',?)")->execute([$oturumId, $erken, $donemId]);
        $listeId = (int)$pdo->lastInsertId();
        $varMi = $pdo->prepare('SELECT COUNT(*) FROM mezunlar WHERE ogrenci_no = ?');
        $ekle = $pdo->prepare('INSERT INTO mezunlar (ogrenci_no, ad_soyad, bolum, gano, calisma_durumu, eposta, telefon, liste_id, oturum_id, mezuniyet_tarihi) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $say = 0;
        foreach ($adaylar as $r) {
            $varMi->execute([$r['ogrenci_no']]);
            if ((int)$varMi->fetchColumn() > 0) throw new RuntimeException($r['ogrenci_no'] . ' numaralı öğrenci zaten mezun olarak kayıtlı (yinelenen mezun kaydı engellendi).');
            $ekle->execute([$r['ogrenci_no'], $r['ad_soyad'], $r['bolum'], $r['gano'], $r['calisma_durumu'], $r['eposta'], $r['telefon'], $listeId, $oturumId, date('Y-m-d')]);
            $pdo->prepare('UPDATE basvurular SET oturum_id = ? WHERE id = ?')->execute([$oturumId, $r['bid']]);
            durum_degistir($pdo, (int)$r['bid'], 'listede', $kid, 'Mezuniyet listesine eklendi (liste #' . $listeId . ')');
            log_islem((int)$r['bid'], 'Mezuniyet listesine eklendi (liste #' . $listeId . ')', $kid);
            $say++;
        }
        log_islem(null, "Liste #$listeId oluşturuldu ($say öğrenci, dönem #$donemId, oturum #$oturumId)" . ($erken ? ' [ERKEN]' : ''), $kid);
        $pdo->commit();
        return $listeId;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

function liste_basvurulari(PDO $pdo, int $listeId, string $durum): array {
    $s = $pdo->prepare('SELECT b.id FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id JOIN mezunlar m ON m.ogrenci_no = o.ogrenci_no WHERE m.liste_id = ? AND b.durum = ?');
    $s->execute([$listeId, $durum]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

/** Listeyi kapatır: kapalı liste değiştirilemez; öğrenciler e-imza aşamasına geçer. */
function liste_kapat(PDO $pdo, int $listeId, int $kid): array {
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT durum FROM mezuniyet_listeleri WHERE id = ? FOR UPDATE');
        $s->execute([$listeId]);
        if ($s->fetchColumn() !== 'olusturuldu') { $pdo->rollBack(); return [false, 'Liste bulunamadı veya zaten kapalı.']; }
        $ids = liste_basvurulari($pdo, $listeId, 'listede');
        $pdo->prepare("UPDATE mezuniyet_listeleri SET durum = 'kapali', kapatma_tarihi = NOW(), kapatan_id = ? WHERE id = ?")->execute([$kid, $listeId]);
        foreach ($ids as $bid) durum_degistir($pdo, $bid, 'eimza_surecinde', $kid, 'Liste #' . $listeId . ' kapatıldı; e-imza aşamasına geçti');
        log_islem(null, "Liste #$listeId kapatıldı (" . count($ids) . ' öğrenci e-imza aşamasında)', $kid);
        $pdo->commit();
        return [true, 'Liste kapatıldı; ' . count($ids) . ' öğrenci e-imza aşamasına geçti.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] liste_kapat: ' . $ex->getMessage());
        return [false, 'Liste kapatılamadı (teknik hata).'];
    }
}

/** YALNIZCA kapatılmamış (olusturuldu) listeyi iptal eder: öğrenciler "belge onaylı" aşamasına döner, oturum yeniden açılır. */
function liste_iptal(PDO $pdo, int $listeId, int $kid): array {
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT durum, oturum_id FROM mezuniyet_listeleri WHERE id = ? FOR UPDATE');
        $s->execute([$listeId]);
        $l = $s->fetch();
        if (!$l || $l['durum'] !== 'olusturuldu') { $pdo->rollBack(); return [false, 'Yalnızca kapatılmamış liste iptal edilebilir.']; }
        foreach (liste_basvurulari($pdo, $listeId, 'listede') as $bid) durum_degistir($pdo, $bid, 'belge_onayli', $kid, 'Liste #' . $listeId . ' iptal edildi');
        $pdo->prepare('DELETE FROM mezunlar WHERE liste_id = ?')->execute([$listeId]);
        $pdo->prepare('DELETE FROM mezuniyet_listeleri WHERE id = ?')->execute([$listeId]);
        $pdo->prepare("UPDATE oturumlar SET durum = 'acik', gercek_kapanis_tarihi = NULL WHERE id = ?")->execute([$l['oturum_id']]);
        log_islem(null, "Liste #$listeId iptal edildi", $kid);
        $pdo->commit();
        return [true, 'Liste iptal edildi; öğrenciler yeniden liste bekleyenlere döndü.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] liste_iptal: ' . $ex->getMessage());
        return [false, 'Liste iptal edilemedi (teknik hata).'];
    }
}

/* ------------------------------------------------------------------ E-İMZA (SİMÜLASYON) SON ONAYI */
/** Son onay: belge tamamlanır, öğrencinin hesabına eklenir (öğrenci ve Bilgi İşlem PDF'i indirebilir). Gerçek e-imza değildir. */
function eimza_onayla(PDO $pdo, int $bid, int $kid): array {
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT durum FROM basvurular WHERE id = ? FOR UPDATE');
        $s->execute([$bid]);
        $b = $pdo->prepare('SELECT ilk_onay_tarihi FROM mezuniyet_belgeleri WHERE basvuru_id = ?');
        $b->execute([$bid]);
        $ilk = $b->fetchColumn();
        if ($s->fetchColumn() !== 'eimza_surecinde' || !$ilk) { $pdo->rollBack(); return [false, 'Başvuru e-imza aşamasında değil veya belge onayı eksik.']; }
        $pdo->prepare("UPDATE mezuniyet_belgeleri SET eimza_durumu = 'simule_edildi', eimza_tarihi = NOW(), eimza_yetkili_id = ?, teslim_tarihi = CURDATE(), teslim_yontemi = 'elektronik', teslim_kaydeden_id = ? WHERE basvuru_id = ?")
            ->execute([$kid, $kid, $bid]);
        $pdo->prepare('UPDATE basvurular SET son_onay_kullanici_id = ?, son_onay_tarihi = NOW() WHERE id = ?')->execute([$kid, $bid]);
        durum_degistir($pdo, $bid, 'tamamlandi', $kid, 'E-imza (simülasyon) son onayı verildi; belge öğrencinin hesabına eklendi');
        log_islem($bid, 'E-imza (simülasyon) son onayı', $kid);
        $pdo->commit();
        try { belge_pdf_dosyasi($pdo, $bid); } catch (Throwable $ex) { error_log('[mezuniyet-otomasyonu] pdf: ' . $ex->getMessage()); }   // PDF üretilemezse indirme sırasında yeniden denenir
        return [true, 'E-imza onaylandı; belge tamamlandı ve öğrencinin hesabına eklendi.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mezuniyet-otomasyonu] eimza_onayla: ' . $ex->getMessage());
        return [false, 'E-imza onayı kaydedilemedi (teknik hata).'];
    }
}
function eimza_toplu_onayla(PDO $pdo, int $kid, array $idler): array {
    return toplu_calistir($pdo, 'eimza_onay', $kid, $idler, ['eimza_surecinde'], fn(int $bid) => ok_sonuc(eimza_onayla($pdo, $bid, $kid)));
}


/* ------------------------------------------------------------------ MEZUNİYET LİSTESİ */


/* ------------------------------------------------------------------ BELGE, E-İMZA (PROTOTİP), TESLİM */

/**
 * Belge adımları: hazirla | bilgi_islem | eimza_simule | teslim.
 * UYARI: e-imza adımı yalnızca SİMÜLASYONDUR; hukuken geçerli bir elektronik imza değildir.
 */
