/* SICA-EPO6: reduce el peso de los archivos ANTES de usarlos o subirlos, y los sube en cola, para que el sistema no se trabe.
 *
 *  SicaCompresion.comprimirArchivo(file, alProgreso) -> Promise<File>
 *      Word (.docx)  : reduce las imágenes que lleva adentro y vuelve a armar el archivo
 *      PDF           : reduce las fotos que lleva adentro; el texto NO se toca (sigue seleccionable)
 *      Excel (.xlsx) : reduce las imágenes que lleva adentro
 *      CSV / TXT     : quita las filas vacías (solo comas o espacios)
 *      Otros         : se devuelven igual
 *  SicaCompresion.cola(tareas, opciones) -> Promise<[{ok, valor|error}]>   (pocas a la vez, con reintentos)
 *
 * Nunca empeora un archivo: si el resultado no pesa claramente menos, se devuelve el original.
 * Si algo falla (navegador viejo, archivo raro o protegido), también se devuelve el original: comprimir es una ayuda, no un requisito.
 */
(function () {
 'use strict';

 var MAX_LADO = 1600;            // px: de sobra para una credencial o una hoja impresa
 var CALIDAD_JPEG = 0.8;
 var UMBRAL_IMAGEN = 150 * 1024; // imágenes más chicas que esto no se tocan
 var MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
 var MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

 function dormir(ms) { return new Promise(function (ok) { setTimeout(ok, ms); }); }

 function cargarBitmap(blob) {
  if (window.createImageBitmap) return createImageBitmap(blob);
  return new Promise(function (ok, mal) {
   var url = URL.createObjectURL(blob), img = new Image();
   img.onload = function () { URL.revokeObjectURL(url); ok(img); };
   img.onerror = function () { URL.revokeObjectURL(url); mal(new Error('imagen no legible')); };
   img.src = url;
  });
 }

 /* Reduce una imagen (Blob) a MAX_LADO px y la vuelve a codificar.
    tipoPreferido: 'image/jpeg' o 'auto' (JPEG si no tiene transparencia; si tiene, PNG).
    Devuelve {blob, tipo, ancho, alto} o null si no se pudo. */
 function reducir(blob, tipoPreferido) {
  return cargarBitmap(blob).then(function (bmp) {
   var w = bmp.width, h = bmp.height, esc = Math.min(1, MAX_LADO / Math.max(w, h));
   w = Math.max(1, Math.round(w * esc)); h = Math.max(1, Math.round(h * esc));
   var c = document.createElement('canvas'); c.width = w; c.height = h;
   var ctx = c.getContext('2d');
   ctx.drawImage(bmp, 0, 0, w, h);
   if (bmp.close) bmp.close();

   // ¿Tiene partes transparentes? (si sí, no se puede pasar a JPEG)
   var alfa = false;
   if (tipoPreferido === 'auto') {
    var d = ctx.getImageData(0, 0, w, h).data;
    for (var i = 3; i < d.length; i += 4 * 7) { if (d[i] < 250) { alfa = true; break; } }
   }
   var tipo = tipoPreferido === 'auto' ? (alfa ? 'image/png' : 'image/jpeg') : tipoPreferido;
   if (tipo === 'image/jpeg') {   // el JPEG no tiene transparencia: se rellena de blanco debajo
    var c2 = document.createElement('canvas'); c2.width = w; c2.height = h;
    var x2 = c2.getContext('2d'); x2.fillStyle = '#fff'; x2.fillRect(0, 0, w, h); x2.drawImage(c, 0, 0);
    c = c2;
   }
   return new Promise(function (ok) {
    c.toBlob(function (b) { ok(b ? {blob: b, tipo: tipo, ancho: w, alto: h} : null); }, tipo, CALIDAD_JPEG);
   });
  }).catch(function () { return null; });
 }

 /* ---------- Word (.docx) y Excel (.xlsx): son un zip; lo que más pesa son las imágenes de word/media o xl/media ---------- */
 function comprimirOffice(file, carpeta, mime, alProgreso) {
  if (!window.JSZip) return Promise.resolve(file);
  var rxMedia = new RegExp('^' + carpeta + '/media/[^/]+\\.(png|jpe?g)$', 'i');
  var zip, cambios = 0, renombres = {};
  return file.arrayBuffer().then(function (buf) { return JSZip.loadAsync(buf); }).then(function (z) {
   zip = z;
   var medios = Object.keys(zip.files).filter(function (n) { return rxMedia.test(n) && !zip.files[n].dir; });
   var seq = Promise.resolve();
   medios.forEach(function (nombre, i) {
    seq = seq.then(function () {
     if (alProgreso) alProgreso('Reduciendo imágenes del archivo… (' + (i + 1) + ' de ' + medios.length + ')');
     return zip.file(nombre).async('blob').then(function (blob) {
      if (blob.size < UMBRAL_IMAGEN) return;
      var esPng = /\.png$/i.test(nombre);
      // JPG sigue siendo JPG. PNG: si no tiene transparencia y pesa mucho se pasa a JPEG; si tiene, se queda PNG (solo reducida).
      return reducir(blob, esPng ? 'auto' : 'image/jpeg').then(function (r) {
       if (!r || r.blob.size > blob.size * 0.8) return;   // sin mejora clara: se queda igual
       if (esPng && r.tipo === 'image/jpeg') {
        var nuevo = nombre.replace(/\.png$/i, '.jpeg');
        if (zip.file(nuevo)) return;   // ya existe un archivo con ese nombre: no se arriesga
        zip.remove(nombre); zip.file(nuevo, r.blob);
        renombres[nombre.replace(/^[^/]+\//, '')] = nuevo.replace(/^[^/]+\//, '');
       } else {
        zip.file(nombre, r.blob);
       }
       cambios++;
      });
     });
    });
   });
   return seq;
  }).then(function () {
   if (!cambios) return file;
   var pend = Object.keys(renombres);
   var arreglos = [];
   if (pend.length) {
    // Los .png que pasaron a .jpeg: se actualizan las referencias y se declara el tipo jpeg
    Object.keys(zip.files).filter(function (n) { return /\.rels$/i.test(n); }).forEach(function (n) {
     arreglos.push(zip.file(n).async('string').then(function (xml) {
      var nuevo = xml;
      pend.forEach(function (v) { nuevo = nuevo.split(v).join(renombres[v]); });
      if (nuevo !== xml) zip.file(n, nuevo);
     }));
    });
    var ct = zip.file('[Content_Types].xml');
    if (ct) arreglos.push(ct.async('string').then(function (xml) {
     if (!/Extension="jpe?g"/i.test(xml)) zip.file('[Content_Types].xml', xml.replace(/<Types([^>]*)>/, '<Types$1><Default Extension="jpeg" ContentType="image/jpeg"/>'));
    }));
   }
   return Promise.all(arreglos).then(function () {
    return zip.generateAsync({type: 'blob', mimeType: mime, compression: 'DEFLATE', compressionOptions: {level: 9}});
   }).then(function (blob) {
    if (blob.size > file.size * 0.9) return file;
    return new File([blob], file.name, {type: mime, lastModified: Date.now()});
   });
  }).catch(function () { return file; });
 }

 /* ---------- PDF: se reducen las fotos (JPEG) que lleva adentro. Las páginas y el TEXTO no se tocan. ---------- */
 var _cargandoPdfLib = null;
 function cargarPdfLib() {
  if (window.PDFLib) return Promise.resolve();
  if (_cargandoPdfLib) return _cargandoPdfLib;
  _cargandoPdfLib = new Promise(function (ok, mal) {
   var s = document.createElement('script');
   s.src = (window.SICA_VENDOR_BASE || '/vendor/') + 'pdf-lib.min.js?v=21';
   s.onload = function () { ok(); }; s.onerror = function () { _cargandoPdfLib = null; mal(new Error('pdf-lib')); };
   document.head.appendChild(s);
  });
  return _cargandoPdfLib;
 }

 function comprimirPdf(file, alProgreso) {
  var P;
  return cargarPdfLib().then(function () { P = window.PDFLib; return file.arrayBuffer(); }).then(function (buf) {
   return P.PDFDocument.load(buf, {updateMetadata: false}).then(function (pdf) {
    var N = P.PDFName, ctx = pdf.context, candidatas = [];
    ctx.enumerateIndirectObjects().forEach(function (par) {
     var ref = par[0], obj = par[1];
     if (!(obj instanceof P.PDFRawStream)) return;
     var d = obj.dict;
     if (d.get(N.of('Subtype')) !== N.of('Image')) return;
     if (!/^\[?\s*\/DCTDecode\s*\]?$/.test(String(d.get(N.of('Filter'))))) return;   // solo JPEG "puro"
     if (d.get(N.of('ImageMask')) || d.get(N.of('Mask')) || d.get(N.of('Decode'))) return;
     if (obj.contents.length < UMBRAL_IMAGEN) return;
     // Solo colores que el navegador sabe reproducir igual (RGB y gris); CMYK, paletas y tintas se dejan como están
     var cs = d.get(N.of('ColorSpace'));
     if (cs instanceof P.PDFRef) cs = ctx.lookup(cs);
     var ok = false;
     if (cs instanceof P.PDFName) ok = (cs === N.of('DeviceRGB') || cs === N.of('DeviceGray'));
     else if (cs instanceof P.PDFArray && cs.get(0) === N.of('ICCBased')) {
      var icc = ctx.lookup(cs.get(1)), n = icc && icc.dict && icc.dict.get(N.of('N'));
      ok = !!n && (String(n) === '1' || String(n) === '3');
     }
     if (ok) candidatas.push([ref, obj]);
    });
    var cambios = 0, seq = Promise.resolve();
    candidatas.forEach(function (par, i) {
     seq = seq.then(function () {
      if (alProgreso) alProgreso('Reduciendo fotos del PDF… (' + (i + 1) + ' de ' + candidatas.length + ')');
      var original = par[1].contents;
      return reducir(new Blob([original], {type: 'image/jpeg'}), 'image/jpeg').then(function (r) {
       if (!r || r.blob.size > original.length * 0.8) return;
       return r.blob.arrayBuffer().then(function (ab) {
        var datos = new Uint8Array(ab), d = par[1].dict;
        var diccionario = {Type: 'XObject', Subtype: 'Image', Width: r.ancho, Height: r.alto, ColorSpace: 'DeviceRGB', BitsPerComponent: 8, Filter: 'DCTDecode'};
        var smask = d.get(N.of('SMask'));
        if (smask) diccionario.SMask = smask;   // la transparencia (si tiene) se conserva tal cual
        ctx.assign(par[0], ctx.stream(datos, diccionario));
        cambios++;
       });
      });
     });
    });
    return seq.then(function () {
     if (!cambios) return file;
     return pdf.save({useObjectStreams: true}).then(function (bytes) {
      if (bytes.length > file.size * 0.9) return file;
      return new File([bytes], file.name, {type: 'application/pdf', lastModified: Date.now()});
     });
    });
   });
  }).catch(function (e) { if (window.console) console.warn('SicaCompresion (PDF): se usa el original.', e); return file; });   // PDF protegido, dañado o sin librería: se usa el original
 }

 /* ---------- CSV / TXT: se quitan las filas vacías (solo comas, punto y coma, tabuladores o espacios) ----------
    Se trabaja con los bytes (sin decodificar el texto) para no dañar acentos ni la codificación del archivo. */
 function comprimirCsv(file) {
  return file.arrayBuffer().then(function (buf) {
   var b = new Uint8Array(buf), n = b.length;
   if ((b[0] === 0xFF && b[1] === 0xFE) || (b[0] === 0xFE && b[1] === 0xFF)) return file;   // UTF-16: no se toca
   for (var k = 0; k < Math.min(n, 2048); k++) if (b[k] === 0) return file;
   var salida = new Uint8Array(n), o = 0, ini = 0, quitadas = 0;
   function vacia(a, z) { for (var i = a; i < z; i++) { var c = b[i]; if (c !== 0x20 && c !== 0x09 && c !== 0x0D && c !== 0x2C && c !== 0x3B && c !== 0x7C) return false; } return true; }
   for (var i = 0; i <= n; i++) {
    if (i === n || b[i] === 0x0A) {
     var fin = i === n ? n : i + 1;
     if (i > ini && vacia(ini, i) || (i === ini && i < n)) quitadas++;   // línea vacía o solo separadores
     else if (fin > ini) { salida.set(b.subarray(ini, fin), o); o += fin - ini; }
     ini = fin;
    }
   }
   if (!quitadas || o > n * 0.9) return file;   // no vale la pena
   return new File([salida.subarray(0, o)], file.name, {type: file.type, lastModified: Date.now()});
  }).catch(function () { return file; });
 }

 function comprimirArchivo(file, alProgreso) {
  if (!file) return Promise.resolve(file);
  if (/\.docx$/i.test(file.name)) return comprimirOffice(file, 'word', MIME_DOCX, alProgreso);
  if (/\.xlsx$/i.test(file.name)) return comprimirOffice(file, 'xl', MIME_XLSX, alProgreso);
  if (/\.pdf$/i.test(file.name)) return comprimirPdf(file, alProgreso);
  if (/\.(csv|txt)$/i.test(file.name)) return comprimirCsv(file);
  return Promise.resolve(file);
 }

 /* Cola: ejecuta las tareas (funciones que devuelven una promesa) de pocas en pocas y reintenta las que fallan por red o por el servidor.
    Un error con .noReintentar = true (por ejemplo, el servidor rechazó el archivo) no se repite. */
 function cola(tareas, op) {
  op = op || {};
  var simultaneas = op.simultaneas || 3, reintentos = op.reintentos == null ? 2 : op.reintentos, espera = op.espera || 800;
  var res = new Array(tareas.length), siguiente = 0, hechas = 0;
  function obrero() {
   var n = siguiente++;
   if (n >= tareas.length) return Promise.resolve();
   var intento = 0;
   function probar() {
    return Promise.resolve().then(tareas[n]).then(function (v) { res[n] = {ok: true, valor: v}; }, function (e) {
     if (!(e && e.noReintentar) && intento < reintentos) { intento++; return dormir(espera * intento).then(probar); }
     res[n] = {ok: false, error: e};
    });
   }
   return probar().then(function () { hechas++; if (op.alProgreso) op.alProgreso(hechas, tareas.length); return obrero(); });
  }
  var obreros = [];
  for (var i = 0; i < Math.min(simultaneas, tareas.length); i++) obreros.push(obrero());
  return Promise.all(obreros).then(function () { return res; });
 }

 function pesoLegible(bytes) { return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB'; }

 window.SicaCompresion = {comprimirArchivo: comprimirArchivo, cola: cola, pesoLegible: pesoLegible};
})();
