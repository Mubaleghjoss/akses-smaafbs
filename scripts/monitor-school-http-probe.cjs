const fs = require('fs');
const https = require('https');

const [mode, targetUrl, tokenFile, bodyFile] = process.argv.slice(2);

if (!['probe', 'post'].includes(mode) || !targetUrl) {
    process.stderr.write('Usage: node monitor-school-http-probe.js <probe|post> <url> [token-file] [body-file]\n');
    process.exit(64);
}

const startedAt = Date.now();
const options = {
    method: mode === 'post' ? 'POST' : 'GET',
    timeout: 20000,
    headers: {
        Accept: 'application/json',
        'User-Agent': 'SMA-AFBS-School-Network-Monitor/1.1',
    },
};

let requestBody = '';

if (mode === 'post') {
    requestBody = fs.readFileSync(bodyFile, 'utf8');
    options.headers.Authorization = `Bearer ${fs.readFileSync(tokenFile, 'utf8').trim()}`;
    options.headers['Content-Type'] = 'application/json';
    options.headers['Content-Length'] = Buffer.byteLength(requestBody);
}

const request = https.request(targetUrl, options, (response) => {
    response.resume();
    response.on('end', () => {
        process.stdout.write(JSON.stringify({
            status: response.statusCode || 0,
            duration_ms: Date.now() - startedAt,
        }));
        process.exit(response.statusCode >= 200 && response.statusCode < 400 ? 0 : 2);
    });
});

request.on('timeout', () => request.destroy(new Error('TIMEOUT')));
request.on('error', (error) => {
    process.stderr.write(error.code || error.message);
    process.exit(1);
});

if (requestBody !== '') {
    request.write(requestBody);
}

request.end();
