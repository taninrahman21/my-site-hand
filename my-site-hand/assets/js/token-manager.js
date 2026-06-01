/**
 * WP my-site-hand — Token Manager JavaScript
 *
 * Handles the generate-token modal: opening, form submission,
 * token reveal, copy, confirmation checkbox, and token revocation.
 */

/* global mysitehandAdmin, msh */

'use strict';

(function () {

	const cfg = window.mysitehandAdmin || {};
	const restUrl = cfg.restUrl || '';
	const restNonce = cfg.restNonce || '';
	const i18n = cfg.i18n || {};

	let generatedToken = null;

	// Auto-detect OS once on load.
	function detectOs() {
		const ua = (navigator.userAgentData?.platform || navigator.platform || navigator.userAgent || '').toLowerCase();
		if (ua.includes('mac') || ua.includes('darwin')) return 'mac';
		if (ua.includes('linux') || ua.includes('android')) return 'linux';
		return 'windows';
	}

	window.mshTokens = {

		selectedOs: detectOs(),

		/**
		 * Switch between AI clients (Claude, Cursor, etc.)
		 * 
		 * @param {string} client - 'claude', 'cursor'
		 */
		switchClientTab: function (client) {
			const tabs = ['claude', 'cursor'];
			tabs.forEach(t => {
				const el = document.getElementById('msh-client-tab-' + t);
				if (el) {
					if (t === client) el.classList.add('msh-os-tab--active');
					else el.classList.remove('msh-os-tab--active');
				}
			});

			const claudePanel = document.getElementById('msh-claude-panel');
			const cursorPanel = document.getElementById('msh-cursor-panel');

			if (client === 'claude') {
				if (claudePanel) claudePanel.style.display = 'block';
				if (cursorPanel) cursorPanel.style.display = 'none';
			} else {
				if (claudePanel) claudePanel.style.display = 'none';
				if (cursorPanel) cursorPanel.style.display = 'block';

				// Populate Cursor fields if token exists.
				if (generatedToken) {
					const urlInput = document.getElementById('msh-cursor-url');
					if (urlInput) urlInput.value = cfg.mcpEndpoint || '';

					const authInput = document.getElementById('msh-cursor-auth');
					if (authInput) authInput.value = 'Bearer ' + generatedToken;
				}
			}
		},


		/**
		 * Open the generate token modal.
		 */
		openGenerateModal: function () {
			const modal = document.getElementById('msh-generate-modal');
			if (!modal) return;

			// Reset form state.
			this._resetModal();

			modal.style.display = 'flex';

			// Focus label input after animation.
			setTimeout(() => {
				const label = document.getElementById('msh-token-label');
				if (label) label.focus();
			}, 150);
		},

		/**
		 * Close the generate token modal.
		 */
		closeModal: function () {
			const modal = document.getElementById('msh-generate-modal');
			if (modal) modal.style.display = 'none';

			// Reload to show new/revoked tokens in table.
			if (generatedToken) {
				window.location.reload();
			}
		},

		/**
		 * Submit token generation request.
		 */
		generateToken: function () {
			const label = document.getElementById('msh-token-label')?.value?.trim();

			if (!label) {
				if (window.msh && window.msh._showToast) {
					window.msh._showToast('Label is required.', 'error');
				}
				document.getElementById('msh-token-label')?.focus();
				return;
			}

			const expiresAt = document.getElementById('msh-token-expires')?.value || null;

			// Collect checked abilities.
			const abilityCheckboxes = document.querySelectorAll('input[name="abilities[]"]:checked');
			const abilities = Array.from(abilityCheckboxes).map(cb => cb.value);

			// Build payload.
			const payload = {
				label: label,
				abilities: abilities,
				expires_at: expiresAt || null
			};

			const btn = document.getElementById('msh-submit-token');
			if (btn) { btn.disabled = true; btn.textContent = i18n.saving || 'Generating…'; }

			fetch(restUrl + 'tokens', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': restNonce
				},
				body: JSON.stringify(payload)
			})
				.then(r => {
					if (!r.ok) return r.json().then(d => { throw new Error(d.message || 'Request failed'); });
					return r.json();
				})
				.then(data => {
					generatedToken = data.token;
					this._revealToken(data.token);
				})
				.catch(err => {
					if (window.msh && window.msh._showToast) {
						window.msh._showToast(err.message || i18n.error || 'Error occurred.', 'error');
					}
					if (btn) { btn.disabled = false; btn.textContent = 'Generate Token'; }
				});
		},

		/**
		 * Switch the active OS tab in the Claude connection section.
		 * 
		 * @param {string} os - 'windows', 'mac', 'linux'
		 */
		switchOsTab: function (os) {
			window.mshTokens.selectedOs = os;

			// Update tab styles.
			const tabs = ['windows', 'mac', 'linux'];
			tabs.forEach(t => {
				const el = document.getElementById('msh-os-tab-' + t);
				if (el) {
					if (t === os) el.classList.add('msh-os-tab--active');
					else el.classList.remove('msh-os-tab--active');
				}
			});

			// Update commands if token exists.
			const step1Input = document.getElementById('msh-claude-step-1');
			const step2Input = document.getElementById('msh-claude-step-2');

			if (step1Input) {
				step1Input.value = (os === 'mac' ? 'sudo ' : '') + 'npm install -g mcp-remote';
			}

			if (generatedToken && step2Input) {
				const endpoint = cfg.mcpEndpoint || '';
				const token = generatedToken;
				let command = '';

				if (os === 'windows') {
					command = `node -e "const fs=require('fs'),os=require('os'),path=require('path'),cp=require('child_process');const home=os.homedir();const p=path.join(home,'AppData','Roaming','Claude','claude_desktop_config.json');const dir=path.dirname(p);if(!fs.existsSync(dir))fs.mkdirSync(dir,{recursive:true});let d={};try{d=JSON.parse(fs.readFileSync(p,'utf8'))}catch(e){};if(!d.mcpServers)d.mcpServers={};const proxy=path.join(cp.execSync('npm root -g').toString().trim(),'mcp-remote','dist','proxy.js');d.mcpServers['my-site-hand']={command:'node',args:[proxy,'${endpoint}','--header','Authorization: Bearer ${token}']};fs.writeFileSync(p,JSON.stringify(d,null,2));console.log('Done');"`;
				} else if (os === 'mac') {
					command = `node -e 'const fs=require("fs"),os=require("os"),path=require("path"),cp=require("child_process");const home=os.homedir();const configPath=path.join(home,"Library","Application Support","Claude","claude_desktop_config.json");const dir=path.dirname(configPath);if(!fs.existsSync(dir))fs.mkdirSync(dir,{recursive:true});let d={};try{d=JSON.parse(fs.readFileSync(configPath,"utf8"))}catch(e){};if(!d.mcpServers)d.mcpServers={};const proxy=path.join(cp.execSync("npm root -g").toString().trim(),"mcp-remote","dist","proxy.js");d.mcpServers["my-site-hand"]={command:"node",args:[proxy,"${endpoint}","--header","Authorization: Bearer ${token}"]};fs.writeFileSync(configPath,JSON.stringify(d,null,2));console.log("Done. Restart Claude Desktop.");'`;
				} else {
					// Linux
					command = `node -e 'const fs=require("fs"),os=require("os"),path=require("path"),cp=require("child_process");const home=os.homedir();const configPath=path.join(home,".config","Claude","claude_desktop_config.json");const dir=path.dirname(configPath);if(!fs.existsSync(dir))fs.mkdirSync(dir,{recursive:true});let d={};try{d=JSON.parse(fs.readFileSync(configPath,"utf8"))}catch(e){};if(!d.mcpServers)d.mcpServers={};const proxy=path.join(cp.execSync("npm root -g").toString().trim(),"mcp-remote","dist","proxy.js");d.mcpServers["my-site-hand"]={command:"node",args:[proxy,"${endpoint}","--header","Authorization: Bearer ${token}"]};fs.writeFileSync(configPath,JSON.stringify(d,null,2));console.log("Done. Restart Claude Desktop.");'`;
				}

				step2Input.value = command;
			}
		},

		/**
		 * Revoke a token via REST API.
		 *
		 * @param {number} tokenId  - Token ID to revoke.
		 * @param {string} label    - Token label for confirmation.
		 */
		revokeToken: function (tokenId, label) {
			const msg = (i18n.confirmRevoke || 'Are you sure you want to revoke this token?')
				.replace('this token', '"' + label + '"');

			if (!confirm(msg)) return;

			fetch(restUrl + 'tokens/' + tokenId, {
				method: 'DELETE',
				headers: { 'X-WP-Nonce': restNonce }
			})
				.then(r => r.json())
				.then(data => {
					if (data.revoked) {
						if (window.msh && window.msh._showToast) {
							window.msh._showToast('Token revoked.');
						}
						setTimeout(() => window.location.reload(), 1000);
					} else {
						if (window.msh && window.msh._showToast) {
							window.msh._showToast(data.message || i18n.error, 'error');
						}
					}
				})
				.catch(() => {
					if (window.msh && window.msh._showToast) {
						window.msh._showToast(i18n.error || 'Error.', 'error');
					}
				});
		},

		/**
		 * Show the token value and switch UI to reveal mode.
		 *
		 * @param {string} token - Raw token string to reveal.
		 * @private
		 */
		_revealToken: function (token) {
			// Hide the generate form / button.
			const form = document.getElementById('msh-generate-token-form');
			if (form) {
				const exemptIds = ['msh-confirm-copied', 'msh-copy-token-btn', 'msh-copy-claude-btn'];
				Array.from(form.elements).forEach(el => {
					// Exempt everything inside the reveal area OR specific IDs.
					if (el.closest('.msh-token-reveal') || exemptIds.includes(el.id)) {
						el.disabled = false;
					} else {
						el.disabled = true;
					}
				});
			}

			const submitBtn = document.getElementById('msh-submit-token');
			if (submitBtn) submitBtn.style.display = 'none';

			const revealArea = document.getElementById('msh-token-reveal');
			if (revealArea) revealArea.style.display = 'block';

			const tokenEl = document.getElementById('msh-new-token-value');
			if (tokenEl) tokenEl.textContent = token;

			// Attach copy handlers programmatically
			const tokenBtn = document.getElementById('msh-copy-token-btn');
			if (tokenBtn) {
				tokenBtn.onclick = () => {
					window.msh.copyText('msh-new-token-value');
				};
			}

			// Initialize the dynamic copy source for Claude command.
			this.switchOsTab(window.mshTokens.selectedOs || detectOs());

			// Show done button; enable when checkbox is checked.
			const doneBtn = document.getElementById('msh-close-after-copy');
			if (doneBtn) doneBtn.style.display = 'inline-flex';

			const checkbox = document.getElementById('msh-confirm-copied');
			if (checkbox) {
				checkbox.addEventListener('change', function () {
					if (doneBtn) doneBtn.disabled = !this.checked;
				});
			}

			// Initialize the dynamic copy source for Claude command.
			this.switchOsTab(window.mshTokens.selectedOs || detectOs());

			// Also populate Cursor fields for immediate use if user switches.
			const urlInput = document.getElementById('msh-cursor-url');
			if (urlInput) urlInput.value = cfg.mcpEndpoint || '';

			const authInput = document.getElementById('msh-cursor-auth');
			if (authInput) authInput.value = 'Bearer ' + token;
		},

		/**
		 * Reset the modal form back to initial state.
		 *
		 * @private
		 */
		_resetModal: function () {
			generatedToken = null;

			const form = document.getElementById('msh-generate-token-form');
			if (form) form.reset();

			// Reset client and OS selection.
			this.switchClientTab('claude');
			this.switchOsTab(detectOs());

			// Clear Cursor fields.
			const cursorUrl = document.getElementById('msh-cursor-url');
			if (cursorUrl) cursorUrl.value = '';
			const cursorAuth = document.getElementById('msh-cursor-auth');
			if (cursorAuth) cursorAuth.value = '';

			const step1 = document.getElementById('msh-claude-step-1');
			if (step1) step1.value = 'npm install -g mcp-remote';

			const step2 = document.getElementById('msh-claude-step-2');
			if (step2) step2.value = '';


			const reveal = document.getElementById('msh-token-reveal');
			if (reveal) reveal.style.display = 'none';

			const submitBtn = document.getElementById('msh-submit-token');
			if (submitBtn) { submitBtn.style.display = 'inline-flex'; submitBtn.disabled = false; submitBtn.textContent = 'Generate Token'; }

			const doneBtn = document.getElementById('msh-close-after-copy');
			if (doneBtn) { doneBtn.style.display = 'none'; doneBtn.disabled = true; }

			// Re-enable form elements.
			if (form) {
				Array.from(form.elements).forEach(el => { el.disabled = false; });
			}
		}
	};

}());
