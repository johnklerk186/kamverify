const puppeteer = require('puppeteer-core');
(async () => {
    const b = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new', args: ['--no-sandbox']
    });
    const p = await b.newPage();
    const errs = [];
    p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
    p.on('response', async r => {
        if (r.url().includes('/orders/quote')) {
            const t = await r.text().catch(() => 'unreadable');
            errs.push('QUOTE RESP ' + r.status() + ' ' + r.url() + ' BODY: ' + t.slice(0, 300));
        }
    });
    await p.goto('https://flying-garmin-dispatch-constitute.trycloudflare.com/login', { waitUntil: 'networkidle2' });
    await p.type('input[name=email]', 'customer@kamverify.com');
    await p.type('input[name=password]', 'password');
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type=submit]')]);
    await p.goto('https://flying-garmin-dispatch-constitute.trycloudflare.com/orders/create', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 2500));

    const state1 = await p.evaluate(() => {
        const el = document.querySelector('[x-data*="buyFlow"]');
        return { hasX: !!(el && el.__x), keys: el && el.__x ? Object.keys(el.__x.$data).join(',') : 'none' };
    });
    console.log('component state:', JSON.stringify(state1));

    await p.click('a[href*="orders/create?service=1"]');
    await new Promise(r => setTimeout(r, 1200));
    const state2 = await p.evaluate(() => {
        const el = document.querySelector('[x-data*="buyFlow"]');
        return el.__x ? { step: el.__x.$data.step, sid: el.__x.$data.serviceId } : 'no __x';
    });
    console.log('after service click:', JSON.stringify(state2));

    await p.evaluate(() => [...document.querySelectorAll('a[href*="country="]')][0].click());
    await new Promise(r => setTimeout(r, 800));
    await p.evaluate(() => [...document.querySelectorAll('button')].find(x => x.innerText.includes('Check availability'))?.click());
    await new Promise(r => setTimeout(r, 4000));
    const state3 = await p.evaluate(() => {
        const el = document.querySelector('[x-data*="buyFlow"]');
        return el.__x ? { step: el.__x.$data.step, cid: el.__x.$data.countryId, quote: el.__x.$data.quote } : 'no __x';
    });
    console.log('after quote:', JSON.stringify(state3));
    console.log('EVENTS:', errs);
    await b.close();
})().catch(e => console.log('FATAL', e.message));
