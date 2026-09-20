'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const controller = fs.readFileSync(path.join(root, 'assets/js/submit-review.js'), 'utf8');
const pairingEndpoint = fs.readFileSync(path.join(root, 'api/review-eligibility/create_rexlink_pairing.php'), 'utf8');
const view = fs.readFileSync(path.join(root, 'public/includes/review-submit-view.php'), 'utf8');

test('QR creation remains an explicit user action', () => {
    const preflight = controller.slice(controller.indexOf('async function preflight'), controller.indexOf('async function confirmSession'));
    assert.doesNotMatch(preflight, /createPairing\s*\(/);
    assert.match(controller, /reviewCreateSession'\)\.addEventListener\('click',createPairing\)/);
});

test('review pairing never forces an active session revocation', () => {
    assert.doesNotMatch(controller, /force_new_pairing\s*:\s*true/);
    assert.doesNotMatch(pairingEndpoint, /force_new_pairing|Replaced by fresh review eligibility pairing/);
    assert.match(pairingEndpoint, /LOWER\(account_row\.wallet_address\)\s*=\s*LOWER\(session_row\.wallet_address\)/);
    assert.match(pairingEndpoint, /session_row\.app_id\s*=\s*'coinrex'/);
});

test('late RexLink events share the serialized session check', () => {
    assert.match(controller, /if\(S\.sessionChecking\)return false/);
    assert.match(controller, /rexlink:session-active/);
});

test('the review wizard exposes exactly three real steps', () => {
    const steps = view.match(/data-review-step="[123]"/g) || [];
    assert.equal(steps.length, 3);
    assert.doesNotMatch(view, /data-review-step="4"/);
});