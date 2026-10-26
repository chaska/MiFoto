/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 *
 * Moderation buttons on the administrator's user edit page: hide, show, remove a person's photo without leaving the page.
 * The server checks the permission and the form token on every call.
 */
(function () {
	'use strict';

	function init(w) {
		if (w.dataset.ready) {
			return;
		}

		w.dataset.ready = '1';

		var T = {};

		try {
			T = JSON.parse(w.dataset.text || '{}');
		} catch (e) {
			T = {};
		}

		var t = function (k) { return T[k] || ''; };
		var q = function (r) { return w.querySelector('[data-role="' + r + '"]'); };
		var toggle = q('toggle');
		var remove = q('remove');
		var stateEl = q('state');
		var status = q('status');
		var busy = false;

		function say(msg, isError) {
			status.textContent = msg || '';
			status.classList.toggle('text-danger', !!isError);
		}

		function setState(state) {
			w.dataset.state = state;
			stateEl.className = 'badge ' + (state === 'hidden' ? 'bg-warning text-dark' : (state === 'visible' ? 'bg-success' : 'bg-secondary'));
			stateEl.textContent = state === 'hidden' ? t('STATE_HIDDEN') : (state === 'visible' ? t('STATE_VISIBLE') : t('NO_PHOTO'));
			toggle.textContent = state === 'hidden' ? t('SHOW') : t('HIDE');
		}

		function placeholder() {
			var s = document.createElement('span');
			s.className = w.dataset.classes || '';
			s.style.cssText = 'width:64px;height:64px;font-size:24px';
			s.setAttribute('aria-hidden', 'true');

			if (/^#[0-9a-fA-F]{6}$/.test(w.dataset.color || '')) {
				s.style.background = w.dataset.color;
			}

			s.textContent = w.dataset.initials || '?';
			q('preview').replaceChildren(s);
		}

		function call(url, done) {
			if (busy || !url) {
				return;
			}

			busy = true;
			var fd = new FormData();
			fd.append(w.dataset.token, '1');

			fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (res) { return res.json().catch(function () { return null; }); })
				.then(function (data) {
					if (data && data.ok) {
						done();
					} else {
						say((data && data.message) || t('ERROR'), true);
					}
				})
				.catch(function () { say(t('ERROR'), true); })
				.then(function () { busy = false; });
		}

		toggle.addEventListener('click', function () {
			var hide = w.dataset.state === 'visible';

			call(hide ? w.dataset.hideUrl : w.dataset.showUrl, function () {
				setState(hide ? 'hidden' : 'visible');
				say(t(hide ? 'DONE_HIDE' : 'DONE_SHOW'), false);
			});
		});

		remove.addEventListener('click', function () {
			if (!window.confirm(t('CONFIRM_REMOVE'))) {
				return;
			}

			call(w.dataset.removeUrl, function () {
				setState('none');
				placeholder();
				q('actions').hidden = true;
				say(t('DONE_REMOVE'), false);
			});
		});
	}

	function start() {
		document.querySelectorAll('.mifoto-admin').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
