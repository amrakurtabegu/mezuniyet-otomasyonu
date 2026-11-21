<?php
/**
 * MEZUNİYET BELGESİ: veri toplama, HTML görünümü ve GERÇEK PDF üretimi (harici kütüphane gerektirmez).
 * Belge, e-imza (SİMÜLASYON) onayıyla birlikte storage/belgeler/ altına kaydedilir; yeniden üretilmeden sunulur.
 * E-imza gerçek değildir ve hukuki geçerliliği yoktur.
 */
function belge_no_uret(int $bid): string { return sprintf('MZN-%s-%04d', date('Y'), $bid); }
function belge_dogrulama_kodu(string $belgeNo, string $ogrNo): string { return strtoupper(substr(hash('sha256', $belgeNo . '|' . $ogrNo . '|mzn-prototip'), 0, 12)); }
function belge_klasoru(): string {
    $d = __DIR__ . '/../storage/belgeler';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

/** Belgenin tüm verisi; başvuru yoksa null. */
function belge_verisi(PDO $pdo, int $bid): ?array {
    $s = $pdo->prepare('SELECT b.id, b.durum, b.basvuru_tarihi, b.son_onay_tarihi, o.ogrenci_no, o.ad_soyad, o.bolum, o.program, k.ad_soyad AS danisman_ad,
        a.gano, bl.belge_no, bl.dogrulama_kodu, bl.hazirlama_tarihi, bl.ilk_onay_tarihi, bl.eimza_durumu, bl.eimza_tarihi, bl.dosya_yolu, ey.ad_soyad AS yetkili_ad,
        m.mezuniyet_tarihi, m.liste_id, ol.planlanan_tarih AS oturum_tarihi
        FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id JOIN kullanicilar k ON k.id = o.danisman_id
        LEFT JOIN akademik_kayitlari a ON a.ogrenci_id = o.id LEFT JOIN mezuniyet_belgeleri bl ON bl.basvuru_id = b.id
        LEFT JOIN kullanicilar ey ON ey.id = bl.eimza_yetkili_id LEFT JOIN mezunlar m ON m.ogrenci_no = o.ogrenci_no
        LEFT JOIN oturumlar ol ON ol.id = m.oturum_id WHERE b.id = ?');
    $s->execute([$bid]);
    $v = $s->fetch();
    if (!$v) return null;
    $c = $pdo->prepare("SELECT COALESCE(SUM(karar = 'onay'), 0) FROM komisyon_kararlari WHERE basvuru_id = ?");
    $c->execute([$bid]);
    $v['komisyon_onay'] = (int)$c->fetchColumn();
    $v['belge_no'] = $v['belge_no'] ?: belge_no_uret($bid);
    $v['dogrulama_kodu'] = $v['dogrulama_kodu'] ?: belge_dogrulama_kodu($v['belge_no'], $v['ogrenci_no']);
    $v['baskan_ad'] = (string)$pdo->query("SELECT ad_soyad FROM kullanicilar WHERE rol = 'bolum_baskani' AND aktif = 1 ORDER BY id LIMIT 1")->fetchColumn();
    return $v;
}
function belge_imzali(array $v): bool { return $v['durum'] === 'tamamlandi' && ($v['eimza_durumu'] ?? 'beklemede') !== 'beklemede'; }

function belge_satirlari(array $v): array {
    return [
        ['Belge No', $v['belge_no']], ['Öğrenci No', $v['ogrenci_no']], ['Adı Soyadı', $v['ad_soyad']],
        ['Bölüm / Program', $v['bolum'] . ($v['program'] ? ' / ' . $v['program'] : '')],
        ['GANO', $v['gano'] !== null ? number_format((float)$v['gano'], 2) : '-'], ['Akademik Danışman', $v['danisman_ad']],
        ['Komisyon Kararı', 'Onaylandı (' . $v['komisyon_onay'] . '/' . KOMISYON_UYE_SAYISI . ' onay)'], ['İlişik Kesme', 'Tüm birimler tamamlandı'],
        ['Mezuniyet Listesi', $v['liste_id'] ? 'Liste #' . $v['liste_id'] . ' - oturum ' . tarih_tr($v['oturum_tarihi']) : 'Henüz liste oluşturulmadı'],
        ['Mezuniyet Tarihi', $v['mezuniyet_tarihi'] ? tarih_tr($v['mezuniyet_tarihi']) : '-'],
    ];
}
function belge_metni(array $v): string {
    return 'Yukarıda kimlik bilgileri yazılı öğrencinin, bölüm mezuniyet komisyonu kararı ve ilişik kesme kontrollerinin tamamlanması sonucunda mezuniyet koşullarını sağladığı tespit edilmiştir. Bu belge, mezuniyet sürecinin tamamlandığını göstermek üzere düzenlenmiştir.';
}

function belge_html(array $v): string {
    $imzali = belge_imzali($v);
    $h = '<div class="belge"><div class="belge-baslik"><b>T.C.</b><br><b>ÜNİVERSİTE ADI</b><br>MÜHENDİSLİK FAKÜLTESİ · ' . e($v['bolum']) . ' BÖLÜMÜ<h2>MEZUNİYET BELGESİ</h2></div>';
    if (!$imzali) $h .= '<div class="belge-filigran">ÖNİZLEME · HENÜZ İMZALANMAMIŞTIR</div>';
    $h .= '<table class="belge-tablo">';
    foreach (belge_satirlari($v) as [$e, $d]) $h .= '<tr><th>' . e($e) . '</th><td>' . e($d) . '</td></tr>';
    $h .= '</table><p>' . e(belge_metni($v)) . '</p><div class="belge-imza">';
    if ($imzali) $h .= '<div class="muhur"><b>ELEKTRONİK İMZA (SİMÜLASYON)</b><br>İmzalayan: ' . e($v['yetkili_ad'] ?: $v['baskan_ad']) . '<br>Tarih: ' . e(tarih_tr($v['eimza_tarihi'], true)) . '<br>Doğrulama kodu: <b>' . e($v['dogrulama_kodu']) . '</b></div>';
    else $h .= '<div class="muhur bos"><b>İMZASIZ ÖNİZLEME</b><br>E-imza onayı verildiğinde belge tamamlanır.</div>';
    $h .= '<div class="imza-ad">' . e($v['baskan_ad']) . '<br>Bölüm Başkanı</div></div>';
    return $h . '<p class="belge-not">Bu belge prototip sistem tarafından üretilmiştir. E-imza adımı simülasyondur; hukuki geçerliliği yoktur.</p></div>';
}

/** Tek sayfalık A4 PDF; Helvetica + Türkçe harfler (ISO-8859-9). $ops: ['t'=>...], ['l'=>...], ['r'=>...] */
function pdf_basit(array $ops): string {
    $c = '';
    foreach ($ops as $o) {
        if ($o[0] === 't') {
            [, $x, $y, $sz, $kalin, $txt, $orta] = $o;
            if ($orta) $x -= mb_strlen($txt) * $sz * ($kalin ? 0.56 : 0.52) / 2;
            $b = mb_convert_encoding(strtr($txt, ['–' => '-', '—' => '-', '’' => "'", '“' => '"', '”' => '"']), 'ISO-8859-9', 'UTF-8');
            $b = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $b);
            $c .= sprintf("BT /F%d %d Tf %.2F %.2F Td (", $kalin ? 2 : 1, $sz, $x, $y) . $b . ") Tj ET\n";
        } elseif ($o[0] === 'l') {
            $c .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $o[1], $o[2], $o[3], $o[4]);
        } elseif ($o[0] === 'r') {
            $c .= sprintf("%.2F %.2F %.2F %.2F re S\n", $o[1], $o[2], $o[3], $o[4]);
        }
    }
    $nesneler = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',
        "<< /Length " . strlen($c) . " >>\nstream\n" . $c . 'endstream',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding 7 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding 7 0 R >>',
        '<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [208 /Gbreve 221 /Idotaccent 222 /Scedilla 240 /gbreve 253 /dotlessi 254 /scedilla] >>',
    ];
    $cikti = "%PDF-1.4\n"; $ofs = [];
    foreach ($nesneler as $i => $n) { $ofs[] = strlen($cikti); $cikti .= ($i + 1) . " 0 obj\n" . $n . "\nendobj\n"; }
    $x = strlen($cikti);
    $cikti .= "xref\n0 " . (count($nesneler) + 1) . "\n0000000000 65535 f \n";
    foreach ($ofs as $o) $cikti .= sprintf("%010d 00000 n \n", $o);
    return $cikti . "trailer\n<< /Size " . (count($nesneler) + 1) . " /Root 1 0 R >>\nstartxref\n" . $x . "\n%%EOF";
}

