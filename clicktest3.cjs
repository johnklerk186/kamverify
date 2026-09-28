const puppeteer = require('puppeteer-core');
(async () => {
    const b = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: 'new', args: ['--no-sandbox']
    });
    const p = await b.newPage();
    const errs = [];
    p.on('pageerror', e => errs.push(e.message));
    await p.goto('https://flying-garmin-dispatch-constitute.trycloudflare.com/login', { waitUntil: 'networkidle2' });
    await p.type('input[name=email]', 'customer@kamverify.com');
    await p.type('input[name=password]', 'password');
    await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type=submit]')]);
    await p.goto('https://flying-garmin-dispatch-constitute.trycloudflare.com/orders/create', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 2500));

    console.log(await p.evaluate(() => {
        const root = document.querySelector('[x-data*="buyFlow"]');
        const data = root && root._x_dataStack ? root._x_dataStack[0] : null;
        return JSON.stringify({
            buyFlowType: typeof window.buyFlow,
            hasStack: !!root._x_dataStack,
            step: data && data.step,
            serviceId: data && data.serviceId,
        });
    }));

    await p.evaluate(() => document.querySelector('a[href*="service=1"]').click());
    await new Promise(r => setTimeout(r, 1000));
    console.log(await p.evaluate(() => {
        const root = document.querySelector('[x-data*="buyFlow"]');
        const data = root && root._x_dataStack ? root._x_dataStack[0] : null;
        return 'after click: step=' + (data && data.step) + ' serviceId=' + (data && data.serviceId) + ' name=' + (data && data.serviceName);
    }));
    console.log('ERRORS:', errs.length ? errs : 'none');
    await b.close();
})().catch(e => console.log('FATAL', e.message));
