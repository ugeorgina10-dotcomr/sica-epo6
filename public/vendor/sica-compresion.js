/* SICA-EPO6: reduce el peso de los archivos ANTES de usarlos o subirlos, y los sube en cola, para que el sistema no se trabe.
 *
 *  SicaCompresion.comprimirArchivo(file, alProgreso) -> Promise<File>
 *      Word (.docx)  : reduce las imágenes que lleva adentro y vuelve a armar el archivo
 *      PDF           : reduce las fotos que lleva adentro; el texto NO se toca (sigue seleccionable)
 *      Excel (.xlsx) : reduce las imágenes que lleva adentro
 *      CSV / TXT     : quita las filas vacías (solo comas o espacios)
 *      Otros         : se devuelven igual
 *  SicaCompresion.validar(file, tipos) / validarVarios(archivos, tipos) -> Promise<null | texto del problema>   (tipo real y no dañado)
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
   s.src = (window.SICA_VENDOR_BASE || '/vendor/') + 'pdf-lib.min.js?v=22';
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


 /* ---------- Validar: ¿es de verdad el tipo de archivo que se permite, y no está dañado? ----------
    No basta con la extensión (alguien puede renombrar una foto a .pdf): se revisa el CONTENIDO real.
    tipos: lista de 'docx' | 'xlsx' | 'xls' | 'csv' | 'pdf'.  Devuelve una promesa con null (está bien) o el texto del problema. */
 var NOMBRE_TIPO = {docx: 'Word (.docx)', xlsx: 'Excel (.xlsx)', xls: 'Excel (.xls)', csv: 'CSV (.csv)', pdf: 'PDF (.pdf)'};
 function listaTipos(tipos) { return tipos.map(function (t) { return NOMBRE_TIPO[t]; }).join(', '); }
 function empieza(b, arr) { for (var i = 0; i < arr.length; i++) if (b[i] !== arr[i]) return false; return true; }


 /* ---------- Seguridad: archivos sospechosos ----------
    Además de que el tipo sea el correcto, se rechaza todo lo que pueda traer algo peligroso adentro. */
 var EXT_PELIGROSAS = ['php','phtml','phar','exe','dll','bat','cmd','com','scr','msi','js','jse','vbs','vbe','wsf','ps1','sh','jar','py','pl','cgi','asp','aspx','jsp','htm','html','svg','hta','lnk','reg','docm','xlsm','xlsb','pptm'];
 var MAX_ENTRADAS_ZIP = 3000, MAX_DESCOMPRIMIDO = 300 * 1048576, MAX_LEER_COMPLETO = 40 * 1048576;

 function problemaDeNombre(nombre) {
  if (!nombre || /[\u0000-\u001F\u007F]/.test(nombre) || nombre.indexOf('..') !== -1 || /[\/\\]/.test(nombre))
   return '"' + String(nombre).replace(/[\u0000-\u001F\u007F]/g, '?') + '" tiene caracteres no permitidos en el nombre. Renómbralo solo con letras, números, espacios o guiones.';
  var partes = nombre.toLowerCase().split('.'); partes.shift();   // lo de antes del primer punto es el nombre, no una extensión
  partes.pop();                                                    // la última es la extensión real: ya se validó aparte
  for (var i = 0; i < partes.length; i++)
   if (EXT_PELIGROSAS.indexOf(partes[i].trim()) !== -1) return '"' + nombre + '" tiene una doble extensión sospechosa (".' + partes[i].trim() + '"). No se puede subir.';
  return null;
 }

 /* PDF: se quita lo que hay entre stream y endstream (datos binarios, donde cualquier combinación de bytes puede salir por azar) y se busca contenido activo */
 function problemaDePdfTexto(txt) {
  var sin = '', pos = 0, n = txt.length;
  while (pos < n) {
   var ini = txt.indexOf('stream', pos);
   if (ini === -1) { sin += txt.substring(pos); break; }
   sin += txt.substring(pos, ini);
   var fin = txt.indexOf('endstream', ini + 6);
   if (fin === -1) break;
   pos = fin + 9;
  }
  var m = /\/(JavaScript|JS|Launch|EmbeddedFile|EmbeddedFiles|RichMedia|XFA|SubmitForm|ImportData)(?![A-Za-z0-9])/.exec(sin);
  return m ? 'trae contenido activo no permitido (/' + m[1] + '): scripts, adjuntos o acciones automáticas. Expórtalo de nuevo a PDF desde Word ("Guardar como PDF") para que quede limpio.' : null;
 }

 /* Word / Excel (.docx / .xlsx) ya abierto con JSZip */
 function problemaDeZip(z) {
  var nombres = Object.keys(z.files);
  if (nombres.length > MAX_ENTRADAS_ZIP) return 'trae demasiadas partes internas (parece una bomba de compresión).';
  var total = 0;
  for (var i = 0; i < nombres.length; i++) {
   var nom = nombres[i].replace(/\\/g, '/'), min = nom.toLowerCase();
   if (nom.charAt(0) === '/' || nom.indexOf('../') !== -1) return 'trae rutas internas peligrosas.';
   if (/vbaproject|macrosheets\/|activex\/|\/embeddings\/|oleobject|vbadata/.test(min)) return 'trae macros, ActiveX u objetos incrustados. Guárdalo sin macros (.docx / .xlsx normal).';
   var e = /\.([a-z0-9]+)$/.exec(min);
   if (e && e[1] !== 'svg' && EXT_PELIGROSAS.indexOf(e[1]) !== -1) return 'trae adentro un archivo ejecutable o script (.' + e[1] + ').';
   var d = z.files[nombres[i]] && z.files[nombres[i]]._data;
   if (d && d.uncompressedSize) {
    total += d.uncompressedSize;
    if (d.compressedSize > 0 && d.uncompressedSize > 5 * 1048576 && d.uncompressedSize / d.compressedSize > 200) return 'una parte está comprimida de forma sospechosa (bomba de compresión).';
   }
  }
  if (total > MAX_DESCOMPRIMIDO) return 'ya abierto pesa demasiado (posible bomba de compresión).';
  var ct = z.file('[Content_Types].xml');
  if (!ct) return null;
  return ct.async('string').then(function (xml) {
   if (/macroEnabled/i.test(xml)) return 'está guardado con macros activadas (docm/xlsm). Guárdalo como .docx o .xlsx normal.';
   var rels = nombres.filter(function (n) { return /\.rels$/i.test(n); }), seq = Promise.resolve(null);
   rels.forEach(function (n) {
    seq = seq.then(function (ya) {
     if (ya) return ya;
     return z.file(n).async('string').then(function (x) {
      return /TargetMode="External"/i.test(x) && /relationships\/(attachedTemplate|oleObject|subDocument|frame|externalLink|externalLinkPath|package)/i.test(x)
       ? 'carga contenido externo (plantilla remota u objeto vinculado).' : null;
     });
    });
   });
   return seq;
  });
 }

 /* Excel antiguo (.xls, formato OLE): los macros viven en un flujo llamado _VBA_PROJECT_CUR / VBA (guardado en UTF-16) */
 function problemaDeXls(bytes) {
  var t = ['_VBA_PROJECT', 'Macros', 'XLM_MACRO'], i, k, j, ok;
  for (i = 0; i < t.length; i++) {
   var pat = []; for (k = 0; k < t[i].length; k++) { pat.push(t[i].charCodeAt(k)); pat.push(0); }
   for (k = 0; k + pat.length <= bytes.length; k++) {
    if (bytes[k] !== pat[0]) continue;
    ok = true; for (j = 1; j < pat.length; j++) if (bytes[k + j] !== pat[j]) { ok = false; break; }
    if (ok) return 'trae macros (VBA). Guárdalo como .xlsx normal, sin macros.';
   }
  }
  return null;
 }

 /* Texto plano (CSV): nada de etiquetas de página web, PHP ni scripts */
 function problemaDeTexto(txt) {
  return /<\s*script|<\?php|<\?=|<\s*iframe|<\s*html|javascript\s*:|<\s*object|<\s*embed|on(error|load)\s*=/i.test(txt) ? 'trae código de página web o de programa (scripts, PHP, HTML). Un CSV solo debe tener datos.' : null;
 }

 /* Celdas con fórmulas peligrosas: =cmd|..., =HYPERLINK(...), DDE, etc. (inyección de fórmulas al abrir en Excel).
    Devuelve el problema o null; las celdas normales con "=" "+" "@" al principio se limpian con sanearCelda. */
 var RX_FORMULA_PELIGROSA = /^[\s\t\r]*[=+\-@][\s\S]*(\bcmd\b|powershell|mshta|\bdde\b|hyperlink\s*\(|importxml|importdata|importhtml|webservice\s*\(|\bshell\b|\|['"]?\s*\/c|\bcalc\b\s*['"!])/i;
 function problemaDeCeldas(filas) {
  for (var i = 0; i < filas.length; i++) {
   var r = filas[i]; if (!r) continue;
   for (var j = 0; j < r.length; j++) {
    var c = r[j];
    if (typeof c === 'string' && RX_FORMULA_PELIGROSA.test(c)) return 'la fila ' + (i + 1) + ' trae una fórmula peligrosa (' + c.slice(0, 40).replace(/[\u0000-\u001F]/g, ' ') + '…). No se cargó nada.';
    if (typeof c === 'string' && /<\s*script|<\?php|javascript\s*:/i.test(c)) return 'la fila ' + (i + 1) + ' trae código de página web (scripts). No se cargó nada.';
   }
  }
  return null;
 }
 function sanearCelda(c) {
  return typeof c === 'string' ? c.replace(/^[\s\t\r]*[=+@]+\s*/, '').replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, '') : c;
 }

 function validar(file, tipos) {
  var nombre = file && file.name ? file.name : 'el archivo';
  var m = /\.([A-Za-z0-9]+)$/.exec(nombre), ext = m ? m[1].toLowerCase() : '';
  if (tipos.indexOf(ext) === -1) return Promise.resolve('"' + nombre + '" no se puede subir: solo se permiten archivos ' + listaTipos(tipos) + '.');
  var pn = problemaDeNombre(nombre); if (pn) return Promise.resolve(pn);
  if (file.size > MAX_LEER_COMPLETO + 1) return Promise.resolve('"' + nombre + '" pesa demasiado para ser una lista o una plantilla (máximo ' + Math.round(MAX_LEER_COMPLETO / 1048576) + ' MB).');
  if (!file.size) return Promise.resolve('"' + nombre + '" está vacío (pesa 0 KB). Vuelve a guardarlo desde el programa donde lo hiciste.');
  var danado = '"' + nombre + '" está dañado o no es un archivo ' + NOMBRE_TIPO[ext] + ' de verdad. Ábrelo en su programa, guárdalo de nuevo y súbelo otra vez.';
  var otroTipo = '"' + nombre + '" dice ser ' + NOMBRE_TIPO[ext] + ' pero por dentro es otro tipo de archivo. Guárdalo con "Guardar como" en el formato correcto.';

  return file.slice(0, 8192).arrayBuffer().then(function (cab) {
   var b = new Uint8Array(cab);
   var esZip = empieza(b, [0x50, 0x4B, 0x03, 0x04]);
   var esPdf = empieza(b, [0x25, 0x50, 0x44, 0x46, 0x2D]);                       // %PDF-
   var esOle = empieza(b, [0xD0, 0xCF, 0x11, 0xE0, 0xA1, 0xB1, 0x1A, 0xE1]);     // Excel antiguo (.xls)
   var esImagen = empieza(b, [0xFF, 0xD8, 0xFF]) || empieza(b, [0x89, 0x50, 0x4E, 0x47]) || empieza(b, [0x47, 0x49, 0x46, 0x38]);
   var esExe = empieza(b, [0x4D, 0x5A]) || empieza(b, [0x7F, 0x45, 0x4C, 0x46]) || empieza(b, [0x23, 0x21]);   // programa o script

   if (ext === 'pdf') {
    if (!esPdf) return (esZip || esOle || esImagen || esExe) ? otroTipo : danado;
    // Un PDF completo termina con %%EOF: si falta, se cortó al guardarlo o al descargarlo
    return file.slice(Math.max(0, file.size - 2048)).text().then(function (fin) {
     if (fin.indexOf('%%EOF') === -1) return danado;
     return file.arrayBuffer().then(function (buf) {
      var p = problemaDePdfTexto(new TextDecoder('latin1').decode(new Uint8Array(buf)));
      return p ? '"' + nombre + '" ' + p : null;
     });
    });
   }
   if (ext === 'docx' || ext === 'xlsx') {
    if (!esZip) return (esPdf || esOle || esImagen || esExe) ? otroTipo : danado;
    if (!window.JSZip) return null;
    return file.arrayBuffer().then(function (buf) { return JSZip.loadAsync(buf); }).then(function (z) {
     var clave = ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
     if (!z.file(clave)) return (z.file('word/document.xml') || z.file('xl/workbook.xml') || z.file('ppt/presentation.xml')) ? otroTipo : danado;
     return Promise.resolve(problemaDeZip(z)).then(function (p) { return p ? '"' + nombre + '" ' + p : null; });
    }, function () { return danado; });   // el zip está truncado o corrupto
   }
   if (ext === 'xls') {
    if (!esOle) return (esZip || esPdf || esImagen || esExe) ? otroTipo : danado;
    return file.arrayBuffer().then(function (buf) { var p = problemaDeXls(new Uint8Array(buf)); return p ? '"' + nombre + '" ' + p : null; });
   }
   // CSV: texto plano. Un archivo binario (foto, PDF, programa, Excel) trae bytes 0 o cabeceras conocidas.
   if (esZip || esPdf || esOle || esImagen || esExe) return otroTipo;
   var utf16 = (b[0] === 0xFF && b[1] === 0xFE) || (b[0] === 0xFE && b[1] === 0xFF);
   if (!utf16) for (var i = 0; i < b.length; i++) if (b[i] === 0) return danado;
   var texto = new TextDecoder(utf16 ? 'utf-16le' : 'latin1').decode(b);
   if (!/[^\s,;|]/.test(texto)) return '"' + nombre + '" no tiene información (solo comas, espacios o filas vacías).';
   return file.arrayBuffer().then(function (buf) {
    var p = problemaDeTexto(new TextDecoder(utf16 ? 'utf-16le' : 'latin1').decode(new Uint8Array(buf)));
    return p ? '"' + nombre + '" ' + p : null;
   });
  }).catch(function () { return danado; });
 }

 /* Valida varios archivos y junta todos los problemas en un solo texto (null si todos están bien) */
 function validarVarios(archivos, tipos) {
  return Promise.all(Array.prototype.map.call(archivos, function (f) { return validar(f, tipos); })).then(function (r) {
   var malos = r.filter(Boolean);
   return malos.length ? malos.join('\n\n') : null;
  });
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

 window.SicaCompresion = {problemaDeCeldas: problemaDeCeldas, sanearCelda: sanearCelda, problemaDeNombre: problemaDeNombre, comprimirArchivo: comprimirArchivo, validar: validar, validarVarios: validarVarios, cola: cola, pesoLegible: pesoLegible};
})();
