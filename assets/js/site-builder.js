/**
 * Site Builder: starter site gallery and editor.
 *
 * Everything here works without an AI key. Choosing and editing only ever
 * write the user's private staged build through /builder/staged; nothing a
 * visitor can see changes until Apply.
 *
 * Data comes from window.mshBuilder (see Admin::enqueue_site_builder()).
 */
(function () {
	'use strict';

	const { __, _n, sprintf } = wp.i18n;
	const D = window.mshBuilder;
	const root = document.getElementById('msh-sb-app');

	if (!D || !root) {
		return;
	}

	const S = {
		sites: D.sites || [],
		staged: D.staged || null,
		live: D.live || null,
		details: {},
		pending: {},
		resets: new Set(),
		timer: null,
		saving: null,
		status: 'saved',
	};

	/* ------------------------------------------------------------------ */
	/* Helpers                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Build an element. Children that are strings become text nodes, so
	 * nothing here ever parses data as HTML.
	 */
	function el(tag, attrs, ...children) {
		const node = document.createElement(tag);
		Object.entries(attrs || {}).forEach(([key, value]) => {
			if (null === value || undefined === value || false === value) {
				return;
			}
			if ('class' === key) {
				node.className = value;
			} else if (key.startsWith('on') && 'function' === typeof value) {
				node.addEventListener(key.slice(2), value);
			} else if ('dataset' === key) {
				Object.assign(node.dataset, value);
			} else if (true === value) {
				node.setAttribute(key, '');
			} else {
				node.setAttribute(key, value);
			}
		});
		children.flat().forEach((child) => {
			if (null === child || undefined === child || false === child) {
				return;
			}
			node.append(child instanceof Node ? child : document.createTextNode(String(child)));
		});
		return node;
	}

	async function api(method, path, body) {
		const res = await fetch(D.restUrl + path, {
			method,
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': D.nonce, 'Content-Type': 'application/json' },
			body: body ? JSON.stringify(body) : undefined,
		});
		let data = null;
		try {
			data = await res.json();
		} catch (e) {
			data = null;
		}
		if (!res.ok) {
			const error = new Error((data && data.message) || __('Something went wrong. Please try again.', 'my-site-hand'));
			error.code = data && data.code;
			throw error;
		}
		return data;
	}

	function toast(message, type) {
		if (window.msh && window.msh._showToast) {
			window.msh._showToast(message, type || 'success');
		}
	}

	/**
	 * Modal dialog. Resolves with the value of the clicked action, or null
	 * when dismissed.
	 */
	function modal({ title, body, actions, wide }) {
		return new Promise((resolve) => {
			const previous = document.activeElement;
			const close = (value) => {
				overlay.remove();
				document.removeEventListener('keydown', onKey);
				if (previous && previous.focus) {
					previous.focus();
				}
				resolve(value);
			};
			const onKey = (event) => {
				if ('Escape' === event.key) {
					close(null);
				}
			};
			const dialog = el(
				'div',
				{ class: 'msh-sb-modal' + (wide ? ' msh-sb-modal--wide' : ''), role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'msh-sb-modal-title' },
				el('h2', { id: 'msh-sb-modal-title' }, title),
				el('div', { class: 'msh-sb-modal-body' }, body),
				el(
					'div',
					{ class: 'msh-sb-modal-actions' },
					(actions || []).map((action) =>
						el('button', {
							type: 'button',
							class: 'msh-btn ' + (action.class || ''),
							onclick: () => (action.onClick ? action.onClick(close) : close(action.value)),
						}, action.label)
					)
				)
			);
			const overlay = el('div', { class: 'msh-sb-overlay', onclick: (e) => e.target === overlay && close(null) }, dialog);
			document.body.append(overlay);
			document.addEventListener('keydown', onKey);
			const first = dialog.querySelector('.msh-btn--primary') || dialog.querySelector('button');
			if (first) {
				first.focus();
			}
		});
	}

	function confirmDialog(title, text, okLabel, danger) {
		return modal({
			title,
			body: el('p', {}, text),
			actions: [
				{ label: __('Cancel', 'my-site-hand'), value: false },
				{ label: okLabel, value: true, class: danger ? 'msh-btn--danger' : 'msh-btn--primary' },
			],
		});
	}

	function siteSummary(slug) {
		return S.sites.find((s) => s.slug === slug) || null;
	}

	/* ------------------------------------------------------------------ */
	/* Gallery                                                            */
	/* ------------------------------------------------------------------ */

	function renderGallery() {
		const promises = [
			__('Nothing changes on your site until you say yes.', 'my-site-hand'),
			__('Works fully without AI.', 'my-site-hand'),
			__('No lock-in: your site stays plain WordPress blocks.', 'my-site-hand'),
		];

		const head = el(
			'div',
			{ class: 'msh-page-head' },
			el(
				'div',
				{ class: 'msh-page-head-info' },
				el('p', { class: 'msh-eyebrow' }, el('i'), __('Site Builder', 'my-site-hand')),
				el('h2', {}, __('Choose a starter site', 'my-site-hand')),
				el('p', { class: 'msh-page-desc' }, __('Pick a ready-made site, change the words, images and colours to fit your business, and preview it on your own site. Apply it only when you are happy, and undo it any time.', 'my-site-hand')),
				el('ul', { class: 'msh-sb-promises' }, promises.map((p) => el('li', {}, p)))
			)
		);

		const continuesLive = S.staged && S.live && S.staged.state && S.staged.state.based_on === S.live.id;
		const live = S.live && !continuesLive
			? el(
				'div',
				{ class: 'msh-sb-resume msh-sb-live', id: 'msh-sb-live' },
				el('div', {},
					el('p', { class: 'msh-sb-live-label' }, el('span', { class: 'msh-sb-live-dot', 'aria-hidden': 'true' }), __('Live on your site', 'my-site-hand')),
					el('p', {}, sprintf(
						/* translators: 1: starter site name, 2: date */
						__('Your site uses the %1$s starter site, applied on %2$s. Open it again to change text, colours or sections, then update your site.', 'my-site-hand'),
						S.live.starter, S.live.date))),
				el('div', { class: 'msh-sb-resume-actions' },
					S.live.front_url ? el('a', { class: 'msh-btn', href: S.live.front_url, target: '_blank', rel: 'noopener' }, __('View site', 'my-site-hand')) : null,
					el('button', { type: 'button', class: 'msh-btn msh-btn--primary', id: 'msh-sb-customize-live', onclick: customizeLive }, __('Customize my site', 'my-site-hand'))))
			: null;

		const resume = S.staged
			? el(
				'div',
				{ class: 'msh-sb-resume' },
				el('p', {}, sprintf(
					/* translators: %s: starter site name */
					continuesLive
						? __('You are changing your live %s site. Your edits are saved as a draft that only you can see until you update your site.', 'my-site-hand')
						: __('You are working on the %s starter site. Your edits are saved as a draft that only you can see.', 'my-site-hand'),
					S.staged.name
				)),
				el('div', { class: 'msh-sb-resume-actions' },
					el('button', { type: 'button', class: 'msh-btn', onclick: discard }, __('Discard draft', 'my-site-hand')),
					el('button', { type: 'button', class: 'msh-btn msh-btn--primary', onclick: () => openEditor() }, __('Continue editing', 'my-site-hand'))
				)
			)
			: null;

		const grid = el('div', { class: 'msh-sb-grid' }, S.sites.map(card));

		// Category chips, so a user can jump straight to their kind of business.
		const categories = [...new Set(S.sites.map((site) => site.category).filter(Boolean))].sort();
		const filter = categories.length > 1
			? el('div', { class: 'msh-sb-filter', role: 'group', 'aria-label': __('Filter starter sites by type', 'my-site-hand') },
				[''].concat(categories).map((cat) => el('button', {
					type: 'button',
					class: 'msh-sb-chip' + ('' === cat ? ' is-current' : ''),
					'aria-pressed': '' === cat ? 'true' : 'false',
					onclick: (event) => {
						filter.querySelectorAll('.msh-sb-chip').forEach((chip) => {
							const on = chip === event.currentTarget;
							chip.classList.toggle('is-current', on);
							chip.setAttribute('aria-pressed', on ? 'true' : 'false');
						});
						grid.querySelectorAll('.msh-sb-card').forEach((node) => {
							node.hidden = '' !== cat && node.dataset.category !== cat;
						});
					},
				}, '' === cat ? sprintf(
					/* translators: %d: number of starter sites */
					__('All (%d)', 'my-site-hand'), S.sites.length) : cat.charAt(0).toUpperCase() + cat.slice(1))))
			: null;

		root.replaceChildren(...[head, live, resume, filter, grid, renderHistorySlot()].filter(Boolean));
		document.dispatchEvent(new CustomEvent('msh-sb:gallery'));
	}

	function card(site) {
		const shot = site.screenshots && site.screenshots[0];
		const isLive = S.live && S.live.slug === site.slug;
		const isDraft = S.staged && S.staged.slug === site.slug;
		let label = __('Use this site', 'my-site-hand');
		if (isDraft) {
			label = __('Continue editing', 'my-site-hand');
		} else if (isLive) {
			label = __('Customize', 'my-site-hand');
		}
		return el(
			'article',
			{ class: 'msh-sb-card' + (isLive ? ' is-live' : ''), dataset: { category: site.category || '', slug: site.slug } },
			isLive ? el('span', { class: 'msh-sb-card-badge' }, el('span', { class: 'msh-sb-live-dot', 'aria-hidden': 'true' }), __('On your site', 'my-site-hand')) : null,
			el(
				'button',
				{ type: 'button', class: 'msh-sb-card-shot', onclick: () => lightbox(site), 'aria-label': sprintf(
					/* translators: %s: starter site name */
					__('Preview the %s starter site', 'my-site-hand'), site.name) },
				shot ? el('img', { src: shot, alt: '', loading: 'lazy' }) : el('span', { class: 'msh-sb-card-placeholder' }, site.name)
			),
			el(
				'div',
				{ class: 'msh-sb-card-body' },
				el('p', { class: 'msh-sb-card-cat' }, site.category),
				el('h3', {}, site.name),
				el('p', { class: 'msh-sb-card-desc' }, site.description),
				el('ul', { class: 'msh-sb-chips', 'aria-label': __('Pages', 'my-site-hand') }, site.pages.map((p) => el('li', {}, p)))
			),
			el(
				'div',
				{ class: 'msh-sb-card-actions' },
				el('button', { type: 'button', class: 'msh-btn', onclick: () => lightbox(site) }, __('Preview', 'my-site-hand')),
				el('button', { type: 'button', class: 'msh-btn msh-btn--primary', onclick: () => useSite(site) }, label)
			)
		);
	}

	function lightbox(site) {
		const shots = site.screenshots || [];
		if (!shots.length) {
			modal({
				title: site.name,
				body: el('p', {}, site.description),
				actions: [{ label: __('Close', 'my-site-hand'), value: null }],
			});
			return;
		}
		let index = 0;
		const img = el('img', { src: shots[0], alt: '' });
		const counter = el('span', { class: 'msh-sb-lightbox-count' });
		const show = (i) => {
			index = (i + shots.length) % shots.length;
			img.src = shots[index];
			img.alt = sprintf(
				/* translators: 1: starter site name, 2: page number, 3: page count */
				__('%1$s screenshot %2$d of %3$d', 'my-site-hand'), site.name, index + 1, shots.length);
			counter.textContent = (index + 1) + ' / ' + shots.length;
		};
		show(0);
		const body = el(
			'div',
			{ class: 'msh-sb-lightbox' },
			el('div', { class: 'msh-sb-lightbox-frame' }, img),
			shots.length > 1
				? el('div', { class: 'msh-sb-lightbox-nav' },
					el('button', { type: 'button', class: 'msh-btn', onclick: () => show(index - 1) }, __('Previous', 'my-site-hand')),
					counter,
					el('button', { type: 'button', class: 'msh-btn', onclick: () => show(index + 1) }, __('Next', 'my-site-hand')))
				: null
		);
		modal({
			title: site.name,
			body,
			wide: true,
			actions: [
				{ label: __('Close', 'my-site-hand'), value: null },
				{ label: __('Use this site', 'my-site-hand'), class: 'msh-btn--primary', onClick: (close) => { close(null); useSite(site); } },
			],
		});
	}

	async function useSite(site) {
		if (S.staged && S.staged.slug === site.slug) {
			openEditor();
			return;
		}
		if (S.live && S.live.slug === site.slug) {
			customizeLive();
			return;
		}
		if (S.staged) {
			const ok = await confirmDialog(
				__('Start over with a different site?', 'my-site-hand'),
				sprintf(
					/* translators: 1: current starter site name, 2: new starter site name */
					__('Your draft of %1$s will be replaced by %2$s, and your edits to it will be lost. Your live site is not affected.', 'my-site-hand'),
					S.staged.name, site.name),
				__('Start over', 'my-site-hand'),
				true
			);
			if (!ok) {
				return;
			}
		}
		try {
			const res = await api('POST', 'staged', { slug: site.slug });
			S.staged = res.staged;
			openEditor();
		} catch (e) {
			toast(e.message, 'error');
		}
	}

	/** Open the live site again, with the text, colours and sections it was applied with. */
	async function customizeLive() {
		if (S.staged && !(S.staged.state && S.staged.state.based_on === S.live.id)) {
			const ok = await confirmDialog(
				__('Replace your current draft?', 'my-site-hand'),
				sprintf(
					/* translators: 1: draft starter site name, 2: live starter site name */
					__('Your draft of %1$s will be replaced by your live %2$s site, and your edits to the draft will be lost. Your live site is not affected.', 'my-site-hand'),
					S.staged.name, S.live.starter),
				__('Replace draft', 'my-site-hand'),
				true
			);
			if (!ok) {
				return;
			}
		} else if (S.staged) {
			openEditor();
			return;
		}
		try {
			const res = await api('POST', 'live/customize');
			S.staged = res.staged;
			delete S.details[S.staged.slug];
			await openEditor();
			if (!res.has_edits) {
				toast(__('This site was applied with an older version, so its edits were not kept. It opens with the starter text. Applying it still replaces the pages it made.', 'my-site-hand'));
			}
		} catch (e) {
			toast(e.message, 'error');
		}
	}

	async function discard() {
		const ok = await confirmDialog(
			__('Discard this draft?', 'my-site-hand'),
			__('All your edits to this starter site will be deleted. Nothing on your live site changes.', 'my-site-hand'),
			__('Discard draft', 'my-site-hand'),
			true
		);
		if (!ok) {
			return;
		}
		try {
			await flush();
			await api('DELETE', 'staged');
			S.staged = null;
			toast(__('Draft discarded.', 'my-site-hand'));
			setView('gallery');
		} catch (e) {
			toast(e.message, 'error');
		}
	}

	/* ------------------------------------------------------------------ */
	/* Editor                                                             */
	/* ------------------------------------------------------------------ */

	async function openEditor() {
		if (!S.staged) {
			setView('gallery');
			return;
		}
		const slug = S.staged.slug;
		if (!S.details[slug] || S.details[slug].slug !== slug || !S.details[slug].layout) {
			root.replaceChildren(el('p', { class: 'msh-sb-loading' }, __('Loading editor...', 'my-site-hand')));
			try {
				S.details[slug] = await api('GET', 'staged/schema');
			} catch (e) {
				toast(e.message, 'error');
				setView('gallery', true);
				return;
			}
		}
		setView('editor', true);
		renderEditor(S.details[slug]);
	}

	/** Is the draft a new version of the live site? */
	function continuing() {
		return !!(S.staged && S.staged.state && S.staged.state.based_on && S.live && S.staged.state.based_on === S.live.id);
	}

	/** Reload the schema after a layout change and redraw, keeping the scroll position. */
	async function reloadEditor() {
		const y = window.scrollY;
		S.details[S.staged.slug] = await api('GET', 'staged/schema');
		renderEditor(S.details[S.staged.slug]);
		window.scrollTo(0, y);
	}

	function value(slot) {
		if (Object.prototype.hasOwnProperty.call(S.pending, slot.id)) {
			return S.pending[slot.id];
		}
		if (S.resets.has(slot.id)) {
			return slot.default;
		}
		const values = S.staged.state.values || {};
		return Object.prototype.hasOwnProperty.call(values, slot.id) ? values[slot.id] : slot.default;
	}

	function isEdited(slot) {
		if (Object.prototype.hasOwnProperty.call(S.pending, slot.id)) {
			return S.pending[slot.id] !== slot.default;
		}
		if (S.resets.has(slot.id)) {
			return false;
		}
		return Object.prototype.hasOwnProperty.call(S.staged.state.values || {}, slot.id);
	}

	function renderEditor(details) {
		const groups = details.groups;
		const slotsByGroup = {};
		details.slots.forEach((slot) => {
			(slotsByGroup[slot.group] = slotsByGroup[slot.group] || []).push(slot);
		});

		const pageTitle = {};
		details.page_list.forEach((p) => (pageTitle[p.slug] = p.title));

		const nav = el('nav', { class: 'msh-sb-nav', 'aria-label': __('Editor sections', 'my-site-hand') });
		const form = el('div', { class: 'msh-sb-form' });

		const addNav = (id, label, level) =>
			nav.append(el('a', { href: '#' + id, class: 'msh-sb-nav-link' + (level ? ' msh-sb-nav-link--sub' : '') }, label));

		// Business basics first, then the design panel.
		const basics = groups.find((g) => 'site/basics' === g.id);
		if (basics) {
			addNav('msh-sb-g-site-basics', basics.label);
			form.append(groupCard(basics, slotsByGroup[basics.id] || [], __('These fill your business details everywhere they appear, on every page.', 'my-site-hand')));
		}

		addNav('msh-sb-design', __('Design', 'my-site-hand'));
		form.append(designCard(details));

		// Pages, each with its sections in the order the user chose.
		details.page_list.forEach((page) => {
			const entries = (details.layout && details.layout[page.slug]) || [];
			nav.append(el('p', { class: 'msh-sb-nav-page' }, page.title));
			form.append(el('h3', { class: 'msh-sb-page-title', id: 'msh-sb-page-' + page.slug }, page.title));
			addNav('msh-sb-layout-' + page.slug, __('Page sections', 'my-site-hand'), 1);
			form.append(layoutCard(details, page, entries));
			entries.forEach((entry) => {
				const g = groups.find((x) => x.id === page.slug + '/' + entry.id);
				if (!g || entry.hidden) {
					return;
				}
				addNav(groupDomId(g), g.label, 1);
				form.append(groupCard(g, slotsByGroup[g.id] || [], '', page.title));
			});
		});

		// Header and footer last.
		const shared = groups.filter((g) => 'site' === g.page && 'site/basics' !== g.id);
		if (shared.length) {
			nav.append(el('p', { class: 'msh-sb-nav-page' }, __('Every page', 'my-site-hand')));
			form.append(el('h3', { class: 'msh-sb-page-title' }, __('Every page', 'my-site-hand')));
			shared.forEach((g) => {
				addNav(groupDomId(g), g.label, 1);
				form.append(groupCard(g, slotsByGroup[g.id] || []));
			});
		}

		const status = el('span', { class: 'msh-sb-status', id: 'msh-sb-status' });

		const toolbar = el(
			'div',
			{ class: 'msh-sb-bar' },
			el('button', { type: 'button', class: 'msh-btn', onclick: async () => { await flush(); setView('gallery'); } }, __('All starter sites', 'my-site-hand')),
			el('div', { class: 'msh-sb-bar-title' },
				el('strong', {}, details.name),
				continuing() ? el('span', { class: 'msh-sb-tag' }, __('Live site', 'my-site-hand')) : null,
				status),
			el('div', { class: 'msh-sb-bar-actions', id: 'msh-sb-bar-actions' },
				el('button', { type: 'button', class: 'msh-btn msh-btn--danger', onclick: discard }, __('Discard', 'my-site-hand')),
				D.ai ? el('button', { type: 'button', class: 'msh-btn', id: 'msh-sb-ai-redesign', onclick: () => aiRedesign() }, __('Redesign with AI', 'my-site-hand')) : null,
				D.ai ? el('button', { type: 'button', class: 'msh-btn', id: 'msh-sb-ai-fill', onclick: () => aiFill(details) }, __('Fill with AI', 'my-site-hand')) : null,
				S.undoRedesign ? el('button', { type: 'button', class: 'msh-btn', id: 'msh-sb-undo-redesign', onclick: undoRedesign }, __('Undo redesign', 'my-site-hand')) : null,
				el('button', { type: 'button', class: 'msh-btn', id: 'msh-sb-preview-btn', onclick: () => openPreview() }, __('Preview', 'my-site-hand')),
				el('button', { type: 'button', class: 'msh-btn msh-btn--primary', id: 'msh-sb-apply-btn', onclick: () => startApply() }, continuing() ? __('Update my site', 'my-site-hand') : __('Apply to my site', 'my-site-hand')))
		);

		if (!D.ai) {
			form.prepend(el('p', { class: 'msh-sb-ai-hint' },
				__('Tip: add an AI key in Settings to fill this automatically. Everything here also works by hand.', 'my-site-hand'), ' ',
				el('a', { href: D.settingsUrl }, __('AI settings', 'my-site-hand'))));
		}

		root.replaceChildren(
			toolbar,
			el('div', { class: 'msh-sb-cols' }, nav, form)
		);
		setStatus(S.status);
		document.dispatchEvent(new CustomEvent('msh-sb:editor', { detail: { details, toolbar: toolbar.querySelector('#msh-sb-bar-actions') } }));
	}

	function groupDomId(group) {
		return 'msh-sb-g-' + group.id.replace(/[^a-z0-9]+/gi, '-');
	}

	function groupCard(group, slots, intro, pageTitle) {
		const fields = el('div', { class: 'msh-sb-fields' }, slots.map(field));
		const reset = el('button', {
			type: 'button',
			class: 'msh-sb-link',
			onclick: () => {
				slots.forEach((slot) => resetSlot(slot, true));
				scheduleSave(true);
			},
		}, __('Reset section', 'my-site-hand'));

		return el(
			'section',
			{ class: 'msh-card msh-sb-group', id: groupDomId(group), dataset: { group: group.id } },
			el('div', { class: 'msh-card-header' },
				el('div', {},
					el('h3', {}, pageTitle ? pageTitle + ' › ' + group.label : group.label),
					intro ? el('p', {}, intro) : null),
				el('div', { class: 'msh-sb-group-tools' }, reset)),
			el('div', { class: 'msh-card-body msh-card-body--pad' }, fields)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Fields                                                             */
	/* ------------------------------------------------------------------ */

	function field(slot) {
		const id = 'msh-sb-f-' + slot.id.replace(/[^a-z0-9]+/gi, '-');
		const wrap = el('div', { class: 'msh-sb-field msh-sb-field--' + slot.type, dataset: { slot: slot.id } });
		const label = el('label', { for: id, class: 'msh-sb-label' }, slot.label);
		const meta = el('div', { class: 'msh-sb-field-meta' });
		const resetBtn = el('button', { type: 'button', class: 'msh-sb-link msh-sb-reset', onclick: () => { resetSlot(slot); scheduleSave(true); } }, __('Reset to default', 'my-site-hand'));

		const refresh = () => {
			wrap.classList.toggle('is-edited', isEdited(slot));
		};

		let control;
		switch (slot.type) {
			case 'richtext':
				control = richtext(slot, id, refresh, meta);
				break;
			case 'image':
				control = imageField(slot, id, refresh);
				break;
			case 'color':
				control = el('input', { type: 'color', id, value: value(slot), oninput: (e) => change(slot, e.target.value, refresh) });
				break;
			case 'url':
				control = urlField(slot, id, refresh);
				break;
			default:
				control = textField(slot, id, refresh, meta);
		}

		const aiBtn = D.ai && aiWritable(slot)
			? el('button', { type: 'button', class: 'msh-sb-link msh-sb-ai-link', onclick: () => aiRewrite(slot, wrap) }, __('Rewrite with AI', 'my-site-hand'))
			: null;

		wrap.append(
			...[
				el('div', { class: 'msh-sb-field-head' }, label, el('span', { class: 'msh-sb-field-tools' }, ...[aiBtn, resetBtn].filter(Boolean))),
				slot.hint ? el('p', { class: 'msh-sb-hint', id: id + '-hint' }, slot.hint) : null,
				control,
				meta,
			].filter(Boolean)
		);
		wrap._refreshValue = () => {
			if (control._set) {
				control._set(value(slot));
			} else if ('value' in control) {
				control.value = value(slot);
			}
			refresh();
		};
		refresh();
		return wrap;
	}

	function counter(meta, length, max) {
		meta.replaceChildren(el('span', { class: 'msh-sb-count' + (length > max ? ' is-over' : '') }, length + ' / ' + max));
	}

	function textField(slot, id, refresh, meta) {
		const long = (slot.max || 0) > 120;
		const input = el(long ? 'textarea' : 'input', {
			id,
			class: 'msh-sb-input',
			maxlength: slot.max || null,
			rows: long ? 3 : null,
			type: long ? null : 'text',
			'aria-describedby': slot.hint ? id + '-hint' : null,
		});
		input.value = value(slot);
		const update = () => counter(meta, input.value.length, slot.max);
		input.addEventListener('input', () => {
			update();
			change(slot, input.value, refresh);
		});
		update();
		input._set = (v) => {
			input.value = v;
			update();
		};
		return input;
	}

	function urlField(slot, id, refresh) {
		const input = el('input', { id, type: 'url', class: 'msh-sb-input', placeholder: 'https://' });
		const error = el('p', { class: 'msh-sb-error', hidden: true }, __('Enter a full web address, starting with https://', 'my-site-hand'));
		input.value = value(slot);
		input.addEventListener('input', () => {
			const ok = '' === input.value || /^https?:\/\/[^\s]+\.[^\s]+/i.test(input.value);
			error.hidden = ok;
			input.setAttribute('aria-invalid', ok ? 'false' : 'true');
			if (ok) {
				change(slot, input.value, refresh);
			}
		});
		const box = el('div', {}, input, error);
		box._set = (v) => (input.value = v);
		return box;
	}

	/**
	 * Rich text: bold, italic and links only. The server runs the same
	 * allowlist through wp_kses, so this cleaning is for the user's benefit,
	 * not for safety.
	 */
	function richtext(slot, id, refresh, meta) {
		const area = el('div', {
			id,
			class: 'msh-sb-rich',
			contenteditable: 'true',
			role: 'textbox',
			'aria-multiline': 'true',
			'aria-label': slot.label,
		});
		const setHtml = (html) => {
			area.replaceChildren(...cleanNodes(html));
		};
		setHtml(value(slot));

		const update = () => counter(meta, area.textContent.length, slot.max);
		const emit = () => {
			update();
			change(slot, serialize(area), refresh);
		};

		const tool = (label, title, run) =>
			el('button', { type: 'button', class: 'msh-sb-tool', title, 'aria-label': title, onmousedown: (e) => e.preventDefault(), onclick: () => { area.focus(); run(); emit(); } }, label);

		const toolbar = el('div', { class: 'msh-sb-rich-tools', role: 'toolbar', 'aria-label': __('Formatting', 'my-site-hand') },
			tool('B', __('Bold', 'my-site-hand'), () => document.execCommand('bold')),
			tool('I', __('Italic', 'my-site-hand'), () => document.execCommand('italic')),
			tool('🔗', __('Add link', 'my-site-hand'), () => {
				const url = window.prompt(__('Link address (https://...)', 'my-site-hand'), 'https://');
				if (url && /^(https?:\/\/|mailto:|tel:)/i.test(url)) {
					document.execCommand('createLink', false, url);
				}
			}),
			tool('⌫', __('Remove link', 'my-site-hand'), () => document.execCommand('unlink'))
		);

		area.addEventListener('input', emit);
		area.addEventListener('paste', (e) => {
			e.preventDefault();
			const text = (e.clipboardData || window.clipboardData).getData('text/plain');
			document.execCommand('insertText', false, text);
		});
		update();

		const box = el('div', { class: 'msh-sb-rich-wrap' }, toolbar, area);
		box._set = (v) => {
			setHtml(v);
			update();
		};
		return box;
	}

	/** Parse HTML into nodes keeping only strong/em/a[href]/br and text. */
	function cleanNodes(html) {
		const doc = new DOMParser().parseFromString('<div>' + (html || '') + '</div>', 'text/html');
		const out = [];
		const walk = (node, parent) => {
			node.childNodes.forEach((child) => {
				if (3 === child.nodeType) {
					parent.push(document.createTextNode(child.textContent));
					return;
				}
				if (1 !== child.nodeType) {
					return;
				}
				const tag = child.tagName.toLowerCase();
				const map = { b: 'strong', strong: 'strong', i: 'em', em: 'em', a: 'a', br: 'br' };
				if (map[tag]) {
					const clean = document.createElement(map[tag]);
					if ('a' === tag) {
						const href = child.getAttribute('href') || '';
						if (!/^(https?:\/\/|mailto:|tel:|\/)/i.test(href)) {
							walk(child, parent);
							return;
						}
						clean.setAttribute('href', href);
					}
					const kids = [];
					walk(child, kids);
					clean.append(...kids);
					parent.push(clean);
				} else {
					// Block-level wrappers from contenteditable become line breaks.
					if (('div' === tag || 'p' === tag) && parent.length) {
						parent.push(document.createElement('br'));
					}
					walk(child, parent);
				}
			});
		};
		walk(doc.body.firstChild, out);
		return out;
	}

	function serialize(area) {
		const holder = document.createElement('div');
		holder.append(...cleanNodes(area.innerHTML));
		return holder.innerHTML.replace(/<br>$/, '').trim();
	}

	function imageField(slot, id, refresh) {
		const thumb = el('img', { alt: '', class: 'msh-sb-thumb' });
		const showThumb = () => {
			const v = value(slot);
			const chosen = S.staged.images && S.staged.images[slot.id];
			thumb.src = 'number' === typeof v || /^\d+$/.test(String(v)) ? (S.imageUrls[slot.id] || chosen || slot.default) : slot.default;
		};
		S.imageUrls = S.imageUrls || {};
		showThumb();

		const pick = el('button', {
			type: 'button',
			id,
			class: 'msh-btn',
			onclick: () => {
				if (!window.wp || !wp.media) {
					toast(__('The media library could not be loaded.', 'my-site-hand'), 'error');
					return;
				}
				const frame = wp.media({
					title: slot.label,
					library: { type: 'image' },
					multiple: false,
					button: { text: __('Use this image', 'my-site-hand') },
				});
				frame.on('select', () => {
					const att = frame.state().get('selection').first().toJSON();
					S.imageUrls[slot.id] = (att.sizes && att.sizes.medium && att.sizes.medium.url) || att.url;
					change(slot, att.id, refresh);
					showThumb();
				});
				frame.open();
			},
		}, __('Choose from media library', 'my-site-hand'));

		const keep = el('button', { type: 'button', class: 'msh-sb-link', onclick: () => { resetSlot(slot); scheduleSave(true); showThumb(); } }, __('Keep default image', 'my-site-hand'));

		const box = el('div', { class: 'msh-sb-image' }, thumb, el('div', { class: 'msh-sb-image-actions' }, pick, keep));
		box._set = showThumb;
		return box;
	}

	function change(slot, newValue, refresh) {
		S.resets.delete(slot.id);
		S.pending[slot.id] = newValue;
		if (refresh) {
			refresh();
		}
		scheduleSave();
	}

	function resetSlot(slot, quiet) {
		delete S.pending[slot.id];
		S.resets.add(slot.id);
		const wrap = root.querySelector('.msh-sb-field[data-slot="' + CSS.escape(slot.id) + '"]');
		if (wrap && wrap._refreshValue) {
			wrap._refreshValue();
		}
		if (!quiet) {
			setStatus('dirty');
		}
	}

	/* ------------------------------------------------------------------ */
	/* Page layout                                                        */
	/* ------------------------------------------------------------------ */

	/** Sections of one page: reorder, hide, remove added ones, add new ones. */
	function layoutCard(details, page, entries) {
		const save = async (next) => {
			try {
				await flush();
				setStatus('saving');
				const res = await api('PATCH', 'staged', { layout: { [page.slug]: next.map((e) => ({ id: e.id, type: e.type, hidden: !!e.hidden })) } });
				S.staged = res.staged;
				S.undoRedesign = null;
				setStatus('saved');
				await reloadEditor();
				document.dispatchEvent(new CustomEvent('msh-sb:saved'));
			} catch (e) {
				setStatus('error');
				toast(e.message, 'error');
			}
		};
		const move = (i, dir) => {
			const next = entries.slice();
			const j = i + dir;
			if (j < 0 || j >= next.length) {
				return;
			}
			[next[i], next[j]] = [next[j], next[i]];
			save(next);
		};

		const rows = entries.map((entry, i) => el('li', { class: 'msh-sb-layout-row' + (entry.hidden ? ' is-hidden' : ''), dataset: { section: entry.id } },
			el('span', { class: 'msh-sb-layout-name' },
				entry.label,
				entry.type ? el('span', { class: 'msh-sb-tag' }, __('Added', 'my-site-hand')) : null,
				entry.hidden ? el('span', { class: 'msh-sb-tag' }, __('Hidden', 'my-site-hand')) : null),
			el('span', { class: 'msh-sb-layout-tools' },
				el('button', { type: 'button', class: 'msh-sb-icon', title: __('Move up', 'my-site-hand'), 'aria-label': sprintf(
					/* translators: %s: section name */
					__('Move %s up', 'my-site-hand'), entry.label), disabled: 0 === i, onclick: () => move(i, -1) }, '↑'),
				el('button', { type: 'button', class: 'msh-sb-icon', title: __('Move down', 'my-site-hand'), 'aria-label': sprintf(
					/* translators: %s: section name */
					__('Move %s down', 'my-site-hand'), entry.label), disabled: i === entries.length - 1, onclick: () => move(i, 1) }, '↓'),
				el('button', { type: 'button', class: 'msh-sb-link', onclick: () => save(entries.map((e, k) => (k === i ? Object.assign({}, e, { hidden: !e.hidden }) : e))) },
					entry.hidden ? __('Show', 'my-site-hand') : __('Hide', 'my-site-hand')),
				entry.type ? el('button', { type: 'button', class: 'msh-sb-link msh-sb-danger', onclick: () => save(entries.filter((e, k) => k !== i)) }, __('Remove', 'my-site-hand')) : null)));

		const picker = el('select', { class: 'msh-sb-input msh-sb-add-type', 'aria-label': __('Section to add', 'my-site-hand') },
			(details.section_types || []).map((t) => el('option', { value: t.type, title: t.description }, t.label)));
		const add = el('div', { class: 'msh-sb-add' },
			picker,
			el('button', { type: 'button', class: 'msh-btn', onclick: () => save(entries.concat([{ id: 'new', type: picker.value }])) }, __('Add section', 'my-site-hand')));

		return el('section', { class: 'msh-card msh-sb-group msh-sb-layout', id: 'msh-sb-layout-' + page.slug },
			el('div', { class: 'msh-card-header' }, el('div', {},
				el('h3', {}, sprintf(
					/* translators: %s: page title */
					__('%s: page sections', 'my-site-hand'), page.title)),
				el('p', {}, __('Change the order, hide what you do not need, or add a new section. Hidden sections keep their text.', 'my-site-hand')))),
			el('div', { class: 'msh-card-body' }, el('ol', { class: 'msh-sb-layout-list' }, rows), add));
	}

	/* ------------------------------------------------------------------ */
	/* AI redesign                                                        */
	/* ------------------------------------------------------------------ */

	function describeChanges(changes) {
		const details = S.details[S.staged.slug];
		const items = [];
		const names = { mode: __('Mode', 'my-site-hand'), corners: __('Corners', 'my-site-hand'), spacing: __('Spacing', 'my-site-hand') };
		Object.entries(changes.design || {}).forEach(([k, v]) => {
			if (names[k]) {
				items.push(names[k] + ': ' + optionLabel(k, v));
			}
		});
		if (changes.font_pair) {
			const pair = details.font_pairs.find((f) => f.id === changes.font_pair);
			items.push(__('Fonts', 'my-site-hand') + ': ' + (pair ? pair.name : changes.font_pair));
		}
		if (changes.custom_color) {
			items.push(el('span', {}, __('Brand colour', 'my-site-hand') + ': ', el('span', { class: 'msh-sb-dot', style: 'background:' + changes.custom_color }), ' ' + changes.custom_color));
		}
		Object.entries(changes.layout || {}).forEach(([slug, entries]) => {
			const page = details.page_list.find((p) => p.slug === slug);
			const before = (details.layout[slug] || []);
			const label = (e) => {
				if (e.type) {
					const t = (details.section_types || []).find((x) => x.type === e.type);
					return t ? t.label : e.type;
				}
				const own = before.find((b) => b.id === e.id);
				return own ? own.label : e.id;
			};
			const added = entries.filter((e) => e.type && !before.some((b) => b.id === e.id)).map(label);
			const hidden = entries.filter((e) => e.hidden && !(before.find((b) => b.id === e.id) || {}).hidden).map(label);
			const shown = entries.filter((e) => !e.hidden && (before.find((b) => b.id === e.id) || {}).hidden).map(label);
			const parts = [];
			if (added.length) {
				parts.push(__('add', 'my-site-hand') + ' ' + added.join(', '));
			}
			if (hidden.length) {
				parts.push(__('hide', 'my-site-hand') + ' ' + hidden.join(', '));
			}
			if (shown.length) {
				parts.push(__('show', 'my-site-hand') + ' ' + shown.join(', '));
			}
			if (!parts.length) {
				parts.push(__('new section order', 'my-site-hand'));
			}
			items.push((page ? page.title : slug) + ': ' + parts.join('; '));
		});
		return items;
	}

	async function aiRedesign() {
		const box = el('textarea', { class: 'msh-sb-input', id: 'msh-sb-ai-request', rows: 4, maxlength: 600,
			placeholder: __('For example: make it dark and luxurious with gold, rounded corners, and add a pricing section to the home page.', 'my-site-hand') });
		const examples = [
			__('Make it dark and modern', 'my-site-hand'),
			__('Softer and friendlier, with rounded corners', 'my-site-hand'),
			__('Add pricing and questions to the home page', 'my-site-hand'),
		].map((t) => el('button', { type: 'button', class: 'msh-sb-chip', onclick: () => { box.value = t; box.focus(); } }, t));
		const go = await modal({
			title: __('Redesign with AI', 'my-site-hand'),
			body: el('div', {},
				el('p', {}, __('Describe the look or the changes you want. The AI picks colours, fonts, corners, spacing and sections from safe options. You see every change before it is used.', 'my-site-hand')),
				box,
				el('div', { class: 'msh-sb-filter' }, examples),
				el('p', { class: 'msh-sb-hint' }, spendLine())),
			actions: [
				{ label: __('Cancel', 'my-site-hand'), value: false },
				{ label: __('Suggest a redesign', 'my-site-hand'), class: 'msh-btn--primary', onClick: (close) => { if (box.value.trim()) { close(true); } else { box.focus(); } } },
			],
		});
		if (!go) {
			return;
		}
		const request = box.value.trim();
		const busy = el('div', { class: 'msh-sb-overlay' }, el('div', { class: 'msh-sb-modal', role: 'alert' }, el('h2', {}, __('Designing...', 'my-site-hand')), el('p', {}, __('This usually takes a few seconds.', 'my-site-hand'))));
		document.body.append(busy);
		let res;
		try {
			await flush();
			res = await api('POST', 'ai/design', { request });
		} catch (e) {
			busy.remove();
			aiFailed(e);
			return;
		}
		busy.remove();
		D.ai.todaySpend += res.cost || 0;

		const ok = await modal({
			title: __('Suggested redesign', 'my-site-hand'),
			body: el('div', {},
				res.summary ? el('p', {}, res.summary) : null,
				el('ul', { class: 'msh-sb-plan' }, describeChanges(res.changes).map((item) => el('li', {}, item))),
				el('p', { class: 'msh-sb-hint' }, __('This changes your draft only. You can undo it, preview it, and nothing goes live until you apply.', 'my-site-hand'))),
			actions: [
				{ label: __('Discard', 'my-site-hand'), value: false },
				{ label: __('Use this redesign', 'my-site-hand'), value: true, class: 'msh-btn--primary' },
			],
		});
		if (!ok) {
			return;
		}

		const before = S.staged.state;
		const beforeLayout = {};
		Object.keys(res.changes.layout || {}).forEach((slug) => (beforeLayout[slug] = before.layout[slug]));
		try {
			setStatus('saving');
			const saved = await api('PATCH', 'staged', res.changes);
			S.staged = saved.staged;
			S.undoRedesign = {
				design: before.design,
				font_pair: before.font_pair,
				palette: before.palette,
				custom_color: before.custom_color,
				layout: beforeLayout,
			};
			setStatus('saved');
			await reloadEditor();
			document.dispatchEvent(new CustomEvent('msh-sb:saved'));
		} catch (e) {
			setStatus('error');
			toast(e.message, 'error');
			return;
		}

		// Offer to write text for any sections the redesign added.
		const added = [];
		Object.entries(res.changes.layout || {}).forEach(([slug, entries]) => {
			entries.forEach((e) => {
				if (e.type && !(before.layout[slug] || []).some((b) => b.id === e.id)) {
					const now = (S.staged.state.layout[slug] || []).find((x) => x.type === e.type && !(before.layout[slug] || []).some((b) => b.id === x.id));
					if (now) {
						added.push(slug + '/' + now.id);
					}
				}
			});
		});
		if (added.length) {
			const write = await confirmDialog(
				__('Write text for the new sections?', 'my-site-hand'),
				__('The new sections have example text. The AI can write text that fits your business, for you to review.', 'my-site-hand'),
				__('Write their text', 'my-site-hand'));
			if (write) {
				const brief = Object.assign({ business_type: S.details[S.staged.slug].name }, S.lastBrief || {});
				try {
					const fill = await api('POST', 'ai/fill', Object.assign({ only_groups: added, replace_edits: true }, brief));
					D.ai.todaySpend += fill.cost || 0;
					reviewProposals(S.details[S.staged.slug], fill);
				} catch (e) {
					aiFailed(e);
				}
			}
		}
		toast(__('Redesign applied to your draft. Use Preview to see it.', 'my-site-hand'));
	}

	async function undoRedesign() {
		if (!S.undoRedesign) {
			return;
		}
		const back = S.undoRedesign;
		try {
			setStatus('saving');
			const changes = { design: Object.assign({}, back.design), font_pair: back.font_pair, custom_color: back.custom_color, layout: back.layout };
			if (!back.custom_color && !(back.design && back.design.palette)) {
				changes.palette = back.palette;
			}
			const res = await api('PATCH', 'staged', changes);
			S.staged = res.staged;
			S.undoRedesign = null;
			setStatus('saved');
			await reloadEditor();
			toast(__('Redesign undone.', 'my-site-hand'));
		} catch (e) {
			setStatus('error');
			toast(e.message, 'error');
		}
	}

	/* ------------------------------------------------------------------ */
	/* Design panel                                                       */
	/* ------------------------------------------------------------------ */

	function optionLabel(key, value) {
		const labels = {
			auto: __('As designed', 'my-site-hand'), light: __('Light', 'my-site-hand'), dark: __('Dark', 'my-site-hand'),
			site: __('As designed', 'my-site-hand'), square: __('Square', 'my-site-hand'), soft: __('Soft', 'my-site-hand'),
			rounded: __('Rounded', 'my-site-hand'), pill: __('Pill', 'my-site-hand'),
			compact: __('Compact', 'my-site-hand'), comfortable: __('Comfortable', 'my-site-hand'), airy: __('Airy', 'my-site-hand'),
		};
		return labels[value] || value;
	}

	/** Light/dark, corners and spacing as rows of choices. */
	function designOptions(details) {
		const design = S.staged.state.design || {};
		const titles = { mode: __('Light or dark', 'my-site-hand'), corners: __('Corners', 'my-site-hand'), spacing: __('Spacing', 'my-site-hand') };
		return el('div', { class: 'msh-sb-design-options' },
			Object.entries(details.design_options || {}).map(([key, options]) => {
				// Colours picked one by one are used as they are, so light or dark does not apply.
				const locked = 'mode' === key && !!design.palette;
				return el('div', { class: 'msh-sb-design-row' },
					el('p', { class: 'msh-sb-label' }, titles[key] || key),
					locked ? el('p', { class: 'msh-sb-hint' }, __('You picked every colour yourself, so these are used as they are. Choose a colour set above to use light or dark again.', 'my-site-hand')) : null,
					el('div', { class: 'msh-sb-filter', role: 'radiogroup', 'aria-label': titles[key] || key },
						options.map((opt) => el('button', {
							type: 'button',
							role: 'radio',
							disabled: locked,
							'aria-checked': design[key] === opt ? 'true' : 'false',
							class: 'msh-sb-chip' + (design[key] === opt ? ' is-current' : ''),
							onclick: () => saveDesign({ design: { [key]: opt } }),
						}, optionLabel(key, opt)))));
			}));
	}

	/** Seven colour pickers; the server fixes contrast before saving. */
	function paletteEditor() {
		const slugs = ['base', 'surface', 'accent', 'contrast', 'muted', 'primary', 'on-primary'];
		const names = {
			base: __('Background', 'my-site-hand'), surface: __('Soft background', 'my-site-hand'), accent: __('Highlight background', 'my-site-hand'),
			contrast: __('Text', 'my-site-hand'), muted: __('Quiet text', 'my-site-hand'), primary: __('Brand and buttons', 'my-site-hand'),
			'on-primary': __('Text on buttons', 'my-site-hand'),
		};
		const colors = S.staged.colors || {};
		const inputs = {};
		const grid = el('div', { class: 'msh-sb-palette-grid' }, slugs.map((slug) => {
			inputs[slug] = el('input', { type: 'color', id: 'msh-sb-pal-' + slug, value: colors[slug] || '#ffffff' });
			return el('label', { class: 'msh-sb-palette-item', for: 'msh-sb-pal-' + slug }, inputs[slug], el('span', {}, names[slug]));
		}));
		return el('details', { class: 'msh-sb-palette-editor' },
			el('summary', {}, __('Pick every colour yourself', 'my-site-hand')),
			el('p', { class: 'msh-sb-hint' }, __('If a text colour would be hard to read, it is adjusted just enough to pass WCAG AA. Backgrounds stay exactly as you pick them.', 'my-site-hand')),
			grid,
			el('button', { type: 'button', class: 'msh-btn', onclick: () => {
				const palette = {};
				slugs.forEach((slug) => (palette[slug] = inputs[slug].value));
				saveDesign({ design: { palette } });
			} }, __('Use these colours', 'my-site-hand')));
	}

	function swatches(colors) {
		return el('span', { class: 'msh-sb-swatches', 'aria-hidden': 'true' },
			['primary', 'contrast', 'surface', 'accent', 'base'].map((slug) => el('span', { style: 'background:' + colors[slug] })));
	}

	function designCard(details) {
		const state = S.staged.state;

		const palettes = el('div', { class: 'msh-sb-options', role: 'radiogroup', 'aria-label': __('Colour palettes', 'my-site-hand') },
			details.palettes.map((p) => {
				const selected = !state.custom_color && !(state.design && state.design.palette) && state.palette === p.id;
				return el('button', {
					type: 'button',
					role: 'radio',
					'aria-checked': selected ? 'true' : 'false',
					class: 'msh-sb-option' + (selected ? ' is-selected' : ''),
					onclick: () => saveDesign({ palette: p.id, design: { palette: null } }),
				}, swatches(p.colors), el('span', {}, p.name));
			}));

		const fonts = el('div', { class: 'msh-sb-options msh-sb-options--fonts', role: 'radiogroup', 'aria-label': __('Font pairs', 'my-site-hand') },
			details.font_pairs.map((f) => {
				const selected = state.font_pair === f.id;
				return el('button', {
					type: 'button',
					role: 'radio',
					'aria-checked': selected ? 'true' : 'false',
					class: 'msh-sb-option msh-sb-font' + (selected ? ' is-selected' : ''),
					onclick: () => saveDesign({ font_pair: f.id }),
				},
				el('span', { class: 'msh-sb-font-sample', style: 'font-family:' + f.heading.fontFamily + ';font-weight:' + (f.heading.fontWeight || 600) }, 'Aa'),
				el('span', { class: 'msh-sb-font-name' }, f.name),
				el('span', { class: 'msh-sb-font-body', style: 'font-family:' + f.body.fontFamily }, __('Clear, friendly body text.', 'my-site-hand')));
			}));

		const brandInput = el('input', { type: 'color', id: 'msh-sb-brand', value: state.custom_color || (S.staged.colors && S.staged.colors.primary) || '#1b4fd8' });
		const preview = el('div', { class: 'msh-sb-brand-preview' }, state.custom_color ? swatches(S.staged.colors) : null);
		const custom = el('div', { class: 'msh-sb-brand' },
			el('label', { for: 'msh-sb-brand', class: 'msh-sb-label' }, __('Or start from your brand colour', 'my-site-hand')),
			el('p', { class: 'msh-sb-hint' }, __('We build a full palette from it and adjust it until every text colour is easy to read (WCAG AA).', 'my-site-hand')),
			el('div', { class: 'msh-sb-brand-row' },
				brandInput,
				el('button', { type: 'button', class: 'msh-btn' + (state.custom_color ? ' is-selected' : ''), onclick: () => saveDesign({ custom_color: brandInput.value, design: { palette: null } }) },
					state.custom_color ? __('Update brand palette', 'my-site-hand') : __('Use my brand colour', 'my-site-hand')),
				preview)
		);

		return el('section', { class: 'msh-card msh-sb-group', id: 'msh-sb-design' },
			el('div', { class: 'msh-card-header' }, el('div', {},
				el('h3', {}, __('Design', 'my-site-hand')),
				el('p', {}, __('Every palette passes WCAG AA contrast for text. Fonts load from the visitor\'s device, so nothing is fetched from other servers.', 'my-site-hand')))),
			el('div', { class: 'msh-card-body msh-card-body--pad' },
				D.ai ? el('button', { type: 'button', class: 'msh-btn msh-sb-ai-colors', onclick: aiColors }, __('Suggest colours with AI', 'my-site-hand')) : null,
				el('p', { class: 'msh-sb-label' }, __('Colour palette', 'my-site-hand')),
				palettes,
				custom,
				paletteEditor(),
				designOptions(S.details[S.staged.slug] || {}),
				el('p', { class: 'msh-sb-label' }, __('Fonts', 'my-site-hand')),
				fonts)
		);
	}

	async function saveDesign(changes) {
		try {
			await flush();
			setStatus('saving');
			const res = await api('PATCH', 'staged', changes);
			S.staged = res.staged;
			setStatus('saved');
			const design = root.querySelector('#msh-sb-design');
			if (design) {
				design.replaceWith(designCard(S.details[S.staged.slug]));
			}
			document.dispatchEvent(new CustomEvent('msh-sb:saved'));
		} catch (e) {
			setStatus('error');
			toast(e.message, 'error');
		}
	}

	/* ------------------------------------------------------------------ */
	/* Autosave                                                           */
	/* ------------------------------------------------------------------ */

	function setStatus(status) {
		S.status = status;
		const node = document.getElementById('msh-sb-status');
		if (!node) {
			return;
		}
		const labels = {
			saved: __('Draft saved', 'my-site-hand'),
			dirty: __('Unsaved changes', 'my-site-hand'),
			saving: __('Saving...', 'my-site-hand'),
			error: __('Not saved. Check your connection.', 'my-site-hand'),
		};
		node.textContent = labels[status] || '';
		node.dataset.status = status;
	}

	function scheduleSave(now) {
		setStatus('dirty');
		clearTimeout(S.timer);
		S.timer = setTimeout(flush, now ? 0 : 800);
	}

	/** Send pending edits. Safe to call any time; resolves when saved. */
	async function flush() {
		clearTimeout(S.timer);
		if (S.saving) {
			await S.saving;
		}
		const values = S.pending;
		const reset = Array.from(S.resets);
		if (!Object.keys(values).length && !reset.length) {
			return;
		}
		S.pending = {};
		S.resets = new Set();
		setStatus('saving');
		S.saving = api('PATCH', 'staged', { values, reset })
			.then((res) => {
				S.staged = res.staged;
				setStatus(Object.keys(S.pending).length ? 'dirty' : 'saved');
				document.dispatchEvent(new CustomEvent('msh-sb:saved'));
			})
			.catch((e) => {
				// Put the edits back so the next save retries them.
				S.pending = Object.assign(values, S.pending);
				reset.forEach((id) => S.resets.add(id));
				setStatus('error');
				toast(e.message, 'error');
			})
			.finally(() => {
				S.saving = null;
			});
		await S.saving;
	}

	window.addEventListener('beforeunload', (event) => {
		if (Object.keys(S.pending).length || S.resets.size) {
			flush();
			event.preventDefault();
			event.returnValue = '';
		}
	});

	/* ------------------------------------------------------------------ */
	/* Optional AI                                                        */
	/* ------------------------------------------------------------------ */

	// Facts only the owner knows, and image descriptions, are never sent.
	const AI_SKIP = ['biz.phone', 'biz.email', 'biz.address', 'biz.hours', 'site.title', 'biz.name'];

	function aiWritable(slot) {
		return ('text' === slot.type || 'richtext' === slot.type) && !AI_SKIP.includes(slot.id) && !/_alt$/.test(slot.id);
	}

	function spendLine() {
		const fmt = (n) => '$' + Number(n).toFixed(n > 0 && n < 0.01 ? 4 : 2);
		return sprintf(
			/* translators: 1: AI provider name, 2: spent today, 3: daily cap */
			__('Uses your %1$s key. Spent today: %2$s of your %3$s daily cap.', 'my-site-hand'),
			D.ai.provider, fmt(D.ai.todaySpend), fmt(D.ai.dailyCap));
	}

	function aiFailed(e) {
		modal({
			title: __('The AI could not help this time', 'my-site-hand'),
			body: el('div', {}, el('p', {}, e.message), el('p', {}, __('Nothing was changed. You can keep editing by hand.', 'my-site-hand'))),
			actions: [{ label: __('OK', 'my-site-hand'), value: null, class: 'msh-btn--primary' }],
		});
	}

	function briefFields(prefill) {
		const b = prefill || S.lastBrief || {};
		const type = el('input', { type: 'text', class: 'msh-sb-input', id: 'msh-sb-ai-type', maxlength: 80, placeholder: __('e.g. family dental practice', 'my-site-hand') });
		type.value = b.business_type || '';
		const name = el('input', { type: 'text', class: 'msh-sb-input', id: 'msh-sb-ai-name', maxlength: 80 });
		name.value = b.name || '';
		const desc = el('textarea', { class: 'msh-sb-input', id: 'msh-sb-ai-desc', rows: 3, maxlength: 600, placeholder: __('What you do, who for, and what makes you different.', 'my-site-hand') });
		desc.value = b.description || '';
		const tones = ['friendly', 'professional', 'bold'];
		const toneLabels = { friendly: __('Friendly', 'my-site-hand'), professional: __('Professional', 'my-site-hand'), bold: __('Bold', 'my-site-hand') };
		const tone = el('div', { class: 'msh-sb-tones', role: 'radiogroup', 'aria-label': __('Tone', 'my-site-hand') },
			tones.map((t) => el('label', {}, el('input', { type: 'radio', name: 'msh-sb-tone', value: t, checked: (b.tone || 'friendly') === t }), ' ', toneLabels[t])));
		const read = () => ({
			business_type: type.value.trim(),
			name: name.value.trim(),
			description: desc.value.trim(),
			tone: (tone.querySelector('input:checked') || {}).value || 'friendly',
		});
		const node = el('div', { class: 'msh-sb-brief' },
			el('label', { class: 'msh-sb-label', for: 'msh-sb-ai-type' }, __('Type of business', 'my-site-hand')), type,
			el('label', { class: 'msh-sb-label', for: 'msh-sb-ai-name' }, __('Business name (optional)', 'my-site-hand')), name,
			el('label', { class: 'msh-sb-label', for: 'msh-sb-ai-desc' }, __('Describe it in a sentence or two', 'my-site-hand')), desc,
			el('p', { class: 'msh-sb-label' }, __('Tone', 'my-site-hand')), tone);
		return { node, read };
	}

	async function aiFill(details) {
		const brief = briefFields();
		const replace = el('input', { type: 'checkbox', id: 'msh-sb-ai-replace' });
		const go = await modal({
			title: __('Fill everything with AI', 'my-site-hand'),
			body: el('div', {},
				el('p', {}, __('One request writes text for every section. You review it before anything is used.', 'my-site-hand')),
				brief.node,
				el('label', { class: 'msh-sb-check' }, replace, ' ', __('Replace text I already edited', 'my-site-hand')),
				el('p', { class: 'msh-sb-hint' }, __('Phone, email, address, opening hours and image descriptions are never filled by AI.', 'my-site-hand')),
				el('p', { class: 'msh-sb-hint' }, spendLine())),
			actions: [
				{ label: __('Cancel', 'my-site-hand'), value: false },
				{ label: __('Write my site', 'my-site-hand'), class: 'msh-btn--primary', onClick: (close) => {
					if (!brief.read().business_type) {
						document.getElementById('msh-sb-ai-type').focus();
						return;
					}
					close(true);
				} },
			],
		});
		if (!go) {
			return;
		}
		const input = brief.read();
		S.lastBrief = input;
		const busy = el('div', { class: 'msh-sb-overlay' }, el('div', { class: 'msh-sb-modal', role: 'alert' }, el('h2', {}, __('Writing...', 'my-site-hand')), el('p', {}, __('This usually takes 20 to 60 seconds.', 'my-site-hand'))));
		document.body.append(busy);
		let res;
		try {
			await flush();
			res = await api('POST', 'ai/fill', Object.assign({ replace_edits: replace.checked }, input));
		} catch (e) {
			busy.remove();
			aiFailed(e);
			return;
		}
		busy.remove();
		D.ai.todaySpend += res.cost || 0;
		reviewProposals(details, res);
	}

	/** Every proposal side by side with the current text; nothing is used until accepted. */
	async function reviewProposals(details, res) {
		const slotById = {};
		details.slots.forEach((sl) => (slotById[sl.id] = sl));
		const pageTitle = {};
		details.page_list.forEach((pg) => (pageTitle[pg.slug] = pg.title));
		const groupLabel = (id) => {
			const g = details.groups.find((x) => x.id === id);
			return g ? (pageTitle[g.page] ? pageTitle[g.page] + ' › ' : '') + g.label : id;
		};
		const ids = Object.keys(res.proposals).filter((id) => slotById[id]);
		if (!ids.length) {
			aiFailed(new Error(__('The AI did not suggest any changes.', 'my-site-hand')));
			return;
		}
		const textOf = (html) => {
			const d = document.createElement('div');
			d.append(...cleanNodes(html));
			return d.textContent;
		};
		const boxes = {};
		const rows = ids.map((id) => {
			boxes[id] = el('input', { type: 'checkbox', checked: true, 'aria-label': slotById[id].label });
			return el('tr', {},
				el('td', {}, boxes[id]),
				el('td', {}, el('strong', {}, slotById[id].label), el('div', { class: 'msh-sb-hint' }, groupLabel(slotById[id].group))),
				el('td', { class: 'msh-sb-current' }, textOf(value(slotById[id]))),
				el('td', {}, textOf(res.proposals[id])));
		});
		const table = el('table', { class: 'msh-sb-review' },
			el('thead', {}, el('tr', {}, el('th', {}, ''), el('th', {}, __('Field', 'my-site-hand')), el('th', {}, __('Now', 'my-site-hand')), el('th', {}, __('Suggestion', 'my-site-hand')))),
			el('tbody', {}, rows));
		const ok = await modal({
			title: __('Review the suggestions', 'my-site-hand'),
			wide: true,
			body: el('div', {},
				el('p', {}, sprintf(
					/* translators: %d: number of suggestions */
					_n('%d suggestion. Untick any you do not want.', '%d suggestions. Untick any you do not want.', ids.length, 'my-site-hand'), ids.length)
					+ (res.skipped ? ' ' + sprintf(
						/* translators: %d: number of fields */
						_n('%d field you edited was left alone.', '%d fields you edited were left alone.', res.skipped, 'my-site-hand'), res.skipped) : '')),
				el('div', { class: 'msh-sb-review-wrap' }, table)),
			actions: [
				{ label: __('Discard all', 'my-site-hand'), value: false },
				{ label: __('Use selected', 'my-site-hand'), value: true, class: 'msh-btn--primary' },
			],
		});
		if (!ok) {
			return;
		}
		let used = 0;
		ids.forEach((id) => {
			if (boxes[id].checked) {
				S.resets.delete(id);
				S.pending[id] = res.proposals[id];
				const wrap = root.querySelector('.msh-sb-field[data-slot="' + CSS.escape(id) + '"]');
				if (wrap && wrap._refreshValue) {
					wrap._refreshValue();
				}
				used++;
			}
		});
		if (used) {
			await flush();
			toast(sprintf(
				/* translators: %d: number of fields */
				_n('%d field updated in your draft.', '%d fields updated in your draft.', used, 'my-site-hand'), used));
		}
	}

	async function aiRewrite(slot, wrap) {
		const old = wrap.querySelector('.msh-sb-suggest');
		if (old) {
			old.remove();
		}
		const instruction = el('select', { class: 'msh-sb-input msh-sb-ai-instruction', 'aria-label': __('How to rewrite', 'my-site-hand') },
			el('option', { value: '' }, __('Rewrite', 'my-site-hand')),
			el('option', { value: 'Make it shorter.' }, __('Shorter', 'my-site-hand')),
			el('option', { value: 'Make it warmer and friendlier.' }, __('Friendlier', 'my-site-hand')),
			el('option', { value: 'Make it more professional.' }, __('More professional', 'my-site-hand')));
		const out = el('div', { class: 'msh-sb-suggest-text' });
		const actions = el('div', { class: 'msh-sb-suggest-actions' });
		const panel = el('div', { class: 'msh-sb-suggest', role: 'region', 'aria-label': __('AI suggestion', 'my-site-hand') },
			el('div', { class: 'msh-sb-suggest-head' }, instruction,
				el('button', { type: 'button', class: 'msh-btn msh-btn--sm', onclick: run }, __('Suggest', 'my-site-hand'))),
			out, actions);
		wrap.append(panel);

		async function run() {
			out.replaceChildren(el('p', { class: 'msh-sb-hint' }, __('Thinking...', 'my-site-hand')));
			actions.replaceChildren();
			let res;
			try {
				res = await api('POST', 'ai/rewrite', { slot: slot.id, current: value(slot), instruction: instruction.value });
			} catch (e) {
				out.replaceChildren(el('p', { class: 'msh-sb-error' }, e.message + ' ' + __('Your text is unchanged.', 'my-site-hand')));
				actions.replaceChildren(el('button', { type: 'button', class: 'msh-btn msh-btn--sm', onclick: () => panel.remove() }, __('Close', 'my-site-hand')));
				return;
			}
			D.ai.todaySpend += res.cost || 0;
			out.replaceChildren(el('p', { class: 'msh-sb-hint' }, __('Suggestion', 'my-site-hand')), el('div', { class: 'msh-sb-suggest-value' }, ...cleanNodes(res.value)));
			actions.replaceChildren(
				el('button', { type: 'button', class: 'msh-btn msh-btn--sm', onclick: () => panel.remove() }, __('Keep mine', 'my-site-hand')),
				el('button', { type: 'button', class: 'msh-btn msh-btn--sm msh-btn--primary', onclick: () => {
					S.resets.delete(slot.id);
					S.pending[slot.id] = res.value;
					wrap._refreshValue();
					scheduleSave(true);
					panel.remove();
				} }, __('Accept', 'my-site-hand')));
		}
		run();
	}

	async function aiColors() {
		const brief = briefFields();
		const go = await modal({
			title: __('Suggest colours with AI', 'my-site-hand'),
			body: el('div', {}, brief.node, el('p', { class: 'msh-sb-hint' }, spendLine())),
			actions: [
				{ label: __('Cancel', 'my-site-hand'), value: false },
				{ label: __('Suggest', 'my-site-hand'), value: true, class: 'msh-btn--primary' },
			],
		});
		if (!go) {
			return;
		}
		S.lastBrief = Object.assign({}, S.lastBrief || {}, brief.read());
		let res;
		try {
			res = await api('POST', 'ai/colors', brief.read());
		} catch (e) {
			aiFailed(e);
			return;
		}
		D.ai.todaySpend += res.cost || 0;
		const use = await modal({
			title: __('Suggested colours', 'my-site-hand'),
			body: el('div', {},
				swatches(res.colors),
				el('p', {}, res.reason),
				el('p', { class: 'msh-sb-hint' }, __('Adjusted so all text passes WCAG AA contrast.', 'my-site-hand'))),
			actions: [
				{ label: __('Keep mine', 'my-site-hand'), value: false },
				{ label: __('Use these colours', 'my-site-hand'), value: true, class: 'msh-btn--primary' },
			],
		});
		if (use) {
			saveDesign({ custom_color: res.brand_color });
		}
	}

	/* ------------------------------------------------------------------ */
	/* Preview                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Show the staged site, rendered by the user's own theme, in a frame of
	 * the chosen device width. Nothing is written: the frame loads the
	 * private preview URL.
	 */
	async function openPreview(device) {
		if (!D.isBlockTheme) {
			modal({
				title: __('Preview needs a block theme', 'my-site-hand'),
				body: el('p', {}, __('Starter sites use the Site Editor, which only block themes have. Use the notice at the top of this page to switch to Twenty Twenty-Five, then preview again.', 'my-site-hand')),
				actions: [{ label: __('OK', 'my-site-hand'), value: null, class: 'msh-btn--primary' }],
			});
			return;
		}

		let data;
		try {
			await flush();
			data = await api('POST', 'preview');
		} catch (e) {
			toast(e.message, 'error');
			return;
		}

		const devices = data.devices;
		const frameUrl = (url) => {
			const u = new URL(url);
			u.searchParams.set('msh_frame', '1');
			return u.toString();
		};
		let current = device && devices[device] ? device : 'desktop';

		const iframe = el('iframe', { class: 'msh-sb-preview-frame', title: __('Preview of your staged site', 'my-site-hand'), src: frameUrl(data.url) });
		const scaler = el('div', { class: 'msh-sb-preview-scaler' }, iframe);
		const stage = el('div', { class: 'msh-sb-preview-stage' }, scaler);

		const fit = () => {
			const width = devices[current];
			const available = stage.clientWidth - 32;
			const scale = Math.min(1, available / width);
			const height = (stage.clientHeight - 32) / scale;
			iframe.style.width = width + 'px';
			iframe.style.height = height + 'px';
			scaler.style.width = width * scale + 'px';
			scaler.style.height = height * scale + 'px';
			iframe.style.transform = 'scale(' + scale + ')';
		};

		const labels = { desktop: __('Desktop', 'my-site-hand'), tablet: __('Tablet', 'my-site-hand'), mobile: __('Mobile', 'my-site-hand') };
		const deviceButtons = Object.keys(devices).map((key) => el('button', {
			type: 'button',
			class: 'msh-btn msh-sb-device' + (key === current ? ' is-current' : ''),
			'aria-pressed': key === current ? 'true' : 'false',
			dataset: { device: key },
			onclick: () => {
				current = key;
				deviceButtons.forEach((b) => {
					const on = b.dataset.device === key;
					b.classList.toggle('is-current', on);
					b.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
				fit();
			},
		}, labels[key] || key, el('span', { class: 'msh-sb-device-w' }, devices[key] + 'px')));

		let reloadTimer = null;
		const reload = () => {
			clearTimeout(reloadTimer);
			reloadTimer = setTimeout(() => iframe.contentWindow && iframe.contentWindow.location.reload(), 300);
		};
		const onKey = (e) => {
			if ('Escape' === e.key) {
				close();
			}
		};
		const close = () => {
			panel.remove();
			window.removeEventListener('resize', fit);
			document.removeEventListener('msh-sb:saved', reload);
			document.removeEventListener('keydown', onKey);
			const btn = document.getElementById('msh-sb-preview-btn');
			if (btn) {
				btn.focus();
			}
		};

		const panel = el('div', { class: 'msh-sb-preview', role: 'dialog', 'aria-modal': 'true', 'aria-label': __('Preview', 'my-site-hand') },
			el('div', { class: 'msh-sb-preview-bar' },
				el('p', { class: 'msh-sb-preview-note' },
					el('strong', {}, __('Preview', 'my-site-hand')),
					el('span', {}, __('Only you can see this. Nothing has changed on your site yet.', 'my-site-hand'))),
				el('div', { class: 'msh-sb-preview-devices', role: 'group', 'aria-label': __('Device', 'my-site-hand') }, deviceButtons),
				el('div', { class: 'msh-sb-preview-actions', id: 'msh-sb-preview-actions' },
					el('a', { class: 'msh-btn', href: data.url, target: '_blank', rel: 'noopener' }, __('Open in new tab', 'my-site-hand')),
					el('button', { type: 'button', class: 'msh-btn', onclick: close }, __('Back to editor', 'my-site-hand')),
					el('button', { type: 'button', class: 'msh-btn msh-btn--primary', onclick: () => { close(); startApply(); } }, __('Apply to my site', 'my-site-hand')))),
			stage);

		document.body.append(panel);
		window.addEventListener('resize', fit);
		document.addEventListener('msh-sb:saved', reload);
		document.addEventListener('keydown', onKey);
		fit();
		deviceButtons[0].focus();
		document.dispatchEvent(new CustomEvent('msh-sb:preview', { detail: { actions: panel.querySelector('#msh-sb-preview-actions'), close } }));
	}

	/* ------------------------------------------------------------------ */
	/* Routing and theme                                                  */
	/* ------------------------------------------------------------------ */

	function setView(view, replace) {
		const url = new URL(window.location.href);
		url.searchParams.set('view', view);
		window.history[replace ? 'replaceState' : 'pushState']({ view }, '', url);
		if ('gallery' === view) {
			renderGallery();
		}
	}

	window.addEventListener('popstate', () => route());

	async function route() {
		const url = new URL(window.location.href);
		const view = url.searchParams.get('view');
		const action = url.searchParams.get('msh-action');

		if ('gallery' !== view && S.staged) {
			await openEditor();
		} else {
			renderGallery();
		}

		// Buttons on the front-end preview bar land here with an action.
		if (action && S.staged) {
			url.searchParams.delete('msh-action');
			window.history.replaceState(window.history.state, '', url);
			if ('discard' === action) {
				discard();
			} else if ('apply' === action) {
				startApply();
			} else {
				document.dispatchEvent(new CustomEvent('msh-sb:action', { detail: { action } }));
			}
		}
	}

	/** The "Site history" list: the last five applies, each with Undo. */
	function renderHistorySlot() {
		const box = el('section', { id: 'msh-sb-history', class: 'msh-card msh-sb-history', hidden: true });
		loadHistory(box);
		return box;
	}

	async function loadHistory(box) {
		let list;
		try {
			list = await api('GET', 'snapshots');
		} catch (e) {
			return;
		}
		if (!list.length) {
			box.hidden = true;
			return;
		}
		const rows = list.map((snap) => el('li', { class: 'msh-sb-history-row' + ('undone' === snap.status ? ' is-undone' : '') },
			el('div', {},
				el('strong', {}, snap.starter),
				el('span', { class: 'msh-sb-history-meta' }, snap.date + ' · ' + sprintf(
					/* translators: %d: number of pages */
					_n('%d page', '%d pages', snap.pages, 'my-site-hand'), snap.pages))),
			'undone' === snap.status
				? el('span', { class: 'msh-sb-history-status' }, __('Undone', 'my-site-hand'))
				: el('button', { type: 'button', class: 'msh-btn', onclick: () => undo(snap, list) }, __('Undo', 'my-site-hand'))));

		box.replaceChildren(
			el('div', { class: 'msh-card-header' }, el('div', {},
				el('h3', {}, __('Site history', 'my-site-hand')),
				el('p', {}, __('Every time you apply a starter site, a snapshot is taken first. Undo puts back your previous front page, title, header, footer and styles, and moves what was created to the trash.', 'my-site-hand')))),
			el('ul', { class: 'msh-sb-history-list' }, rows));
		box.hidden = false;
	}

	async function undo(snap, list) {
		const newer = list.filter((s) => 'applied' === s.status && s.created_at > snap.created_at);
		const body = el('div', {},
			el('p', {}, sprintf(
				/* translators: 1: starter site name, 2: date */
				__('This undoes the %1$s site applied on %2$s:', 'my-site-hand'), snap.starter, snap.date)),
			el('ul', {},
				el('li', {}, __('Your previous front page, site title and tagline come back.', 'my-site-hand')),
				el('li', {}, __('Your previous header, footer and global styles come back.', 'my-site-hand')),
				el('li', {}, __('The pages, menu and images that were created move to the trash. You can restore them from the trash if you change your mind.', 'my-site-hand'))),
			newer.length ? el('p', { class: 'msh-sb-warn' }, sprintf(
				/* translators: %d: number of later builds */
				_n('%d later build will be undone first.', '%d later builds will be undone first.', newer.length, 'my-site-hand'), newer.length)) : null,
			el('p', {}, __('Edits you made to those pages after applying go to the trash with them.', 'my-site-hand')));

		const ok = await modal({
			title: __('Undo this build?', 'my-site-hand'),
			body,
			actions: [
				{ label: __('Cancel', 'my-site-hand'), value: false },
				{ label: __('Undo', 'my-site-hand'), value: true, class: 'msh-btn--danger' },
			],
		});
		if (!ok) {
			return;
		}
		try {
			const res = await api('POST', 'snapshots/' + snap.id + '/restore', { confirm: true });
			if (res.errors && res.errors.length) {
				toast(res.errors.join(' '), 'error');
			} else {
				toast(__('Undone. Your previous site is back.', 'my-site-hand'));
			}
			S.live = await api('GET', 'live');
			renderGallery();
		} catch (e) {
			toast(e.message, 'error');
		}
	}

	/* ------------------------------------------------------------------ */
	/* Apply                                                              */
	/* ------------------------------------------------------------------ */

	/** Show exactly what will change, then apply only on a clear yes. */
	async function startApply() {
		if (!D.isBlockTheme) {
			openPreview();
			return;
		}
		let plan;
		try {
			await flush();
			plan = await api('GET', 'apply');
		} catch (e) {
			toast(e.message, 'error');
			return;
		}

		const item = (label, detail) => el('li', {}, el('strong', {}, label), ' ', detail);
		const change = (from, to) => (from === to ? to : sprintf(
			/* translators: 1: old value, 2: new value */
			__('"%1$s" becomes "%2$s"', 'my-site-hand'), from || __('(empty)', 'my-site-hand'), to));

		const body = el('div', {},
			el('p', {}, sprintf(
				/* translators: %s: theme name */
				__('Applying makes these changes to your live site, using your %s theme:', 'my-site-hand'), plan.theme)),
			el('ul', { class: 'msh-sb-plan' },
				item(__('Pages published:', 'my-site-hand'), plan.pages.join(', ')),
				item(__('Front page:', 'my-site-hand'), plan.front_page),
				item(__('Site title:', 'my-site-hand'), change(plan.site_title.from, plan.site_title.to)),
				item(__('Tagline:', 'my-site-hand'), change(plan.tagline.from, plan.tagline.to)),
				item(__('Header and footer:', 'my-site-hand'), __('replaced, saved as your theme customizations', 'my-site-hand')),
				item(__('Global styles:', 'my-site-hand'), __('colours, fonts and spacing replaced', 'my-site-hand')),
				item(__('Menu:', 'my-site-hand'), plan.menu_items.join(', ')),
				plan.images ? item(__('Media library:', 'my-site-hand'), sprintf(
					/* translators: %d: number of images */
					_n('up to %d image added', 'up to %d images added', plan.images, 'my-site-hand'), plan.images)) : null),
			plan.replaces ? el('p', {}, sprintf(
				/* translators: 1: number of pages, 2: starter site name, 3: date */
				_n('The %1$d page from your current %2$s site (applied on %3$s) goes to the trash, and the new version takes its web address. Undo brings it back.', 'The %1$d pages from your current %2$s site (applied on %3$s) go to the trash, and the new versions take their web addresses. Undo brings them back.', plan.replaces.pages, 'my-site-hand'),
				plan.replaces.pages, plan.replaces.name, plan.replaces.date)) : null,
			plan.replaces && plan.replaces.edited.length ? el('p', { class: 'msh-sb-warn' }, sprintf(
				/* translators: %s: list of page titles */
				__('These pages were changed outside the Site Builder after applying: %s. Those changes go to the trash with the old pages. Copy anything you want to keep first.', 'my-site-hand'),
				plan.replaces.edited.join(', '))) : null,
			plan.existing_slugs.length ? el('p', { class: 'msh-sb-warn' }, __('You already have pages with some of these names. Yours are kept, and the new pages get a number added to their web address.', 'my-site-hand')) : null,
			el('p', {}, __('A snapshot is taken first, so you can undo all of this from Site history at any time. Your existing posts and pages are not changed.', 'my-site-hand')));

		const ok = await modal({
			title: plan.replaces
				? __('Update your site?', 'my-site-hand')
				: sprintf(
					/* translators: %s: starter site name */
					__('Apply %s to your site?', 'my-site-hand'), plan.name),
			body,
			wide: false,
			actions: [
				{ label: __('Cancel', 'my-site-hand'), value: false },
				{ label: plan.replaces ? __('Yes, update my site', 'my-site-hand') : __('Yes, apply to my site', 'my-site-hand'), value: true, class: 'msh-btn--primary' },
			],
		});
		if (!ok) {
			return;
		}

		const busy = el('div', { class: 'msh-sb-overlay' }, el('div', { class: 'msh-sb-modal', role: 'alert' }, el('h2', {}, __('Applying...', 'my-site-hand')), el('p', {}, __('Publishing pages and saving your header, footer and styles. This takes a few seconds.', 'my-site-hand'))));
		document.body.append(busy);
		try {
			const res = await api('POST', 'apply', { confirm: true });
			busy.remove();
			S.staged = null;
			S.live = res.live || null;
			S.details = {};
			setView('gallery');
			const done = await modal({
				title: plan.replaces ? __('Your site is updated', 'my-site-hand') : __('Your new site is live', 'my-site-hand'),
				body: el('div', {},
					el('p', {}, __('Everything is ordinary WordPress content now. You can keep editing it in the Site Editor, with or without this plugin.', 'my-site-hand')),
					el('p', {}, __('To change it again, press Customize my site at the top of this page. Changed your mind? Use Undo in Site history below.', 'my-site-hand'))),
				actions: [
					{ label: __('Close', 'my-site-hand'), value: null },
					{ label: __('Edit in Site Editor', 'my-site-hand'), value: 'editor' },
					{ label: __('View my site', 'my-site-hand'), value: 'view', class: 'msh-btn--primary' },
				],
			});
			if ('view' === done) {
				window.open(res.snapshot.front_url || '/', '_blank', 'noopener');
			} else if ('editor' === done) {
				window.location.href = D.siteEditorUrl;
			}
		} catch (e) {
			busy.remove();
			modal({
				title: __('Nothing was changed', 'my-site-hand'),
				body: el('p', {}, e.message),
				actions: [{ label: __('OK', 'my-site-hand'), value: null, class: 'msh-btn--primary' }],
			});
		}
	}

	const themeButton = document.getElementById('msh-sb-install-theme');
	if (themeButton) {
		themeButton.addEventListener('click', async () => {
			const ok = await confirmDialog(
				__('Switch to Twenty Twenty-Five?', 'my-site-hand'),
				__('This downloads Twenty Twenty-Five from WordPress.org if needed and makes it your active theme. Your posts, pages and media stay as they are. Menus and widgets made for your current theme may need setting up again. You can switch back under Appearance > Themes.', 'my-site-hand'),
				__('Yes, switch theme', 'my-site-hand')
			);
			if (!ok) {
				return;
			}
			themeButton.disabled = true;
			themeButton.textContent = __('Installing...', 'my-site-hand');
			try {
				await api('POST', 'theme', { confirm: true });
				window.location.reload();
			} catch (e) {
				themeButton.disabled = false;
				themeButton.textContent = __('Try again', 'my-site-hand');
				toast(e.message, 'error');
			}
		});
	}

	// Shared with the preview, apply and AI layers.
	window.mshSiteBuilder = { S, D, el, api, toast, modal, confirmDialog, flush, setStatus, openEditor, openPreview, startApply, setView, renderGallery, siteSummary, field, value, change };

	route();
})();
