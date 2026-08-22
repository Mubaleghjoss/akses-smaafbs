import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/filament-admin-passkeys.js', import.meta.url), 'utf8');

const bytes = (value) => new TextEncoder().encode(value).buffer;

function loadPasskeyBridge(credentials) {
    const window = {
        atob,
        btoa,
        isSecureContext: true,
        PublicKeyCredential: function PublicKeyCredential() {},
        setTimeout,
    };
    const navigator = { credentials };
    const context = vm.createContext({
        ArrayBuffer,
        DOMException,
        JSON,
        Promise,
        Uint8Array,
        console,
        navigator,
        setTimeout,
        window,
    });

    vm.runInContext(source, context);

    return window.adminPasskeyLoginBridge();
}

function assertion() {
    return {
        rawId: bytes('credential'),
        response: {
            clientDataJSON: bytes('client-data'),
            authenticatorData: bytes('authenticator-data'),
            signature: bytes('signature'),
            userHandle: bytes('user:1'),
        },
    };
}

function issue(number) {
    return {
        status: 'issued',
        challengeId: `challenge-${number}`,
        publicKeyOptions: {
            challenge: 'Y2hhbGxlbmdl',
            rpId: 'app.smaafbs.sch.id',
            userVerification: 'required',
        },
    };
}

test('credential manager unknown error retries once with required mediation', async () => {
    const requests = [];
    const reports = [];
    let silentAccessResets = 0;
    const bridge = loadPasskeyBridge({
        async get(request) {
            requests.push(request);

            if (requests.length === 1) {
                throw new DOMException('An unknown error occurred while talking to the credential manager.', 'UnknownError');
            }

            return assertion();
        },
        async preventSilentAccess() {
            silentAccessResets += 1;
        },
    });
    let issued = 0;
    const wire = {
        async beginPasskeyLogin() {
            issued += 1;
            return issue(issued);
        },
        async reportPasskeyClientFailure(challengeId, code) {
            reports.push({ challengeId, code });
        },
        async completePasskeyLogin() {
            return { redirected: true };
        },
    };

    await bridge.start(wire);

    assert.equal(requests.length, 2);
    assert.equal(requests[0].mediation, undefined);
    assert.equal(requests[1].mediation, 'required');
    assert.equal(silentAccessResets, 1);
    assert.deepEqual(reports, [{
        challengeId: 'challenge-1',
        code: 'client_credential_manager_unknown',
    }]);
    assert.equal(bridge.canRetry, false);
});

test('second credential manager error stops and shows Indonesian retry guidance', async () => {
    const requests = [];
    const reports = [];
    const bridge = loadPasskeyBridge({
        async get(request) {
            requests.push(request);
            throw new DOMException('An unknown error occurred while talking to the credential manager.', 'UnknownError');
        },
        async preventSilentAccess() {},
    });
    let issued = 0;
    const wire = {
        async beginPasskeyLogin() {
            issued += 1;
            return issue(issued);
        },
        async reportPasskeyClientFailure(challengeId, code) {
            reports.push({ challengeId, code });
        },
    };

    await bridge.start(wire);

    assert.equal(requests.length, 2);
    assert.equal(reports.length, 2);
    assert.equal(bridge.canRetry, true);
    assert.equal(bridge.messageTone, 'error');
    assert.match(bridge.localMessage, /Pengelola passkey perangkat belum merespons/);
    assert.doesNotMatch(bridge.localMessage, /unknown error/i);
});