function belge_pdf_icerik(array $v): string {
    $imzali = belge_imzali($v);
    $o = [['t', 297, 790, 12, true, 'T.C.', true], ['t', 297, 774, 14, true, 'ÜNİVERSİTE ADI', true],
          ['t', 297, 758, 10, false, 'MÜHENDİSLİK FAKÜLTESİ · ' . $v['bolum'] . ' BÖLÜMÜ', true], ['t', 297, 722, 18, true, 'MEZUNİYET BELGESİ', true]];
    if (!$imzali) $o[] = ['t', 297, 700, 11, true, 'ÖNİZLEME - HENÜZ İMZALANMAMIŞTIR', true];
    $y = 672;
    $o[] = ['l', 60, $y + 18, 535, $y + 18];
    foreach (belge_satirlari($v) as [$e, $d]) {
        $o[] = ['t', 66, $y, 10, true, $e, false]; $o[] = ['t', 210, $y, 10, false, (string)$d, false];
        $o[] = ['l', 60, $y - 8, 535, $y - 8]; $y -= 24;
    }
    $y -= 10;
    foreach (explode("\n", wordwrap(belge_metni($v), 92, "\n", true)) as $satir) { $o[] = ['t', 60, $y, 10, false, $satir, false]; $y -= 15; }
    $o[] = ['r', 60, 150, 250, 92];
    if ($imzali) {
        $o[] = ['t', 68, 220, 9, true, 'ELEKTRONİK İMZA (SİMÜLASYON)', false];
        $o[] = ['t', 68, 204, 9, false, 'İmzalayan: ' . ($v['yetkili_ad'] ?: $v['baskan_ad']), false];
        $o[] = ['t', 68, 190, 9, false, 'Tarih: ' . tarih_tr($v['eimza_tarihi'], true), false];
        $o[] = ['t', 68, 176, 9, false, 'Doğrulama kodu: ' . $v['dogrulama_kodu'], false];
    } else {
        $o[] = ['t', 68, 210, 9, true, 'İMZASIZ ÖNİZLEME', false]; $o[] = ['t', 68, 194, 9, false, 'E-imza onayı verildiğinde belge tamamlanır.', false];
    }
    $o[] = ['t', 430, 200, 10, true, $v['baskan_ad'], true]; $o[] = ['t', 430, 186, 10, false, 'Bölüm Başkanı', true];
    $o[] = ['t', 297, 90, 8, false, 'Bu belge prototip sistem tarafından üretilmiştir. E-imza adımı simülasyondur; hukuki geçerliliği yoktur.', true];
    return pdf_basit($o);
}

/** İmzalı belgenin PDF dosyasını (yoksa) üretir ve saklar; dosya yolunu döndürür. Başarısızlıkta null (çağıran sayfa anlık üretime düşer). */
function belge_pdf_dosyasi(PDO $pdo, int $bid): ?string {
    $v = belge_verisi($pdo, $bid);
    if (!$v || !belge_imzali($v)) return null;
    $yol = $v['dosya_yolu'] ?: '';
    if ($yol !== '' && is_file(belge_klasoru() . '/' . $yol)) return belge_klasoru() . '/' . $yol;
    $ad = $v['belge_no'] . '_' . $v['ogrenci_no'] . '.pdf';
    if (@file_put_contents(belge_klasoru() . '/' . $ad, belge_pdf_icerik($v)) === false) return null;
    $pdo->prepare('UPDATE mezuniyet_belgeleri SET dosya_yolu = ? WHERE basvuru_id = ?')->execute([$ad, $bid]);
    return belge_klasoru() . '/' . $ad;
}
