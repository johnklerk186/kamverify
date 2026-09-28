const puppeteer = require('puppeteer-core');
(async () => {
    const b = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new', args: ['--no-sandbox']
    });
    const p = await b.newPage();
    await p.goto('https://flying-garmin-dispatch-constitute.trycloudflare.com/login', { waitUntil: 'networkidle2' });
    await p.type('input[name=email]', 'customer@kamverify.com');
    await p.type('input[name=password]', 'password');
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type=submit]')]);
    await p.goto('https://flying-garmin-dispatch-constitute.trycloudflare.com/orders/create', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 1500));

    await p.click('a[href*="orders/create?service=1"]'); // WhatsApp
    await new Promise(r => setTimeout(r, 1000));
    const step2 = await p.evaluate(() => document.body.innerText.includes('Choose a country'));
    await p.evaluate(() => [...document.querySelectorAll('a[href*="country="]')][0].click());
    await new Promise(r => setTimeout(r, 800));
    await p.evaluate(() => [...document.querySelectorAll('button')].find(x => x.innerText.includes('Check availability'))?.click());
    await new Promise(r => setTimeout(r, 3500));
    const res = await p.evaluate(() => document.body.innerText.match(/Confirm your purchase[\s\S]{0,400}/)?.[0] || 'NO CONFIRM STEP');
    console.log('step2 countries shown:', step2);
    console.log(res.slice(0, 400));
    await b.close();
})().catch(e => console.log('FATAL', e.message));
