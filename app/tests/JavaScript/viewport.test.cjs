const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const script = fs.readFileSync(path.join(__dirname, '../../public/assets/md-notes-viewport.js'), 'utf8');

test('visible viewport follows the keyboard and coalesces resize events without overriding pinch zoom', () => {
    const styles = {}, events = {}, frames = [];
    const viewport = { height: 844, offsetTop: 0, scale: 1, addEventListener: (name, callback) => { events[name] = callback; } };
    const windowEvents = {};
    vm.runInNewContext(script, {
        window: { visualViewport: viewport, addEventListener: (name, callback) => { windowEvents[name] = callback; } },
        document: { documentElement: { style: { setProperty: (name, value) => { styles[name] = value; } } } },
        requestAnimationFrame: callback => frames.push(callback),
    });
    assert.equal(styles['--visible-height'], '844px');
    viewport.height = 360;
    viewport.offsetTop = 25;
    events.resize(); events.scroll();
    assert.equal(frames.length, 1);
    frames.shift()();
    assert.equal(styles['--visible-height'], '360px');
    assert.equal(styles['--visible-top'], '25px');
    viewport.scale = 2; viewport.height = 180;
    events.resize(); frames.shift()();
    assert.equal(styles['--visible-height'], '360px');
    viewport.scale = 1; viewport.height = 844; viewport.offsetTop = 0;
    windowEvents.pageshow(); frames.shift()();
    assert.equal(styles['--visible-height'], '844px');
    assert.equal(styles['--visible-top'], '0px');
});

test('browsers without VisualViewport keep the CSS fallback', () => {
    assert.doesNotThrow(() => vm.runInNewContext(script, { window: {} }));
});
