/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 *
 * Click any MiFoto photo (whoever draws it: Engage comments, the map, a module...) and it opens bigger in a dialog, with a way to
 * report it and, if the administrator set one, where to write to have it removed. Photos are recognised by their address
 * (index.php?option=com_mifoto&task=photo.show&u=<key>), so no other extension has to do anything.
 * Everything shown is put in with textContent: nothing from the page is ever parsed as HTML.
 */
(function (root) {
	'use strict';

	var doc = root.document;
	var KEY = /[?&]u=([a-f0-9]{16})(?:&|$)/;

	function options() {
		if (root.Joomla && typeof root.Joomla.getOptions === 'function') {
			return root.Joomla.getOptions('com_mifoto.lightbox') || {};
		}

		return {};
	}

	/** The public key of the photo behind an <img>, or '' when it is not one of ours. Only addresses of this same site count. */
	function photoKey(img) {
		var src = img.getAttribute('src') || '';
		var url;

		try {
			url = new URL(src, root.location.href);
		} catch (e) {
			return '';
		}

		if (url.origin !== root.location.origin || url.searchParams.get('option') !== 'com_mifoto' || url.searchParams.get('task') !== 'photo.show') {
			return '';
		}

		var m = KEY.exec(url.search);

		return m ? m[1] : '';
	}

	function bigUrl(img) {
		var url = new URL(img.getAttribute('src'), root.location.href);

		url.searchParams.set('s', '300');

		return url.pathname + url.search;
	}

	function el(tag, cls, text) {
		var n = doc.createElement(tag);

		if (cls) { n.className = cls; }

		if (text !== undefined) { n.textContent = text; }

		return n;
	}

	var dlg = null;
	var parts = {};
	var currentKey = '';

	function build(o) {
		var T = o.texts || {};

		dlg = el('dialog', 'mifoto-lb');
		dlg.setAttribute('aria-label', T.ZOOM_LABEL || '');

		parts.img = el('img', 'mifoto-lb__img');
		parts.img.alt = '';
		parts.status = el('p', 'mifoto-lb__status');
		parts.status.setAttribute('role', 'status');

		var close = el('button', 'mifoto-lb__close', '×');
		close.type = 'button';
		close.setAttribute('aria-label', T.ZOOM_CLOSE || 'Close');
		close.addEventListener('click', function () { dlg.close(); });

		parts.contact = el('p', 'mifoto-lb__contact');
		parts.report = el('button', 'mifoto-lb__link', T.REPORT || 'Report');
		parts.report.type = 'button';

		parts.form = el('form', 'mifoto-lb__form');
		parts.form.hidden = true;

		var legend = el('p', 'mifoto-lb__legend', T.REPORT_TITLE || '');
		var reasonLabel = el('label', '', T.REPORT_REASON || '');
		parts.reason = el('select', 'form-select');
		Object.keys(o.reasons || {}).forEach(function (k) {
			var opt = el('option', '', o.reasons[k]);
			opt.value = k;
			parts.reason.appendChild(opt);
		});
		reasonLabel.appendChild(parts.reason);

		var commentLabel = el('label', '', T.REPORT_COMMENT || '');
		parts.comment = el('textarea', 'form-control');
		parts.comment.rows = 3;
		parts.comment.maxLength = 500;
		commentLabel.appendChild(parts.comment);

		var send = el('button', 'btn btn-primary', T.REPORT_SEND || 'Send');
		send.type = 'submit';
		var cancel = el('button', 'btn btn-link', T.REPORT_CANCEL || 'Cancel');
		cancel.type = 'button';
		cancel.addEventListener('click', function () { parts.form.hidden = true; });

		[legend, reasonLabel, commentLabel, send, cancel].forEach(function (n) { parts.form.appendChild(n); });

		parts.report.addEventListener('click', function () {
			if (!o.loggedIn) {
				parts.status.textContent = T.REPORT_LOGIN || '';

				return;
			}

			parts.status.textContent = '';
			parts.form.hidden = false;
			parts.reason.focus();
		});

		parts.form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			send.disabled = true;

			var fd = new FormData();
			fd.append(o.token, '1');
			fd.append('u', currentKey);
			fd.append('reason', parts.reason.value);
			fd.append('comment', parts.comment.value);

			root.fetch(o.reportUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (res) { return res.json().catch(function () { return null; }); })
				.then(function (data) {
					parts.status.textContent = (data && data.message) || T.REPORT_ERR || '';

					if (data && data.ok) {
						parts.form.hidden = true;
						parts.comment.value = '';
					}
				})
				.catch(function () { parts.status.textContent = T.REPORT_ERR || ''; })
				.then(function () { send.disabled = false; });
		});

		dlg.appendChild(close);
		dlg.appendChild(parts.img);
		dlg.appendChild(parts.contact);
		dlg.appendChild(parts.report);
		dlg.appendChild(parts.form);
		dlg.appendChild(parts.status);

		/* a click on the dark area around the photo closes it */
		dlg.addEventListener('click', function (ev) { if (ev.target === dlg) { dlg.close(); } });

		doc.body.appendChild(dlg);
	}

	function contactLine(o) {
		parts.contact.textContent = '';

		if (!o.contact || !o.contactText) {
			return;
		}

		var bits = o.contactText.split('%s');
		var isMail = o.contact.indexOf('@') !== -1;
		var a = el('a', '', o.contact);

		a.href = (isMail ? 'mailto:' : '') + o.contact;

		if (!isMail) {
			a.rel = 'noopener';
			a.target = '_blank';
		}

		parts.contact.appendChild(doc.createTextNode(bits[0]));
		parts.contact.appendChild(a);
		parts.contact.appendChild(doc.createTextNode(bits.slice(1).join('%s')));
	}

	function open(img, key) {
		var o = options();

		if (!dlg) {
			if (typeof doc.createElement('dialog').showModal !== 'function') {
				return false;
			}

			build(o);
		}

		currentKey = key;
		parts.img.src = bigUrl(img);
		parts.status.textContent = '';
		parts.form.hidden = true;
		parts.report.hidden = !o.reportUrl || !o.loggedIn;
		contactLine(o);
		dlg.showModal();

		return true;
	}

	doc.addEventListener('click', function (ev) {
		if (ev.defaultPrevented || ev.button !== 0 || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.altKey) {
			return;
		}

		var img = ev.target && ev.target.closest ? ev.target.closest('img') : null;

		if (!img || img.closest('dialog.mifoto-lb') || img.closest('.mifoto-crop')) {
			return;
		}

		var key = photoKey(img);

		if (key !== '' && open(img, key)) {
			ev.preventDefault();
		}
	});

	root.MiFotoLightbox = { photoKey: photoKey };
}(typeof window !== 'undefined' ? window : this));
