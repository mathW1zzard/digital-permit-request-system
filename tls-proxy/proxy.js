// Local HTTPS front door for development (Week 14). Terminates TLS and forwards to the plain-HTTP
// servers on this computer, telling them the original request was HTTPS.
//   https://localhost:8443  ->  http://127.0.0.1:8000   (Laravel API)
//   https://127.0.0.1:9443  ->  http://127.0.0.1:9000   (mock gateway)
// Run from the repository root after `php tls-proxy/make-dev-certs.php`:  node tls-proxy/proxy.js
// Uses only Node's built-in modules. Not for production use.
'use strict';

const fs = require('fs');
const http = require('http');
const https = require('https');
const path = require('path');

const certDir = path.join(__dirname, '..', 'certs');
const tls = {
  key: fs.readFileSync(path.join(certDir, 'localhost-key.pem')),
  cert: fs.readFileSync(path.join(certDir, 'localhost.pem')),
  minVersion: 'TLSv1.2',
};
const routes = [
  { listen: 8443, target: 8000, name: 'api' },
  { listen: 9443, target: 9000, name: 'gateway' },
];

for (const route of routes) {
  https.createServer(tls, (req, res) => {
    const headers = { ...req.headers, 'x-forwarded-proto': 'https', 'x-forwarded-port': String(route.listen), 'x-forwarded-for': req.socket.remoteAddress };
    const upstream = http.request({ host: '127.0.0.1', port: route.target, method: req.method, path: req.url, headers }, (up) => {
      res.writeHead(up.statusCode, up.headers);
      up.pipe(res);
    });
    upstream.on('error', () => {
      if (!res.headersSent) res.writeHead(502, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ error: { code: 'upstream_unavailable', message: 'The service behind the proxy is not running.' } }));
    });
    req.pipe(upstream);
  }).listen(route.listen, () => console.log(`${route.name}: https://localhost:${route.listen} -> http://127.0.0.1:${route.target}`));
}
