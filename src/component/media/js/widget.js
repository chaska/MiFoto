/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 *
 * Photo widget for the Joomla profile form: choose, crop, upload, change, remove.
 * The browser only makes things comfortable; the server re-checks everything (real file type, size, pixels, permission).
 * No user id is ever sent: the server uses the logged-in session.
 */
(function (root) {
	'use strict';

	/* ---- geometry of the crop (pure functions, tested with node) ---- */

	function clamp(v, lo, hi) {
		return Math.min(hi, Math.max(lo, v));
	}

	/** Scale so the image COVERS the square viewport at zoom 1, times the zoom. */
	function fit(natW, natH, box, zoom) {
		var s = (box / Math.min(natW, natH)) * zoom;
		return { s: s, w: natW * s, h: natH * s };
	}

	/** Keeps the image covering the whole viewport: no empty strip can show. */
	function clampOffset(x, y, w, h, box) {
		return { x: clamp(x, Math.min(0, box - w), 0), y: clamp(y, Math.min(0, box - h), 0) };
	}

	/** The square of the ORIGINAL image that the viewport shows. */
	function cropRect(natW, natH, box, zoom, x, y) {
		var f = fit(natW, natH, box, zoom);
		var o = clampOffset(x, y, f.w, f.h, box);
		var size = Math.min(box / f.s, natW, natH);
		var sx = clamp(-o.x / f.s, 0, natW - size);
		var sy = clamp(-o.y / f.s, 0, natH - size);

		return { sx: sx, sy: sy, size: size, x: o.x, y: o.y, s: f.s };
	}

	/** New offset after a zoom change that keeps the centre of the viewport where it is. */
	function zoomAround(natW, natH, box, oldZoom, newZoom, x, y) {
		var a = fit(natW, natH, box, oldZoom);
		var cx = (box / 2 - x) / a.s;
		var cy = (box / 2 - y) / a.s;
		var b = fit(natW, natH, box, newZoom);

		return clampOffset(box / 2 - cx * b.s, box / 2 - cy * b.s, b.w, b.h, box);
	}

	var api = { clamp: clamp, fit: fit, clampOffset: clampOffset, cropRect: cropRect, zoomAround: zoomAround };

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document === 'undefined') {
		return;
	}

	root.MiFotoCrop = api;

	/* ---- the widget ---- */

	var TYPES = ['image/jpeg', 'image/png', 'image/webp'];

	function initWidget(w) {
		if (w.dataset.mifotoReady) {
			return;
		}

		w.dataset.mifotoReady = '1';

		var T = {};

		try {
			T = JSON.parse(w.dataset.text || '{}');
		} catch (e) {
			T = {};
		}

		var t = function (k) { return T[k] || ''; };
		var q = function (r) { return w.querySelector('[data-role="' + r + '"]'); };
		var box = q('avatar');
		var chooseBtn = q('choose');
		var removeBtn = q('remove');
		var fileInput = q('file');
		var statusEl = q('status');
		var dlg = q('dialog');
		var viewport = q('viewport');
		var image = q('image');
		var zoomEl = q('zoom');
		var maxBytes = parseInt(w.dataset.maxBytes, 10) || 0;
		var canCrop = w.dataset.crop === '1' && dlg && typeof dlg.showModal === 'function' && !!window.HTMLCanvasElement;
		var consentOwner = q('consent-owner');
		var consentData = q('consent-data');
		var busy = false;
		var current = null;

		/* Both boxes (owner of the photo + data protection) must be ticked before a photo is chosen; the server checks them again. */
		function consentGiven() {
			return !!(consentOwner && consentData && consentOwner.checked && consentData.checked);
		}

		function askConsent() {
			setStatus(t('CONSENT_NEEDED'), true);
			var first = consentOwner && !consentOwner.checked ? consentOwner : consentData;

			if (first && first.focus) { first.focus(); }
		}

		function setStatus(msg, isError) {
			statusEl.textContent = msg || '';
			statusEl.classList.toggle('is-error', !!isError);
		}

		function setBusy(on) {
			busy = on;
			[chooseBtn, removeBtn].forEach(function (b) { if (b) { b.disabled = on; } });
			w.classList.toggle('is-busy', on);
		}

		function sameOrigin(url) {
			try {
				return new URL(url, window.location.href).origin === window.location.origin;
			} catch (e) {
				return false;
			}
		}

		function showPhoto(url) {
			if (!sameOrigin(url)) {
				return;
			}

			var img = document.createElement('img');
			img.className = (w.dataset.classes || '') + ' mifoto-widget__avatar';
			img.src = url;
			img.width = 96;
			img.height = 96;
			img.alt = '';
			box.replaceChildren(img);

			if (chooseBtn) { chooseBtn.textContent = t('CHANGE'); }
			removeBtn.hidden = false;
		}

		function showPlaceholder() {
			var s = document.createElement('span');
			s.className = (w.dataset.classes || '') + ' mifoto-widget__avatar';
			s.setAttribute('aria-hidden', 'true');

			if (/^#[0-9a-fA-F]{6}$/.test(w.dataset.color || '')) {
				s.style.background = w.dataset.color;
			}

			s.textContent = w.dataset.initials || '?';
			box.replaceChildren(s);

			if (chooseBtn) { chooseBtn.textContent = t('CHOOSE'); }
			removeBtn.hidden = true;
		}

		function post(url, body) {
			body.append(w.dataset.token, '1');

			return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (res) {
					return res.json().catch(function () { return null; }).then(function (data) { return { ok: res.ok, data: data }; });
				});
		}

		function upload(blob, name) {
			if (busy) {
				return;
			}

			setBusy(true);
			setStatus(t('UPLOADING'), false);

			var fd = new FormData();
			fd.append('photo', blob, name);

			if (consentGiven()) {
				fd.append('consent_owner', '1');
				fd.append('consent_data', '1');
			}

			post(w.dataset.uploadUrl, fd).then(function (r) {
				if (r.ok && r.data && r.data.ok) {
					showPhoto(r.data.url);
					setStatus(t('UPLOADED'), false);
				} else {
					setStatus((r.data && r.data.message) || t('GENERIC_ERROR'), true);
				}
			}).catch(function () {
				setStatus(t('GENERIC_ERROR'), true);
			}).then(function () {
				setBusy(false);
			});
		}

		function chosen(file) {
			if (!file) {
				return;
			}

			if (TYPES.indexOf(file.type) === -1) {
				setStatus(t('BAD_TYPE'), true);
				return;
			}

			if (maxBytes && file.size > maxBytes) {
				setStatus(t('TOO_BIG'), true);
				return;
			}

			setStatus('', false);

			if (canCrop) {
				openCrop(file);
			} else {
				upload(file, file.name || 'foto');
			}
		}

		/* ---- crop dialog ---- */

		var geo = null;

		function render() {
			var f = fit(geo.natW, geo.natH, geo.box, geo.zoom);
			image.style.width = f.w + 'px';
			image.style.height = f.h + 'px';
			image.style.left = geo.x + 'px';
			image.style.top = geo.y + 'px';
		}

		function openCrop(file) {
			var url = URL.createObjectURL(file);
			current = { file: file, url: url };

			image.onload = function () {
				dlg.showModal();
				// The real size of the square on this screen (it is 80vw on a narrow phone), never a guess.
				var b = Math.round(viewport.getBoundingClientRect().width || parseFloat(window.getComputedStyle(viewport).width) || 0);

				if (b < 50) {
					closeCrop();
					setStatus(t('GENERIC_ERROR'), true);
					return;
				}

				geo = { natW: image.naturalWidth, natH: image.naturalHeight, box: b, zoom: 1, x: 0, y: 0 };
				var f = fit(geo.natW, geo.natH, b, 1);
				var o = clampOffset((b - f.w) / 2, (b - f.h) / 2, f.w, f.h, b);
				geo.x = o.x;
				geo.y = o.y;
				zoomEl.value = '1';
				render();
				viewport.focus();
			};

			image.onerror = function () {
				closeCrop();
				setStatus(t('CANT_READ'), true);
			};

			image.removeAttribute('style');
			image.src = url;
		}

		function closeCrop() {
			if (dlg.open) {
				dlg.close();
			}

			if (current) {
				URL.revokeObjectURL(current.url);
				image.removeAttribute('src');
				current = null;
			}

			geo = null;
		}

		var drag = null;

		viewport.addEventListener('pointerdown', function (e) {
			if (!geo) { return; }
			drag = { px: e.clientX, py: e.clientY, x: geo.x, y: geo.y };
			viewport.setPointerCapture(e.pointerId);
		});

		viewport.addEventListener('pointermove', function (e) {
			if (!drag || !geo) { return; }
			var f = fit(geo.natW, geo.natH, geo.box, geo.zoom);
			var o = clampOffset(drag.x + (e.clientX - drag.px), drag.y + (e.clientY - drag.py), f.w, f.h, geo.box);
			geo.x = o.x;
			geo.y = o.y;
			render();
		});

		['pointerup', 'pointercancel'].forEach(function (ev) {
			viewport.addEventListener(ev, function () { drag = null; });
		});

		viewport.addEventListener('keydown', function (e) {
			if (!geo) { return; }
			var step = { ArrowLeft: [10, 0], ArrowRight: [-10, 0], ArrowUp: [0, 10], ArrowDown: [0, -10] }[e.key];
			if (!step) { return; }
			e.preventDefault();
			var f = fit(geo.natW, geo.natH, geo.box, geo.zoom);
			var o = clampOffset(geo.x + step[0], geo.y + step[1], f.w, f.h, geo.box);
			geo.x = o.x;
			geo.y = o.y;
			render();
		});

		zoomEl.addEventListener('input', function () {
			if (!geo) { return; }
			var z = clamp(parseFloat(zoomEl.value) || 1, 1, 4);
			var o = zoomAround(geo.natW, geo.natH, geo.box, geo.zoom, z, geo.x, geo.y);
			geo.zoom = z;
			geo.x = o.x;
			geo.y = o.y;
			render();
		});

		q('crop-cancel').addEventListener('click', closeCrop);
		dlg.addEventListener('cancel', function (e) { e.preventDefault(); closeCrop(); });

		q('crop-whole').addEventListener('click', function () {
			var f = current && current.file;
			closeCrop();
			if (f) { upload(f, f.name || 'foto'); }
		});

		q('crop-save').addEventListener('click', function () {
			if (!geo) { return; }

			var r = cropRect(geo.natW, geo.natH, geo.box, geo.zoom, geo.x, geo.y);
			var out = clamp(Math.round(r.size), 32, 1024);
			var canvas = document.createElement('canvas');
			canvas.width = out;
			canvas.height = out;
			var ctx = canvas.getContext('2d');
			ctx.fillStyle = '#fff';
			ctx.fillRect(0, 0, out, out);
			ctx.imageSmoothingQuality = 'high';
			ctx.drawImage(image, r.sx, r.sy, r.size, r.size, 0, 0, out, out);
			closeCrop();

			canvas.toBlob(function (blob) {
				if (blob) {
					upload(blob, 'foto.jpg');
				} else {
					setStatus(t('GENERIC_ERROR'), true);
				}
			}, 'image/jpeg', 0.92);
		});

		/* ---- buttons ---- */

		if (chooseBtn && fileInput) {
			chooseBtn.addEventListener('click', function () {
				if (busy) { return; }

				if (!consentGiven()) { askConsent(); return; }

				setStatus('', false);
				fileInput.click();
			});
			fileInput.addEventListener('change', function () {
				var f = fileInput.files && fileInput.files[0];
				fileInput.value = '';
				chosen(f);
			});
		}

		removeBtn.addEventListener('click', function () {
			if (busy || !window.confirm(t('REMOVE_CONFIRM'))) {
				return;
			}

			setBusy(true);
			post(w.dataset.removeUrl, new FormData()).then(function (r) {
				if (r.ok && r.data && r.data.ok) {
					showPlaceholder();
					setStatus(t('REMOVED'), false);
				} else {
					setStatus((r.data && r.data.message) || t('GENERIC_ERROR'), true);
				}
			}).catch(function () {
				setStatus(t('GENERIC_ERROR'), true);
			}).then(function () {
				setBusy(false);
			});
		});
	}

	function start() {
		document.querySelectorAll('.mifoto-widget').forEach(initWidget);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})(typeof window !== 'undefined' ? window : this);
