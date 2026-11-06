// v1.4.0 WordPress.org banner. Renders banner-1544x500.png and banner-772x250.png.
//
//   node banner-1.4.0.js <out-dir> <shots-dir>
//
// <shots-dir> holds real renders of the Restaurant starter site from a live
// install: r-default.png, r-dark.png and r-sea.png (1280x860 at 1.5x), and
// r-default-mobile.png (390x844 at 3x). Nothing on a screen is drawn by hand.
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');
const OUT = process.argv[2];
const SHOTS = process.argv[3];

const img = (f) => 'data:image/png;base64,' + fs.readFileSync(path.join(SHOTS, f)).toString('base64');
const MONO = "font-family:'IBM Plex Mono',monospace";
const SANS = "font-family:'Familjen Grotesk',sans-serif";

// The brand hand, from brand/build.py hand().
const hand = (w, h, fill) => `<svg width="${w}" height="${h}" viewBox="0 0 160 200" fill="${fill}">
<rect x="38" y="24" width="24" height="82"/><rect x="70" y="8" width="24" height="98"/>
<rect x="102" y="18" width="24" height="88"/><rect x="134" y="44" width="24" height="62"/>
<rect x="38" y="106" width="120" height="86"/><rect x="0" y="118" width="42" height="34"/></svg>`;

const browserCard = (file, cls) => `<div class="win ${cls}"><div class="bar"><i></i><i></i><i></i><span>yoursite.com</span></div><div class="scr" style="background-image:url(${img(file)})"></div></div>`;

