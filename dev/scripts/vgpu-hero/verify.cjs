/* Read-only public UI QA. Start the SQLite PHP preview before npm test.
 * CHROMIUM_EXECUTABLE can point to an existing local browser installation.
 */
const { chromium } = require('@playwright/test');
const { AxeBuilder } = require('@axe-core/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.UI_BASE_URL || 'http://127.0.0.1:8817';
assert(['localhost', '127.0.0.1'].includes(new URL(base).hostname), 'Use the local preview only');
const out = path.resolve(__dirname, '../../_work/ui-verification');
fs.mkdirSync(out, { recursive: true });
const routes = ['', 'about', 'portfolio', 'blog', 'updates', 'rmrp', 'contact', 'gallery', 'travel', 'music', 'videos', 'downloads', 'post-code', 'science-corner', 'comedy', 'more', 'yunobot', 'search?q=code', 'privacy', 'terms', 'cookies', 'sitemap', '404'];
const checks = [];
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE || undefined, args: ['--no-sandbox', '--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  try {
    for (const width of (process.env.UI_STATES_ONLY ? [] : [1440, 390, 320])) {
      const context = await browser.newContext({ viewport: { width, height: width > 700 ? 1000 : 844 }, isMobile: width < 700, hasTouch: width < 700 });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      for (const route of routes) {
        await page.goto(base + '/' + route, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(120);
        const audit = await new AxeBuilder({ page }).withRules(['color-contrast', 'button-name', 'select-name', 'label']).analyze();
        assert.deepEqual(audit.violations.map(v => ({ rule: v.id, nodes: v.nodes.map(n => ({ target: n.target, reason: n.failureSummary })) })), [], `${width}px /${route}`);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
        assert.equal(overflow, false, `Horizontal overflow: ${width}px /${route}`);
        checks.push(`${width}px /${route}: contrast, names, overflow`);
      }
      await page.goto(base + '/');
      await page.waitForFunction(() => document.querySelector('canvas').dataset.sceneState === 'running');
      const canvas = page.locator('.ui-hero-canvas');
      const metrics = await canvas.evaluate(e => ({ ...e.dataset }));
      assert(Number(metrics.triangles) < 6000 && Number(metrics.drawCalls) < 30, 'Scene budget');
      await page.screenshot({ path: path.join(out, `home-${width}.png`) });
      const pause = page.getByRole('button', { name: 'Pause background' });
      await pause.click();
      const frames = await canvas.getAttribute('data-frames');
      await page.waitForTimeout(250);
      assert.equal(await canvas.getAttribute('data-frames'), frames, 'Paused scene must not render');
      await page.getByRole('button', { name: 'Play background' }).click();
      const trigger = page.getByRole('link', { name: 'Enlarge the study image' });
      await trigger.focus(); await page.keyboard.press('Enter');
      assert.equal(await page.locator('dialog').evaluate(e => e.open), true);
      assert.equal(await page.locator('.ui-image-dialog-close').evaluate(e => e === document.activeElement), true, 'Dialog focus');
      await page.screenshot({ path: path.join(out, `image-${width}.png`) });
      assert.equal(await canvas.getAttribute('data-scene-state'), 'paused', 'Dialog pauses decoration');
      await page.getByRole('button', { name: 'Original size' }).click();
      assert.equal(await page.locator('.ui-image-dialog-scroll img').evaluate(e => e.getBoundingClientRect().width), 1100);
      await page.getByRole('button', { name: 'Fit to screen' }).click();
      await page.keyboard.press('Escape');
      assert.equal(await trigger.evaluate(e => e === document.activeElement), true, 'Focus returns to photo');
      await page.evaluate(() => scrollTo({top: document.querySelector('.ui-hero').offsetHeight + 50, behavior: 'instant'}));
      await page.waitForFunction(() => document.querySelector('canvas').dataset.sceneState === 'paused');
      assert.equal(await canvas.getAttribute('data-scene-state'), 'paused', 'Offscreen scene pauses');
      await page.evaluate(() => scrollTo({top: 0, behavior: 'instant'}));
      await page.waitForFunction(() => document.querySelector('canvas').dataset.sceneState === 'running');
      await page.emulateMedia({ reducedMotion: 'reduce' });
      await page.waitForTimeout(120);
      const reducedFrames = await canvas.getAttribute('data-frames');
      await page.waitForTimeout(250);
      assert.equal(await canvas.getAttribute('data-frames'), reducedFrames, 'Reduced motion renders only a still');
      await page.emulateMedia({ reducedMotion: 'no-preference' });
      await page.waitForFunction(() => document.querySelector('canvas').dataset.sceneState === 'running');
      checks.push(`${width}px: 3D ${metrics.triangles} triangles / ${metrics.drawCalls} calls; pause, reduced motion, offscreen, image keyboard/zoom`);
      assert.deepEqual(errors, [], 'Browser errors');
      console.log(`PASS: ${width}px route and interaction checks`);
      await context.close();
    }
    const context = await browser.newContext({ viewport: {width: 1280, height: 900} });
    const page = await context.newPage();
    await page.goto(base + '/dev/_work/music-preview.html');
    await page.locator('.ui-music-play-btn').first().hover();
    await page.waitForTimeout(200);
    assert.equal(await page.locator('.ui-music-play-btn').first().evaluate(e => getComputedStyle(e).color), 'rgb(21, 18, 15)');
    await page.locator('.ui-music-play-btn').first().click();
    await page.waitForTimeout(200);
    assert.equal(await page.locator('.ui-music-play-btn').first().evaluate(e => getComputedStyle(e).color), 'rgb(21, 18, 15)');
    checks.push('Music hover and selected hover: dark icon on amber');
    await page.goto(base + '/yunobot');
    await page.locator('.chat-input').fill('Who is Yunus Emre Vurgun?');
    await page.locator('.chat-input').press('Enter');
    await page.waitForTimeout(2000);
    const chatAudit = await new AxeBuilder({page}).withRules(['color-contrast']).analyze();
    assert.deepEqual(chatAudit.violations.map(v=>v.nodes.map(n=>n.target)), [], 'Chat response contrast');
    await page.goto(base + '/admin/login');
    assert.equal(await page.locator('body').evaluate(e => getComputedStyle(e).getPropertyValue('--color-bg-primary').trim()), '#e3e2de', 'Admin palette unchanged');
    checks.push('YunoBot response and admin palette');
    await context.close();
    const fallback = await browser.newContext({javaScriptEnabled: false});
    const fallbackPage = await fallback.newPage(); await fallbackPage.goto(base + '/');
    assert.match(await fallbackPage.locator('[data-image-expand]').getAttribute('href'), /landing-hero-desk\.webp$/);
    checks.push('No-JavaScript image link'); await fallback.close();
    fs.writeFileSync(path.join(out, 'results.json'), JSON.stringify(checks, null, 2));
    console.log(`PASS: ${checks.length} public UI checks. Screenshots: ${out}`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
