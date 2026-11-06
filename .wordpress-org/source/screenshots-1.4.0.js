// v1.4.0 WordPress.org screenshots 1-16 (PNG, 1600x1000).
//
//   node screenshots-1.4.0.js <out-dir> <shots-dir>
//
// <shots-dir> holds real captures from a live install: admin UI at 2x
// (ui-*.png) and starter site renders (r-*.png, home-*.png). Every screen in
// a frame is a capture; only the backgrounds, headlines and callouts are
// drawn here.
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');
const OUT = process.argv[2];
const SHOTS = process.argv[3];

const W = 1600;
const H = 1000;
const cache = {};
const src = (f) => (cache[f] = cache[f] || 'data:image/png;base64,' + fs.readFileSync(path.join(SHOTS, f)).toString('base64'));
const size = (f) => { const b = fs.readFileSync(path.join(SHOTS, f)); return [b.readUInt32BE(16), b.readUInt32BE(20)]; };

/** A region of a capture, shown at a given width. */
function crop(f, r, dispW, style = '') {
	const [nw, nh] = size(f);
	r = Object.assign({ x: 0, y: 0, w: nw, h: nh }, r);
	const k = dispW / r.w;
	return `<div class="crop" style="width:${dispW}px;height:${Math.round(r.h * k)}px;background-image:url(${src(f)});background-size:${nw * k}px ${nh * k}px;background-position:${-r.x * k}px ${-r.y * k}px;${style}"></div>`;
}
/** The region starting at (x, y), w source pixels wide, that fills dispW x dispH. */
const fit = (f, x, y, w, dispW, dispH, style = '') => crop(f, { x, y, w, h: Math.round(w * dispH / dispW) }, dispW, style);
const win = (inner, style = '', url = 'yoursite.com/wp-admin', cls = '') => `<div class="win ${cls}" style="${style}"><div class="bar"><i></i><i></i><i></i><span>${url}</span></div>${inner}</div>`;
const card = (inner, style = '') => `<div class="card" style="${style}">${inner}</div>`;
const phone = (inner, style = '') => `<div class="phone" style="${style}"><div class="pscr">${inner}</div></div>`;
const chip = (icon, t, s, style, tone = 'blue') => `<div class="chip" style="${style}"><div class="ic ${tone}">${ICON[icon]}</div><div><div class="t">${t}</div>${s ? `<div class="s">${s}</div>` : ''}</div></div>`;
const head = (n, eb, h1, sub, style = '') => `<div class="head" style="${style}"><div class="eb"><b>${String(n).padStart(2, '0')}</b><i></i>${eb}</div><h1>${h1}</h1>${sub ? `<p>${sub}</p>` : ''}</div>`;

const ICON = {
	check: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
	spark: '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.2 6.6L21 11l-6.8 2.4L12 20l-2.2-6.6L3 11l6.8-2.4z"/></svg>',
	undo: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/></svg>',
	eye: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>',
	lock: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
	type: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M4 7V4h16v3M9 20h6M12 4v16"/></svg>',
	plus: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
	link: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>',
	pulse: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="2 12 6 12 9 4 15 20 18 12 22 12"/></svg>',
	plug: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M9 2v6M15 2v6M6 8h12v4a6 6 0 0 1-12 0zM12 18v4"/></svg>',
	list: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>',
};

