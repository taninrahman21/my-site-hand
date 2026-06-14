/**
 * My Site Hand — AI Assistant chat client.
 *
 * Vanilla JS. No jQuery, no build step. Talks to the my-site-hand/v1/chat
 * REST endpoints. API keys live server-side only; this client never sees them.
 */

/* global mshAiChat, mysitehandAdmin, marked, hljs */

'use strict';

(function () {

	const cfg = window.mshAiChat || {};
	const admin = window.mysitehandAdmin || {};

	const restUrl = cfg.restUrl || '';
	const restNonce = cfg.restNonce || '';
	const models = cfg.models || {};

	const STRINGS = {
		errorGeneric: 'Something went wrong. Please try again.',
		usedTool: 'Used tool:',
		statusSuccess: 'Success',
		statusFailed: 'Failed',
		confirmClear: 'Clear this conversation? This cannot be undone.',
		testing: 'Testing…',
		saving: 'Saving…',
		needProviderKey: 'Choose a provider and enter an API key first.',
		saved: 'Saved. Activating…',
		connOk: 'Connection successful.',
		connFail: 'Connection failed.'
	};

	let sessionId = '';

	/** -----------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	function uuid() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			const r = (Math.random() * 16) | 0;
			const v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
	}

	function getSessionId() {
		if (!sessionId) {
			try {
				sessionId = window.sessionStorage.getItem('msh_chat_session') || '';
			} catch (e) { }
		}
		if (!sessionId) {
			sessionId = uuid();
			saveSessionId(sessionId);
		}
		return sessionId;
	}

	function saveSessionId(id) {
		sessionId = id;
		try {
			window.sessionStorage.setItem('msh_chat_session', id);
		} catch (e) { }
	}

	function resetSessionId() {
		sessionId = uuid();
		saveSessionId(sessionId);
	}

	function escapeHtml(str) {
		const div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	function nowTime() {
		const d = new Date();
		const h = String(d.getHours()).padStart(2, '0');
		const m = String(d.getMinutes()).padStart(2, '0');
		return h + ':' + m;
	}

	function getWindow() {
		return document.getElementById('msh-chat-window');
	}

	function scrollToBottom() {
		const win = getWindow();
		if (win) {
			win.scrollTop = win.scrollHeight;
		}
	}

	function hideWelcome() {
		const welcome = document.getElementById('msh-welcome-screen');
		if (welcome) {
			welcome.style.display = 'none';
		}
	}

	function showWelcome() {
		const welcome = document.getElementById('msh-welcome-screen');
		if (welcome) {
			welcome.style.display = '';
		}
	}

	function parseMarkdown(text) {
		if (typeof marked !== 'undefined') {
			return marked.parse(text);
		}
		return '<p>' + escapeHtml(text).replace(/\n/g, '<br>') + '</p>';
	}

	/** -----------------------------------------------------------------------
	 * Rendering
	 * ---------------------------------------------------------------------- */

	function renderMessage(role, content, time) {
		hideWelcome();
		const win = getWindow();
		if (!win) {
			return;
		}

		const wrap = document.createElement('div');
		wrap.className = 'msh-message msh-message--' + (role === 'user' ? 'user' : 'assistant');

		const body = document.createElement('div');
		body.className = 'msh-message__body msh-markdown';
		
		if (role === 'assistant') {
			body.innerHTML = parseMarkdown(content);
			if (typeof hljs !== 'undefined') {
				body.querySelectorAll('pre code').forEach((block) => {
					hljs.highlightElement(block);
				});
			}
		} else {
			body.textContent = content;
		}

		wrap.appendChild(body);

		if (time) {
			const ts = document.createElement('div');
			ts.className = 'msh-message__time';
			ts.textContent = time;
			wrap.appendChild(ts);
		}

		win.appendChild(wrap);
		scrollToBottom();
	}

	function renderTool(toolName, status) {
		hideWelcome();
		const win = getWindow();
		if (!win) {
			return;
		}

		const wrap = document.createElement('div');
		wrap.className = 'msh-message msh-message--tool';

		const label = status === 'success' ? STRINGS.statusSuccess : STRINGS.statusFailed;
		wrap.textContent = '⚙ ' + STRINGS.usedTool + ' ' + toolName + ' — ' + label;

		win.appendChild(wrap);
		scrollToBottom();
	}

	function addLoading() {
		hideWelcome();
		const win = getWindow();
		if (!win) return null;

		const wrap = document.createElement('div');
		wrap.className = 'msh-message msh-message--assistant msh-message--loading';
		wrap.innerHTML = '<div class="msh-loading"><span></span><span></span><span></span></div>';

		win.appendChild(wrap);
		scrollToBottom();
		return wrap;
	}

	function clearMessagesUI() {
		const win = getWindow();
		if (win) {
			win.querySelectorAll('.msh-message').forEach(el => el.remove());
		}
	}

	/** -----------------------------------------------------------------------
	 * Networking
	 * ---------------------------------------------------------------------- */

	function apiHeaders() {
		return {
			'Content-Type': 'application/json',
			'X-WP-Nonce': restNonce
		};
	}

	async function sendMessage() {
		const input = document.getElementById('msh-chat-input');
		if (!input) return;

		const message = input.value.trim();
		if (message === '') return;

		renderMessage('user', message, nowTime());
		input.value = '';
		autoResize();

		const loading = addLoading();

		try {
			const res = await fetch(restUrl + 'send', {
				method: 'POST',
				headers: apiHeaders(),
				body: JSON.stringify({ message: message, session_id: getSessionId() })
			});

			if ( res.status === 429 ) {
				const data = await res.json();
				if (loading) loading.remove();
				renderMessage('assistant', '⚠️ ' + ( data.message || 'Daily limit reached.' ));
				updateUsageText( cfg.freeLimit, cfg.freeLimit, 0 );
				return;
			}

			const data = await res.json();
			if (loading) loading.remove();

			if (!res.ok) {
				const msg = (data && data.message) ? data.message : STRINGS.errorGeneric;
				renderMessage('assistant', msg, nowTime());
				return;
			}

			if (Array.isArray(data.tools_used) && data.tools_used.length) {
				data.tools_used.forEach(t => renderTool(t.name || '', t.status || ''));
			} else if (data.tool_used) {
				renderTool(data.tool_used, data.tool_status || '');
			}

			renderMessage('assistant', data.reply || '', nowTime());
			loadUsage();
			loadSessions(); // Refresh list to show new title
		} catch (e) {
			if (loading) loading.remove();
			renderMessage('assistant', STRINGS.errorGeneric, nowTime());
		}
	}

	async function loadHistory() {
		clearMessagesUI();
		try {
			const res = await fetch(restUrl + 'history?session_id=' + encodeURIComponent(getSessionId()), {
				method: 'GET',
				headers: apiHeaders()
			});

			if (!res.ok) return;
			const messages = await res.json();

			if (!Array.isArray(messages) || messages.length === 0) {
				showWelcome();
				return;
			}

			messages.forEach(function (m) {
				if (m.role === 'tool') {
					renderTool(m.tool_name || '', m.tool_status || '');
				} else {
					renderMessage(m.role === 'user' ? 'user' : 'assistant', m.content || '');
				}
			});

			scrollToBottom();
		} catch (e) {
			/* Non-fatal */
		}
	}

	async function loadSessions() {
		const container = document.getElementById('msh-chat-threads');
		if (!container) return;

		try {
			const res = await fetch(restUrl + 'sessions', {
				headers: apiHeaders()
			});
			if (!res.ok) return;
			const sessions = await res.json();

			container.innerHTML = '';
			if (!sessions.length) {
				container.innerHTML = '<div class="msh-loading-threads">No conversations yet</div>';
				return;
			}

			sessions.forEach(s => {
				const wrap = document.createElement('div');
				wrap.style.display = 'flex';
				wrap.style.gap = '4px';

				const btn = document.createElement('button');
				btn.className = 'msh-thread-btn' + (s.session_id === getSessionId() ? ' is-active' : '');
				btn.style.flex = '1';
				btn.textContent = s.title;
				btn.addEventListener('click', () => {
					saveSessionId(s.session_id);
					document.querySelectorAll('.msh-thread-btn').forEach(b => b.classList.remove('is-active'));
					btn.classList.add('is-active');
					loadHistory();
					const sidebar = document.getElementById('msh-chat-sidebar');
					const overlay = document.getElementById('msh-chat-sidebar-overlay');
					if (sidebar) sidebar.classList.remove('is-open');
					if (overlay) overlay.classList.remove('is-active');
				});

				const delBtn = document.createElement('button');
				delBtn.className = 'msh-thread-del';
				delBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
				delBtn.title = 'Delete Conversation';
				delBtn.addEventListener('click', async (e) => {
					e.stopPropagation();
					if (!window.confirm('Delete this conversation?')) return;
					try {
						await fetch(restUrl + 'clear', {
							method: 'POST',
							headers: apiHeaders(),
							body: JSON.stringify({ session_id: s.session_id })
						});
					} catch (e) {}
					if (s.session_id === getSessionId()) {
						resetSessionId();
						clearMessagesUI();
						showWelcome();
					}
					loadSessions();
				});

				wrap.appendChild(btn);
				wrap.appendChild(delBtn);
				container.appendChild(wrap);
			});
		} catch (e) {
			container.innerHTML = '<div class="msh-loading-threads">Failed to load</div>';
		}
	}

	async function clearConversation() {
		if (!window.confirm(STRINGS.confirmClear)) return;

		try {
			await fetch(restUrl + 'clear', {
				method: 'POST',
				headers: apiHeaders(),
				body: JSON.stringify({ session_id: getSessionId() })
			});
		} catch (e) { }

		clearMessagesUI();
		resetSessionId();
		showWelcome();
		loadSessions();
	}

	async function loadUsage() {
		if ( ! cfg.isUsingProxy ) return;
		try {
			const res = await fetch( cfg.usageUrl, {
				headers: { 'X-WP-Nonce': cfg.restNonce }
			} );
			if ( ! res.ok ) return;
			const data = await res.json();
			updateUsageText( data.used, data.limit, data.remaining );
		} catch ( e ) {
			// Non-critical — fail silently.
		}
	}

	function updateUsageText( used, limit, remaining ) {
		const el = document.getElementById( 'msh-usage-text' );
		if ( ! el ) return;
		if ( remaining === null || remaining === undefined ) {
			el.textContent = 'usage unavailable';
			return;
		}
		el.textContent = remaining + ' of ' + limit;
	}

	/** -----------------------------------------------------------------------
	 * Input behavior
	 * ---------------------------------------------------------------------- */

	function autoResize() {
		const input = document.getElementById('msh-chat-input');
		if (!input) return;
		input.style.height = 'auto';
		const max = 140; // ~5 lines
		input.style.height = Math.min(input.scrollHeight, max) + 'px';
	}

	/** -----------------------------------------------------------------------
	 * API Setup + model picker
	 * ---------------------------------------------------------------------- */

	function saveOption(name, value) {
		return fetch(admin.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				action: 'my_site_hand_save_option',
				option_name: name,
				option_value: value,
				nonce: admin.nonce || ''
			})
		}).then(r => r.json());
	}

	function rebuildModelPicker(provider) {
		const select = document.getElementById('msh-model-select');
		if (!select) return;
		select.innerHTML = '';
		const auto = document.createElement('option');
		auto.value = 'auto';
		auto.textContent = 'Auto';
		select.appendChild(auto);
		(models[provider] || []).forEach(m => {
			const opt = document.createElement('option');
			opt.value = m;
			opt.textContent = m;
			select.appendChild(opt);
		});
		select.value = 'auto';
	}

	function setSetupResult(text, color) {
		const el = document.getElementById('msh-setup-result');
		if (el) {
			el.textContent = text;
			el.style.color = color || 'var(--msh-text-muted)';
		}
	}

	function initSetup() {
		const toggle = document.getElementById('msh-api-setup-toggle');
		const panel = document.getElementById('msh-api-setup-panel');
		const providerSel = document.getElementById('msh-setup-provider');
		const keyInput = document.getElementById('msh-setup-key');
		const saveBtn = document.getElementById('msh-setup-save');
		const testBtn = document.getElementById('msh-setup-test');
		const modelSel = document.getElementById('msh-model-select');

		if (toggle && panel) {
			toggle.addEventListener('click', () => panel.hidden = !panel.hidden);
		}

		if (providerSel) {
			providerSel.addEventListener('change', () => {
				saveOption('mysitehand_ai_provider', providerSel.value);
				rebuildModelPicker(providerSel.value);
				saveOption('mysitehand_ai_model', 'auto');
			});
		}

		if (modelSel) {
			modelSel.addEventListener('change', () => {
				saveOption('mysitehand_ai_model', modelSel.value).then(() => setSetupResult('', ''));
			});
		}

		if (saveBtn) {
			saveBtn.addEventListener('click', async () => {
				const provider = providerSel ? providerSel.value : '';
				const key = keyInput ? keyInput.value.trim() : '';

				if (!provider) {
					setSetupResult(STRINGS.needProviderKey, 'var(--msh-danger)');
					return;
				}
				if (!cfg.isConfigured && key === '') {
					setSetupResult(STRINGS.needProviderKey, 'var(--msh-danger)');
					return;
				}

				saveBtn.disabled = true;
				setSetupResult(STRINGS.saving, 'var(--msh-text-muted)');

				try {
					await saveOption('mysitehand_ai_provider', provider);
					if (key !== '') {
						await saveOption('mysitehand_ai_api_key', key);
					}
					setSetupResult(STRINGS.saved, 'var(--msh-success)');
					setTimeout(() => window.location.reload(), 600);
				} catch (e) {
					setSetupResult(STRINGS.connFail, 'var(--msh-danger)');
					saveBtn.disabled = false;
				}
			});
		}

		if (testBtn) {
			testBtn.addEventListener('click', async () => {
				testBtn.disabled = true;
				setSetupResult(STRINGS.testing, 'var(--msh-text-muted)');

				try {
					if (providerSel && providerSel.value) {
						await saveOption('mysitehand_ai_provider', providerSel.value);
					}
					if (keyInput && keyInput.value.trim() !== '') {
						await saveOption('mysitehand_ai_api_key', keyInput.value.trim());
					}

					const res = await fetch(restUrl + 'test-connection', {
						method: 'POST',
						headers: apiHeaders()
					});
					const data = await res.json();

					if (data && data.success) {
						setSetupResult(STRINGS.connOk, 'var(--msh-success)');
					} else {
						setSetupResult((data && data.message) ? data.message : STRINGS.connFail, 'var(--msh-danger)');
					}
				} catch (e) {
					setSetupResult(STRINGS.connFail, 'var(--msh-danger)');
				} finally {
					testBtn.disabled = false;
				}
			});
		}
	}

	/** -----------------------------------------------------------------------
	 * Init
	 * ---------------------------------------------------------------------- */

	document.addEventListener('DOMContentLoaded', function () {
		if (!getWindow()) return;

		initSetup();

		if (!cfg.isConfigured) return;

		// We try to load a session ID if there is one, but we don't force it 
		// if they just click "New Chat". 
		sessionId = getSessionId();

		const input = document.getElementById('msh-chat-input');
		const sendBtn = document.getElementById('msh-chat-send-btn');
		const clearBtn = document.getElementById('msh-chat-clear-btn');
		const newChatBtn = document.getElementById('msh-new-chat-btn');
		const historyToggleBtn = document.getElementById('msh-history-toggle-btn');
		const sidebar = document.getElementById('msh-chat-sidebar');
		const overlay = document.getElementById('msh-chat-sidebar-overlay');
		const closeSidebarBtn = document.getElementById('msh-close-sidebar-btn');

		function openSidebar() {
			if (sidebar) sidebar.classList.add('is-open');
			if (overlay) overlay.classList.add('is-active');
		}

		function closeSidebar() {
			if (sidebar) sidebar.classList.remove('is-open');
			if (overlay) overlay.classList.remove('is-active');
		}

		if (historyToggleBtn) historyToggleBtn.addEventListener('click', openSidebar);
		if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
		if (overlay) overlay.addEventListener('click', closeSidebar);

		if (sendBtn) sendBtn.addEventListener('click', sendMessage);
		if (clearBtn) clearBtn.addEventListener('click', clearConversation);
		if (newChatBtn) {
			newChatBtn.addEventListener('click', () => {
				clearMessagesUI();
				resetSessionId();
				showWelcome();
				document.querySelectorAll('.msh-thread-btn').forEach(b => b.classList.remove('is-active'));
				closeSidebar();
			});
		}

		if (input) {
			input.addEventListener('input', autoResize);
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' && !e.shiftKey) {
					e.preventDefault();
					sendMessage();
				}
			});
		}

		document.querySelectorAll('.msh-prompt-btn').forEach(btn => {
			btn.addEventListener('click', () => {
				if (!input) return;
				input.value = btn.getAttribute('data-prompt') || btn.textContent.trim();
				autoResize();
				sendMessage();
			});
		});

		loadSessions().then(() => {
			// Try to find the most recent session from the UI, or use current session ID if it exists in list
			const activeBtn = document.querySelector('.msh-thread-btn.is-active');
			if (!activeBtn) {
				const firstBtn = document.querySelector('.msh-thread-btn');
				if (firstBtn && sessionId !== getSessionId()) {
					// We'll let `loadHistory` load whichever session was stored.
				}
			}
			loadHistory();
		});
		
		loadUsage();
	});

}());
