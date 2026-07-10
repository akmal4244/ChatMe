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

test('widget fits a 320px viewport and reports non-success API responses', () => {
  assert.match(source, /width:min\(370px,calc\(100% - 24px\)\)/);
  assert.doesNotMatch(source, /calc\(100vw - 24px\)/);
  assert.match(source, /if\s*\(!r\.ok\)\s*throw new Error/);
  assert.match(source, /if\s*\(!d\.response\)\s*throw new Error/);
  assert.match(source, /Promise\.race\(\[fetch/);
  assert.match(source, /controller\.abort\(\)/);
  assert.match(source, /inputEl\.value = text/);
  assert.match(source, /closeBtn\.style\.color = primaryTextColor/);
});

test('message bubble padding overrides the widget reset so text is not clipped', () => {
  assert.match(source, /#chatme-root \*\{[^}]*padding:0[^}]*\}/);
  assert.match(source, /#chatme-root \.chatme-msg\{[^}]*padding:10px 14px[^}]*\}/);
});

test('widget defaults and status use clear Bahasa Melayu', () => {
  assert.match(source, /Pembantu ChatMe/);
  assert.match(source, /Helo! Bagaimana saya boleh membantu anda\?/);
  assert.match(source, /Taip mesej anda\.\.\./);
  assert.match(source, /Sedia membantu/);
  assert.match(source, /Disediakan oleh/);
  assert.doesNotMatch(source, /Powered by|Type your message|>Online</);
});
