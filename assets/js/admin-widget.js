/**
 * My Site Hand — Frontend Floating Chat Widget
 */
'use strict';

(function () {
	const cfg = window.mshFrontendChat || {};
	if (!cfg.restUrl) return;

	let sessionId = '';

	function uuid() {
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			const r = (Math.random() * 16) | 0;
			const v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
	}

	function getSessionId() {
		if (!sessionId) {
			try { sessionId = window.sessionStorage.getItem('msh_chat_session') || ''; } catch (e) { }
		}
		if (!sessionId) {
			sessionId = uuid();
			try { window.sessionStorage.setItem('msh_chat_session', sessionId); } catch (e) { }
		}
		return sessionId;
	}

	function parseMarkdown(text) {
		if (typeof marked !== 'undefined') return marked.parse(text);
		return '<p>' + text.replace(/\n/g, '<br>') + '</p>';
	}

	function buildUI() {
		const toggleBtn = document.createElement('button');
		toggleBtn.id = 'msh-fw-toggle';
		toggleBtn.innerHTML = `<img src="${cfg.iconUrl}" alt="Chat">`;
		document.body.appendChild(toggleBtn);

		const panel = document.createElement('div');
		panel.id = 'msh-fw-panel';
		panel.innerHTML = `
			<div class="msh-fw-history-drawer" id="msh-fw-history-drawer">
				<div class="msh-fw-drawer-header">
					<button id="msh-fw-new-chat"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> New Chat</button>
				</div>
				<div id="msh-fw-sessions" class="msh-fw-sessions">Loading...</div>
			</div>
			<div class="msh-fw-header">
				<div style="display:flex; align-items:center; gap:8px;">
					<button id="msh-fw-history-toggle" title="History"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
					<h3>My Site Hand</h3>
				</div>
				<button class="msh-fw-close"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
			</div>
			<div class="msh-fw-messages" id="msh-fw-messages"></div>
			${cfg.isUsingProxy ? `
			<div class="msh-api-promo" style="padding:8px 12px; font-size:12px;">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; color:#f59e0b; flex-shrink:0;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
				<span><a href="${cfg.settingsUrl}">Set your own API key here</a> or don't want API hassle? <a href="mailto:taninrahman21@gmail.com?subject=My Site Hand Premium Request" target="_blank" rel="noopener noreferrer">Apply for Premium</a> for unlimited chats.</span>
			</div>
			` : ''}
			<div class="msh-fw-input-area">
				<textarea id="msh-fw-input" rows="1" placeholder="Ask anything..."></textarea>
				<button id="msh-fw-send"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg></button>
			</div>
		`;
		document.body.appendChild(panel);

		toggleBtn.addEventListener('click', () => {
			panel.classList.add('is-open');
			loadSessions();
		});
		panel.querySelector('.msh-fw-close').addEventListener('click', () => panel.classList.remove('is-open'));

		const historyDrawer = panel.querySelector('#msh-fw-history-drawer');
		panel.querySelector('#msh-fw-history-toggle').addEventListener('click', () => {
			historyDrawer.classList.toggle('is-open');
		});

		panel.querySelector('#msh-fw-new-chat').addEventListener('click', () => {
			sessionId = uuid();
			try { window.sessionStorage.setItem('msh_chat_session', sessionId); } catch (e) { }
			document.getElementById('msh-fw-messages').innerHTML = '';
			historyDrawer.classList.remove('is-open');
		});

		const input = panel.querySelector('#msh-fw-input');
		const sendBtn = panel.querySelector('#msh-fw-send');

		sendBtn.addEventListener('click', sendMessage);
		input.addEventListener('keydown', (e) => {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				sendMessage();
			}
		});

		loadHistory();
	}

	async function loadSessions() {
		const container = document.getElementById('msh-fw-sessions');
		if (!container) return;
		try {
			const res = await fetch(cfg.restUrl + 'sessions', {
				headers: { 'X-WP-Nonce': cfg.restNonce }
			});
			if (!res.ok) return;
			const sessions = await res.json();
			container.innerHTML = '';
			if (!sessions.length) {
				container.innerHTML = '<div style="padding:12px;color:#666;font-size:13px;">No conversations yet</div>';
				return;
			}
			sessions.forEach(s => {
				const wrap = document.createElement('div');
				wrap.style.display = 'flex';
				wrap.style.gap = '4px';

				const btn = document.createElement('button');
				btn.className = 'msh-fw-session-btn' + (s.session_id === getSessionId() ? ' is-active' : '');
				btn.style.flex = '1';
				btn.textContent = s.title;
				btn.addEventListener('click', () => {
					sessionId = s.session_id;
					try { window.sessionStorage.setItem('msh_chat_session', sessionId); } catch (e) { }
					document.querySelectorAll('.msh-fw-session-btn').forEach(b => b.classList.remove('is-active'));
					btn.classList.add('is-active');
					document.getElementById('msh-fw-history-drawer').classList.remove('is-open');
					loadHistory();
				});

				const delBtn = document.createElement('button');
				delBtn.className = 'msh-fw-session-del';
				delBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
				delBtn.title = 'Delete Conversation';
				delBtn.addEventListener('click', async (e) => {
					e.stopPropagation();
					if (!confirm('Delete this conversation?')) return;
					try {
						await fetch(cfg.restUrl + 'clear', {
							method: 'POST',
							headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
							body: JSON.stringify({ session_id: s.session_id })
						});
					} catch (e) {}
					if (s.session_id === getSessionId()) {
						sessionId = uuid();
						try { window.sessionStorage.setItem('msh_chat_session', sessionId); } catch (e) { }
						document.getElementById('msh-fw-messages').innerHTML = '';
					}
					loadSessions();
				});

				wrap.appendChild(btn);
				wrap.appendChild(delBtn);
				container.appendChild(wrap);
			});
		} catch (e) {
			container.innerHTML = '<div style="padding:12px;color:red;font-size:13px;">Failed to load</div>';
		}
	}

	function scrollToBottom() {
		const msgs = document.getElementById('msh-fw-messages');
		if (msgs) msgs.scrollTop = msgs.scrollHeight;
	}

	function renderMessage(role, content) {
		const msgs = document.getElementById('msh-fw-messages');
		if (!msgs) return;
		
		const wrap = document.createElement('div');
		wrap.className = 'msh-fw-msg msh-fw-msg--' + role;

		if (role === 'assistant') {
			wrap.innerHTML = parseMarkdown(content);
			if (typeof hljs !== 'undefined') {
				wrap.querySelectorAll('pre code').forEach((block) => hljs.highlightElement(block));
			}
		} else {
			wrap.textContent = content;
		}

		msgs.appendChild(wrap);
		scrollToBottom();
		return wrap;
	}

	async function sendMessage() {
		const input = document.getElementById('msh-fw-input');
		if (!input) return;
		const message = input.value.trim();
		if (!message) return;

		renderMessage('user', message);
		input.value = '';

		const loading = document.createElement('div');
		loading.className = 'msh-fw-msg msh-fw-msg--assistant';
		loading.textContent = 'Typing...';
		document.getElementById('msh-fw-messages').appendChild(loading);
		scrollToBottom();

		try {
			const res = await fetch(cfg.restUrl + 'send', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce },
				body: JSON.stringify({ message: message, session_id: getSessionId() })
			});
			const data = await res.json();
			loading.remove();

			if (!res.ok) {
				renderMessage('assistant', data.message || 'Error occurred.');
				return;
			}
			renderMessage('assistant', data.reply || 'Done.');
		} catch (e) {
			loading.remove();
			renderMessage('assistant', 'Network error.');
		}
	}

	async function loadHistory() {
		const msgs = document.getElementById('msh-fw-messages');
		if (msgs) msgs.innerHTML = '';
		try {
			const res = await fetch(cfg.restUrl + 'history?session_id=' + encodeURIComponent(getSessionId()), {
				headers: { 'X-WP-Nonce': cfg.restNonce }
			});
			if (!res.ok) return;
			const messages = await res.json();
			messages.forEach(m => {
				if (m.role !== 'tool') {
					renderMessage(m.role, m.content);
				}
			});
		} catch (e) {}
	}

	document.addEventListener('DOMContentLoaded', buildUI);
})();
