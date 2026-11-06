// Dev tooling (never shipped). Real block validation with @wordpress/blocks:
//   cd tools/starter-sites && npm install && node validate-blocks.cjs ../../starter-sites/*/pages/*.html
// Also run it on `wp my-site-hand validate-starter-sites --dump=<dir>` output to check resolved markup.
// Usage: node validate.cjs file1.html [file2.html ...]
// Parses each file with the real @wordpress/blocks parser after registering
// core blocks, and reports any block that fails save-output validation.
const { JSDOM } = require('jsdom');
const dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'http://localhost/' });
global.window = dom.window;
global.document = dom.window.document;
for (const k of ['navigator', 'Node', 'HTMLElement', 'Element', 'DOMParser', 'MutationObserver', 'getComputedStyle', 'CustomEvent', 'Event', 'KeyboardEvent', 'MouseEvent', 'requestAnimationFrame', 'cancelAnimationFrame', 'localStorage', 'sessionStorage', 'Range', 'Text', 'HTMLDocument', 'DocumentFragment']) {
	if (!(k in global) && dom.window[k]) {
		try { global[k] = dom.window[k]; } catch (e) { Object.defineProperty(global, k, { value: dom.window[k] }); }
	}
}
window.matchMedia = window.matchMedia || (() => ({ matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} }));
global.matchMedia = window.matchMedia;
global.requestAnimationFrame = global.requestAnimationFrame || ((cb) => setTimeout(cb, 0));
window.requestAnimationFrame = global.requestAnimationFrame;
global.ResizeObserver = window.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} };
global.IntersectionObserver = window.IntersectionObserver = class { observe() {} unobserve() {} disconnect() {} };

const fs = require('fs');
const warn = console.warn, err = console.error, info = console.info;
const logs = [];
console.warn = (...a) => logs.push(a.join(' '));
console.error = (...a) => logs.push(a.join(' '));
console.info = () => {};

const { registerCoreBlocks } = require('@wordpress/block-library');
const blocks = require('@wordpress/blocks');
registerCoreBlocks();

let failures = 0;
function walk(list, file, path) {
	for (const b of list) {
		const here = path + '/' + b.name;
		if (!b.isValid) {
			failures++;
			const issues = (b.validationIssues || []).map((i) => {
				try { return require('util').format(...i.args); } catch (e) { return String(i.args); }
			});
			console.log(`INVALID ${file} ${here}\n  ${issues.join('\n  ')}`);
		}
		if (b.name === 'core/missing' || b.name === 'core/freeform') {
			failures++;
			console.log(`UNKNOWN ${file} ${here} ${b.attributes.originalName || ''}`);
		}
		walk(b.innerBlocks || [], file, here);
	}
}
for (const file of process.argv.slice(2)) {
	const html = fs.readFileSync(file, 'utf8');
	const parsed = blocks.parse(html);
	walk(parsed, file, '');
	console.log(`checked ${file}: ${parsed.length} top-level blocks`);
}
console.warn = warn; console.error = err;
console.log(failures ? `FAILED: ${failures} invalid block(s)` : 'OK: all blocks valid');
process.exit(failures ? 1 : 0);
