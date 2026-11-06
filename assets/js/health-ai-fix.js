/**
 * AI Bulk Fix review panel.
 *
 * Only enqueued when an API key is configured. With no key this file is never
 * loaded, and the Site Health page is exactly what it was before AI existed.
 *
 * The flow, and the rules it keeps:
 *
 * 1. Show the estimated cost BEFORE anything is generated.
 * 2. Generate proposals one batch per request, driven from here, like the
 *    scan — never one long PHP request that shared hosting would kill.
 * 3. Show every proposal. Nothing is written until Apply is pressed, and
 *    there is no "skip review": one bad unattended result would cost the
 *    user's trust in the whole plugin, with no undo.
 * 4. Apply approved items in batches, report each item's own outcome, and
 *    update the issue list and the score in place, exactly like Quick Fix.
 * 5. Closing the panel discards whatever was not applied.
 *
 * @package MySiteHand
 */

(function () {
	'use strict';

	var cfg = window.mshAiFix || {};
	var root = document.getElementById('msh-health');

	var wpI18n = (window.wp && window.wp.i18n) || {};

	var __ = wpI18n.__ || function (text) {
		return text;
	};

	var _n = wpI18n._n || function (single, plural, number) {
		return 1 === number ? single : plural;
	};

	var sprintf = wpI18n.sprintf || function (format) {
		return format;
	};

	if (!root || !cfg.restUrl || !cfg.checks) {
		return;
	}

	// wp_localize_script() turns every top-level scalar into a string, so
	// numbers are coerced here once rather than trusted everywhere.
	var GENERATE_BATCH = parseInt(cfg.generateBatch, 10) || 10;
	var APPLY_BATCH = parseInt(cfg.applyBatch, 10) || 20;
	var TODAY_SPEND = parseFloat(cfg.todaySpend) || 0;
	var DAILY_CAP = parseFloat(cfg.dailyCap) || 0;

	// One panel at a time: the server keeps a single proposal set per user,
	// so opening a second check's panel would silently replace the first.
	var active = null;

	// ---------------------------------------------------------------------
	// Transport
	// ---------------------------------------------------------------------

	function request(path, options) {
		var opts = options || {};

		return fetch(cfg.restUrl + path, {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: opts.body ? JSON.stringify(opts.body) : undefined
		}).then(function (response) {
			return response.json().catch(function () {
				return {};
			}).then(function (data) {
				if (!response.ok) {
					var err = new Error((data && data.message) || '');
					err.code = (data && data.code) || '';
					err.status = response.status;
					throw err;
				}
				return data;
			});
		});
	}

	/**
	 * The message for an error, always one that says what to do next.
	 *
	 * The server's messages already do (it knows whether the key was rejected
	 * or the cap was reached), so they are preferred. The fallback is only for
	 * a request that never produced a response at all.
	 */
	function errorMessage(error) {
		if (error && error.message) {
			return error.message;
		}

		return __('The connection to your site was lost. Check your internet connection, then try again.', 'my-site-hand');
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	function make(tag, className, text) {
		var node = document.createElement(tag);

		if (className) {
			node.className = className;
		}

		if (typeof text === 'string') {
			node.textContent = text;
		}

		return node;
	}

	function button(label, className) {
		var node = make('button', 'msh-btn msh-btn--sm' + (className ? ' ' + className : ''), label);
		node.type = 'button';
		return node;
	}

	/** Mirrors AI_Manager::format_usd(). */
	function usd(amount) {
		amount = Number(amount) || 0;

		if (amount > 0 && amount < 0.01) {
			return '$' + amount.toFixed(4);
		}

		return '$' + amount.toFixed(2);
	}

	function openRows(checkId) {
		return Array.prototype.filter.call(
			root.querySelectorAll('.msh-hissue[data-check="' + checkId + '"]'),
			function (li) {
				return li.getAttribute('data-object-id') && !li.classList.contains('msh-hissue--fixed');
			}
		);
	}

	function issueRow(checkId, objectId) {
		return root.querySelector('.msh-hissue[data-check="' + checkId + '"][data-object-id="' + objectId + '"]');
	}

	function chunk(list, size) {
		var out = [];

		for (var i = 0; i < list.length; i += size) {
			out.push(list.slice(i, i + size));
		}

		return out;
	}

	// ---------------------------------------------------------------------
	// Entry button: keeps its count in step with the issue list, which the
	// scan and Quick Fix both change underneath it.
	// ---------------------------------------------------------------------

	function refreshEntry(wrap) {
		var checkId = wrap.getAttribute('data-check');
		var count = openRows(checkId).length;
		var label = wrap.querySelector('.msh-aifix-start-label');

		/* translators: %d: number of issues AI will write fixes for */
		var text = sprintf(_n('Fix %d with AI', 'Fix all %d with AI', count, 'my-site-hand'), count);

		// Only write on a real change. This runs from a MutationObserver, and
		// an unconditional write is itself a mutation: it would loop forever.
		if (label && label.textContent !== text) {
			label.textContent = text;
		}

		// Never hide a panel the user is working in.
		var hide = count === 0 && !(active && active.wrap === wrap);

		if (wrap.hidden !== hide) {
			wrap.hidden = hide;
		}
	}

	function refreshAllEntries() {
		root.querySelectorAll('.msh-aifix').forEach(refreshEntry);
	}

	// ---------------------------------------------------------------------
	// Panel
	// ---------------------------------------------------------------------

	function openPanel(wrap) {
		if (active && active.wrap === wrap) {
			return;
		}

		if (active) {
			closePanel(active);
		}

		var checkId = wrap.getAttribute('data-check');
		var check = cfg.checks[checkId] || {};
		var rows = openRows(checkId);

		var panel = {
			wrap: wrap,
			checkId: checkId,
			check: check,
			host: wrap.querySelector('.msh-aifix-panel'),
			start: wrap.querySelector('.msh-aifix-start'),
			ids: rows.map(function (li) {
				return parseInt(li.getAttribute('data-object-id'), 10);
			}),
			items: {},
			order: [],
			cost: 0,
			generating: false,
			applying: false,
			stopped: false,
			node: {}
		};

		active = panel;
		buildPanel(panel);

		panel.host.hidden = false;
		panel.start.setAttribute('aria-expanded', 'true');
		panel.start.disabled = true;
	}

	function closePanel(panel) {
		panel.stopped = true;

		// Closing discards. Anything already applied stays applied; that was
		// a deliberate, separate action.
		request('ai/discard', { method: 'POST' }).catch(function () {
			// Proposals expire on their own after thirty minutes.
		});

		panel.host.innerHTML = '';
		panel.host.hidden = true;
		panel.start.setAttribute('aria-expanded', 'false');
		panel.start.disabled = false;

		if (active === panel) {
			active = null;
		}

		refreshEntry(panel.wrap);
		panel.start.focus();
	}

	function buildPanel(panel) {
		var host = panel.host;
		var n = panel.ids.length;
		var estimate = n * (Number(panel.check.costPerItem) || 0);

		host.innerHTML = '';
		host.setAttribute('role', 'region');
		host.setAttribute('aria-label', __('AI suggestions', 'my-site-hand'));

		var head = make('div', 'msh-aifix-head');
		var title = make('h3', 'msh-aifix-title', sprintf(
			/* translators: %d: number of items */
			_n('Write a suggestion for %d item', 'Write suggestions for %d items', n, 'my-site-hand'),
			n
		));
		head.appendChild(title);

		var close = button(__('Close', 'my-site-hand'), 'msh-btn--ghost msh-aifix-close');
		close.addEventListener('click', function () {
			closePanel(panel);
		});
		head.appendChild(close);
		host.appendChild(head);

		// Before generating: what it costs, and that nothing changes yet.
		var intro = make('div', 'msh-aifix-intro');

		intro.appendChild(make('p', 'msh-aifix-estimate', sprintf(
			/* translators: 1: estimated cost, e.g. $0.03. 2: AI provider and model name. */
			__('Estimated cost: about %1$s, billed to your own account with %2$s.', 'my-site-hand'),
			usd(estimate),
			cfg.providerLabel || ''
		)));

		intro.appendChild(make('p', 'msh-aifix-capnote', sprintf(
			/* translators: 1: amount spent today, 2: daily spend cap */
			__('Spent today so far: %1$s of your %2$s daily cap.', 'my-site-hand'),
			usd(TODAY_SPEND),
			usd(DAILY_CAP)
		)));

		intro.appendChild(make('p', 'msh-aifix-safe', __('Nothing on your site changes until you review the suggestions and press Apply.', 'my-site-hand')));

		var go = button(__('Write suggestions', 'my-site-hand'), 'msh-btn--primary');
		go.addEventListener('click', function () {
			intro.hidden = true;
			generate(panel);
		});
		intro.appendChild(go);
		host.appendChild(intro);

		// Progress, announced to screen readers as it moves.
		var progress = make('div', 'msh-aifix-progress');
		progress.hidden = true;

		var bar = make('div', 'msh-health-progressbar');
		bar.setAttribute('role', 'progressbar');
		bar.setAttribute('aria-valuemin', '0');
		bar.setAttribute('aria-valuemax', '100');
		bar.setAttribute('aria-valuenow', '0');
		bar.setAttribute('aria-label', __('Progress', 'my-site-hand'));

		var fill = make('span', 'msh-health-progressfill');
		bar.appendChild(fill);
		progress.appendChild(bar);

		var progressRow = make('div', 'msh-aifix-progressrow');
		var status = make('span', 'msh-aifix-status');
		status.setAttribute('role', 'status');
		status.setAttribute('aria-live', 'polite');
		progressRow.appendChild(status);

		var stop = button(__('Stop', 'my-site-hand'), 'msh-btn--ghost');
		stop.addEventListener('click', function () {
			panel.stopped = true;
			stop.disabled = true;
		});
		progressRow.appendChild(stop);
		progress.appendChild(progressRow);
		host.appendChild(progress);

		var error = make('p', 'msh-aifix-error');
		error.setAttribute('role', 'alert');
		error.hidden = true;
		host.appendChild(error);

		var notice = make('p', 'msh-aifix-notice');
		notice.hidden = true;
		host.appendChild(notice);

		// Review.
		var review = make('div', 'msh-aifix-review');
		review.hidden = true;

		var bulk = make('div', 'msh-aifix-bulk');
		var approveAll = button(__('Approve all', 'my-site-hand'));
		var rejectAll = button(__('Reject all', 'my-site-hand'), 'msh-btn--ghost');
		approveAll.addEventListener('click', function () {
			setAll(panel, 'approved');
		});
		rejectAll.addEventListener('click', function () {
			setAll(panel, 'rejected');
		});
		bulk.appendChild(approveAll);
		bulk.appendChild(rejectAll);
		bulk.appendChild(make('span', 'msh-aifix-keyhint', __('Tip: select a row and press Enter to approve it.', 'my-site-hand')));
		review.appendChild(bulk);

		var list = make('ul', 'msh-aifix-list');
		list.setAttribute('aria-label', __('Suggestions', 'my-site-hand'));
		review.appendChild(list);

		var foot = make('div', 'msh-aifix-foot');
		var tally = make('p', 'msh-aifix-tally');
		var spend = make('p', 'msh-aifix-spend');
		var apply = button('', 'msh-btn--primary msh-aifix-apply');
		apply.addEventListener('click', function () {
			applyApproved(panel);
		});
		foot.appendChild(tally);
		foot.appendChild(spend);
		foot.appendChild(apply);
		review.appendChild(foot);
		host.appendChild(review);

		panel.node = {
			intro: intro,
			progress: progress,
			fill: fill,
			bar: bar,
			status: status,
			stop: stop,
			error: error,
			notice: notice,
			review: review,
			list: list,
			tally: tally,
			spend: spend,
			apply: apply,
			approveAll: approveAll,
			rejectAll: rejectAll,
			estimate: estimate
		};

		go.focus();
	}

	function setProgress(panel, done, total, label) {
		var pct = total ? Math.round((done / total) * 100) : 0;

		panel.node.fill.style.width = pct + '%';
		panel.node.bar.setAttribute('aria-valuenow', String(pct));
		panel.node.status.textContent = label;
	}

	function showError(panel, message) {
		panel.node.error.textContent = message;
		panel.node.error.hidden = false;
	}

	function addNotice(panel, message) {
		var notice = panel.node.notice;

		notice.textContent = notice.textContent ? notice.textContent + ' ' + message : message;
		notice.hidden = false;
	}

	// ---------------------------------------------------------------------
	// Generate
	// ---------------------------------------------------------------------

	function generate(panel) {
		var batches = chunk(panel.ids, GENERATE_BATCH);
		var total = panel.ids.length;
		var done = 0;
		var discarded = 0;
		var networkFailures = 0;

		panel.generating = true;
		panel.node.progress.hidden = false;
		panel.node.review.hidden = false;
		panel.node.error.hidden = true;
		renderTally(panel);

		setProgress(panel, 0, total, sprintf(
			/* translators: 1: items done, 2: total items */
			__('Writing suggestions: %1$d of %2$d', 'my-site-hand'),
			0,
			total
		));

		function runBatch(index, attempt) {
			if (panel.stopped || index >= batches.length) {
				return Promise.resolve();
			}

			var ids = batches[index];

			return request('ai/generate', {
				method: 'POST',
				body: { check: panel.checkId, object_ids: ids, attempt: attempt }
			}).then(function (data) {
				networkFailures = 0;
				panel.cost += data.cost || 0;

				// The reply could not be read: one retry with a stricter
				// instruction, then that batch is marked failed and the
				// run carries on.
				if (data.batch_status === 'retry' && attempt === 1) {
					return runBatch(index, 2);
				}

				(data.items || []).forEach(function (item) {
					addItem(panel, item);
				});

				discarded += data.discarded || 0;

				if (data.batch_status === 'failed' && data.message) {
					addNotice(panel, data.message);
				}

				done += ids.length;
				setProgress(panel, done, total, sprintf(
					/* translators: 1: items done, 2: total items */
					__('Writing suggestions: %1$d of %2$d', 'my-site-hand'),
					done,
					total
				));

				return runBatch(index + 1, 1);
			}).catch(function (error) {
				// An error the server explained (bad key, spend cap, rate
				// limit) would only repeat on the next batch. Stop, keep what
				// was already generated reviewable, and say what to do.
				if (error && error.status) {
					showError(panel, errorMessage(error));
					return null;
				}

				// No response at all. One blip should not end the run; two in
				// a row should.
				networkFailures += 1;

				if (networkFailures >= 2) {
					showError(panel, errorMessage(error));
					return null;
				}

				return runBatch(index, attempt);
			});
		}

		runBatch(0, 1).then(function () {
			panel.generating = false;
			panel.node.stop.hidden = true;

			var finished = sprintf(
				/* translators: %d: number of suggestions written */
				_n('%d suggestion ready to review.', '%d suggestions ready to review.', panel.order.length, 'my-site-hand'),
				panel.order.length
			);

			if (panel.stopped && done < total) {
				finished = __('Stopped. The suggestions written so far are below.', 'my-site-hand');
			}

			setProgress(panel, total, total, finished);

			if (discarded > 0) {
				addNotice(panel, sprintf(
					/* translators: %d: number of suggestions discarded */
					_n(
						'%d suggestion did not meet the length rules and was discarded. Those items are marked below.',
						'%d suggestions did not meet the length rules and were discarded. Those items are marked below.',
						discarded,
						'my-site-hand'
					),
					discarded
				));
			}

			// The list can be long; land keyboard users at its top.
			var first = panel.node.list.querySelector('.msh-aifix-item');

			if (first) {
				first.focus();
			}

			renderTally(panel);
		});
	}

	// ---------------------------------------------------------------------
	// Review rows
	// ---------------------------------------------------------------------

	function addItem(panel, data) {
		var id = data.object_id;

		// A row rendered earlier in this run is updated, not duplicated.
		var existing = panel.items[id];

		var item = {
			id: id,
			label: data.label || ('#' + id),
			detail: data.detail || '',
			current: data.current || '',
			proposed: data.proposed || '',
			edited: null,
			error: data.error || '',
			// 'manual' covers both an empty suggestion ("needs your input")
			// and one that failed: either way, the user writes it.
			state: data.status === 'failed' ? 'failed' : (data.needs_input ? 'manual' : 'pending'),
			node: existing ? existing.node : null
		};

		panel.items[id] = item;

		if (!existing) {
			panel.order.push(id);
		}

		renderItem(panel, item);
		renderTally(panel);
	}

	function renderItem(panel, item) {
		var li = item.node;

		if (!li) {
			li = make('li', 'msh-aifix-item');
			li.tabIndex = 0;
			li.setAttribute('data-object-id', String(item.id));

			// Enter on the row itself approves it. On a button or an input,
			// Enter keeps its usual meaning.
			li.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' && event.target === li && item.state !== 'manual' && item.state !== 'failed') {
					event.preventDefault();
					setState(panel, item, 'approved');
				}
			});

			item.node = li;
			panel.node.list.appendChild(li);
		}

		li.innerHTML = '';
		li.setAttribute('data-state', item.state);

		var name = make('div', 'msh-aifix-name');
		name.appendChild(make('strong', '', item.label));

		if (item.detail) {
			name.appendChild(make('span', 'msh-aifix-detail', item.detail));
		}

		li.appendChild(name);

		var now = make('div', 'msh-aifix-line');
		now.appendChild(make('span', 'msh-aifix-key', __('Now:', 'my-site-hand')));
		now.appendChild(make('span', 'msh-aifix-now', item.current || __('(empty)', 'my-site-hand')));
		li.appendChild(now);

		var label = item.label;

		if (item.state === 'manual' || item.state === 'failed') {
			renderManual(panel, item, li);
		} else if (item.state === 'editing') {
			renderEditor(panel, item, li);
		} else {
			var proposed = make('div', 'msh-aifix-line');
			proposed.appendChild(make('span', 'msh-aifix-key', item.edited !== null
				? __('Your edit:', 'my-site-hand')
				: __('Proposed:', 'my-site-hand')));
			proposed.appendChild(make('span', 'msh-aifix-proposed', valueOf(item)));
			li.appendChild(proposed);

			if (item.state === 'applied') {
				li.appendChild(make('p', 'msh-aifix-done', __('Saved to your site.', 'my-site-hand')));
			} else if (item.state === 'error') {
				var err = make('p', 'msh-aifix-rowerror', item.error);
				err.setAttribute('role', 'alert');
				li.appendChild(err);
			}

			if (item.state !== 'applied') {
				var actions = make('div', 'msh-aifix-actions');

				var approve = button('✓ ' + __('Approve', 'my-site-hand'), item.state === 'approved' ? 'msh-btn--primary' : '');
				approve.setAttribute('aria-pressed', item.state === 'approved' ? 'true' : 'false');
				approve.setAttribute('aria-label', sprintf(
					/* translators: %s: item name */
					__('Approve suggestion for %s', 'my-site-hand'),
					label
				));
				approve.addEventListener('click', function () {
					setState(panel, item, item.state === 'approved' ? 'pending' : 'approved');
				});

				var reject = button('✗ ' + __('Reject', 'my-site-hand'), 'msh-btn--ghost');
				reject.setAttribute('aria-pressed', item.state === 'rejected' ? 'true' : 'false');
				reject.setAttribute('aria-label', sprintf(
					/* translators: %s: item name */
					__('Reject suggestion for %s', 'my-site-hand'),
					label
				));
				reject.addEventListener('click', function () {
					setState(panel, item, item.state === 'rejected' ? 'pending' : 'rejected');
				});

				var edit = button('✎ ' + __('Edit', 'my-site-hand'), 'msh-btn--ghost');
				edit.setAttribute('aria-label', sprintf(
					/* translators: %s: item name */
					__('Edit suggestion for %s', 'my-site-hand'),
					label
				));
				edit.addEventListener('click', function () {
					item.previous = item.state;
					setState(panel, item, 'editing');
				});

				[approve, reject, edit].forEach(function (control) {
					control.disabled = panel.applying;
					actions.appendChild(control);
				});

				li.appendChild(actions);
			}
		}
	}

	function valueOf(item) {
		return item.edited !== null ? item.edited : item.proposed;
	}

	/** Character counter that turns red past the limit. */
	function counter(input, max) {
		var count = make('span', 'msh-aifix-count');

		function update() {
			var length = input.value.length;

			count.textContent = sprintf(
				/* translators: 1: characters typed, 2: recommended maximum */
				__('%1$d / %2$d characters', 'my-site-hand'),
				length,
				max
			);
			count.classList.toggle('msh-aifix-count--over', length > max);
		}

		input.addEventListener('input', update);
		update();

		return count;
	}

	function renderEditor(panel, item, li) {
		var max = parseInt(panel.check.maxLength, 10) || 160;
		var input = make(panel.check.kind === 'meta' ? 'textarea' : 'input', 'msh-hissue-input msh-aifix-input');

		if (input.tagName === 'INPUT') {
			input.type = 'text';
		} else {
			input.rows = 3;
		}

		input.value = valueOf(item);
		input.setAttribute('aria-label', sprintf(
			/* translators: %s: item name */
			__('Edit suggestion for %s', 'my-site-hand'),
			item.label
		));

		var save = button(__('Use this text', 'my-site-hand'), 'msh-btn--primary');
		var cancel = button(__('Cancel', 'my-site-hand'), 'msh-btn--ghost');

		function commit() {
			var value = input.value.trim();

			if ('' === value) {
				input.focus();
				return;
			}

			// Edited text is what gets applied, never the original proposal.
			item.edited = value === item.proposed ? null : value;
			setState(panel, item, 'approved');
		}

		save.addEventListener('click', commit);
		cancel.addEventListener('click', function () {
			setState(panel, item, item.previous || 'pending');
		});

		input.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				setState(panel, item, item.previous || 'pending');
			} else if (event.key === 'Enter' && input.tagName === 'INPUT') {
				event.preventDefault();
				commit();
			}
		});

		var wrap = make('div', 'msh-aifix-editor');
		wrap.appendChild(input);
		wrap.appendChild(counter(input, max));

		var actions = make('div', 'msh-aifix-actions');
		actions.appendChild(save);
		actions.appendChild(cancel);
		wrap.appendChild(actions);
		li.appendChild(wrap);

		window.setTimeout(function () {
			input.focus();
		}, 0);
	}

	/**
	 * An item the AI could not write: the user types it, and it is saved
	 * through Quick Fix itself, exactly as if typed into the issue row.
	 */
	function renderManual(panel, item, li) {
		var max = parseInt(panel.check.maxLength, 10) || 160;

		li.appendChild(make('p', 'msh-aifix-manualnote', item.state === 'failed' && item.error
			? item.error
			: __('Needs your input: there was not enough information to write this honestly.', 'my-site-hand')));

		var row = issueRow(panel.checkId, item.id);

		if (!row) {
			return;
		}

		var input = make('input', 'msh-hissue-input msh-aifix-input');
		input.type = 'text';
		input.placeholder = panel.check.kind === 'meta'
			? __('Write a short description…', 'my-site-hand')
			: __('Describe this image…', 'my-site-hand');
		input.setAttribute('aria-label', sprintf(
			/* translators: 1: the prompt or action. 2: the item it applies to. */
			__('%1$s — %2$s', 'my-site-hand'),
			input.placeholder,
			item.label
		));

		var save = button(__('Save', 'my-site-hand'));
		var note = make('span', 'msh-aifix-manualresult');
		note.setAttribute('role', 'status');

		function commit() {
			var value = input.value.trim();

			if ('' === value || panel.applying) {
				return;
			}

			save.disabled = true;
			input.disabled = true;
			note.textContent = __('Saving…', 'my-site-hand');

			var meta = {};

			try {
				meta = JSON.parse(row.getAttribute('data-fix-meta') || '{}');
			} catch (e) {
				meta = {};
			}

			request('fix', {
				method: 'POST',
				body: {
					check: panel.checkId,
					fix_type: row.getAttribute('data-fix-type'),
					fix_meta: meta,
					value: value
				}
			}).then(function (data) {
				item.edited = data.value || value;
				item.state = 'applied';
				resolveRow(panel.checkId, item.id, item.edited, __('Saved', 'my-site-hand'));
				renderItem(panel, item);
				renderTally(panel);
			}).catch(function (error) {
				note.textContent = errorMessage(error);
				save.disabled = false;
				input.disabled = false;
				input.focus();
			});
		}

		save.addEventListener('click', commit);
		input.addEventListener('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				commit();
			}
		});

		var wrap = make('div', 'msh-aifix-editor');
		wrap.appendChild(input);
		wrap.appendChild(counter(input, max));

		var actions = make('div', 'msh-aifix-actions');
		actions.appendChild(save);
		actions.appendChild(note);
		wrap.appendChild(actions);
		li.appendChild(wrap);
	}

	function setState(panel, item, state) {
		if (panel.applying || item.state === 'applied') {
			return;
		}

		var hadFocus = item.node && item.node.contains(document.activeElement);

		item.state = state;
		renderItem(panel, item);
		renderTally(panel);

		// Re-rendering replaces the buttons; keep keyboard users in the row.
		if (hadFocus && state !== 'editing') {
			item.node.focus();
		}
	}

	function setAll(panel, state) {
		panel.order.forEach(function (id) {
			var item = panel.items[id];

			if (item.state === 'pending' || item.state === 'approved' || item.state === 'rejected' || item.state === 'error') {
				item.state = state;
				renderItem(panel, item);
			}
		});

		renderTally(panel);
	}

	function count(panel, state) {
		return panel.order.filter(function (id) {
			return panel.items[id].state === state;
		}).length;
	}

	function renderTally(panel) {
		var approved = count(panel, 'approved');
		var parts = [
			/* translators: %d: number of approved suggestions */
			sprintf(__('%d approved', 'my-site-hand'), approved),
			/* translators: %d: number of rejected suggestions */
			sprintf(__('%d rejected', 'my-site-hand'), count(panel, 'rejected')),
			/* translators: %d: number of suggestions not yet reviewed */
			sprintf(__('%d pending', 'my-site-hand'), count(panel, 'pending') + count(panel, 'editing') + count(panel, 'error'))
		];

		var manual = count(panel, 'manual') + count(panel, 'failed');
		var applied = count(panel, 'applied');

		if (manual) {
			/* translators: %d: number of items that need typing in by hand */
			parts.push(sprintf(__('%d need your input', 'my-site-hand'), manual));
		}

		if (applied) {
			/* translators: %d: number of items saved */
			parts.push(sprintf(__('%d saved', 'my-site-hand'), applied));
		}

		panel.node.tally.textContent = parts.join(' · ');

		panel.node.spend.textContent = panel.generating || panel.order.length === 0
			? sprintf(
				/* translators: %s: estimated cost, e.g. $0.03 */
				__('Estimated cost: about %s', 'my-site-hand'),
				usd(panel.node.estimate)
			)
			: sprintf(
				/* translators: 1: actual cost, 2: the estimate shown beforehand */
				__('Actual cost: %1$s (estimated %2$s)', 'my-site-hand'),
				usd(panel.cost),
				usd(panel.node.estimate)
			);

		panel.node.apply.textContent = sprintf(
			/* translators: %d: number of approved suggestions */
			_n('Apply %d approved', 'Apply %d approved', approved, 'my-site-hand'),
			approved
		);
		panel.node.apply.disabled = approved === 0 || panel.generating || panel.applying;
		panel.node.approveAll.disabled = panel.generating || panel.applying;
		panel.node.rejectAll.disabled = panel.generating || panel.applying;
	}

	// ---------------------------------------------------------------------
	// Apply
	// ---------------------------------------------------------------------

	/**
	 * Mark an issue row in the main list as fixed, and move the score —
	 * the same visible result a Quick Fix produces.
	 */
	function resolveRow(checkId, objectId, value, noteText) {
		var li = issueRow(checkId, objectId);

		if (!li || li.classList.contains('msh-hissue--fixed')) {
			return;
		}

		li.classList.add('msh-hissue--fixed');
		li.setAttribute('data-fix-state', 'saved');

		var input = li.querySelector('.msh-hissue-fix input');

		if (input) {
			input.value = value;
			input.disabled = true;
		}

		li.querySelectorAll('button').forEach(function (control) {
			control.disabled = true;
		});

		var host = li.querySelector('.msh-hissue-fix');
		var note = li.querySelector('.msh-hissue-note');

		if (!note && host) {
			note = make('span', 'msh-hissue-note');
			host.appendChild(note);
		}

		if (note) {
			note.textContent = noteText;
			note.setAttribute('role', 'status');
		}

		if (window.mshHealthScore && window.mshHealthScore.removeIssue) {
			window.mshHealthScore.removeIssue(checkId, li.getAttribute('data-severity'));
		}
	}

	function applyApproved(panel) {
		var ids = panel.order.filter(function (id) {
			return panel.items[id].state === 'approved';
		});

		if (!ids.length || panel.applying) {
			return;
		}

		var batches = chunk(ids, APPLY_BATCH);
		var done = 0;
		var applied = 0;
		var failed = 0;

		panel.applying = true;
		panel.node.error.hidden = true;
		panel.node.progress.hidden = false;
		panel.node.stop.hidden = true;
		renderTally(panel);
		panel.order.forEach(function (id) {
			renderItem(panel, panel.items[id]);
		});

		setProgress(panel, 0, ids.length, sprintf(
			/* translators: 1: items saved, 2: total items */
			__('Saving: %1$d of %2$d', 'my-site-hand'),
			0,
			ids.length
		));

		function runBatch(index) {
			if (index >= batches.length) {
				return Promise.resolve();
			}

			var batch = batches[index];
			var values = {};

			batch.forEach(function (id) {
				if (panel.items[id].edited !== null) {
					values[id] = panel.items[id].edited;
				}
			});

			return request('ai/apply', {
				method: 'POST',
				body: { object_ids: batch, values: values }
			}).then(function (data) {
				var results = data.results || {};

				batch.forEach(function (id) {
					var item = panel.items[id];
					var result = results[id];

					if (result && result.status === 'applied') {
						item.state = 'applied';
						item.edited = result.value;
						resolveRow(panel.checkId, id, result.value, __('Fixed with AI', 'my-site-hand'));
						applied += 1;
					} else {
						// Per-row failure; the rest of the batch still applied.
						item.state = 'error';
						item.error = (result && result.error) || __('This item could not be saved. Try it again, or fix it by hand.', 'my-site-hand');
						failed += 1;
					}
				});

				done += batch.length;
				setProgress(panel, done, ids.length, sprintf(
					/* translators: 1: items saved, 2: total items */
					__('Saving: %1$d of %2$d', 'my-site-hand'),
					done,
					ids.length
				));

				return runBatch(index + 1);
			}).catch(function (error) {
				batch.forEach(function (id) {
					panel.items[id].state = 'error';
					panel.items[id].error = errorMessage(error);
				});
				failed += batch.length;
				showError(panel, errorMessage(error));
				return null;
			});
		}

		runBatch(0).then(function () {
			panel.applying = false;

			panel.order.forEach(function (id) {
				renderItem(panel, panel.items[id]);
			});

			var summary = failed
				? sprintf(
					/* translators: 1: number saved, 2: number that failed */
					__('%1$d saved, %2$d could not be saved. The failed rows say why.', 'my-site-hand'),
					applied,
					failed
				)
				: sprintf(
					/* translators: %d: number of items saved */
					_n('%d fix saved to your site.', '%d fixes saved to your site.', applied, 'my-site-hand'),
					applied
				);

			setProgress(panel, ids.length, ids.length, summary);
			renderTally(panel);
			refreshEntry(panel.wrap);
		});
	}

	// ---------------------------------------------------------------------
	// Wiring
	// ---------------------------------------------------------------------

	root.addEventListener('click', function (event) {
		var start = event.target.closest('.msh-aifix-start');

		if (start) {
			openPanel(start.closest('.msh-aifix'));
		}
	});

	// The scan re-renders issue rows and Quick Fix resolves them one by one;
	// keep each "Fix all N" count true without either of them knowing this
	// file exists.
	var checks = document.getElementById('msh-health-checks');

	if (checks && window.MutationObserver) {
		var pending = null;

		new window.MutationObserver(function () {
			if (pending) {
				return;
			}

			pending = window.setTimeout(function () {
				pending = null;
				refreshAllEntries();
			}, 150);
		}).observe(checks, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
	}

	refreshAllEntries();
})();
