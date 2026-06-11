/**
 * Пререндер (SSG): рендерит React-приложение в строку и инжектит
 * готовую разметку в htdocs/index.html вместо <!--app-html-->.
 *
 * Запускается из `npm run build` после клиентского и SSR-билдов.
 */

import { readFileSync, writeFileSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const htmlFile = path.join(root, 'htdocs', 'index.html');
const ssrDir = path.join(root, '.prerender');

const { render } = await import(path.join(ssrDir, 'entry-server.js'));

const appHtml = render();
if (!appHtml || appHtml.length < 1000) {
  throw new Error(`Prerender produced suspiciously small output (${appHtml.length} chars)`);
}

const template = readFileSync(htmlFile, 'utf-8');
if (!template.includes('<!--app-html-->')) {
  throw new Error('Placeholder <!--app-html--> not found in htdocs/index.html');
}

writeFileSync(htmlFile, template.replace('<!--app-html-->', appHtml));

// Временный SSR-бандл больше не нужен
rmSync(ssrDir, { recursive: true, force: true });

console.log(`[prerender] OK: injected ${(appHtml.length / 1024).toFixed(1)} KB of markup into htdocs/index.html`);
