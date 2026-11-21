/* Mezuniyet Komisyonu Otomasyonu – ortak davranışlar: yan menü, satır bağlantıları, toplu seçim, tablo arama/süzme/sıralama */
(function () {
  'use strict';
  var d = document;
  function q(s, k) { return (k || d).querySelector(s); }
  function qa(s, k) { return Array.prototype.slice.call((k || d).querySelectorAll(s)); }
  var tr = function (s) { return String(s).toLocaleLowerCase('tr'); };

  var kenar = q('#kenar'), ac = q('#menuAc');
  if (kenar && ac) {
    ac.addEventListener('click', function () { kenar.classList.toggle('acik'); });
    d.addEventListener('click', function (e) { if (kenar.classList.contains('acik') && !kenar.contains(e.target) && !ac.contains(e.target)) kenar.classList.remove('acik'); });
  }
  d.addEventListener('click', function (e) { qa('details.acilir[open]').forEach(function (x) { if (!x.contains(e.target)) x.removeAttribute('open'); }); });

  /* Satıra tıklayınca tam sayfa inceleme: <tr data-href="..."> */
  d.addEventListener('click', function (e) {
    var r = e.target.closest('[data-href]');
    if (r && !e.target.closest('a,button,input,select,textarea,label')) window.location.href = r.getAttribute('data-href');
  });

  /* Toplu işlem formları: butonlar yalnızca kayıt seçilince aktif; onay penceresinde seçilen sayı gösterilir */
  qa('form[data-toplu-form]').forEach(function (f) {
    var dugmeler = qa('button[data-toplu]', f), sayac = q('[data-secili-sayac]', f);
    function say() { return qa('input.sec:checked', f).length; }
    function guncelle() { var n = say(); dugmeler.forEach(function (b) { b.disabled = n === 0; }); if (sayac) sayac.textContent = n + ' kayıt seçili'; }
    f.addEventListener('change', guncelle);
    f.addEventListener('submit', function (e) {
      var b = e.submitter;
      if (b && b.getAttribute('data-onay') && !confirm(b.getAttribute('data-onay').replace('{n}', say()))) e.preventDefault();
    });
    guncelle();
  });
  qa('input[data-hepsi]').forEach(function (h) {
    h.addEventListener('change', function () {
      qa('input.' + h.getAttribute('data-hepsi')).forEach(function (c) { var s = c.closest('tr'); if (!s || s.style.display !== 'none') c.checked = h.checked; });
    });
  });
  qa('[data-tumu-tamam]').forEach(function (b) {
    b.addEventListener('click', function () { qa('select[data-birim]').forEach(function (s) { s.value = 'tamam'; }); });
  });

  /* Tablolar: arama, süzme, sıralama (table[data-tablo="kimlik"]) */
  qa('table[data-tablo]').forEach(function (tablo) {
    var id = tablo.getAttribute('data-tablo'), govde = q('tbody', tablo);
    var satirlar = qa('tr', govde).filter(function (r) { return !r.classList.contains('bos-satir'); });
    var arama = q('input[data-ara="' + id + '"]'), sayac = q('[data-sayac="' + id + '"]');
    var suzgecler = qa('select[data-filtre="' + id + '"]');
    var bos = q('.bos-satir', govde);
    function uygula() {
      var s = arama ? tr(arama.value.trim()) : '', gorunen = 0;
      satirlar.forEach(function (r) {
        var ok = !s || tr(r.textContent).indexOf(s) > -1;
        suzgecler.forEach(function (f) { var a = f.getAttribute('data-alan'); if (ok && f.value !== '' && r.getAttribute('data-' + a) !== f.value) ok = false; });
        r.style.display = ok ? '' : 'none'; if (ok) gorunen++;
      });
      if (bos) bos.style.display = gorunen ? 'none' : '';
      if (sayac) sayac.textContent = gorunen + ' / ' + satirlar.length + ' kayıt';
    }
    if (arama) arama.addEventListener('input', uygula);
    suzgecler.forEach(function (f) { f.addEventListener('change', uygula); });
    qa('th[data-sirala]', tablo).forEach(function (th) {
      th.addEventListener('click', function () {
        var i = th.cellIndex, artan = !th.classList.contains('artan');
        qa('th', tablo).forEach(function (x) { x.classList.remove('artan', 'azalan'); });
        th.classList.add(artan ? 'artan' : 'azalan');
        var deger = function (r) { var c = r.cells[i]; return c.hasAttribute('data-v') ? c.getAttribute('data-v') : c.textContent.trim(); };
        satirlar.sort(function (a, b) {
          var x = deger(a), y = deger(b), nx = parseFloat(String(x).replace(',', '.')), ny = parseFloat(String(y).replace(',', '.'));
          var r = (!isNaN(nx) && !isNaN(ny) && /^[\d.,\s-]+/.test(x) && /^[\d.,\s-]+/.test(y)) ? nx - ny : String(x).localeCompare(String(y), 'tr');
          return artan ? r : -r;
        });
        satirlar.forEach(function (r) { govde.insertBefore(r, bos || null); });
      });
    });
    uygula();
  });
})();
