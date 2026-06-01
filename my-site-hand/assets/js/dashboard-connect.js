/**
 * WP my-site-hand — Dashboard Connect JavaScript
 *
 * Handles the "Connect AI Client" widget on the dashboard:
 * live token input, client tab switching, OS detection,
 * and command generation.
 */

/* global mysitehandAdmin, msh */

'use strict';

(function () {

	const cfg = window.mysitehandAdmin || {};
	const i18n = cfg.i18n || {};

	// Auto-detect OS.
	function detectOs() {
		const ua = (navigator.userAgentData?.platform || navigator.platform || navigator.userAgent || '').toLowerCase();
		if (ua.includes('mac') || ua.includes('darwin')) return 'mac';
		if (ua.includes('linux') || ua.includes('android')) return 'linux';
		return 'windows';
	}

	window.mshDash = {

		selectedOs: detectOs(),
		selectedClient: 'claude',

		/**
		 * Initialize the widget.
		 */
		init: function () {
			// Auto-select OS tab.
			this.switchOs(this.selectedOs);

			// Listen for token input.
			const tokenInput = document.getElementById('msh-dash-token');
			if (tokenInput) {
				tokenInput.addEventListener('input', () => this.updateCommands());
			}
		},

		/**
		 * Switch between AI clients (Claude, Cursor, etc.)
		 * 
		 * @param {string} client - 'claude', 'cursor'
		 */
		switchClient: function (client) {
			this.selectedClient = client;

			// Update tab styles.
			const tabs = ['claude', 'cursor'];
			tabs.forEach(t => {
				const el = document.getElementById('msh-dash-client-tab-' + t);
				if (el) {
					if (t === client) el.classList.add('msh-tab-btn--active');
					else el.classList.remove('msh-tab-btn--active');
				}
			});

			// Toggle panels.
			const claudePanel = document.getElementById('msh-dash-claude-panel');
			const cursorPanel = document.getElementById('msh-dash-cursor-panel');

			if (client === 'claude') {
				if (claudePanel) claudePanel.style.display = 'block';
				if (cursorPanel) cursorPanel.style.display = 'none';
			} else {
				if (claudePanel) claudePanel.style.display = 'none';
				if (cursorPanel) cursorPanel.style.display = 'block';
			}

			this.updateCommands();
		},

		/**
		 * Switch the active OS tab in the Claude connection section.
		 * 
		 * @param {string} os - 'windows', 'mac', 'linux'
		 */
		switchOs: function (os) {
			this.selectedOs = os;

			// Update tab styles.
			const tabs = ['windows', 'mac', 'linux'];
			tabs.forEach(t => {
				const el = document.getElementById('msh-dash-os-tab-' + t);
				if (el) {
					if (t === os) el.classList.add('msh-os-tab--active');
					else el.classList.remove('msh-os-tab--active');
				}
			});

			this.updateCommands();
		},

		/**
		 * Update the generated commands and fields based on the token and selection.
		 */
		updateCommands: function () {
			const token = document.getElementById('msh-dash-token')?.value?.trim() || '';
			const endpoint = cfg.mcpEndpoint || '';

			if (this.selectedClient === 'claude') {
				const step1Input = document.getElementById('msh-dash-claude-step-1');
				const step2Input = document.getElementById('msh-dash-claude-step-2');

				if (step1Input) {
					step1Input.value = (this.selectedOs === 'mac' ? 'sudo ' : '') + 'npm install -g mcp-remote';
				}

				if (step2Input) {
					if (!token) {
						step2Input.value = '';
						step2Input.placeholder = 'Paste token first...';
						return;
					}

					let command = '';
					if (this.selectedOs === 'windows') {
						command = `node -e "const fs=require('fs'),os=require('os'),path=require('path'),cp=require('child_process');const home=os.homedir();const p=path.join(home,'AppData','Roaming','Claude','claude_desktop_config.json');const dir=path.dirname(p);if(!fs.existsSync(dir))fs.mkdirSync(dir,{recursive:true});let d={};try{d=JSON.parse(fs.readFileSync(p,'utf8'))}catch(e){};if(!d.mcpServers)d.mcpServers={};const proxy=path.join(cp.execSync('npm root -g').toString().trim(),'mcp-remote','dist','proxy.js');d.mcpServers['my-site-hand']={command:'node',args:[proxy,'${endpoint}','--header','Authorization: Bearer ${token}']};fs.writeFileSync(p,JSON.stringify(d,null,2));console.log('Done');"`;
					} else if (this.selectedOs === 'mac') {
						command = `node -e 'const fs=require("fs"),os=require("os"),path=require("path"),cp=require("child_process");const home=os.homedir();const configPath=path.join(home,"Library","Application Support","Claude","claude_desktop_config.json");const dir=path.dirname(configPath);if(!fs.existsSync(dir))fs.mkdirSync(dir,{recursive:true});let d={};try{d=JSON.parse(fs.readFileSync(configPath,"utf8"))}catch(e){};if(!d.mcpServers)d.mcpServers={};const proxy=path.join(cp.execSync("npm root -g").toString().trim(),"mcp-remote","dist","proxy.js");d.mcpServers["my-site-hand"]={command:"node",args:[proxy,"${endpoint}","--header","Authorization: Bearer ${token}"]};fs.writeFileSync(configPath,JSON.stringify(d,null,2));console.log("Done. Restart Claude Desktop.");'`;
					} else {
						// Linux
						command = `node -e 'const fs=require("fs"),os=require("os"),path=require("path"),cp=require("child_process");const home=os.homedir();const configPath=path.join(home,".config","Claude","claude_desktop_config.json");const dir=path.dirname(configPath);if(!fs.existsSync(dir))fs.mkdirSync(dir,{recursive:true});let d={};try{d=JSON.parse(fs.readFileSync(configPath,"utf8"))}catch(e){};if(!d.mcpServers)d.mcpServers={};const proxy=path.join(cp.execSync("npm root -g").toString().trim(),"mcp-remote","dist","proxy.js");d.mcpServers["my-site-hand"]={command:"node",args:[proxy,"${endpoint}","--header","Authorization: Bearer ${token}"]};fs.writeFileSync(configPath,JSON.stringify(d,null,2));console.log("Done. Restart Claude Desktop.");'`;
					}

					step2Input.value = command;
				}
			} else {
				// Cursor / IDEs
				const authInput = document.getElementById('msh-dash-cursor-auth');
				if (authInput) {
					authInput.value = token ? 'Bearer ' + token : '';
				}
			}
		},
	};

	// Initialize on DOM ready.
	document.addEventListener('DOMContentLoaded', function () {
		window.mshDash.init();
	});

}());
