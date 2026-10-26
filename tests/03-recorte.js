'use strict';
// Geometria del recorte (funciones puras de mifoto.js). Sin dependencias: node tests/03-recorte.js
const path = require('path');
const g = require(path.join(__dirname, '..', 'src', 'plugins', 'user', 'mifoto', 'media', 'js', 'mifoto.js'));

let ok = 0, fail = 0;
const afirma = (c, m) => { if (c) { ok++; console.log('  ok    ' + m); } else { fail++; console.log('  FALLA ' + m); } };
const casi = (a, b, e = 1e-6) => Math.abs(a - b) <= e;

console.log('\n== Encaje: la imagen siempre cubre el cuadro ==');
for (const [w, h] of [[4000, 3000], [3000, 4000], [500, 500], [320, 3200], [3200, 320]]) {
	const f = g.fit(w, h, 280, 1);
	afirma(casi(Math.min(f.w, f.h), 280), `${w}x${h}: el lado corto mide justo el cuadro (${Math.round(f.w)}x${Math.round(f.h)})`);
}

console.log('\n== Desplazamiento: nunca queda un borde vacio ==');
{
	const f = g.fit(4000, 3000, 280, 1);
	const o = g.clampOffset(500, 500, f.w, f.h, 280);
	afirma(o.x === 0 && o.y === 0, 'arrastrar mas alla del borde superior izquierdo se frena en 0');
	const p = g.clampOffset(-99999, -99999, f.w, f.h, 280);
	afirma(casi(p.x, 280 - f.w) && casi(p.y, 280 - f.h), 'arrastrar mas alla del borde inferior derecho se frena donde acaba la imagen');
	const q = g.clampOffset(-10, -10, 280, 280, 280);
	afirma(q.x === 0 && q.y === 0, 'si la imagen mide justo el cuadro no hay donde moverla');
}

console.log('\n== Rectangulo recortado sobre el original ==');
for (const [w, h, zoom, x, y] of [[4000, 3000, 1, 0, 0], [4000, 3000, 1, -9999, -9999], [3000, 4000, 2.5, -300, -700], [500, 500, 4, -1000, 5], [800, 600, 1.37, -50, -20]]) {
	const r = g.cropRect(w, h, 280, zoom, x, y);
	afirma(r.size > 0 && r.sx >= 0 && r.sy >= 0 && r.sx + r.size <= w + 1e-6 && r.sy + r.size <= h + 1e-6, `${w}x${h} zoom ${zoom}: el cuadrado cae dentro de la foto (${Math.round(r.sx)},${Math.round(r.sy)} lado ${Math.round(r.size)})`);
}
{
	const r = g.cropRect(4000, 3000, 280, 1, 0, 0);
	afirma(casi(r.size, 3000), 'zoom 1: el cuadrado es el lado corto entero (3000)');
	const z = g.cropRect(4000, 3000, 280, 2, 0, 0);
	afirma(casi(z.size, 1500), 'zoom 2: la mitad del lado (1500)');
}

console.log('\n== Zoom que mantiene el centro ==');
{
	const w = 4000, h = 3000, box = 280;
	const f = g.fit(w, h, box, 1);
	const start = g.clampOffset((box - f.w) / 2, (box - f.h) / 2, f.w, f.h, box);
	const c1 = g.cropRect(w, h, box, 1, start.x, start.y);
	const centro1 = [c1.sx + c1.size / 2, c1.sy + c1.size / 2];
	const o = g.zoomAround(w, h, box, 1, 3, start.x, start.y);
	const c2 = g.cropRect(w, h, box, 3, o.x, o.y);
	const centro2 = [c2.sx + c2.size / 2, c2.sy + c2.size / 2];
	afirma(casi(centro1[0], centro2[0], 1) && casi(centro1[1], centro2[1], 1), `al hacer zoom el centro de la foto no salta (${Math.round(centro1[0])},${Math.round(centro1[1])} -> ${Math.round(centro2[0])},${Math.round(centro2[1])})`);
	const back = g.zoomAround(w, h, box, 3, 1, o.x, o.y);
	afirma(casi(back.x, start.x, 1e-3) && casi(back.y, start.y, 1e-3), 'ida y vuelta de zoom vuelve al encuadre inicial');
}

console.log('\n== Valores raros no rompen ==');
{
	const r = g.cropRect(16, 16, 280, 4, 0, 0);
	afirma(Number.isFinite(r.sx) && Number.isFinite(r.size) && r.size > 0, 'imagen minuscula de 16x16');
	afirma(g.clamp(5, 0, 3) === 3 && g.clamp(-1, 0, 3) === 0, 'clamp');
}

console.log(`\n${ok} correctas, ${fail} fallos`);
process.exit(fail ? 1 : 0);
