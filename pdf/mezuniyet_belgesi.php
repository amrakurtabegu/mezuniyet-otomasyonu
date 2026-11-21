<?php
// Mezuniyet belgesi: görüntüleme ve PDF indirme. Öğrenci YALNIZCA kendi e-imzalı (tamamlanmış) belgesini görür;
// Bilgi İşlem (bölüm başkanı) belge aşamasındaki tüm belgeleri; sekreter yalnızca imzasız önizlemeleri görür.
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['ogrenci', 'bolum_baskani', 'sekreter']);
$pdo = db();
$bid = (int)($_GET['basvuru_id'] ?? 0);
$v = belge_verisi($pdo, $bid);
$izin = false; $imzali = false;
if ($v) {
    $imzali = belge_imzali($v);
    if ($u['rol'] === 'ogrenci') {
        $s = $pdo->prepare('SELECT COUNT(*) FROM basvurular b JOIN ogrenciler o ON o.id = b.ogrenci_id WHERE b.id = ? AND o.kullanici_id = ?');
        $s->execute([$bid, $u['id']]);
        $izin = $imzali && (int)$s->fetchColumn() === 1;
    } elseif ($u['rol'] === 'bolum_baskani') {
        $izin = in_array($v['durum'], ['iliski_kesme_tamam', 'belge_hazirlaniyor', 'belge_onayli', 'listede', 'eimza_surecinde', 'tamamlandi'], true);
    } else {
        $izin = in_array($v['durum'], ['iliski_kesme_tamam', 'belge_hazirlaniyor'], true);
    }
}
if (!$izin) { http_response_code(403); exit('Bu belgeyi görüntüleme yetkiniz yok veya belge henüz hazır değil.'); }

if (($_GET['dosya'] ?? '') === 'pdf') {
    $yol = $imzali ? belge_pdf_dosyasi($pdo, $bid) : null;
    $icerik = $yol ? (string)file_get_contents($yol) : belge_pdf_icerik($v);
    $indir = isset($_GET['indir']);
    if ($imzali) log_islem($bid, 'Belge ' . ($indir ? 'indirildi' : 'görüntülendi') . ' (' . $u['ad'] . ')', $u['id']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($indir ? 'attachment' : 'inline') . '; filename="' . $v['belge_no'] . '.pdf"');
    header('Content-Length: ' . strlen($icerik));
    header('Cache-Control: private, no-store');
    echo $icerik;
    exit;
}
$geri = BASE_URL . rol_anasayfa($u['rol']);
$pdfUrl = BASE_URL . '/pdf/mezuniyet_belgesi.php?basvuru_id=' . $bid . '&dosya=pdf';
layout_header('Mezuniyet Belgesi', ['kirinti' => [['Ana Sayfa', $geri], ['Mezuniyet Belgesi']],
    'eylem' => '<div class="satir no-print" style="margin:0">' . ($imzali ? '<a class="dugme yesil" href="' . e($pdfUrl) . '&indir=1">PDF indir</a>' : '<a class="dugme hayalet" target="_blank" href="' . e($pdfUrl) . '">PDF önizle</a>')
               . '<button type="button" class="dugme hayalet" onclick="window.print()">Yazdır</button><a class="dugme hayalet" href="' . e($geri) . '">Geri dön</a></div>']);
echo belge_html($v);
layout_footer();
