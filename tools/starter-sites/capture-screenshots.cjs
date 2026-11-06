/**
 * Capture gallery screenshots of a starter site from the live preview.
 *
 *   node capture-screenshots.cjs <cookies.json> <preview-url> <slug> <page-slug>...
 *
 * <cookies.json> holds {"cookies":[{"name","value"}]} for an administrator
 * whose staged build is <slug> with default values, and <preview-url> is
 * that user's preview URL. The first page slug is the front page.
 *
 * Writes /starter-sites/<slug>/screenshots/<page>.jpg (800px wide, the top
 * 1600px of the 1280px desktop layout). Re-run build.py afterwards so the
 * manifest lists them. Dev tooling only, never shipped.
 */
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const [cookieFile, previewUrl, slug, ...pages] = process.argv.slice(2);
const BROWSER = process.env.MSH_BROWSER || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = path.join(__dirname, '..', '..', 'starter-sites', slug, 'screenshots');

(async () => {
	const auth = JSON.parse(fs.readFileSync(cookieFile, 'utf8'));
	const base = new URL(previewUrl);
	fs.mkdirSync(OUT, { recursive: true });

	const browser = await puppeteer.launch({ executablePath: BROWSER, headless: 'new' });
	const page = await browser.newPage();
	await page.setCookie(...auth.cookies.map((c) => ({ ...c, domain: base.hostname, path: '/' })));
	await page.setViewport({ width: 1280, height: 1600, deviceScaleFactor: 0.625 });

	for (const [i, pageSlug] of pages.entries()) {
		const url = new URL(base);
		url.searchParams.set('msh_frame', '1');
		if (i > 0) {
			url.searchParams.set('msh_page', pageSlug);
		}
		await page.goto(url.toString(), { waitUntil: 'networkidle0' });
		const file = path.join(OUT, pageSlug + '.jpg');
		await page.screenshot({ path: file, type: 'jpeg', quality: 74, clip: { x: 0, y: 0, width: 1280, height: 1600 } });
		console.log(path.relative(process.cwd(), file), Math.round(fs.statSync(file).size / 1024) + ' KB');
	}

	await browser.close();
})();
