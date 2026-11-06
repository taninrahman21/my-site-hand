/**
 * Site Builder preview: keep the visitor inside the preview.
 *
 * Same-site links (the logo, the menu, buttons) get the preview token added,
 * so clicking around shows the staged site instead of dropping back to the
 * live one. Admin links and links to other sites are left alone.
 */
(function () {
	'use strict';

	const P = window.mshPreview;
	if (!P || !P.token) {
		return;
	}

	const home = new URL(P.home);
	const admin = new RegExp(P.adminRe);

	document.addEventListener('click', (event) => {
		const link = event.target.closest('a[href]');
		if (!link || link.closest('.msh-pv-bar') || event.defaultPrevented) {
			return;
		}

		let url;
		try {
			url = new URL(link.href, window.location.href);
		} catch (e) {
			return;
		}

		if (url.origin !== home.origin || admin.test(url.href) || /wp-login\.php/.test(url.pathname)) {
			return;
		}

		if (url.searchParams.has('msh_preview')) {
			return;
		}

		url.searchParams.set('msh_preview', P.token);
		if (P.framed) {
			url.searchParams.set('msh_frame', '1');
		}

		event.preventDefault();
		window.location.href = url.toString();
	}, true);
})();
