const puppeteer = require('puppeteer-core');

(async () => {
    const base = 'https://flying-garmin-dispatch-constitute.trycloudflare.com';
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new',
        args: ['--no-sandbox', '--disable-gpu']
    });
    const page = await browser.newPage();
    const errors = [];
    page.on('console', m => { if (m.type() === 'error') errors.push('CONSOLE: ' + m.text()); });
    page.on('pageerror', e => errors.push('PAGEERROR: ' + e.message));
    page.on('requestfailed', r => errors.push('REQFAIL: ' + r.url() + ' ' + (r.failure()?.errorText || '')));

    // login
    await page.goto(base + '/login', { waitUntil: 'networkidle2', timeout: 30000 });
    const token = await page.$eval('input[name=_token]', el => el.value);
    await page.type('input[name=email]', 'customer@kamverify.com');
    await page.type('input[name=password]', 'password');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }),
        page.click('button[type=submit]')
    ]);
    console.log('logged in, url:', page.url());

    await page.goto(base + '/orders/create', { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 3000)); // let Alpine settle

    const info = await page.evaluate(() => {
        const names = document.querySelector('div[x-data*="buyFlow"]');
        const links = [...document.querySelectorAll('a[href*="orders/create?service="]')];
        return {
            serviceNames: (document.documentElement.innerHTML.match(/serviceNames: (\{[^}]*\}|\[\])/) || [])[1] || 'NOT FOUND',
            linkCount: links.length,
            visibleLinks: links.filter(a => a.offsetParent !== null).length,
            alpineVersion: window.Alpine ? window.Alpine.version : 'NO ALPINE',
            step: names && names.__x ? names.__x.$data.step : 'n/a',
            bodySnippet: document.body.innerText.slice(0, 600)
        };
    });
    console.log(JSON.stringify(info, null, 2));
    console.log('ERRORS:', errors.length ? errors : 'none');
    await page.screenshot({ path: 'C:\\Users\\GLOBAL STORE\\Desktop\\site\\kamverify.com\\buypage.png', fullPage: true });
    await browser.close();
})().catch(e => { console.log('FATAL:', e.message); process.exit(1); });
