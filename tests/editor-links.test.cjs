'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

test('CRM rich-text editor preserves hyperlinks on change and form submission', () => {
  const source = fs.readFileSync(path.join(__dirname, '../public/assets/admin-editor.js'), 'utf8');
  const callbacks = {};
  const formCallbacks = {};
  let onReady;
  let editorConfig;
  const form = {
    addEventListener(name, fn) { formCallbacks[name] = fn; },
    requestSubmit() { formCallbacks.submit(); }
  };
  const textarea = {
    name: 'body', dataset: {}, value: '',
    closest(selector) { assert.equal(selector, 'form'); return form; },
    dispatchEvent() {}
  };
  const editor = {
    html: '<p>Before</p>',
    e: { on(name, fn) { callbacks[name] = fn; } },
    synchronizeValues() { textarea.value = this.html; },
    s: { insertHTML(html) { editor.html += html; } },
    container: { insertAdjacentElement() {} }
  };
  const document = {
    addEventListener(name, fn) { if (name === 'DOMContentLoaded') onReady = fn; },
    querySelectorAll(selector) { return selector.includes('textarea[name="body"]') ? [textarea] : []; },
    querySelector() { return null; },
    getElementById() { return null; },
    createTextNode(textContent) { return { textContent }; },
    createElement() { return { appendChild() {}, addEventListener() {} }; }
  };
  vm.runInNewContext(source, {
    document,
    window: { Jodit: { make(_textarea, config) { editorConfig = config; return editor; } } },
    setTimeout(fn) { fn(); },
    Event: class { constructor(type) { this.type = type; } },
    prompt() { return null; },
    fetch() { throw Error('Unexpected network call'); },
    console
  });
  onReady();
  assert.equal(editorConfig.iframe, true);
  assert.match(editorConfig.iframeStyle, /a\{color:.*text-decoration:underline/);
  assert.ok(editorConfig.buttons.includes('link'));
  assert.equal(editorConfig.processPasteFromWord, true);
  const anchor = '<a href="https://example.com/review?ref=1">Read review</a>';
  editor.s.insertHTML(anchor);
  callbacks.change();
  assert.ok(textarea.value.includes(anchor), 'link survives the editor change handler');
  editor.html += '<p><a href="/guide/next">Next</a></p>';
  formCallbacks.submit();
  assert.ok(textarea.value.includes('href="/guide/next"'), 'last-minute link edits survive form submit');
  editor.html += '<a href="mailto:editor@example.com">Email</a>';
  callbacks.keydown({ ctrlKey: true, key: 's', preventDefault() {} });
  assert.ok(textarea.value.includes('mailto:editor@example.com'), 'Ctrl+S saves the latest links');
});

test('CRM Amazon overrides explicitly mark the submitted fields and auto-protect edits', () => {
  const source = fs.readFileSync(path.join(__dirname, '../public/assets/product-sync.js'), 'utf8');
  assert.match(source, /amazon_override_present/);
  assert.match(source, /fieldInput\.addEventListener\('input',\(\)=>\{input\.checked=true;/);
  assert.match(source, /affiliate_url/);
});
