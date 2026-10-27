/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 *
 * Administrator screens: (1) one click on a cell of the permissions table, (2) the live preview of the settings, (3) the
 * confirmation before removing a photo. The server decides everything (token, permission, validation); this only draws.
 */
(function (root) {
	'use strict';

	/* ---- pure: what the cell of a state looks like (tested with node) ---- */
	var EDITABLE = { own: 1, inh: 1, '': 1 };

	function nextAction(state) {
		return state === 'own' ? 'remove' : (EDITABLE[state] ? 'add' : 'none');
	}

	var api = { nextAction: nextAction };

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document === 'undefined') {
		return;
	}

	function svgCheck(cls) {
		var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		s.setAttribute('class', 'mfa-i');
		s.setAttribute('viewBox', '0 0 24 24');
		s.setAttribute('aria-hidden', 'true');
		var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
		p.setAttribute('d', cls === 'x' ? 'M6 6l12 12M18 6L6 18' : 'M5 12.5l4.5 4.5L19 7.5');
		s.appendChild(p);
		return s;
	}

	/* ---- (1) permissions ---- */
	function initMatrix(box) {
		var editable = box.dataset.mfaMatrix === '1';
		var T = {};

		try { T = JSON.parse(box.dataset.text || '{}'); } catch (e) { T = {}; }

		var rows = box.querySelector('[data-role="rows"]');
		var msg = box.querySelector('[data-role="msg"]');
		var KEYS = [['up', 'P_UP'], ['ver', 'P_VIEW'], ['mod', 'P_MOD']];
		var busy = false;

		function stateText(s) {
			return T[{ own: 'S_OWN', inh: 'S_INH', fixed: 'S_FIXED', deny: 'S_DENY', 'deny-inh': 'S_DENY_INH' }[s] || 'S_NONE'] || '';
		}

		function cell(row, key, label) {
			var s = row.cells[key];
			var td = document.createElement('td');
			var mark = document.createElement('span');

			if (s === 'own' || s === 'inh' || s === 'fixed') {
				mark.className = 'mfa-yes' + (s === 'inh' ? ' is-inh' : (s === 'fixed' ? ' is-lock' : ''));
				mark.appendChild(svgCheck('check'));
			} else if (s === 'deny' || s === 'deny-inh') {
				mark.className = 'mfa-no is-deny';
				mark.appendChild(svgCheck('x'));
			} else {
				mark.className = 'mfa-no';
				mark.setAttribute('aria-hidden', 'true');
				mark.textContent = '—';
			}

			if (!editable || !EDITABLE[s]) {
				var sr = document.createElement('span');
				sr.className = 'mfa-sr';
				sr.textContent = stateText(s);
				td.appendChild(mark);
				td.appendChild(sr);
				return td;
			}

			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'mfa-cell';
			b.dataset.g = String(row.id);
			b.dataset.k = key;
			b.setAttribute('aria-label', row.title + ', ' + label + ': ' + stateText(s) + '. ' + (nextAction(s) === 'remove' ? T.PRESS_REMOVE : T.PRESS_ADD));
			b.appendChild(mark);
			td.appendChild(b);
			return td;
		}

		function draw(list) {
			rows.replaceChildren();
			list.forEach(function (r) {
				var tr = document.createElement('tr');
				var th = document.createElement('th');
				th.scope = 'row';
				th.style.paddingLeft = (r.level * 14 + 6) + 'px';
				th.textContent = r.title;
				tr.appendChild(th);
				KEYS.forEach(function (k) { tr.appendChild(cell(r, k[0], T[k[1]] || k[0])); });
				rows.appendChild(tr);
			});
		}

		function say(text, isError, undo) {
			msg.hidden = false;
			msg.classList.toggle('is-error', !!isError);
			msg.textContent = text + ' ';

			if (undo) {
				var u = document.createElement('button');
				u.type = 'button';
				u.textContent = T.UNDO || 'Undo';
				u.addEventListener('click', undo);
				msg.appendChild(u);
			}
		}

		function toggle(g, k, silent) {
			if (busy) { return; }
			busy = true;
			var fd = new FormData();
			fd.append(box.dataset.token, '1');
			fd.append('g', g);
			fd.append('k', k);

			fetch(box.dataset.url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (res) { return res.json().catch(function () { return null; }); })
				.then(function (data) {
					if (data && data.rows) { draw(data.rows); }

					if (data && data.ok) {
						var row = data.rows.filter(function (r) { return String(r.id) === g; })[0];
						var label = (KEYS.filter(function (x) { return x[0] === k; })[0] || [0, ''])[1];

						if (silent) { msg.hidden = true; } else {
							say((T.SAVED || '%s · %s').replace('%s', function () { return row ? row.title : ''; }).replace('%s', function () { return T[label] || k; }) + ' ' + stateText(row ? row.cells[k] : ''), false, function () { toggle(g, k, true); });
						}
					} else {
						say((data && data.message) || T.ERR_SAVE || 'Error', true);
					}

					var again = box.querySelector('.mfa-cell[data-g="' + g + '"][data-k="' + k + '"]');
					if (again) { again.focus(); }
				})
				.catch(function () { say(T.ERR_SAVE || 'Error', true); })
				.then(function () { busy = false; });
		}

		if (editable) {
			box.addEventListener('click', function (e) {
				var b = e.target.closest('.mfa-cell');
				if (b) { toggle(b.dataset.g, b.dataset.k, false); }
			});
		}
	}

	/* ---- (2) settings preview ---- */
	function initSettings(form) {
		var samples = form.querySelectorAll('.mfa-sample');
		var dirty = form.querySelector('[data-role="dirty"]');
		var KINDS = { shape: 's', frame: 'f', shadow: 'sh' };

		function repaint() {
			samples.forEach(function (el) {
				Array.prototype.slice.call(el.classList).forEach(function (c) {
					if (/^mfa-(s|f|sh)-/.test(c)) { el.classList.remove(c); }
				});

				Object.keys(KINDS).forEach(function (kind) {
					var checked = form.querySelector('input[name="jform[' + kind + ']"]:checked');
					if (checked && /^[a-z]+$/.test(checked.value)) { el.classList.add('mfa-' + KINDS[kind] + '-' + checked.value); }
				});
			});
		}

		function touched() { if (dirty) { dirty.hidden = false; } }

		function warn(ev) {
			if (dirty && !dirty.hidden) { ev.preventDefault(); ev.returnValue = ''; }
		}

		form.addEventListener('change', function (ev) {
			if (ev.target && ev.target.matches('input[type="radio"]')) { repaint(); }
			touched();
		});
		form.addEventListener('input', touched);
		form.addEventListener('submit', function () { window.removeEventListener('beforeunload', warn); });
		window.addEventListener('beforeunload', warn);
	}

	/* ---- (3) confirm before removing ---- */
	function initConfirm() {
		document.addEventListener('submit', function (e) {
			var f = e.target.closest ? e.target.closest('form[data-confirm]') : null;
			if (f && !window.confirm(f.dataset.confirm)) { e.preventDefault(); }
		});
	}

	function start() {
		document.querySelectorAll('[data-mfa-matrix]').forEach(initMatrix);
		document.querySelectorAll('[data-mfa-settings]').forEach(initSettings);
		initConfirm();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})(typeof window !== 'undefined' ? window : this);
