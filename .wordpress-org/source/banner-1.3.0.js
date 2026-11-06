// v1.3.0 WordPress.org banner, in the v1.1.0 banner's visual system.
// Renders banner-1544x500.png and banner-772x250.png.
const puppeteer = require('puppeteer-core');
const path = require('path');
const OUT = process.argv[2];

const W = '255,255,255';
const MONO = "font-family:'IBM Plex Mono',monospace";
const SANS = "font-family:'Familjen Grotesk',sans-serif";

// The brand hand, from brand/build.py hand().
const hand = (w, h, fill) => `<svg width="${w}" height="${h}" viewBox="0 0 160 200" fill="${fill}">
<rect x="38" y="24" width="24" height="82"/><rect x="70" y="8" width="24" height="98"/>
<rect x="102" y="18" width="24" height="88"/><rect x="134" y="44" width="24" height="62"/>
<rect x="38" y="106" width="120" height="86"/><rect x="0" y="118" width="42" height="34"/></svg>`;

const check = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#0C1426" stroke-width="3.4"><polyline points="20 6 9 17 4 12"/></svg>`;

const row = (file, text, approved) => `
  <div class="row">
    <div class="rtext"><div class="file">${file}</div><div class="prop">${text}</div></div>
    ${approved
		? `<span class="tick">${check}</span>`
		: `<span class="pend">Review</span>`}
  </div>`;

const html = `<!doctype html><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Familjen+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{width:1544px;height:500px;overflow:hidden;background:#1B4FD8}
.bn{position:relative;width:1544px;height:500px;overflow:hidden;transform-origin:0 0;
  background:radial-gradient(1100px 620px at 18% 0%,#2259E6 0%,rgba(34,89,230,0) 60%),
             linear-gradient(100deg,#1C50DC 0%,#1B4FD8 45%,#1543C2 100%)}
.lft{position:absolute;left:68px;top:58px;width:760px}
.lock{display:flex;align-items:center;gap:20px}
.lock .nm{${SANS};font-weight:700;font-size:27px;letter-spacing:-.025em;color:#fff;line-height:1.05}
.lock .tg{${MONO};font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:rgba(${W},.72);margin-top:6px}
.eb{${MONO};font-size:13px;font-weight:500;letter-spacing:.2em;text-transform:uppercase;
  color:rgba(${W},.72);margin:36px 0 22px;display:flex;align-items:center;gap:14px}
.eb i{width:34px;height:1.5px;background:rgba(${W},.55);display:block}
h1{${SANS};font-weight:700;font-size:50px;line-height:1.14;letter-spacing:-.035em;color:#fff}
.sub{${SANS};font-size:20px;line-height:1.6;color:rgba(${W},.8);margin-top:20px;max-width:640px}
.by{position:absolute;left:68px;bottom:44px;${MONO};font-size:13px;letter-spacing:.2em;text-transform:uppercase;color:rgba(${W},.62)}

.score{position:absolute;left:912px;top:80px;width:552px;height:168px;background:#fff;border-radius:10px;
  box-shadow:0 30px 60px -30px rgba(5,20,70,.6);display:flex;align-items:center;padding:0 26px;gap:28px}
.sbox{width:120px;height:120px;background:#E5F3EC;border:1.5px solid #B2DCC7;display:flex;align-items:center;justify-content:center}
.sbox .n{display:flex;align-items:baseline}
.sbox b{${SANS};font-weight:700;font-size:46px;color:#0A7A4F;letter-spacing:-.02em}
.sbox span{${SANS};font-size:16px;color:#0A7A4F;opacity:.75}
.stx .band{${SANS};font-weight:700;font-size:22px;color:#0C1426}
.stx .meta{${SANS};font-size:15px;color:#666E80;margin-top:6px}
.trend{display:flex;align-items:center;gap:14px;margin-top:14px;${MONO};font-size:14px;color:#414A5C}
.trend em{font-style:normal;color:#0A7A4F;font-weight:600}

.ai{position:absolute;left:912px;top:272px;width:536px;height:198px;border-radius:10px;
  background:rgba(${W},.1);border:1px solid rgba(${W},.3);box-shadow:0 30px 60px -30px rgba(5,20,70,.55);padding:16px 22px}
.ai .lbl{margin-bottom:4px;${MONO};font-size:11.5px;letter-spacing:.18em;text-transform:uppercase;color:rgba(${W},.72)}
.row{display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(${W},.18)}
.rtext{display:flex;align-items:baseline;gap:14px;min-width:0}
.file{${MONO};font-size:12px;color:rgba(${W},.6);width:118px;flex-shrink:0}
.prop{${SANS};font-size:15.5px;color:#fff;white-space:nowrap}
.tick{width:22px;height:22px;border-radius:50%;background:#4ADE9B;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.pend{${MONO};font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;color:rgba(${W},.75);border:1px solid rgba(${W},.4);padding:2px 7px;border-radius:4px}
.foot{display:flex;align-items:center;justify-content:space-between;margin-top:14px}
.foot .t{${MONO};font-size:12px;color:rgba(${W},.72)}
.foot .btn{${SANS};font-weight:600;font-size:14px;color:#1239A8;background:#fff;padding:7px 14px;border-radius:5px}
</style>
<div class="bn" id="bn">
  <div class="lft">
    <div class="lock">${hand(34, 42, '#fff')}<div><div class="nm">My Site Hand</div><div class="tg">For WordPress</div></div></div>
    <div class="eb"><i></i>Site health · Quick Fix · AI bulk fix</div>
    <h1>Find What's Broken.<br>Fix It All at Once.</h1>
    <div class="sub">A 30-second scan for broken links, missing alt text and SEO gaps. Fix each one inline, or let AI fix them all &mdash; you approve every change.</div>
  </div>
  <div class="by">By builtbytanin</div>

  <div class="score">
    <div class="sbox"><div class="n"><b>84</b><span>/100</span></div></div>
    <div class="stx">
      <div class="band">Good</div>
      <div class="meta">Last scanned 1 minute ago</div>
      <div class="trend">
        <svg width="90" height="22"><polyline points="2,19 30,15 60,9 88,3" fill="none" stroke="#0A7A4F" stroke-width="2.4"/></svg>
        58 &rarr; 71 &rarr; 84 <em>+26</em>
      </div>
    </div>
  </div>

  <div class="ai">
    <div class="lbl">AI suggestions &middot; review before saving</div>
    ${row('team-photo.jpg', 'Studio team standing outside the office', true)}
    ${row('workbench.jpg', 'Oak workbench with chisels and a hand plane', true)}
    ${row('forklift.jpg', 'Orange forklift moving pallets in an aisle', false)}
    <div class="foot"><span class="t">2 approved &middot; est. $0.0004</span><span class="btn">Apply 2 approved</span></div>
  </div>
</div>`;

