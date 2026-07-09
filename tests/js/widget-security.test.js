import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../public/widget.js', import.meta.url), 'utf8');

test('untrusted widget configuration is not interpolated into innerHTML', () => {
  const markupStart = source.indexOf('root.innerHTML =');
  const markupEnd = source.indexOf('document.body.appendChild(root)');
  const markupBuilder = source.slice(markupStart, markupEnd);

  assert.ok(markupStart >= 0 && markupEnd > markupStart);
  assert.doesNotMatch(markupBuilder, /config\.(?:avatarUrl|botName|placeholderText)/);
  assert.match(source, /textContent\s*=\s*config\.botName/);
  assert.match(source, /placeholder\s*=\s*config\.placeholderText/);
});