const CSS = `
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:${W}px;height:${H}px;overflow:hidden}
.cv{position:relative;width:${W}px;height:${H}px;overflow:hidden;font-family:'Familjen Grotesk',sans-serif}
.dark{color:#fff;background:
  radial-gradient(900px 600px at 85% 30%,rgba(64,120,255,.45),rgba(64,120,255,0) 70%),
  radial-gradient(700px 500px at 0% 100%,rgba(124,92,255,.30),rgba(124,92,255,0) 70%),
  linear-gradient(125deg,#0A1230 0%,#0E1B48 55%,#142E80 100%)}
.light{color:#0C1426;background:
  radial-gradient(800px 560px at 90% 0%,rgba(27,79,216,.13),rgba(27,79,216,0) 70%),
  radial-gradient(700px 520px at 0% 100%,rgba(124,92,255,.10),rgba(124,92,255,0) 70%),
  linear-gradient(180deg,#F5F7FE 0%,#EAF0FD 100%)}
.dots{position:absolute;inset:0;background-image:radial-gradient(currentColor 1px,transparent 1.3px);background-size:24px 24px;opacity:.07}
.head{position:absolute;left:72px;top:64px;width:640px;z-index:5}
.eb{font-family:'IBM Plex Mono',monospace;font-size:14px;font-weight:500;letter-spacing:.2em;text-transform:uppercase;display:flex;align-items:center;gap:12px;margin-bottom:20px}
.dark .eb{color:#8FB0FF}.light .eb{color:#1B4FD8}
.eb b{font-weight:600}.eb i{width:28px;height:1.5px;background:currentColor;opacity:.7;display:block}
h1{font-weight:700;font-size:56px;line-height:1.04;letter-spacing:-.04em}
h1 em{font-style:normal}
.dark h1 em{background:linear-gradient(90deg,#9CC0FF,#E7D3FF);-webkit-background-clip:text;color:transparent}
.light h1 em{background:linear-gradient(90deg,#1B4FD8,#7C5CFF);-webkit-background-clip:text;color:transparent}
.head p{font-size:21px;line-height:1.5;margin-top:18px}
.dark .head p{color:rgba(255,255,255,.76)}.light .head p{color:#4A5470}
.crop{background-repeat:no-repeat;display:block}
.win{position:absolute;border-radius:14px;overflow:hidden;background:#fff;
  box-shadow:0 50px 100px -40px rgba(8,18,60,.55),0 18px 40px -20px rgba(8,18,60,.35),0 0 0 1px rgba(12,20,38,.07)}
.dark .win{box-shadow:0 50px 100px -30px rgba(0,0,0,.75),0 0 0 1px rgba(255,255,255,.08)}
.win .bar{height:34px;background:#F1F3F8;display:flex;align-items:center;gap:7px;padding:0 14px;border-bottom:1px solid #E3E7EF}
.win .bar i{width:11px;height:11px;border-radius:50%;background:#D3D8E2;display:block}
.win .bar i:nth-child(1){background:#FF6159}.win .bar i:nth-child(2){background:#FFBD2E}.win .bar i:nth-child(3){background:#28C941}
.win .bar span{margin-left:12px;height:20px;width:280px;border-radius:10px;background:#fff;font-family:'IBM Plex Mono',monospace;font-size:11px;color:#7A8296;display:flex;align-items:center;padding:0 10px}
.card{position:absolute;border-radius:14px;overflow:hidden;background:#fff;
  box-shadow:0 40px 80px -30px rgba(8,18,60,.55),0 0 0 1px rgba(12,20,38,.08)}
.phone{position:absolute;border-radius:40px;background:#0C1426;padding:11px;box-shadow:0 50px 90px -30px rgba(0,0,0,.7),0 0 0 2px rgba(255,255,255,.12)}
.pscr{border-radius:30px;overflow:hidden;background:#000}
.chip{position:absolute;z-index:10;display:flex;align-items:center;gap:12px;background:#fff;color:#0C1426;border-radius:14px;padding:12px 18px 12px 12px;
  box-shadow:0 26px 50px -20px rgba(8,18,60,.55),0 0 0 1px rgba(12,20,38,.06)}
.chip .ic{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ic.blue{background:#E8EEFF;color:#1B4FD8}.ic.green{background:#E3F8EE;color:#0A7A4F}.ic.violet{background:linear-gradient(135deg,#7C5CFF,#1B4FD8);color:#fff}.ic.amber{background:#FFF3DC;color:#A15C00}
.chip .t{font-weight:700;font-size:17px;white-space:nowrap}
.chip .s{font-family:'IBM Plex Mono',monospace;font-size:12px;color:#6A7388;margin-top:2px;white-space:nowrap}
.pills{display:flex;flex-wrap:wrap;gap:10px}
.pills span{font-size:16px;font-weight:600;padding:9px 15px;border-radius:30px}
.dark .pills span{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff}
.light .pills span{background:#fff;border:1px solid #D9E1F5;color:#0C1426}
.tag{position:absolute;z-index:6;font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;padding:7px 12px;border-radius:8px}
`;

const page = (theme, body) => `<!doctype html><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<style>${CSS}</style><div class="cv ${theme}"><div class="dots"></div>${body}</div>`;

const homes = ['home-agency.png', 'home-home-services.png', 'home-fitness.png', 'home-salon.png', 'home-real-estate.png', 'home-law.png', 'home-coach.png', 'home-clinic.png', 'r-default.png', 'home-portfolio.png'];
const site = (f, dispW, h) => crop(f, { x: 0, y: 0, w: 1920, h: Math.round(1920 * h / dispW) }, dispW);

