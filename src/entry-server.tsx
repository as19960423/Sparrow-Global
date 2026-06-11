/**
 * Серверный entry для пререндера (SSG).
 * Используется только скриптом scripts/prerender.mjs на этапе сборки.
 */

import { StrictMode } from 'react';
import { renderToString } from 'react-dom/server';
import App from './App.tsx';

export function render(): string {
  return renderToString(
    <StrictMode>
      <App />
    </StrictMode>,
  );
}