const html = `<!doctype html><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:1544px;height:500px;overflow:hidden;background:#0A1230}
.bn{position:relative;width:1544px;height:500px;overflow:hidden;
  background:
    radial-gradient(700px 420px at 78% 40%,rgba(64,120,255,.55),rgba(64,120,255,0) 70%),
    radial-gradient(520px 380px at 8% 110%,rgba(27,79,216,.55),rgba(27,79,216,0) 70%),
    linear-gradient(115deg,#0A1230 0%,#0E1B48 50%,#132B78 100%)}
.grid{position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.09) 1px,transparent 1.2px);background-size:22px 22px;
  -webkit-mask-image:linear-gradient(90deg,rgba(0,0,0,.9),rgba(0,0,0,.15) 55%,rgba(0,0,0,0))}
.lft{position:absolute;left:68px;top:54px;width:690px}
.lock{display:flex;align-items:center;gap:16px}
.lock .nm{${SANS};font-weight:700;font-size:26px;letter-spacing:-.02em;color:#fff}
.eb{${MONO};font-size:12.5px;font-weight:500;letter-spacing:.22em;text-transform:uppercase;color:#8FB0FF;margin:40px 0 18px;display:flex;align-items:center;gap:12px}
.eb i{width:30px;height:1.5px;background:#8FB0FF;display:block}
h1{${SANS};font-weight:700;font-size:58px;line-height:1.04;letter-spacing:-.04em;color:#fff}
h1 em{font-style:normal;background:linear-gradient(90deg,#9CC0FF,#E7D3FF);-webkit-background-clip:text;color:transparent}
.sub{${SANS};font-size:19px;line-height:1.55;color:rgba(255,255,255,.78);margin-top:20px;max-width:600px}
.ticks{display:flex;gap:22px;margin-top:26px}
.ticks span{${SANS};font-size:15px;font-weight:600;color:#fff;display:flex;align-items:center;gap:8px}
.ticks b{width:20px;height:20px;border-radius:50%;background:#3DDC97;display:flex;align-items:center;justify-content:center}

.stage{position:absolute;left:770px;top:0;width:800px;height:500px;perspective:1600px}
.win{position:absolute;width:560px;border-radius:12px;overflow:hidden;background:#fff;
  box-shadow:0 40px 80px -30px rgba(0,0,0,.75),0 0 0 1px rgba(255,255,255,.08)}
.win .bar{height:26px;background:#F1F3F8;display:flex;align-items:center;gap:6px;padding:0 11px}
.win .bar i{width:9px;height:9px;border-radius:50%;background:#D3D8E2;display:block}
.win .bar span{margin-left:10px;height:16px;flex:1;max-width:220px;border-radius:8px;background:#fff;${MONO};font-size:9.5px;color:#7A8296;display:flex;align-items:center;padding:0 9px}
.win .scr{height:350px;background-size:100% auto;background-position:top center;background-repeat:no-repeat}
.w1{left:40px;top:92px;transform:rotateY(18deg) rotateZ(-2deg) scale(.86);opacity:.95}
.w2{left:300px;top:50px;transform:rotateY(-14deg) rotateZ(1.5deg) scale(.86);opacity:.95}
.w3{left:150px;top:118px;width:600px;transform:rotateX(4deg)}
.w3 .scr{height:376px}

.phone{position:absolute;left:640px;top:200px;width:128px;height:262px;border-radius:22px;background:#0C1426;padding:6px;
  box-shadow:0 30px 60px -20px rgba(0,0,0,.8),0 0 0 1px rgba(255,255,255,.12)}
.phone div{width:100%;height:100%;border-radius:17px;background:url(${img('r-default-mobile.png')}) top center/100% auto no-repeat}

.chip{position:absolute;display:flex;align-items:center;gap:10px;background:#fff;border-radius:12px;padding:10px 14px 10px 10px;
  box-shadow:0 22px 44px -18px rgba(0,0,0,.7)}
.chip .ic{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.chip .t{${SANS};font-weight:700;font-size:14px;color:#0C1426;white-space:nowrap}
.chip .s{${MONO};font-size:10px;letter-spacing:.04em;color:#6A7388;margin-top:1px;white-space:nowrap}
.c1{left:860px;top:38px}
.c2{left:1300px;top:420px}
.sw{position:absolute;left:1490px;top:150px;display:flex;flex-direction:column;gap:8px;padding:9px 7px;border-radius:20px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25)}
.sw b{width:20px;height:20px;border-radius:50%;display:block;border:2px solid rgba(255,255,255,.9)}
</style>
<div class="bn">
  <div class="grid"></div>
  <div class="lft">
    <div class="lock">${hand(28, 35, '#fff')}<div class="nm">My Site Hand</div></div>
    <div class="eb"><i></i>Free site builder for WordPress</div>
    <h1>Your new website,<br><em>built in minutes.</em></h1>
    <div class="sub">Ten starter sites in plain WordPress blocks. Make one yours by hand or with AI, preview it first, and undo anytime.</div>
    <div class="ticks">
      <span><b>${tick()}</b>No lock-in</span><span><b>${tick()}</b>Preview first</span><span><b>${tick()}</b>Undo anytime</span>
    </div>
  </div>
  <div class="stage">
    ${browserCard('r-sea.png', 'w1')}
    ${browserCard('r-dark.png', 'w2')}
    ${browserCard('r-default.png', 'w3')}
  </div>
  <div class="phone" style="left:1405px;top:214px"><div></div></div>
  <div class="chip c1"><div class="ic" style="background:linear-gradient(135deg,#7C5CFF,#1B4FD8)">${spark()}</div><div><div class="t">Redesign with AI</div><div class="s">"Make it dark and warm"</div></div></div>
  <div class="chip c2"><div class="ic" style="background:#E8FBF2">${undo()}</div><div><div class="t">Undo anytime</div><div class="s">Snapshot before every apply</div></div></div>
</div>`;

function tick() {
	return '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#06281A" stroke-width="3.6"><polyline points="20 6 9 17 4 12"/></svg>';
}
function spark() {
	return '<svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M12 2l2.2 6.6L21 11l-6.8 2.4L12 20l-2.2-6.6L3 11l6.8-2.4z"/></svg>';
}
function undo() {
	return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0A7A4F" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/></svg>';
}

(async () => {
	const browser = await puppeteer.launch({ executablePath: process.env.MSH_BROWSER || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new' });
	for (const [w, h, dsf] of [[1544, 500, 1], [772, 250, 0.5]]) {
		const page = await browser.newPage();
		await page.setViewport({ width: 1544, height: 500, deviceScaleFactor: dsf });
		await page.setContent(html, { waitUntil: 'networkidle0' });
		await page.evaluateHandle('document.fonts.ready');
		const file = path.join(OUT, `banner-${w}x${h}.png`);
		await page.screenshot({ path: file, clip: { x: 0, y: 0, width: 1544, height: 500 } });
		console.log('wrote', file);
		await page.close();
	}
	await browser.close();
})();