(async () => {
	const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new' });
	const page = await browser.newPage();
	await page.setViewport({ width: 1544, height: 500, deviceScaleFactor: 1 });
	await page.setContent(html, { waitUntil: 'networkidle0' });
	await page.evaluateHandle('document.fonts.ready');
	const fonts = await page.evaluate(() => [document.fonts.check('700 50px "Familjen Grotesk"'), document.fonts.check('13px "IBM Plex Mono"')]);
	// Guard against text overflowing its card.
	const overflow = await page.evaluate(() => [...document.querySelectorAll('.prop,.sub,h1')].filter((e) => e.scrollWidth > e.clientWidth + 1).length);
	await page.screenshot({ path: path.join(OUT, 'banner-1544x500.png'), clip: { x: 0, y: 0, width: 1544, height: 500 } });

	// 772x250: the same artwork at half size, rendered (not resampled) for crisp text.
	await page.setViewport({ width: 772, height: 250, deviceScaleFactor: 1 });
	await page.evaluate(() => { document.documentElement.style.cssText = 'width:772px;height:250px'; document.body.style.cssText = 'width:772px;height:250px'; document.getElementById('bn').style.transform = 'scale(0.5)'; });
	await page.screenshot({ path: path.join(OUT, 'banner-772x250.png'), clip: { x: 0, y: 0, width: 772, height: 250 } });
	console.log('fonts', fonts, 'overflowing elements', overflow);
	await browser.close();
})();
