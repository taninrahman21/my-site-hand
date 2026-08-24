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

			// Compute dynamic expires_at date string from dropdown value
			let expiresAt = null;
			const expiresSelect = document.getElementById('msh-token-expires')?.value;
			if (expiresSelect && expiresSelect !== 'never') {
				const date = new Date();
				if (expiresSelect === '30_days') {
					date.setDate(date.getDate() + 30);
				} else if (expiresSelect === '90_days') {
					date.setDate(date.getDate() + 90);
				} else if (expiresSelect === '6_months') {
					date.setMonth(date.getMonth() + 6);
				} else if (expiresSelect === '1_year') {
					date.setFullYear(date.getFullYear() + 1);
				}
				expiresAt = date.toISOString().split('T')[0]; // YYYY-MM-DD
			}

			// Collect checked abilities or set to full access.
			let abilities = [];
			const isFull = document.querySelector('input[name="access_type"]:checked')?.value === 'full';
			if (isFull) {
				abilities = ['*'];
			} else {
				const abilityCheckboxes = document.querySelectorAll('input[name="abilities[]"]:checked');
				abilities = Array.from(abilityCheckboxes).map(cb => cb.value);
			}

			// Collect allowed ips
			const allowedIpsText = document.getElementById('msh-token-allowed-ips')?.value?.trim() || '';

			// Build payload.
			const payload = {
				label: label,
				abilities: abilities,
				expires_at: expiresAt,
				allowed_ips: allowedIpsText
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
					if (btn) {
						btn.disabled = false;
						btn.textContent = 'Generate token';
					}
				});
		},

		/**
		 * Toggle the active state of a scope card.
		 */
		toggleScopeCard: function (scope) {
			const checkbox = document.getElementById('msh-scope-' + scope);
			if (!checkbox) return;
			checkbox.checked = !checkbox.checked;
			checkbox.dispatchEvent(new Event('change'));
		},

		/**
		 * Handle scope checkbox card changes.
		 */
		onScopeCheckboxChange: function (event, scope) {
			const checked = event.target.checked;
			const card = event.target.closest('.msh-scope-card');
			
			if (card) {
				if (checked) card.classList.add('msh-scope-card--active');
				else card.classList.remove('msh-scope-card--active');
			}

			if (scope === 'custom') {
				const wrapper = document.getElementById('msh-custom-abilities-wrapper');
				if (wrapper) {
					wrapper.style.display = checked ? 'block' : 'none';
				}
			} else {
				// For preset scopes: check/uncheck corresponding checkboxes in the list
				const checkboxes = document.querySelectorAll(`input[name="abilities[]"][data-scope="${scope}"]`);
				checkboxes.forEach(cb => {
					cb.checked = checked;
				});
			}

			this.updateSubmitButtonState();
		},

		/**
		 * Handle custom individual checkbox updates.
		 */
		onCustomAbilityChange: function (scope) {
			const totalInScope = document.querySelectorAll(`input[name="abilities[]"][data-scope="${scope}"]`).length;
			const checkedInScope = document.querySelectorAll(`input[name="abilities[]"][data-scope="${scope}"]:checked`).length;
			const scopeCheckbox = document.getElementById('msh-scope-' + scope);
			const card = scopeCheckbox?.closest('.msh-scope-card');

			if (scopeCheckbox) {
				const allChecked = totalInScope === checkedInScope && totalInScope > 0;
				scopeCheckbox.checked = allChecked;
				if (card) {
					if (allChecked) card.classList.add('msh-scope-card--active');
					else card.classList.remove('msh-scope-card--active');
				}
			}

			this.updateSubmitButtonState();
		},

		/**
		 * Toggle visibility of the revealed token value.
		 */
		toggleTokenVisibility: function () {
			const maskedInput = document.getElementById('msh-new-token-value-masked');
			const plainInput = document.getElementById('msh-new-token-value');
			const eyeIcon = document.getElementById('msh-eye-icon');
			const eyeOffIcon = document.getElementById('msh-eye-off-icon');

			if (plainInput && maskedInput) {
				if (plainInput.style.display === 'none') {
					// Show plain text
					plainInput.style.display = 'block';
					maskedInput.style.display = 'none';
					if (eyeIcon) eyeIcon.style.display = 'none';
					if (eyeOffIcon) eyeOffIcon.style.display = 'block';
				} else {
					// Hide plain text
					plainInput.style.display = 'none';
					maskedInput.style.display = 'block';
					if (eyeIcon) eyeIcon.style.display = 'block';
					if (eyeOffIcon) eyeOffIcon.style.display = 'none';
				}
			}
		},

		/**
		 * Handle change of access type (full vs limited).
		 */
		onAccessTypeChange: function () {
			const isFull = document.querySelector('input[name="access_type"]:checked')?.value === 'full';
			const scopesArea = document.getElementById('msh-scopes-selection-area');
			if (scopesArea) {
				scopesArea.style.display = isFull ? 'none' : 'block';
			}
			this.updateSubmitButtonState();
		},

		/**
		 * Enable / disable generate token button.
		 */
		updateSubmitButtonState: function () {
			const label = document.getElementById('msh-token-label')?.value?.trim();
			const btn = document.getElementById('msh-submit-token');
			if (!btn) return;

			const isFull = document.querySelector('input[name="access_type"]:checked')?.value === 'full';
			const hasCheckedAbilities = document.querySelectorAll('input[name="abilities[]"]:checked').length > 0;
			
			const validationMsg = document.getElementById('msh-scopes-validation-msg');
			if (!isFull && !hasCheckedAbilities) {
				if (validationMsg) validationMsg.style.display = 'block';
			} else {
				if (validationMsg) validationMsg.style.display = 'none';
			}

			// Validate IPs
			const ipsTextarea = document.getElementById('msh-token-allowed-ips');
			const ipsMsg = document.getElementById('msh-ips-validation-msg');
			let ipsValid = true;
			
			if (ipsTextarea && ipsTextarea.value.trim()) {
				const ips = ipsTextarea.value.split(',').map(s => s.trim()).filter(s => s);
				const ipRegex = /^[a-fA-F0-9\.:]+(\/\d{1,2})?$/;
				for (const ip of ips) {
					if (!ipRegex.test(ip)) {
						ipsValid = false;
						if (ipsMsg) {
							ipsMsg.textContent = 'Invalid IP or CIDR format: ' + ip;
							ipsMsg.style.display = 'block';
						}
						break;
					}
				}
			}
			
			if (ipsValid && ipsMsg) {
				ipsMsg.style.display = 'none';
			}

			if (label && (isFull || hasCheckedAbilities) && ipsValid) {
				btn.disabled = false;
				btn.classList.remove('msh-btn--disabled');
			} else {
				btn.disabled = true;
				btn.classList.add('msh-btn--disabled');
			}
		},

		/**
		 * Insert the current IP into the allowed IPs textarea.
		 * 
		 * @param {string} ip
		 */
		insertCurrentIp: function (ip) {
			const textarea = document.getElementById('msh-token-allowed-ips');
			if (!textarea) return;
			let val = textarea.value.trim();
			if (val) {
				if (!val.endsWith(',')) val += ', ';
				val += ip;
			} else {
				val = ip;
			}
			textarea.value = val;
			this.updateSubmitButtonState();
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
			// Switch modal step panels
			const createStep = document.getElementById('msh-modal-create-step');
			const createdStep = document.getElementById('msh-modal-created-step');

			if (createStep) createStep.style.display = 'none';
			if (createdStep) createdStep.style.display = 'flex';

			// Populate token values
			const plainEl = document.getElementById('msh-new-token-value');
			if (plainEl) plainEl.value = token;

			const maskedEl = document.getElementById('msh-new-token-value-masked');
			if (maskedEl) maskedEl.value = token;

			// Attach copy button handler
			const tokenBtn = document.getElementById('msh-copy-token-btn');
			if (tokenBtn) {
				tokenBtn.onclick = () => {
					window.msh.copyText('msh-new-token-value');
				};
			}

			// Force showing masked view initially
			if (plainEl) plainEl.style.display = 'none';
			if (maskedEl) maskedEl.style.display = 'block';
			
			const eyeIcon = document.getElementById('msh-eye-icon');
			const eyeOffIcon = document.getElementById('msh-eye-off-icon');
			if (eyeIcon) eyeIcon.style.display = 'block';
			if (eyeOffIcon) eyeOffIcon.style.display = 'none';

			// Populate guide details
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

			// Hide created view and show create view
			const createStep = document.getElementById('msh-modal-create-step');
			const createdStep = document.getElementById('msh-modal-created-step');

			if (createStep) createStep.style.display = 'flex';
			if (createdStep) createdStep.style.display = 'none';

			// Reset preset cards active states
			document.querySelectorAll('.msh-scope-card').forEach(card => {
				card.classList.remove('msh-scope-card--active');
			});

			// Hide custom abilities list
			const wrapper = document.getElementById('msh-custom-abilities-wrapper');
			if (wrapper) wrapper.style.display = 'none';

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

			const allowedIps = document.getElementById('msh-token-allowed-ips');
			if (allowedIps) allowedIps.value = '';
			const ipsMsg = document.getElementById('msh-ips-validation-msg');
			if (ipsMsg) ipsMsg.style.display = 'none';

			const submitBtn = document.getElementById('msh-submit-token');
			if (submitBtn) {
				submitBtn.disabled = true;
				submitBtn.classList.add('msh-btn--disabled');
				submitBtn.textContent = 'Generate token';
			}
		}
	};

}());
