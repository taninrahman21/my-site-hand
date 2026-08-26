/**
 * Site Health scan driver.
 *
 * Runs the scan from the browser, one check at a time, in separate requests.
 * Checks run strictly sequentially: firing them in parallel would multiply
 * server load and make the timeouts we are avoiding more likely, not less.
 *
 * Nothing is persisted in the browser. Server-side run state is the source of
 * truth, so reloading mid-scan is safe.
 *
 * @package MySiteHand
 */

(function () {
	'use strict';

	var cfg = window.mshHealth || {};
	var root = document.getElementById('msh-health');

	if (!root || !cfg.restUrl) {
		return;
	}

	var el = {
		empty: document.getElementById('msh-health-empty'),
		summary: document.getElementById('msh-health-summary'),
		progress: document.getElementById('msh-health-progress'),
		progressBar: document.getElementById('msh-health-progressbar'),
		progressFill: document.getElementById('msh-health-progressfill'),
		progressLabel: document.getElementById('msh-health-progresslabel'),
		cancel: document.getElementById('msh-health-cancel'),
		error: document.getElementById('msh-health-error'),
		checks: document.getElementById('msh-health-checks'),
		scoreBox: document.getElementById('msh-health-scorebox'),
		scoreNum: document.getElementById('msh-health-scorenum'),
		bandLabel: document.getElementById('msh-health-bandlabel'),
		meta: document.getElementById('msh-health-meta')
	};

	var state = {
		running: false,
		cancelled: false,
		checks: [],
		// Per-check severity and live issue counts, for the client-side score.
		results: {}
	};

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
			return response.json().then(function (data) {
				if (!response.ok) {
					var err = new Error((data && data.message) || 'HTTP ' + response.status);
					err.status = response.status;
					throw err;
				}
				return data;
			});
		});
	}

	/**
	 * One retry, then give up. A single blip should not end a scan; a genuine
	 * failure should not be hammered.
	 */
	function requestWithRetry(path, options) {
		return request(path, options).catch(function () {
			return request(path, options);
		});
	}

	// ---------------------------------------------------------------------
	// Rendering
	// ---------------------------------------------------------------------

	function row(id) {
		return el.checks.querySelector('.msh-hcheck[data-check="' + id + '"]');
	}

	function setRowStatus(id, status, countText, count) {
		var node = row(id);

		if (!node) {
			return;
		}

		node.setAttribute('data-status', status);

		// Keep the count attribute in step so a cleared check stops being
		// styled as an alarm.
		if (typeof count === 'number') {
			node.setAttribute('data-count', String(count));
		}

		var label = node.querySelector('.msh-hcheck-count');

		if (label && typeof countText === 'string') {
			label.textContent = countText;
		}
	}

	function countLabel(status, count) {
		var i18n = cfg.i18n || {};

		if (status === 'running') {
			return i18n.scanning || 'scanning…';
		}
		if (status === 'skipped') {
			return i18n.skipped || 'not applicable';
		}
		if (status === 'error') {
			return i18n.failed || 'could not run';
		}
		if (status === 'pending') {
			return i18n.queued || 'queued';
		}
		if (!count) {
			return i18n.clean || 'nothing found';
		}

		return count + ' ' + (i18n.found || 'found');
	}

	function clearIssues(id) {
		var node = row(id);
		var list = node && node.querySelector('.msh-hissues');

		if (list) {
			list.innerHTML = '';
		}

		var truncated = node && node.querySelector('.msh-hcheck-truncated');

		if (truncated) {
			truncated.remove();
		}
	}

	function appendIssues(id, issues) {
		var node = row(id);
		var list = node && node.querySelector('.msh-hissues');

		if (!list || !issues || !issues.length) {
			return;
		}

		var fragment = document.createDocumentFragment();

		issues.forEach(function (issue) {
			fragment.appendChild(issueNode(issue, id));
		});

		list.appendChild(fragment);
		decorateAll(list);
	}

	function issueNode(issue, checkId) {
		var li = document.createElement('li');

		li.className = 'msh-hissue msh-hissue--' + issue.severity;
		li.setAttribute('data-check', checkId);
		li.setAttribute('data-severity', issue.severity);
		li.setAttribute('data-fix-type', issue.fix_type || '');
		li.setAttribute('data-fix-meta', JSON.stringify(issue.fix_meta || {}));

		if (issue.object_id) {
			li.setAttribute('data-object-id', String(issue.object_id));
		}

		var main = document.createElement('div');
		main.className = 'msh-hissue-main';

		var title = document.createElement('span');
		title.className = 'msh-hissue-title';
		title.textContent = issue.title;
		main.appendChild(title);

		if (issue.context) {
			var context = document.createElement('span');
			context.className = 'msh-hissue-context';
			context.textContent = issue.context;
			main.appendChild(context);
		}

		li.appendChild(main);

		var fix = document.createElement('div');
		fix.className = 'msh-hissue-fix';
		li.appendChild(fix);

		if (issue.link) {
			var link = document.createElement('a');
			link.className = 'msh-hissue-link';
			link.href = issue.link;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.textContent = (cfg.i18n && cfg.i18n.open) || 'Open';
			li.appendChild(link);
		}

		return li;
	}

	function setProgress(done, total) {
		var pct = total ? Math.round((done / total) * 100) : 0;

		if (el.progressFill) {
			el.progressFill.style.width = pct + '%';
		}

		if (el.progressBar) {
			el.progressBar.setAttribute('aria-valuenow', String(pct));
		}

		if (el.progressLabel) {
			var template = (cfg.i18n && cfg.i18n.progress) || '%1$d of %2$d checks complete';
			el.progressLabel.textContent = template
				.replace('%1$d', String(done))
				.replace('%2$d', String(total));
		}
	}

	function showError(message) {
		if (!el.error) {
			return;
		}

		el.error.textContent = message;
		el.error.hidden = false;
	}

	function hideError() {
		if (el.error) {
			el.error.hidden = true;
		}
	}

	// ---------------------------------------------------------------------
	// Scoring — mirrors Site_Health_Scanner exactly, including the per-check
	// deduction cap, so the number can move live as issues are repaired.
	// ---------------------------------------------------------------------

	function computeScore() {
		var weights = cfg.weights || { critical: 10, warning: 3, notice: 1 };
		var caps = cfg.caps || { critical: 25, warning: 15, notice: 10 };
		var deducted = 0;

		Object.keys(state.results).forEach(function (id) {
			var result = state.results[id];

			if (result.status !== 'done' && result.status !== 'running') {
				return;
			}

			var raw = 0;

			result.issues.forEach(function (severity) {
				raw += weights[severity] || weights.notice;
			});

			deducted += Math.min(raw, caps[result.severity] || caps.notice);
		});

		return Math.max(0, 100 - deducted);
	}

	function bandFor(score) {
		if (score >= 80) {
			return 'good';
		}
		if (score >= 50) {
			return 'warning';
		}
		return 'critical';
	}

	function renderScore(score, metaText) {
		var band = bandFor(score);

		if (el.scoreNum) {
			el.scoreNum.textContent = String(score);
		}

		if (el.scoreBox) {
			el.scoreBox.className = 'msh-health-scorebox msh-health-scorebox--' + band;
		}

		if (el.bandLabel) {
			el.bandLabel.textContent = (cfg.bands && cfg.bands[band]) || '';
		}

		if (typeof metaText === 'string' && el.meta) {
			el.meta.textContent = metaText;
		}

		if (el.summary) {
			el.summary.hidden = false;
		}

		if (el.empty) {
			el.empty.hidden = true;
		}
	}

	// Exposed so Quick Fix can recompute after a repair.
	window.mshHealthScore = {
		recompute: function () {
			renderScore(computeScore());
		},
		removeIssue: function (checkId, severity) {
			var result = state.results[checkId];

			if (!result) {
				return;
			}

			var index = result.issues.indexOf(severity);

			if (index > -1) {
				result.issues.splice(index, 1);
			}

			setRowStatus(checkId, 'done', countLabel('done', result.issues.length), result.issues.length);
			renderScore(computeScore());
		}
	};

	// ---------------------------------------------------------------------
	// Quick Fix
	//
	// A report without repair is a list of complaints. These controls turn
	// each row into something the user can actually resolve, with no AI, no
	// API key, and without leaving the page.
	// ---------------------------------------------------------------------

	// Saves are serialized: tabbing quickly through rows must not fire a dozen
	// concurrent writes at the site.
	var saveQueue = Promise.resolve();

	function enqueue(task) {
		var run = saveQueue.then(task, task);

		// Keep the chain alive even when a save fails.
		saveQueue = run.then(function () {}, function () {});

		return run;
	}

	function t(key, fallback) {
		return (cfg.i18n && cfg.i18n[key]) || fallback;
	}

	function meta(li) {
		try {
			return JSON.parse(li.getAttribute('data-fix-meta') || '{}');
		} catch (e) {
			return {};
		}
	}

	function issueLabel(li) {
		var title = li.querySelector('.msh-hissue-title');
		return title ? title.textContent : '';
	}

	function setFixState(li, stateName, message) {
		li.setAttribute('data-fix-state', stateName);

		var note = li.querySelector('.msh-hissue-note');

		if (!note) {
			note = document.createElement('span');
			note.className = 'msh-hissue-note';
			li.querySelector('.msh-hissue-fix').appendChild(note);
		}

		note.textContent = message || '';
		note.setAttribute('role', stateName === 'error' ? 'alert' : 'status');
	}

	function markResolved(li) {
		li.classList.add('msh-hissue--fixed');

		var input = li.querySelector('input');

		if (input) {
			input.disabled = true;
		}

		li.querySelectorAll('button').forEach(function (button) {
			button.disabled = true;
		});

		window.mshHealthScore.removeIssue(
			li.getAttribute('data-check'),
			li.getAttribute('data-severity')
		);
	}

	function submitFix(li, value, onSuccess, onFailure) {
		setFixState(li, 'saving', t('saving', 'Saving…'));

		return enqueue(function () {
			return request('fix', {
				method: 'POST',
				body: {
					check: li.getAttribute('data-check'),
					fix_type: li.getAttribute('data-fix-type'),
					fix_meta: meta(li),
					value: value
				}
			});
		}).then(function (response) {
			setFixState(li, 'saved', t('saved', 'Saved'));
			markResolved(li);

			if (onSuccess) {
				onSuccess(response);
			}

			return response;
		}).catch(function (error) {
			// Optimistic UI is fine, but it has to roll back.
			setFixState(li, 'error', error.message || t('genericError', 'Something went wrong.'));

			if (onFailure) {
				onFailure(error);
			}

			return null;
		});
	}

	function buildTextFix(li, host) {
		var fixMeta = meta(li);
		var isAlt = fixMeta.meta_key === '_wp_attachment_image_alt';

		var input = document.createElement('input');
		input.type = 'text';
		input.className = 'msh-hissue-input';
		input.placeholder = isAlt
			? t('describeImage', 'Describe this image…')
			: t('describePage', 'Write a short description…');
		input.setAttribute('aria-label', input.placeholder + ' — ' + issueLabel(li));

		var previous = '';
		var saving = false;

		function commit() {
			var value = input.value.trim();

			if (saving || '' === value || value === previous) {
				return;
			}

			saving = true;
			previous = value;

			submitFix(li, value, null, function () {
				// Restore what was there before the failed attempt.
				input.value = '';
				previous = '';
				input.disabled = false;
				saving = false;
				input.focus();
			});
		}

		input.addEventListener('blur', commit);
		input.addEventListener('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				commit();
			}
		});

		host.appendChild(input);
	}

	function buildUrlFix(li, host) {
		var input = document.createElement('input');
		input.type = 'url';
		input.className = 'msh-hissue-input';
		input.placeholder = t('replacementUrl', 'Replace with a working URL…');
		input.setAttribute('aria-label', input.placeholder + ' — ' + issueLabel(li));

		var saving = false;

		function commit() {
			var value = input.value.trim();

			if (saving || '' === value) {
				return;
			}

			saving = true;

			submitFix(li, value, null, function () {
				input.disabled = false;
				saving = false;
				input.focus();
			});
		}

		input.addEventListener('blur', commit);
		input.addEventListener('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				commit();
			}
		});

		host.appendChild(input);
	}

	function buildDeleteFix(li, host) {
		// Deliberately per-row and two-step. There is no select-all here, and
		// there never should be: this check produces false positives by design.
		var trash = document.createElement('button');
		trash.type = 'button';
		trash.className = 'msh-btn msh-btn--sm msh-hissue-delete';
		trash.textContent = t('moveToTrash', 'Move to trash');
		trash.setAttribute('aria-label', t('moveToTrash', 'Move to trash') + ' — ' + issueLabel(li));

		var confirmWrap = document.createElement('span');
		confirmWrap.className = 'msh-hissue-confirm';
		confirmWrap.hidden = true;

		var question = document.createElement('span');
		question.className = 'msh-hissue-confirmq';
		question.textContent = t('confirmDelete', 'Move this file to the trash?');

		var yes = document.createElement('button');
		yes.type = 'button';
		yes.className = 'msh-btn msh-btn--sm msh-btn--danger';
		yes.textContent = t('yes', 'Yes');

		var no = document.createElement('button');
		no.type = 'button';
		no.className = 'msh-btn msh-btn--sm';
		no.textContent = t('cancel', 'Cancel');

		trash.addEventListener('click', function () {
			trash.hidden = true;
			confirmWrap.hidden = false;
			yes.focus();
		});

		no.addEventListener('click', function () {
			confirmWrap.hidden = true;
			trash.hidden = false;
			trash.focus();
		});

		yes.addEventListener('click', function () {
			yes.disabled = true;

			submitFix(li, '', null, function () {
				yes.disabled = false;
				confirmWrap.hidden = true;
				trash.hidden = false;
			});
		});

		confirmWrap.appendChild(question);
		confirmWrap.appendChild(yes);
		confirmWrap.appendChild(no);

		host.appendChild(trash);
		host.appendChild(confirmWrap);
	}

	function decorate(li) {
		if (li.getAttribute('data-fix-ready') === '1') {
			return;
		}

		var host = li.querySelector('.msh-hissue-fix');

		if (!host) {
			return;
		}

		li.setAttribute('data-fix-ready', '1');

		switch (li.getAttribute('data-fix-type')) {
			case 'inline_text':
				buildTextFix(li, host);
				break;
			case 'inline_url':
				buildUrlFix(li, host);
				break;
			case 'delete':
				buildDeleteFix(li, host);
				break;
			default:
				break;
		}
	}

	function decorateAll(scope) {
		(scope || document).querySelectorAll('.msh-hissue').forEach(decorate);
	}

	/**
	 * Seed the client-side score from a server-rendered report.
	 *
	 * The report stores at most fifty issue rows per check but always the true
	 * total, so the remainder is filled in from the check's own severity.
	 * Without this the live score would jump the moment the first fix landed.
	 */
	function seedFromDom() {
		el.checks.querySelectorAll('.msh-hcheck').forEach(function (node) {
			var id = node.getAttribute('data-check');
			var status = node.getAttribute('data-status');
			var severity = node.getAttribute('data-severity');
			var total = parseInt(node.getAttribute('data-count') || '0', 10);
			var rendered = [];

			node.querySelectorAll('.msh-hissue').forEach(function (issue) {
				rendered.push(issue.getAttribute('data-severity'));
			});

			while (rendered.length < total) {
				rendered.push(severity);
			}

			state.results[id] = {
				severity: severity,
				status: status,
				issues: rendered
			};
		});
	}

	// ---------------------------------------------------------------------
	// The scan
	// ---------------------------------------------------------------------

	function runCheck(check) {
		var offset = 0;
		var lastOffset = -1;

		state.results[check.id] = {
			severity: check.severity,
			status: 'running',
			issues: []
		};

		setRowStatus(check.id, 'running', countLabel('running'));
		clearIssues(check.id);

		function step() {
			if (state.cancelled) {
				return Promise.resolve();
			}

			return requestWithRetry('run-check', {
				method: 'POST',
				body: { check: check.id, offset: offset }
			}).then(function (data) {
				var result = state.results[check.id];

				(data.batch_issues || []).forEach(function (issue) {
					result.issues.push(issue.severity);
				});

				appendIssues(check.id, data.batch_issues);

				if (data.status === 'skipped' || data.status === 'error') {
					result.status = data.status;
					setRowStatus(check.id, data.status, countLabel(data.status));
					return null;
				}

				if (data.done) {
					result.status = 'done';
					setRowStatus(check.id, 'done', countLabel('done', result.issues.length), result.issues.length);
					renderScore(computeScore());
					return null;
				}

				setRowStatus(check.id, 'running', countLabel('running'));

				// A check that does not advance would loop forever.
				if (data.next_offset === null || data.next_offset === lastOffset) {
					result.status = 'error';
					setRowStatus(check.id, 'error', countLabel('error'));
					return null;
				}

				lastOffset = data.next_offset;
				offset = data.next_offset;

				return step();
			}).catch(function () {
				// One failing check must not end the scan.
				state.results[check.id].status = 'error';
				setRowStatus(check.id, 'error', countLabel('error'));
				return null;
			});
		}

		return step();
	}

	function start() {
		if (state.running) {
			return;
		}

		state.running = true;
		state.cancelled = false;
		state.results = {};

		hideError();

		if (el.empty) {
			el.empty.hidden = true;
		}

		if (el.progress) {
			el.progress.hidden = false;
		}

		setProgress(0, 1);

		if (el.progressLabel) {
			el.progressLabel.textContent = (cfg.i18n && cfg.i18n.preparing) || 'Preparing…';
		}

		requestWithRetry('checks').then(function (data) {
			state.checks = (data.checks || []).filter(function (check) {
				return check.applicable;
			});

			// Anything inapplicable is settled before the scan even starts.
			(data.checks || []).forEach(function (check) {
				if (!check.applicable) {
					state.results[check.id] = { severity: check.severity, status: 'skipped', issues: [] };
					setRowStatus(check.id, 'skipped', countLabel('skipped'));
				} else {
					setRowStatus(check.id, 'pending', countLabel('pending'));
					clearIssues(check.id);
				}
			});

			var total = state.checks.length;
			var index = 0;

			setProgress(0, total || 1);

			function next() {
				if (state.cancelled || index >= total) {
					return Promise.resolve();
				}

				var check = state.checks[index];

				return runCheck(check).then(function () {
					index += 1;
					setProgress(index, total || 1);
					return next();
				});
			}

			return next();
		}).then(function () {
			if (state.cancelled) {
				finish();
				showError((cfg.i18n && cfg.i18n.cancelled) || 'Scan cancelled.');
				return null;
			}

			return requestWithRetry('finalize', { method: 'POST' }).then(function (report) {
				renderScore(report.score, '');
				finish();
				return null;
			});
		}).catch(function () {
			showError((cfg.i18n && cfg.i18n.genericError) || 'Something went wrong. Please try again.');
			finish();
		});
	}

	function finish() {
		state.running = false;

		if (el.progress) {
			el.progress.hidden = true;
		}
	}

	// ---------------------------------------------------------------------
	// Wiring
	// ---------------------------------------------------------------------

	document.addEventListener('click', function (event) {
		var trigger = event.target.closest('[data-msh-health-start]');

		if (trigger) {
			event.preventDefault();
			start();
			return;
		}

		var head = event.target.closest('.msh-hcheck-head');

		if (head) {
			var expanded = head.getAttribute('aria-expanded') === 'true';
			var body = document.getElementById(head.getAttribute('aria-controls'));

			head.setAttribute('aria-expanded', expanded ? 'false' : 'true');

			if (body) {
				body.hidden = expanded;
			}
		}
	});

	if (el.cancel) {
		el.cancel.addEventListener('click', function () {
			// Stops after the request already in flight; no further endpoints
			// are called.
			state.cancelled = true;
		});
	}

	// Wire up whatever the server already rendered.
	if (root.getAttribute('data-has-report') === '1') {
		seedFromDom();
	}

	decorateAll(document);

	if (root.getAttribute('data-autostart') === '1') {
		start();
	}
})();
