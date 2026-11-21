<?php
// Eski "Süreç Takibi" sayfası: işlevleri rol panellerine taşındı (sekreter: ilişik kesme + belge hazırlama; başkan: son onay + e-imza + teslim).
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(['bolum_baskani', 'sekreter']);
redirect(rol_anasayfa($u['rol']));
