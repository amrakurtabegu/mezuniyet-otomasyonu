<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect(rol_anasayfa(current_user()['rol']));
$no = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $no = trim((string)($_POST['no'] ?? ''));
    $hata = giris_yap($no, (string)($_POST['sifre'] ?? ''));
    if ($hata === null) redirect(rol_anasayfa(current_user()['rol']));
    flash($hata, 'err');
}
?><!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sisteme Giriş – Mezuniyet Komisyonu Otomasyonu</title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/style.css"></head>
<body>
<div class="giris">
  <section class="giris-sol">
    <div class="marka"><span class="marka-isaret">M</span><span>Mezuniyet<br>Komisyonu</span></div>
    <div>
      <h1>Mezuniyet sürecini tek ekrandan yürütün.</h1>
      <p>Başvurudan dekanlık evrakına kadar tüm aşamalar öğrenci, danışman, komisyon, sekreterlik ve bölüm başkanlığı için ortak bir sistemde izlenir.</p>
      <ol class="adim-listesi">
        <li><span>1</span>Öğrenci başvurusu ve akademik uygunluk kontrolü</li>
        <li><span>2</span>Danışman onayı ve komisyon oylaması (5 üyeden en az 3 onay)</li>
        <li><span>3</span>İlişik kesme, bölüm başkanı son onayı ve mezuniyet listesi</li>
        <li><span>4</span>Belge hazırlama, e-imza (simülasyon) ve teslim</li>
      </ol>
    </div>
    <small style="color:#9fb0cc">Ders projesi prototipi · Tüm öğrenci verileri kurgusaldır (DEMO)</small>
  </section>
  <section class="giris-sag">
    <div class="giris-kart">
      <h2>Sisteme giriş</h2>
      <p class="muted">Öğrenci veya kullanıcı numaranız ve şifrenizle giriş yapın.</p>
      <?php foreach ($_SESSION['flash'] ?? [] as [$tur, $m]) echo '<div class="bildirim ' . e($tur) . '" role="alert">' . e($m) . '</div>'; unset($_SESSION['flash']); ?>
      <form method="post">
        <?= csrf_field() ?>
        <label for="no">Öğrenci / Kullanıcı numarası</label>
        <input id="no" name="no" value="<?= e($no) ?>" required autofocus autocomplete="username">
        <label for="sifre">Şifre</label>
        <input id="sifre" type="password" name="sifre" required autocomplete="current-password">
        <button class="dugme" type="submit">Giriş yap</button>
      </form>
      <p class="muted" style="margin-top:18px">Demo hesaplar README dosyasında listelenmiştir.</p>
    </div>
  </section>
</div>
</body></html>