const shots = {
	// 1. The wall of finished sites.
	'screenshot-1': page('dark', `
		${head(1, 'Ten starter sites', 'Your new website is <em>already built.</em>', 'Real businesses, real example text, five pages each. Pick the closest one and make it yours.', 'width:560px')}
		<div class="pills" style="position:absolute;left:72px;top:440px;width:540px;z-index:5">${['Business', 'Home services', 'Fitness', 'Salon and spa', 'Real estate', 'Law firm', 'Coach', 'Clinic', 'Restaurant', 'Portfolio'].map((t) => `<span>${t}</span>`).join('')}</div>
		${chip('check', 'Free, no account', 'All ten sites included', 'left:72px;top:690px', 'green')}
		${chip('lock', 'No lock-in', 'Plain WordPress blocks', 'left:72px;top:790px')}
		<div style="position:absolute;left:720px;top:-40px;width:1300px;height:1300px;perspective:2200px">
			<div style="position:absolute;left:0;top:0;display:grid;grid-template-columns:repeat(3,400px);gap:28px;transform:rotateX(22deg) rotateY(-16deg) rotateZ(8deg);transform-origin:30% 30%">
				${homes.concat(['r-dark.png', 'r-sea.png']).map((f) => card(site(f, 400, 268), 'position:relative;border-radius:10px')).join('')}
			</div>
		</div>`),

	// 2. Gallery.
	'screenshot-2': page('light', `
		${head(2, 'Choose', 'Pick the one closest to <em>your business.</em>', '', 'width:1200px')}
		${win(fit('ui-gallery.png', 0, 230, 2520, 1456, 760), 'left:72px;top:220px', 'yoursite.com/wp-admin › Site Builder')}
		${chip('eye', 'Preview any site first', 'Screenshots of every page', 'left:1210px;top:150px')}
		${chip('check', 'Nothing changes until you say yes', 'Edits stay in a private draft', 'left:1150px;top:880px', 'green')}`),

	// 3. Editor by hand.
	'screenshot-3': page('light', `
		${head(3, 'Customize', 'Edit every word and picture. <em>No code.</em>', 'Every heading, paragraph, button and image has its own field. Changes save as a private draft.', 'width:520px')}
		${win(fit('ui-editor.png', 0, 230, 1500, 930, 900), 'left:620px;top:64px', 'yoursite.com/wp-admin › Site Builder')}
		${chip('type', 'Type your name once', 'It fills every page', 'left:72px;top:430px')}
		${win(site('r-filled.png', 560, 330), 'left:72px;top:560px;width:560px;z-index:3', 'fornorosso.com')}`),

	// 4. One site, any look.
	'screenshot-4': page('dark', `
		${head(4, 'Design', 'One starter site.<br><em>Any look you want.</em>', 'Colours, fonts, light or dark, corner style and spacing, for the whole site at once.', 'width:680px')}
		${win(site('r-default.png', 600, 400), 'left:72px;top:330px;width:600px;transform:rotate(-2deg)', 'yoursite.com')}
		${win(site('r-sea.png', 600, 400), 'left:500px;top:420px;width:600px;z-index:2', 'yoursite.com')}
		${win(site('r-olive.png', 600, 400), 'left:930px;top:330px;width:600px;transform:rotate(2deg)', 'yoursite.com')}
		${card(crop('ui-design.png', { x: 0, y: 1100, w: 1300, h: 430 }, 620), 'left:900px;top:70px;z-index:4')}
		${chip('check', 'Every colour passes WCAG AA', 'Checked automatically', 'left:72px;top:880px', 'green')}`),

	// 5. Layout.
	'screenshot-5': page('light', `
		${head(5, 'Layout', 'Move, hide or add <em>sections.</em>', 'Change the order of any page, hide what you do not need, and add new sections that match your design.', 'width:560px')}
		${win(crop('ui-layout-home.png', {}, 900), 'left:640px;top:64px;z-index:3', 'yoursite.com/wp-admin › Site Builder')}
		${win(crop('r-dark-full.png', { y: 4180, h: 1060 }, 760), 'left:790px;top:590px;width:760px;z-index:2', 'fornorosso.com')}
		<div class="tag" style="left:1420px;top:650px;background:#1B4FD8;color:#fff;z-index:4">Added</div>
		<div style="position:absolute;left:72px;top:470px;width:540px;z-index:5">
			<div style="font-family:'IBM Plex Mono',monospace;font-size:13px;letter-spacing:.16em;text-transform:uppercase;color:#1B4FD8;margin-bottom:14px">10 sections you can add</div>
			<div class="pills">${['Features', 'Steps', 'Numbers', 'Testimonial', 'Questions', 'Pricing', 'Team', 'Image and text', 'Call to action', 'Text'].map((t) => `<span>${t}</span>`).join('')}</div>
		</div>
		${chip('plus', 'Added sections match your site', 'Same colours, fonts and corners', 'left:72px;top:800px')}
		${chip('undo', 'Hidden sections keep their text', '', 'left:72px;top:900px', 'green')}`),

	// 6. Redesign with AI: before and after.
	'screenshot-6': page('dark', `
		${head(6, 'Redesign with AI', 'Describe it. <em>AI redesigns it.</em>', '', 'width:1200px')}
		${card(crop('ui-redesign-ask.png', { h: 440 }, 560), 'left:72px;top:250px;z-index:3')}
		${card(crop('ui-redesign-review.png', {}, 560), 'left:72px;top:470px;z-index:4')}
		<div style="position:absolute;left:690px;top:250px;width:840px;height:560px;z-index:2">
			${win(site('r-filled.png', 840, 526), 'left:0;top:0;width:840px', 'fornorosso.com')}
			${win(site('r-dark.png', 840, 526), 'left:0;top:0;width:840px;clip-path:polygon(52% 0,100% 0,100% 100%,40% 100%)', 'fornorosso.com')}
			<div style="position:absolute;left:0;top:0;width:840px;height:560px;pointer-events:none">
				<div style="position:absolute;left:46%;top:0;bottom:0;width:4px;background:#fff;transform:skewX(-12deg);box-shadow:0 0 20px rgba(0,0,0,.4)"></div>
			</div>
			<div class="tag" style="left:20px;bottom:20px;background:#fff;color:#0C1426">Before</div>
			<div class="tag" style="right:20px;bottom:20px;background:#D6A84E;color:#1A1408">After</div>
		</div>
		${chip('spark', 'You see every change first', 'Then one click to undo', 'left:1040px;top:860px', 'violet')}`),

	// 7. Fill with AI.
	'screenshot-7': page('light', `
		${head(7, 'Write with AI', 'AI writes your text. <em>You approve every word.</em>', '', 'width:1300px')}
		${card(crop('ui-fill-review.png', { h: 1180 }, 1060), 'left:72px;top:250px')}
		${card(crop('ui-fill-brief.png', { h: 700 }, 420), 'left:1110px;top:300px;z-index:3')}
		${chip('lock', 'Your facts stay yours', 'Phone, email and address are never filled by AI', 'left:1010px;top:700px', 'green')}
		${chip('spark', 'Your own key, a daily cap', 'Claude or OpenAI', 'left:1110px;top:820px', 'violet')}`),

	// 8. Preview at three sizes.
	'screenshot-8': page('dark', `
		${head(8, 'Preview', 'See it on your own site <em>before anyone else.</em>', '', 'width:1300px')}
		${win(crop('ui-preview-desktop.png', {}, 1100), 'left:72px;top:250px', 'yoursite.com/?preview')}
		${phone(crop('ui-preview-mobile.png', { x: 1050, y: 150, w: 780, h: 1650 }, 300), 'left:1200px;top:300px')}
		${chip('eye', 'Only you can see the preview', 'Desktop, tablet and phone', 'left:860px;top:880px')}`),

	// 9. Apply.
	'screenshot-9': page('light', `
		${head(9, 'Apply', 'Nothing goes live <em>until you say yes.</em>', 'You see exactly what will change. A snapshot is taken first, so you can always go back.', 'width:620px')}
		<div style="position:absolute;left:700px;top:90px;width:840px;height:820px;border-radius:18px;overflow:hidden;filter:saturate(.9)">${crop('r-dark-full.png', { y: 0, h: 1880 }, 840)}</div>
		<div style="position:absolute;left:700px;top:90px;width:840px;height:820px;border-radius:18px;background:rgba(10,18,48,.55)"></div>
		${card(crop('ui-apply.png', {}, 640), 'left:800px;top:200px;z-index:3')}
		${chip('check', 'A snapshot is taken first', 'Undo any time from Site history', 'left:72px;top:460px', 'green')}
		${chip('lock', 'No surprises', 'Your posts are not changed', 'left:72px;top:560px')}`),

	// 10. Customize the live site.
	'screenshot-10': page('dark', `
		${head(10, 'Customize my site', 'Change your live site <em>any time.</em>', 'Open it again with all your edits, change anything, then update. Same web addresses, and Undo brings the old version back.', 'width:640px')}
		${card(crop('ui-live-panel.png', {}, 820), 'left:720px;top:110px;z-index:3')}
		${card(crop('ui-toolbar-live.png', {}, 820), 'left:720px;top:220px;z-index:3')}
		${card(crop('ui-update-plan.png', {}, 620), 'left:820px;top:330px;z-index:4')}
		${chip('link', 'Links keep working', 'Pages keep their web addresses', 'left:72px;top:520px')}
		${chip('undo', 'Undo brings the old version back', '', 'left:72px;top:620px', 'green')}`),

	// 11. Site history and Undo.
	'screenshot-11': page('light', `
		${head(11, 'Undo', 'Changed your mind? <em>One click.</em>', '', 'width:1300px')}
		${card(crop('ui-history.png', {}, 1200), 'left:72px;top:240px')}
		${card(crop('ui-undo.png', {}, 640), 'left:880px;top:520px;z-index:3')}
		${chip('check', 'Moved to the trash, never deleted', 'You can restore anything', 'left:72px;top:820px', 'green')}`),

	// 12. Site health.
	'screenshot-12': page('light', `
		${head(12, 'Site health', 'Find what is wrong <em>in seconds.</em>', 'Broken links, missing alt text, SEO gaps and more, in one plain list with a score out of 100.', 'width:560px')}
		${win(fit('ui-health.png', 30, 250, 1900, 930, 900), 'left:620px;top:64px', 'yoursite.com/wp-admin › Site Health')}
		${chip('pulse', '12 checks, one click', 'No setup needed', 'left:72px;top:470px')}
		${chip('check', 'Fix from the report', 'Alt text, links, unused files', 'left:72px;top:570px', 'green')}`),

	// 13. Dashboard.
	'screenshot-13': page('light', `
		${head(13, 'Dashboard', 'Your site and your AI tools, <em>in one place.</em>', '', 'width:1300px')}
		${win(fit('ui-dashboard.png', 0, 250, 2520, 1456, 780), 'left:72px;top:220px', 'yoursite.com/wp-admin › My Site Hand')}
		${chip('pulse', 'Health score and AI activity', 'Side by side', 'left:1180px;top:150px')}`),

	// 14. MCP.
	'screenshot-14': page('dark', `
		${head(14, 'MCP server', 'Build your site from <em>Claude, Cursor or VS Code.</em>', 'Your AI assistant can choose a starter site, fill it in and send you a preview link. Nothing goes live until you confirm.', 'width:530px')}
		${win(fit('ui-docs.png', 0, 250, 1480, 900, 880), 'left:640px;top:64px', 'yoursite.com/wp-admin › How to use')}
		${chip('plug', 'Optional extra', 'The Site Builder needs none of it', 'left:72px;top:560px')}
		${chip('lock', 'Apply needs your confirmation', '', 'left:72px;top:660px', 'green')}`),

	// 15. Abilities and tokens.
	'screenshot-15': page('light', `
		${head(15, 'Permissions', 'You decide what <em>AI tools can do.</em>', 'Every ability is its own switch, and every token is limited to what you allow.', 'width:560px')}
		${win(fit('ui-abilities.png', 0, 250, 1700, 900, 880), 'left:640px;top:64px', 'yoursite.com/wp-admin › Abilities')}
		${win(fit('ui-tokens.png', 0, 250, 1900, 640, 470), 'left:72px;top:440px;z-index:3', 'yoursite.com/wp-admin › API Tokens')}`),

	// 16. Audit log.
	'screenshot-16': page('light', `
		${head(16, 'Audit log', 'Every action is <em>written down.</em>', 'Who did what, when and how it ended. Builds, AI requests and every MCP call. Export it to CSV.', 'width:560px')}
		${win(fit('ui-audit.png', 0, 250, 1580, 930, 880), 'left:620px;top:64px', 'yoursite.com/wp-admin › Audit Log')}
		${chip('list', 'Kept for 14 days', 'Exportable to CSV', 'left:72px;top:500px')}`),
};

(async () => {
	const browser = await puppeteer.launch({ executablePath: process.env.MSH_BROWSER || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new' });
	const only = process.argv[4] ? process.argv[4].split(',') : null;
	for (const [name, html] of Object.entries(shots)) {
		if (only && !only.includes(name)) {
			continue;
		}
		const p = await browser.newPage();
		await p.setViewport({ width: W, height: H, deviceScaleFactor: 1 });
		await p.setContent(html, { waitUntil: 'load', timeout: 120000 });
		await p.evaluateHandle('document.fonts.ready');
		await p.screenshot({ path: path.join(OUT, name + '.png') });
		console.log('wrote', name + '.png');
		await p.close();
	}
	await browser.close();
})();
