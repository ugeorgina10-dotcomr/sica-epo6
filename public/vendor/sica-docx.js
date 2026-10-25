/* SICA-EPO6 — Lector de plantillas de Word (.docx) para las credenciales.
 *
 * Lee el archivo de Word (cuadros de texto, grupos, imágenes, líneas, tablas, párrafos con tabuladores),
 * lo acomoda en "puntos" (1 pt = 1/72 de pulgada, igual que Word) y lo dibuja en un <canvas>.
 * Encuentra solo:
 *   - el cuadro "QR"            -> ahí se coloca el código QR del alumno
 *   - las etiquetas NOMBRE / CURP / GRADO / GRUPO / TURNO -> los datos se escriben junto a cada una
 * Cuando se cambia el diseño en Word basta con subir el archivo de nuevo: todo se vuelve a acomodar.
 *
 * Necesita JSZip (vendor/jszip.min.js). Uso:
 *   const m = await SicaDocx.cargar(arrayBuffer);        // lee y acomoda el Word
 *   SicaDocx.dibujar(canvas, m, datos|null, {ancho:760}); // datos = {nombre, curp, grado, grupo, turno}
 *   m.ancho / m.alto = tamaño real en puntos de la credencial
 */
(function (global) {
 'use strict';

 const NS = {
  w: 'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
  wp: 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
  a: 'http://schemas.openxmlformats.org/drawingml/2006/main',
  pic: 'http://schemas.openxmlformats.org/drawingml/2006/picture',
  wps: 'http://schemas.microsoft.com/office/word/2010/wordprocessingShape',
  wpg: 'http://schemas.microsoft.com/office/word/2010/wordprocessingGroup',
  mc: 'http://schemas.openxmlformats.org/markup-compatibility/2006',
  r: 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'
 };
 const EMU = 12700;           // 1 pt = 12700 EMU
 const num = (v, d) => { const n = parseFloat(v); return isNaN(n) ? (d === undefined ? 0 : d) : n; };

 /* ---------- ayudas de XML ---------- */
 const kids = (el, ns, name) => {
  const out = [];
  if (!el) return out;
  for (let c = el.firstElementChild; c; c = c.nextElementSibling) {
   if (c.localName === name && (!ns || c.namespaceURI === NS[ns])) out.push(c);
  }
  return out;
 };
 const kid = (el, ns, name) => kids(el, ns, name)[0] || null;
 const wa = (el, name) => el ? (el.getAttributeNS(NS.w, name) || el.getAttribute('w:' + name) || '') : '';
 const at = (el, name) => el ? (el.getAttribute(name) || '') : '';
 const parseXml = s => new DOMParser().parseFromString(s, 'application/xml');
 const boolVal = e => { if (!e) return undefined; const v = wa(e, 'val'); return !(v === '0' || v === 'false' || v === 'off'); };
 /* descendiente directo o profundo por nombre local */
 const deep = (el, name, ns) => {
  if (!el) return null;
  const l = el.getElementsByTagNameNS(ns ? NS[ns] : '*', name);
  return l.length ? l[0] : null;
 };

 /* ---------- medición de texto ---------- */
 const mcanvas = document.createElement('canvas');
 const mctx = mcanvas.getContext('2d');
 function familia(n) {
  n = n || 'Calibri';
  const q = '"' + n + '"';
  if (/times|georgia|cambria|garamond|serif/i.test(n) && !/sans/i.test(n)) return q + ',"Liberation Serif","Times New Roman",serif';
  if (/courier|consolas|mono/i.test(n)) return q + ',"Courier New",monospace';
  if (/calibri/i.test(n)) return q + ',Carlito,Calibri,Arial,"Liberation Sans",sans-serif';
  return q + ',Arial,"Liberation Sans",Helvetica,sans-serif';
 }
 const fontStr = (r, px) => (r.i ? 'italic ' : '') + (r.b ? 'bold ' : '') + px + 'px ' + familia(r.font);
 function medir(txt, r) {
  if (!txt) return 0;
  mctx.font = fontStr(r, r.size * 100);
  return mctx.measureText(txt).width / 100 + (r.spacing || 0) * txt.length;
 }
 const medirSimple = (txt, tam, negrita, fuente) => medir(txt, {size: tam, b: negrita, font: fuente || 'Arial'});

 /* ---------- propiedades de texto y de párrafo ---------- */
 function parseRPr(el) {
  const o = {};
  if (!el) return o;
  const f = kid(el, 'w', 'rFonts');
  if (f) { const n = wa(f, 'ascii') || wa(f, 'hAnsi'); if (n) o.font = n; }
  const sz = kid(el, 'w', 'sz'); if (sz) o.size = num(wa(sz, 'val')) / 2;
  const b = boolVal(kid(el, 'w', 'b')); if (b !== undefined) o.b = b;
  const i = boolVal(kid(el, 'w', 'i')); if (i !== undefined) o.i = i;
  const u = kid(el, 'w', 'u'); if (u) { const v = wa(u, 'val'); o.u = !!v && v !== 'none'; }
  const c = kid(el, 'w', 'color'); if (c) { const v = wa(c, 'val'); if (v && v !== 'auto') o.color = '#' + v; }
  const sp = kid(el, 'w', 'spacing'); if (sp) o.spacing = num(wa(sp, 'val')) / 20;
  const caps = boolVal(kid(el, 'w', 'caps')); if (caps !== undefined) o.caps = caps;
  return o;
 }
 function parsePPr(el) {
  const o = {};
  if (!el) return o;
  const jc = kid(el, 'w', 'jc'); if (jc) o.jc = wa(jc, 'val');
  const ind = kid(el, 'w', 'ind');
  if (ind) {
   const l = wa(ind, 'left') || wa(ind, 'start'), r = wa(ind, 'right') || wa(ind, 'end');
   if (l !== '') o.indL = num(l) / 20;
   if (r !== '') o.indR = num(r) / 20;
   if (wa(ind, 'firstLine') !== '') o.first = num(wa(ind, 'firstLine')) / 20;
   if (wa(ind, 'hanging') !== '') o.first = -num(wa(ind, 'hanging')) / 20;
  }
  const sp = kid(el, 'w', 'spacing');
  if (sp) {
   if (wa(sp, 'before') !== '') o.before = num(wa(sp, 'before')) / 20;
   if (wa(sp, 'after') !== '') o.after = num(wa(sp, 'after')) / 20;
   if (wa(sp, 'line') !== '') { o.line = num(wa(sp, 'line')); o.lineRule = wa(sp, 'lineRule') || 'auto'; }
  }
  const tabs = kid(el, 'w', 'tabs');
  if (tabs) {
   o.tabs = kids(tabs, 'w', 'tab').map(t => ({pos: num(wa(t, 'pos')) / 20, val: wa(t, 'val') || 'left'})).filter(t => t.val !== 'clear');
  }
  return o;
 }
 const unir = (...partes) => Object.assign({}, ...partes.filter(Boolean));

 /* =====================================================================
    Lectura de todo el documento
    ===================================================================== */
 async function cargar(buffer) {
  if (!global.JSZip) throw new Error('Falta la librería JSZip.');
  let zip;
  try { zip = await global.JSZip.loadAsync(buffer); }
  catch (e) { throw new Error('El archivo no es un documento de Word (.docx) válido.'); }
  const docFile = zip.file('word/document.xml');
  if (!docFile) throw new Error('El archivo no es un documento de Word (.docx) válido.');
  const doc = parseXml(await docFile.async('string'));
  const stFile = zip.file('word/styles.xml');
  const sty = stFile ? parseXml(await stFile.async('string')) : null;
  const relFile = zip.file('word/_rels/document.xml.rels');
  const rels = {};
  if (relFile) {
   const rx = parseXml(await relFile.async('string'));
   Array.prototype.forEach.call(rx.getElementsByTagName('Relationship'), r => { rels[r.getAttribute('Id')] = r.getAttribute('Target'); });
  }
  // Imágenes del documento
  const imgs = {};
  const ext2mime = {png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', bmp: 'image/bmp', webp: 'image/webp'};
  await Promise.all(Object.keys(rels).map(async id => {
   const t = rels[id];
   if (!/^media\//i.test(t) && !/\.(png|jpe?g|gif|bmp|webp)$/i.test(t)) return;
   const f = zip.file('word/' + t.replace(/^\/?(word\/)?/, ''));
   if (!f) return;
   const m = ext2mime[(t.split('.').pop() || '').toLowerCase()];
   if (!m) return;
   const b64 = await f.async('base64');
   await new Promise(ok => {
    const im = new Image();
    im.onload = () => { imgs[id] = im; ok(); };
    im.onerror = () => ok();
    im.src = 'data:' + m + ';base64,' + b64;
   });
  }));
  return acomodar(doc, sty, imgs);
 }

 /* =====================================================================
    Acomodo (layout)
    ===================================================================== */
 function acomodar(doc, sty, imgs) {
  const S = {out: [], boxes: {}, labels: [], avisos: [], ph: null};
  const body = deep(doc, 'body', 'w');

  /* ----- estilos ----- */
  const estilos = {};
  let defRPr = {}, defPPr = {}, estiloDef = null;
  if (sty) {
   const dd = deep(sty, 'docDefaults', 'w');
   if (dd) {
    defRPr = parseRPr(deep(kid(dd, 'w', 'rPrDefault'), 'rPr', 'w'));
    defPPr = parsePPr(deep(kid(dd, 'w', 'pPrDefault'), 'pPr', 'w'));
   }
   Array.prototype.forEach.call(sty.getElementsByTagNameNS(NS.w, 'style'), s => {
    const id = wa(s, 'styleId');
    estilos[id] = {
     tipo: wa(s, 'type'),
     base: wa(kid(s, 'w', 'basedOn'), 'val'),
     rPr: parseRPr(kid(s, 'w', 'rPr')),
     pPr: parsePPr(kid(s, 'w', 'pPr'))
    };
    if (wa(s, 'type') === 'paragraph' && wa(s, 'default') === '1') estiloDef = id;
   });
  }
  const cadena = id => {
   const out = []; let n = 0;
   while (id && estilos[id] && n++ < 12) { out.unshift(estilos[id]); id = estilos[id].base; }
   return out;
  };
  function runBase(estiloId) {
   const ch = cadena(estiloId || estiloDef);
   const dflt = estiloDef && estiloId !== estiloDef ? cadena(estiloDef) : [];
   return unir({font: 'Calibri', size: 11}, defRPr, ...dflt.map(e => e.rPr), ...ch.map(e => e.rPr));
  }
  function parBase(estiloId) {
   const ch = cadena(estiloId || estiloDef);
   const dflt = estiloDef && estiloId !== estiloDef ? cadena(estiloDef) : [];
   return unir(defPPr, ...dflt.map(e => e.pPr), ...ch.map(e => e.pPr));
  }

  /* ----- página y secciones ----- */
  const bodyKids = [];
  for (let c = body.firstElementChild; c; c = c.nextElementSibling) bodyKids.push(c);
  const sectDe = p => kid(kid(p, 'w', 'pPr'), 'w', 'sectPr');
  const finalSect = bodyKids.filter(c => c.localName === 'sectPr').pop() || null;
  const pagSect = finalSect || bodyKids.map(sectDe).filter(Boolean).pop();
  const pgSz = kid(pagSect, 'w', 'pgSz'), pgMar = kid(pagSect, 'w', 'pgMar');
  const page = {
   w: pgSz ? num(wa(pgSz, 'w'), 12240) / 20 : 612, h: pgSz ? num(wa(pgSz, 'h'), 15840) / 20 : 792,
   ml: pgMar ? num(wa(pgMar, 'left'), 1440) / 20 : 72, mr: pgMar ? num(wa(pgMar, 'right'), 1440) / 20 : 72,
   mt: pgMar ? num(wa(pgMar, 'top'), 1440) / 20 : 72
  };
  const contenidoW = page.w - page.ml - page.mr;
  const seccionDe = new Array(bodyKids.length);
  {
   let actual = finalSect;
   for (let i = bodyKids.length - 1; i >= 0; i--) {
    const sp = bodyKids[i].localName === 'p' ? sectDe(bodyKids[i]) : null;
    if (sp) actual = sp;
    seccionDe[i] = actual;
   }
  }
  function anchoColumna(sect) {
   const cols = kid(sect, 'w', 'cols');
   const n = cols ? num(wa(cols, 'num'), 1) : 1;
   if (!cols || n < 2) return contenidoW;
   const cc = kids(cols, 'w', 'col');
   if (cc.length && wa(cc[0], 'w')) return num(wa(cc[0], 'w')) / 20;
   const sep = num(wa(cols, 'space'), 720) / 20;
   return (contenidoW - sep * (n - 1)) / n;
  }

  /* ----- salida de operaciones ----- */
  const emitir = op => { if (op.layer === undefined) op.layer = 1; if (S.ph && op.t === 't') op.ph = S.ph; S.out.push(op); };
  function conSalida(fn) { const guardada = S.out; S.out = []; const r = fn(); const tmp = S.out; S.out = guardada; return {r, ops: tmp}; }
  function mover(op, dy) {
   if (op.y !== undefined) op.y += dy;
   if (op.cmds) op.cmds.forEach(c => { for (let k = 2; k < c.length; k += 2) c[k] += dy; });
  }

  /* ----- párrafos ----- */
  function resolverRun(estiloPar, rPrDirecto) {
   return unir(runBase(estiloPar), rPrDirecto);
  }

  function layoutP(p, x0, y, w) {
   const pPr = kid(p, 'w', 'pPr');
   const estiloId = wa(kid(pPr, 'w', 'pStyle'), 'val') || null;
   const pp = unir(parBase(estiloId), parsePPr(pPr));
   const marca = resolverRun(estiloId, parseRPr(kid(pPr, 'w', 'rPr')));
   const indL = pp.indL || 0, indR = pp.indR || 0, first = pp.first || 0;
   const paraTop = y;
   y += pp.before || 0;
   const items = [];
   const reunir = (nodo, extra) => {
    for (let c = nodo.firstElementChild; c; c = c.nextElementSibling) {
     const n = c.localName;
     if (n === 'r') {
      const rPr = parseRPr(kid(c, 'w', 'rPr'));
      const rs = unir(resolverRun(estiloId, null), rPr);
      if (rs.caps) rs._caps = true;
      for (let e = c.firstElementChild; e; e = e.nextElementSibling) {
       const en = e.localName;
       if (en === 't') items.push({k: 't', s: rs._caps ? e.textContent.toUpperCase() : e.textContent, r: rs});
       else if (en === 'tab') items.push({k: 'tab', r: rs});
       else if (en === 'br') { const ty = wa(e, 'type'); if (!ty || ty === 'textWrapping') items.push({k: 'br', r: rs}); }
       else if (en === 'noBreakHyphen') items.push({k: 't', s: '-', r: rs});
       else if (en === 'drawing') dibujoEnRun(e, items);
       else if (en === 'AlternateContent') {
        const ch = kid(e, 'mc', 'Choice') || kid(e, 'mc', 'Fallback');
        const dr = ch && kid(ch, 'w', 'drawing');
        if (dr) dibujoEnRun(dr, items);
       } else if (en === 'pict') S.avisos.push('Hay un elemento antiguo de Word (VML) que no se puede dibujar.');
      }
     } else if (n === 'hyperlink' || n === 'ins' || n === 'smartTag' || n === 'sdt' || n === 'sdtContent' || n === 'fldSimple') reunir(c, extra);
    }
   };
   function dibujoEnRun(dr, lista) {
    const inl = kid(dr, 'wp', 'inline'), anc = kid(dr, 'wp', 'anchor');
    const nodo = inl || anc;
    if (!nodo) return;
    const ext = kid(nodo, 'wp', 'extent');
    const w2 = num(at(ext, 'cx')) / EMU, h2 = num(at(ext, 'cy')) / EMU;
    const gd = deep(nodo, 'graphicData', 'a');
    if (inl) lista.push({k: 'inline', w: w2, h: h2, gd: gd});
    else lista.push({k: 'anchor', nodo: nodo, w: w2, h: h2, gd: gd});
   }
   reunir(p);
   // Un párrafo vacío que solo trae el salto de sección no ocupa renglón (así lo muestran Word y LibreOffice)
   if (kid(pPr, 'w', 'sectPr') && !items.length) return 0;
   const soloPunto = items.length > 0 && items.every(i => i.k === 't' || i.k === 'tab' || i.k === 'br') && items.map(i => i.s || '').join('').trim() === '.';

   // Tabuladores
   const tabs = (pp.tabs || []).slice().sort((a, b) => a.pos - b.pos);
   const siguienteTab = rel => {
    for (const t of tabs) if (t.pos > rel + 0.05) return t.pos;
    return (Math.floor((rel + 0.05) / 36) + 1) * 36;
   };

   // Partir en líneas
   const lineas = [];
   let ln = {segs: [], x: indL + first, hayTexto: false};
   const limiteD = () => w - indR;
   const cerrar = () => { lineas.push(ln); ln = {segs: [], x: indL, hayTexto: false}; };
   for (const it of items) {
    if (it.k === 'br') { ln.segs.push({k: 'brk', r: it.r}); cerrar(); continue; }
    if (it.k === 'tab') {
     const stop = siguienteTab(ln.x);
     ln.segs.push({k: 'tab', x: ln.x, w: stop - ln.x, r: it.r});
     ln.x = stop; ln.hayTexto = true; continue;
    }
    if (it.k === 'inline') {
     if (ln.hayTexto && ln.x + it.w > limiteD() + 0.01) cerrar();
     ln.segs.push({k: 'inline', x: ln.x, w: it.w, h: it.h, gd: it.gd, r: marca});
     ln.x += it.w; ln.hayTexto = true; continue;
    }
    if (it.k === 'anchor') { ln.segs.push({k: 'anchor', x: ln.x, it: it, r: marca}); continue; }
    if (it.k === 't') {
     const partes = it.s.split(/(\s+)/).filter(s => s !== '');
     for (const tk of partes) {
      const esEsp = /^\s+$/.test(tk);
      const ancho = medir(tk, it.r);
      if (!esEsp && ln.hayTexto && ln.x + ancho > limiteD() + 0.01) cerrar();
      if (esEsp && !ln.hayTexto && ln.segs.length === 0 && lineas.length > 0) continue;   // sin espacios al inicio de una línea nueva
      ln.segs.push({k: 't', s: tk, x: ln.x, w: ancho, r: it.r, esp: esEsp});
      ln.x += ancho;
      if (!esEsp) ln.hayTexto = true;
     }
    }
   }
   cerrar();

   // Alturas y colocación de cada línea
   let cy = y;
   const textoLineas = [];
   lineas.forEach((l, idx) => {
    let tamMax = 0, hInline = 0, hayAlgo = false;
    l.segs.forEach(s => {
     if (s.k === 't' || s.k === 'tab') { tamMax = Math.max(tamMax, s.r.size); hayAlgo = true; }
     if (s.k === 'inline') hInline = Math.max(hInline, s.h);
    });
    if (!hayAlgo && !hInline) tamMax = marca.size;
    if (!tamMax && hInline) tamMax = 0;
    const natural = Math.max(tamMax * 1.15, hInline ? hInline + tamMax * 0.212 : 0);
    let alto, base;
    const regla = pp.lineRule || 'auto', val = pp.line;
    if (val && regla === 'exact') { alto = val / 20; base = cy + alto * 0.8; }
    else if (val && regla === 'atLeast') { alto = Math.max(val / 20, natural); base = cy + alto - tamMax * 0.212; }
    else { alto = natural * ((val || 240) / 240); base = cy + alto - tamMax * 0.212; }
    // alineación
    let corrimiento = 0;
    const hayTab = l.segs.some(s => s.k === 'tab');
    if (!hayTab && (pp.jc === 'center' || pp.jc === 'right' || pp.jc === 'end')) {
     let fin = indL + (idx === 0 ? first : 0);
     const visibles = l.segs.filter(s => s.k === 't' || s.k === 'inline');
     let anchoL = 0;
     if (visibles.length) {
      const ult = (() => { for (let k = visibles.length - 1; k >= 0; k--) if (!visibles[k].esp) return visibles[k]; return visibles[visibles.length - 1]; })();
      anchoL = ult.x + ult.w - visibles[0].x;
      fin = visibles[0].x;
     }
     const libre = (w - indR) - (fin);
     corrimiento = pp.jc === 'center' ? (libre - anchoL) / 2 : (libre - anchoL);
     if (corrimiento < 0) corrimiento = 0;
    }
    const lineTop = cy;
    let textoPlano = '', mapa = [];
    l.segs.forEach(s => {
     const ax = x0 + s.x + corrimiento;
     if (s.k === 't') {
      if (!soloPunto) emitir({t: 't', x: ax, y: base, s: s.s, f: s.r, w: s.w, u: s.r.u && !s.esp});
      if (s.r.u && s.esp) emitir({t: 'ul', x1: ax, x2: ax + s.w, y: base + s.r.size * 0.12, th: Math.max(s.r.size / 16, 0.3), color: s.r.color || '#000'});
      mapa.push({ini: textoPlano.length, seg: s, x: ax}); textoPlano += s.s;
     } else if (s.k === 'tab') {
      if (s.r.u) emitir({t: 'ul', x1: ax, x2: ax + s.w, y: base + s.r.size * 0.12, th: Math.max(s.r.size / 16, 0.3), color: s.r.color || '#000'});
      mapa.push({ini: textoPlano.length, seg: s, x: ax}); textoPlano += ' ';
     } else if (s.k === 'inline') {
      dibujarGD(s.gd, ax, base - s.h, s.w, s.h, 1);
     } else if (s.k === 'anchor') {
      s.abs = {x: ax, lineTop: lineTop};
     }
    });
    // etiquetas (NOMBRE:, CURP:, ...)
    const re = /(NOMBRE(?:\s+DEL\s+ESTUDIANTE)?|CURP|GRADO|GRUPO|TURNO)\s*:/gi;
    let mm;
    while ((mm = re.exec(textoPlano))) {
     const posIni = mm.index, posFin = mm.index + mm[0].length;
     const xDe = (pos, fin) => {
      let m = null;
      for (const e of mapa) if (e.ini <= pos) m = e;
      if (!m) return x0 + indL;
      if (m.seg.k !== 't') return m.x + (fin ? m.seg.w : 0);
      return m.x + medir(m.seg.s.slice(0, pos - m.ini + (fin ? 0 : 0)), m.seg.r);
     };
     const clave = mm[1].toUpperCase().startsWith('NOMBRE') ? 'nombre' : mm[1].toLowerCase();
     S.labels.push({
      k: clave, xIni: xDe(posIni, false), xFin: xDe(posFin, false), base: base, size: marca.size && tamMax ? tamMax : marca.size,
      lineTop: lineTop, lineH: alto, izq: x0 + indL, ancho: w, y: lineTop
     });
    }
    cy += alto;
   });
   const altoTotal = cy - paraTop;
   // anclas (imágenes y figuras flotantes)
   lineas.forEach(l => l.segs.forEach(s => {
    if (s.k !== 'anchor') return;
    colocarAncla(s.it.nodo, {x0: x0, w: w, paraTop: paraTop, charX: s.abs ? s.abs.x : x0, lineTop: s.abs ? s.abs.lineTop : paraTop});
   }));
   return (cy - y) + (pp.after || 0) + (pp.before || 0);
  }

  /* ----- bloques: párrafos y tablas ----- */
  function layoutBloque(nodos, x0, y, w) {
   let cy = y;
   for (const n of nodos) {
    if (n.localName === 'p') cy += layoutP(n, x0, cy, w);
    else if (n.localName === 'tbl') cy += layoutTabla(n, x0, cy, w);
   }
   return cy - y;
  }

  function layoutTabla(tbl, x0, y, w) {
   const tblPr = kid(tbl, 'w', 'tblPr');
   const ind = num(wa(kid(tblPr, 'w', 'tblInd'), 'w')) / 20;
   const estT = wa(kid(tblPr, 'w', 'tblStyle'), 'val');
   const mar = {l: 0, r: 0, t: 0, b: 0};
   const leerMar = el => {
    if (!el) return;
    const g = (n, k) => { const e = kid(el, 'w', n) || kid(el, 'w', n === 'left' ? 'start' : n === 'right' ? 'end' : n); if (e) mar[k] = num(wa(e, 'w')) / 20; };
    g('left', 'l'); g('right', 'r'); g('top', 't'); g('bottom', 'b');
   };
   if (sty && estT) {
    Array.prototype.forEach.call(sty.getElementsByTagNameNS(NS.w, 'style'), s => { if (wa(s, 'styleId') === estT) leerMar(deep(kid(s, 'w', 'tblPr'), 'tblCellMar', 'w')); });
   }
   leerMar(kid(tblPr, 'w', 'tblCellMar'));
   const grid = kids(kid(tbl, 'w', 'tblGrid'), 'w', 'gridCol').map(g => num(wa(g, 'w')) / 20);
   let cy = y;
   kids(tbl, 'w', 'tr').forEach(tr => {
    const trPr = kid(tr, 'w', 'trPr');
    const hMin = num(wa(kid(trPr, 'w', 'trHeight'), 'val')) / 20;
    let cx = x0 + ind, col = 0, altoFila = hMin;
    const celdas = [];
    kids(tr, 'w', 'tc').forEach(tc => {
     const tcPr = kid(tc, 'w', 'tcPr');
     const span = Math.max(1, num(wa(kid(tcPr, 'w', 'gridSpan'), 'val'), 1));
     let cw = num(wa(kid(tcPr, 'w', 'tcW'), 'w')) / 20;
     if (!cw) { cw = 0; for (let k = 0; k < span; k++) cw += grid[col + k] || 0; }
     const shd = kid(tcPr, 'w', 'shd');
     const fill = shd && wa(shd, 'fill') && wa(shd, 'fill') !== 'auto' ? '#' + wa(shd, 'fill') : null;
     const contenido = kids(tc, 'w', 'p').concat(kids(tc, 'w', 'tbl')).length ? Array.prototype.filter.call(tc.children, n => n.localName === 'p' || n.localName === 'tbl') : [];
     const res = conSalida(() => layoutBloque(contenido, cx + mar.l, cy + mar.t, Math.max(cw - mar.l - mar.r, 1)));
     celdas.push({x: cx, w: cw, fill: fill, ops: res.ops});
     altoFila = Math.max(altoFila, res.r + mar.t + mar.b);
     cx += cw; col += span;
    });
    celdas.forEach(c => {
     if (c.fill) emitir({t: 'path', cmds: [['M', c.x, cy], ['L', c.x + c.w, cy], ['L', c.x + c.w, cy + altoFila], ['L', c.x, cy + altoFila], ['Z']], fill: c.fill, layer: 1});
     c.ops.forEach(o => emitir(o));
    });
    cy += altoFila;
   });
   return cy - y;
  }

  /* ----- dibujos: anclas, grupos, figuras, imágenes ----- */
  function colocarAncla(nodo, info) {
   const ext = kid(nodo, 'wp', 'extent');
   const w = num(at(ext, 'cx')) / EMU, h = num(at(ext, 'cy')) / EMU;
   const ph = kid(nodo, 'wp', 'positionH'), pv = kid(nodo, 'wp', 'positionV');
   const relH = at(ph, 'relativeFrom'), relV = at(pv, 'relativeFrom');
   const offH = kid(ph, 'wp', 'posOffset'), offV = kid(pv, 'wp', 'posOffset');
   const alH = kid(ph, 'wp', 'align'), alV = kid(pv, 'wp', 'align');
   let baseX = 0, anchoRef = page.w;
   if (relH === 'page') { baseX = 0; anchoRef = page.w; }
   else if (relH === 'margin' || relH === 'insideMargin' || relH === 'outsideMargin') { baseX = page.ml; anchoRef = contenidoW; }
   else if (relH === 'character') { baseX = info.charX; anchoRef = info.w; }
   else { baseX = info.x0; anchoRef = info.w; }
   let x = baseX + (offH ? num(offH.textContent) / EMU : 0);
   if (alH) { const a = alH.textContent; x = a === 'center' ? baseX + (anchoRef - w) / 2 : a === 'right' ? baseX + anchoRef - w : baseX; }
   let baseY = 0;
   if (relV === 'page') baseY = 0;
   else if (relV === 'margin' || relV === 'topMargin') baseY = page.mt;
   else if (relV === 'line') baseY = info.lineTop;
   else baseY = info.paraTop;
   let yy = baseY + (offV ? num(offV.textContent) / EMU : 0);
   if (alV) { const a = alV.textContent; yy = a === 'bottom' ? baseY + 0 : baseY; }
   const detras = at(nodo, 'behindDoc') === '1';
   dibujarGD(deep(nodo, 'graphicData', 'a'), x, yy, w, h, detras ? 0 : 2);
  }

  function dibujarGD(gd, x, y, w, h, capa) {
   if (!gd) return;
   const g = deep(gd, 'wgp', 'wpg') || null;
   if (gd.firstElementChild && gd.firstElementChild.localName === 'wgp') return dibujarGrupo(gd.firstElementChild, x, y, w, h, capa);
   const hijo = gd.firstElementChild;
   if (!hijo) return;
   if (hijo.localName === 'wsp') return dibujarForma(hijo, x, y, w, h, capa);
   if (hijo.localName === 'pic') return dibujarPic(hijo, x, y, w, h, capa);
  }

  function xfrmDe(spPr) {
   const xf = kid(spPr, 'a', 'xfrm');
   const off = kid(xf, 'a', 'off'), ext = kid(xf, 'a', 'ext');
   return {x: num(at(off, 'x')), y: num(at(off, 'y')), cx: num(at(ext, 'cx')), cy: num(at(ext, 'cy')), xf: xf};
  }

  function dibujarGrupo(g, x, y, w, h, capa) {
   const gp = kid(g, 'wpg', 'grpSpPr') || kid(g, 'pic', 'grpSpPr');
   const xf = kid(gp, 'a', 'xfrm');
   const chOff = kid(xf, 'a', 'chOff'), chExt = kid(xf, 'a', 'chExt');
   const ox = num(at(chOff, 'x')), oy = num(at(chOff, 'y'));
   const cw = num(at(chExt, 'cx')) || (w * EMU), ch = num(at(chExt, 'cy')) || (h * EMU);
   const sx = (w * EMU) / cw, sy = (h * EMU) / ch;
   for (let c = g.firstElementChild; c; c = c.nextElementSibling) {
    const n = c.localName;
    if (n !== 'wsp' && n !== 'pic' && n !== 'grpSp' && n !== 'wgp') continue;
    const spPr = kid(c, 'wps', 'spPr') || kid(c, 'pic', 'spPr') || kid(c, 'wpg', 'grpSpPr');
    const r = xfrmDe(spPr);
    const nx = x + (r.x - ox) * sx / EMU, ny = y + (r.y - oy) * sy / EMU, nw = r.cx * sx / EMU, nh = r.cy * sy / EMU;
    if (n === 'wsp') dibujarForma(c, nx, ny, nw, nh, capa);
    else if (n === 'pic') dibujarPic(c, nx, ny, nw, nh, capa);
    else dibujarGrupo(c, nx, ny, nw, nh, capa);
   }
  }

  function dibujarPic(pic, x, y, w, h, capa) {
   const bf = kid(pic, 'pic', 'blipFill');
   const blip = kid(bf, 'a', 'blip');
   const id = blip ? (blip.getAttributeNS(NS.r, 'embed') || blip.getAttribute('r:embed')) : null;
   const im = id ? imgs[id] : null;
   if (!im) return;
   const sr = kid(bf, 'a', 'srcRect');
   const crop = sr ? {l: num(at(sr, 'l')) / 100000, t: num(at(sr, 't')) / 100000, r: num(at(sr, 'r')) / 100000, b: num(at(sr, 'b')) / 100000} : null;
   emitir({t: 'img', x: x, y: y, w: w, h: h, img: im, crop: crop, layer: capa});
  }

  function colorDe(fillEl) {
   if (!fillEl) return null;
   const sf = kid(fillEl, 'a', 'solidFill');
   if (!sf) return null;
   const c = kid(sf, 'a', 'srgbClr');
   if (c) return '#' + at(c, 'val');
   const s = kid(sf, 'a', 'schemeClr');
   if (s) { const v = at(s, 'val'); return v === 'bg1' ? '#ffffff' : '#000000'; }
   return null;
  }

  function dibujarForma(wsp, x, y, w, h, capa) {
   const spPr = kid(wsp, 'wps', 'spPr');
   const ln = kid(spPr, 'a', 'ln');
   const relleno = kid(spPr, 'a', 'noFill') ? null : colorDe(spPr);
   let trazo = null, grosor = 0, dash = null;
   if (ln && !kid(ln, 'a', 'noFill')) {
    trazo = colorDe(ln) || (kid(ln, 'a', 'solidFill') ? '#000000' : null);
    grosor = num(at(ln, 'w'), 9525) / EMU;
    const pd = kid(ln, 'a', 'prstDash'); dash = pd ? at(pd, 'val') : null;
   }
   // contorno de la figura
   const cg = kid(spPr, 'a', 'custGeom'), pg = kid(spPr, 'a', 'prstGeom');
   let cmds = null;
   if (cg) {
    const pl = kid(cg, 'a', 'pathLst');
    const path = kid(pl, 'a', 'path');
    if (path) {
     const pw = num(at(path, 'w')), ph = num(at(path, 'h'));
     const kx = pw ? w / (pw / EMU) / EMU * EMU / EMU : 1;
     const fx = v => x + (pw ? v / pw * w : v / EMU), fy = v => y + (ph ? v / ph * h : v / EMU);
     cmds = [];
     for (let c = path.firstElementChild; c; c = c.nextElementSibling) {
      const pts = kids(c, 'a', 'pt').map(p => [fx(num(at(p, 'x'))), fy(num(at(p, 'y')))]);
      if (c.localName === 'moveTo' && pts[0]) cmds.push(['M', pts[0][0], pts[0][1]]);
      else if (c.localName === 'lnTo' && pts[0]) cmds.push(['L', pts[0][0], pts[0][1]]);
      else if (c.localName === 'cubicBezTo' && pts.length === 3) cmds.push(['C', pts[0][0], pts[0][1], pts[1][0], pts[1][1], pts[2][0], pts[2][1]]);
      else if (c.localName === 'close') cmds.push(['Z']);
     }
    }
   } else if (pg) {
    const pr = at(pg, 'prst');
    if (pr === 'line' || pr === 'straightConnector1') cmds = [['M', x, y], ['L', x + w, y + h]];
    else cmds = [['M', x, y], ['L', x + w, y], ['L', x + w, y + h], ['L', x, y + h], ['Z']];
   }
   if (cmds && (relleno || trazo)) emitir({t: 'path', cmds: cmds, fill: relleno, stroke: trazo, lw: grosor, dash: dash, layer: capa});
   // texto dentro de la figura
   const tx = kid(wsp, 'wps', 'txbx');
   const contenido = tx ? kid(tx, 'w', 'txbxContent') : null;
   if (contenido) {
    const bp = kid(wsp, 'wps', 'bodyPr');
    const ins = n => at(bp, n) !== '' ? num(at(bp, n)) / EMU : (n === 'lIns' || n === 'rIns' ? 7.2 : 3.6);
    const li = ins('lIns'), ti = ins('tIns'), ri = ins('rIns'), bi = ins('bIns');
    const parrafos = Array.prototype.filter.call(contenido.children, n => n.localName === 'p' || n.localName === 'tbl');
    const plano = Array.prototype.map.call(contenido.getElementsByTagNameNS(NS.w, 't'), t => t.textContent).join('').replace(/\s+/g, ' ').trim();
    const esQR = /^QR$/i.test(plano), esFoto = /^FOTOGRAF[IÍ]A$/i.test(plano);
    if (esQR) S.boxes.qr = {x: x, y: y, w: w, h: h};
    if (esFoto) S.boxes.foto = {x: x, y: y, w: w, h: h};
    const antes = S.ph;
    S.ph = esQR ? 'qr' : null;
    const antesLabels = S.labels.length;
    const res = conSalida(() => layoutBloque(parrafos, x + li, y + ti, Math.max(w - li - ri, 1)));
    S.ph = antes;
    let dy = 0;
    const anc = at(bp, 'anchor');
    const libre = h - ti - bi - res.r;
    if (anc === 'ctr') dy = libre / 2; else if (anc === 'b') dy = libre;
    if (dy) {
     res.ops.forEach(o => mover(o, dy));
     for (let k = antesLabels; k < S.labels.length; k++) { S.labels[k].base += dy; S.labels[k].lineTop += dy; S.labels[k].y += dy; }
    }
    res.ops.forEach(o => { if (o.layer === 1) o.layer = capa === 0 ? 0 : (capa === 2 ? 2 : 1); emitir(o); });
   }
  }

  /* ----- recorrer el cuerpo ----- */
  let cy = page.mt;
  for (let i = 0; i < bodyKids.length; i++) {
   const n = bodyKids[i];
   if (n.localName !== 'p' && n.localName !== 'tbl') continue;
   const sect = seccionDe[i];
   const ancho = anchoColumna(sect);
   cy += layoutBloque([n], page.ml, cy, ancho);
  }

  /* ----- tamaño real de la credencial (zona con contenido) ----- */
  let x1 = Infinity, y1 = Infinity, x2 = -Infinity, y2 = -Infinity;
  const caja = (a, b, c, d) => { x1 = Math.min(x1, a); y1 = Math.min(y1, b); x2 = Math.max(x2, c); y2 = Math.max(y2, d); };
  S.out.forEach(o => {
   if (o.t === 't') { if (o.s.trim()) caja(o.x, o.y - o.f.size * 0.8, o.x + o.w, o.y + o.f.size * 0.22); }
   else if (o.t === 'ul') caja(o.x1, o.y, o.x2, o.y + o.th);
   else if (o.t === 'img') caja(o.x, o.y, o.x + o.w, o.y + o.h);
   else if (o.t === 'path') {
    const m = (o.lw || 0) / 2;
    o.cmds.forEach(c => { for (let k = 1; k < c.length; k += 2) caja(c[k] - m, c[k + 1] - m, c[k] + m, c[k + 1] + m); });
   }
  });
  if (!isFinite(x1)) throw new Error('La plantilla de Word está vacía.');
  const ops = S.out.map((o, i) => ({o, i})).sort((a, b) => (a.o.layer - b.o.layer) || (a.i - b.i)).map(e => e.o);

  const campos = {};
  S.labels.forEach(l => { if (!campos[l.k]) campos[l.k] = l; });
  // GRADO y GRUPO comparten renglón: GRADO termina donde empieza GRUPO
  Object.keys(campos).forEach(k => {
   const l = campos[k];
   l.limiteDer = Infinity;
   Object.keys(campos).forEach(o => {
    const m = campos[o];
    if (m !== l && Math.abs(m.base - l.base) < 1 && m.xIni > l.xFin) l.limiteDer = Math.min(l.limiteDer, m.xIni);
   });
  });
  const faltan = ['nombre', 'curp', 'grado', 'grupo', 'turno'].filter(k => !campos[k]);
  if (faltan.length) S.avisos.push('No se encontraron las etiquetas: ' + faltan.join(', ').toUpperCase() + '.');
  return {
   ancho: x2 - x1, alto: y2 - y1, ox: x1, oy: y1, ops: ops, campos: campos,
   qr: S.boxes.qr || null, foto: S.boxes.foto || null, avisos: S.avisos
  };
 }

 /* =====================================================================
    Dibujo en canvas
    ===================================================================== */
 const DASH = {sysDot: [1, 1], dot: [1, 2], sysDash: [3, 1], dash: [4, 3], lgDash: [8, 3], sysDashDot: [3, 1, 1, 1]};
 function pintar(ctx, o) {
  if (o.t === 't') {
   ctx.font = fontStr(o.f, o.f.size);
   ctx.fillStyle = o.f.color || '#000';
   if ('letterSpacing' in ctx) ctx.letterSpacing = (o.f.spacing || 0) + 'px';
   ctx.fillText(o.s, o.x, o.y);
   if ('letterSpacing' in ctx) ctx.letterSpacing = '0px';
   if (o.u) { ctx.fillRect(o.x, o.y + o.f.size * 0.12, o.w, Math.max(o.f.size / 16, 0.3)); }
  } else if (o.t === 'ul') {
   ctx.fillStyle = o.color || '#000';
   ctx.fillRect(o.x1, o.y, o.x2 - o.x1, o.th);
  } else if (o.t === 'img') {
   const im = o.img, c = o.crop;
   if (c) {
    const sw = im.naturalWidth, sh = im.naturalHeight;
    ctx.drawImage(im, c.l * sw, c.t * sh, sw * (1 - c.l - c.r), sh * (1 - c.t - c.b), o.x, o.y, o.w, o.h);
   } else ctx.drawImage(im, o.x, o.y, o.w, o.h);
  } else if (o.t === 'path') {
   ctx.beginPath();
   o.cmds.forEach(c => {
    if (c[0] === 'M') ctx.moveTo(c[1], c[2]);
    else if (c[0] === 'L') ctx.lineTo(c[1], c[2]);
    else if (c[0] === 'C') ctx.bezierCurveTo(c[1], c[2], c[3], c[4], c[5], c[6]);
    else ctx.closePath();
   });
   if (o.fill) { ctx.fillStyle = o.fill; ctx.fill(); }
   if (o.stroke) {
    ctx.strokeStyle = o.stroke; ctx.lineWidth = Math.max(o.lw, 0.2);
    const d = DASH[o.dash];
    ctx.setLineDash(d ? d.map(v => v * Math.max(o.lw, 0.5)) : []);
    ctx.stroke(); ctx.setLineDash([]);
   }
  }
 }

 /* Reparte el nombre en renglones: el primero va a la derecha de la etiqueta (como CURP, GRADO...) y los demás
    debajo, a todo lo ancho que dejen el QR y la foto. Busca la letra más grande que quepa dejando aire antes del
    siguiente dato (CURP). Devuelve [{txt, x, y}] y el tamaño. */
 function repartirNombre(txt, n, tope, limiteEn, FUENTE, tamMax) {
  const palabras = String(txt).trim().split(/\s+/).filter(Boolean);
  // El nombre va DEBAJO de su etiqueta, a todo lo ancho que dejen el QR y la foto
  const x1 = n.izq;
  const base0 = t => n.base + t * 1.3;
  const anchoLinea = (k, t, inter) => {
   const y = base0(t) + k * inter;
   return limiteEn(n.izq, y - t, y + t * 0.3) - n.izq;
  };
  const intentar = (t, maxL) => {
   const inter = t * 1.18, lineas = [];
   let cur = '', k = 0;
   for (const w of palabras) {
    const prueba = cur ? cur + ' ' + w : w;
    if (medirSimple(prueba, t, true, FUENTE) <= anchoLinea(k, t, inter) + 0.01) { cur = prueba; continue; }
    if (!cur) return null;                         // ni una palabra sola cabe en el renglón
    lineas.push(cur); cur = w; k++;
    if (k >= maxL || medirSimple(cur, t, true, FUENTE) > anchoLinea(k, t, inter) + 0.01) return null;
   }
   lineas.push(cur);
   const abajo = base0(t) + (lineas.length - 1) * inter + t * 0.25;
   if (lineas.length > maxL || abajo > tope + 0.01) return null;
   return {t, inter, lineas};
  };
  for (let t = tamMax || 9; t >= 5.4 - 0.001; t -= 0.25) {
   const r = intentar(t, 3);
   if (r) return arm(r);
  }
  // No cabe ni con la letra mínima: se corta con "…" para no encimarse con el CURP
  const t = 5.4, inter = t * 1.18, maxL = Math.max(1, Math.min(3, Math.floor((tope - base0(t) - t * 0.25) / inter) + 1));
  const lineas = []; let cur = '', k = 0;
  for (const w of palabras) {
   const prueba = cur ? cur + ' ' + w : w;
   if (!cur || medirSimple(prueba, t, true, FUENTE) <= anchoLinea(k, t, inter)) cur = prueba;
   else { if (k + 1 >= maxL) { cur = prueba; break; } lineas.push(cur); cur = w; k++; }
  }
  lineas.push(cur);
  const ult = lineas.length - 1;
  let u = lineas[ult];
  const aMax = anchoLinea(ult, t, inter);
  if (medirSimple(u, t, true, FUENTE) > aMax) { while (u.length > 1 && medirSimple(u + '…', t, true, FUENTE) > aMax) u = u.slice(0, -1); lineas[ult] = u + '…'; }
  return arm({t, inter, lineas});
  function arm(r) {
   return {t: r.t, renglones: r.lineas.map((l, i) => ({txt: l, x: n.izq, y: base0(r.t) + i * r.inter}))};
  }
 }

 function rellenar(ctx, m, d) {
  const C = m.campos, FUENTE = 'Arial';
  const derecha = m.ox + m.ancho - 4;
  const obst = [m.qr, m.foto].filter(Boolean);
  const AIRE = 5;   // pt de separación entre el texto y el cuadro del QR / la foto
  const limiteEn = (xDesde, yArriba, yAbajo) => {
   let lim = derecha;
   obst.forEach(b => { if (b.x >= xDesde - 0.5 && b.y < yAbajo && b.y + b.h > yArriba) lim = Math.min(lim, b.x - AIRE); });
   return lim;
  };
  ctx.fillStyle = '#000'; ctx.textBaseline = 'alphabetic';
  if ('letterSpacing' in ctx) ctx.letterSpacing = '0px';
  const poner = (txt, x, y, tam) => { ctx.font = 'bold ' + tam + 'px Arial, "Liberation Sans", sans-serif'; ctx.fillText(txt, x, y); };

  // CURP / GRADO / GRUPO / TURNO: a la derecha de su etiqueta, todos con la MISMA letra
  const valores = {curp: d.curp || '', grado: d.grado || '', grupo: d.grupo || '', turno: d.turno || ''};
  const ks = ['curp', 'grado', 'grupo', 'turno'].filter(k => C[k] && valores[k]);
  const limDe = (k, y) => { const l = C[k]; return Math.min(limiteEn(l.xFin + 4, y - l.size, y + 1), l.limiteDer === Infinity ? Infinity : l.limiteDer - 3); };
  const cabe = (k, tope) => {
   const txt = String(valores[k]).toUpperCase(), l = C[k], disp = limDe(k, l.base) - (l.xFin + 4);
   let t = tope;
   while (t > 5.5 && medirSimple(txt, t, true, FUENTE) > disp) t -= 0.25;
   return t;
  };
  // La CURP es la más larga: toma la letra más grande que le quepa junto a su etiqueta.
  // Grupo y turno (cortos) van con letra grande y GRADO con su palabra completa, todos del mismo tamaño.
  // La CURP (el dato más largo) fija el tamaño de todos los datos: la letra más grande que quepa.
  // Va junto a su etiqueta o, si así se lee más grande, justo debajo de ella (hay espacio antes de la siguiente etiqueta).
  const MAXT = 7.5;
  const sigDe = k => ['nombre', 'curp', 'grado', 'grupo', 'turno'].map(q => C[q]).filter(l => l && l.lineTop > C[k].lineTop + 1).sort((a, b) => a.lineTop - b.lineTop)[0];
  let TC = ks.indexOf('curp') >= 0 ? cabe('curp', MAXT) : 9, curpAbajo = false;
  if (ks.indexOf('curp') >= 0) {
   const l = C.curp, sg = sigDe('curp'), txt = String(valores.curp).toUpperCase();
   let t = MAXT;
   const libreX = y => limiteEn(l.izq, y - t, y + 2) - l.izq;
   while (t > 5.5) {
    const y2 = l.base + t * 1.15;
    if (medirSimple(txt, t, true, FUENTE) <= libreX(y2) && (!sg || y2 + t * 0.3 < sg.lineTop - 1.5)) break;
    t -= 0.25;
   }
   
  }
  const TG = TC;
  const topeNombre = Math.min(MAXT + 1, TC + 1);
  // NOMBRE: primer renglón junto a su etiqueta, el resto debajo; siempre queda aire antes del CURP
  const n = C.nombre;
  if (n && d.nombre) {
   const sigu = ['curp', 'grado', 'grupo', 'turno'].map(k => C[k]).filter(l => l && l.lineTop > n.lineTop + 1).sort((a, b) => a.lineTop - b.lineTop)[0];
   const tope = sigu ? (sigu.base - sigu.size * 0.8) - 4.5 : n.base + 30;
   const r = repartirNombre(String(d.nombre).toUpperCase(), n, tope, limiteEn, FUENTE, topeNombre);
   r.renglones.forEach(l => poner(l.txt, l.x, l.y, r.t));
  }
  ks.forEach(k => {
   const l = C[k], x = l.xFin + 4, txt = String(valores[k]).toUpperCase();
   if (k === 'curp') { if (curpAbajo) poner(txt, l.izq, l.base + TC * 1.15, TC); else poner(txt, x, l.base, TC); return; }
   if (k === 'grado') {
    // Primero/Segundo/Tercero siempre enfrente de "GRADO:": letra lo más grande que quepa antes de "GRUPO:"
    let t = TG; const lim = limDe(k, l.base) - x;
    while (t > 4.5 && medirSimple(txt, t, true, FUENTE) > lim) t -= 0.25;
    poner(txt, x, l.base, t);
    return;
   }
   poner(txt, x, l.base, TG);
  });
 }

 function dibujar(canvas, m, datos, opc) {
  opc = opc || {};
  const esc = (opc.ancho || 760) / m.ancho;
  canvas.width = Math.round(m.ancho * esc);
  canvas.height = Math.round(m.alto * esc);
  const ctx = canvas.getContext('2d');
  ctx.setTransform(1, 0, 0, 1, 0, 0);
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.setTransform(esc, 0, 0, esc, -m.ox * esc, -m.oy * esc);
  const conQR = !!(datos && datos.conQR !== false && datos.curp && m.qr && global.QRious);
  m.ops.forEach(o => { if (conQR && o.ph === 'qr') return; pintar(ctx, o); });
  if (datos) {
   rellenar(ctx, m, datos);
   if (conQR) {
    // El QR ocupa el cuadro que se marcó en Word, centrado
    const q = new global.QRious({value: String(datos.curp), size: 420, level: 'M', padding: 26});
    const lado = Math.min(m.qr.w, m.qr.h);   // todo el cuadro que marca el Word
    ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(q.canvas, m.qr.x + (m.qr.w - lado) / 2, m.qr.y + (m.qr.h - lado) / 2, lado, lado);
    ctx.imageSmoothingEnabled = true;
   }
  }
  ctx.setTransform(1, 0, 0, 1, 0, 0);
  return canvas;
 }

 global.SicaDocx = {cargar: cargar, dibujar: dibujar};
})(window);
