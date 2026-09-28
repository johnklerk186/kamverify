const puppeteer = require('puppeteer-core');
(async () => {
    const b = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new', args: ['--no-sandbox']
    });
    const p = await b.newPage();
    await p.setViewport({ width: 1280, height: 900 });
    const errs = [];
    p.on('pageerror', e => errs.push(e.message));
    await p.goto('https://flying-garmin-dispatch-constitute.trycloudflare.com/login', { waitUntil: 'networkidle2' });
    await p.type('input[name=email]', 'customer@kamverify.com');
    await p.type('input[name=password]', 'password');
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type=submit]')]);
    await p.goto('https://flying-garmin-dispatch-constitute.trycloudflare.com/orders/create', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 2000));
    await p.screenshot({ path: 'step1_services.png' });

    // WhatsApp
    await p.evaluate(() => document.querySelector('a[href*="service=1"]').click());
    await new Promise(r => setTimeout(r, 800));
    await p.screenshot({ path: 'step2_countries.png' });

    // first country
    await p.evaluate(() => document.querySelector('a[href*="country="]').click());
    await new Promise(r => setTimeout(r, 500));

    // check availability
    await p.evaluate(() => [...document.querySelectorAll('button')].find(x => x.innerText.includes('Check availability'))?.click());
    await new Promise(r => setTimeout(r, 4000));
    await p.screenshot({ path: 'step3_quote.png' });

    const quote = await p.evaluate(() => {
        const root = document.querySelector('[x-data*="buyFlow"]');
        return root._x_dataStack ? JSON.stringify(root._x_dataStack[0].quote) : 'nostate';
    });
    const text = await p.evaluate(() => document.body.innerText.match(/Confirm your purchase[\s\S]{0,350}/)?.[0]);
    console.log('QUOTE:', quote);
    console.log(text);
    console.log('ERRORS:', errs.length ? errs : 'none');
    await b.close();
})().catch(e => console.log('FATAL', e.message));
