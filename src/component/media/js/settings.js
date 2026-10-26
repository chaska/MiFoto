/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 *
 * Settings screen: the preview changes as soon as shape, frame or shadow change, and a note appears while there are
 * unsaved changes. Saving itself is Joomla's (com_config); nothing here sends anything.
 */
(function () {
	'use strict';

	var form = document.getElementById('mifoto-settings');

	if (!form) {
		return;
	}

	var samples = form.querySelectorAll('.mifoto-sample');
	var note = document.getElementById('mifoto-unsaved');
	var KINDS = ['shape', 'frame', 'shadow'];

	function look(kind) {
		var checked = form.querySelector('input[name="jform[' + kind + ']"]:checked');

		return checked ? checked.value : '';
	}

	function repaint() {
		samples.forEach(function (el) {
			Array.prototype.slice.call(el.classList).forEach(function (c) {
				if (/^mifoto-(shape|frame|shadow)-/.test(c)) {
					el.classList.remove(c);
				}
			});

			KINDS.forEach(function (kind) {
				var v = look(kind);

				if (/^[a-z]+$/.test(v)) {
					el.classList.add('mifoto-' + kind + '-' + v);
				}
			});
		});
	}

	form.addEventListener('change', function (ev) {
		if (ev.target && ev.target.matches('input[type="radio"]')) {
			repaint();
		}

		if (note) {
			note.hidden = false;
		}
	});

	form.addEventListener('input', function () {
		if (note) {
			note.hidden = false;
		}
	});

	form.addEventListener('submit', function () {
		window.removeEventListener('beforeunload', warn);
	});

	function warn(ev) {
		if (note && !note.hidden) {
			ev.preventDefault();
			ev.returnValue = '';
		}
	}

	window.addEventListener('beforeunload', warn);
}());
