# RexLink SDK v1

`assets/js/rexlink-sdk.js` is the browser SDK for pairing a registered dApp with the RexLink mobile wallet. The Node service under `rexlink-service/` is the canonical low-latency API; PHP endpoints remain compatibility fallbacks for CoinRex pages.

## Performance contract

- Pairing creation uses a 900ms Node request budget.
- CoinRex pages allow a bounded 1.9-second PHP fallback, keeping automatic QR creation within a three-second total budget when either backend is healthy.
- QR codes render from the bundled `qrcode-browser.js`; a server-generated SVG is fallback only.
- Pairing completion is checked every 300ms, with realtime events as an additional signal.
- Expiry cleanup runs in the background and is not awaited by pairing creation or completion.

The three-second target covers server creation, QR rendering, and website recognition after the wallet approves. It cannot include the time a person takes to scan and approve, or an unavailable network/service.

## Browser setup

Load scripts in this order:

```html
<script src=/assets/js/qrcode-browser.js></script>
<script src=/assets/js/rexlink-pairing.js></script>
<script src=/assets/js/rexlink-sdk.js></script>
```

Initialize with the canonical Node URL and registered application ID:

```js
RexLink.init({
  apiBaseUrl: 'https://rexlink-api.example.com',
  appId: 'example-dapp',
  webActorToken: signedActorToken,
  requestTimeoutMs: 1600,
});
```

Create and watch a pairing:

```js
const pairing = await RexLink.createPairing({
  purpose: 'claim',
  durationMinutes: 5,
  networkSlugs: ['polygon', 'base', 'plasma'],
});

await RexLink.renderQR(pairing.qr_payload, '#pairing-qr');

const connected = await RexLink.pollPairingStatus(pairing.pairing_id, {
  interval: 300,
  shouldContinue: () => pairingModalIsOpen,
});
```

The SDK retains the response-only `status_token` in memory for anonymous authentication polling. It is never embedded in the QR code. Logged-in flows are also scoped to the authenticated user.

## Network contract

Pairing sessions are dApp-scoped and multi-network. The backend validates optional networkSlugs against enabled EVM rows in the network registry; omitting it selects every enabled network. QR payloads expose network_scope, supported_networks, and preferred_network. Legacy network_slug and chain_id remain display hints for older wallet builds.

Every approval must provide a matching network_slug and chain_id. RexLink rejects unknown, disabled, or mismatched chains before presenting the approval. REX claim approvals remain bound to their configured Polygon deployment.

## dApp registration

Every `appId` must exist as an active row in `rex_signer_apps`. Apply `database/migrations/2026_08_20_rexlink_sdk_v1.sql`, then register future dApps with a stable ID, display name, canonical URL, and optional callback/public-key metadata. QR display metadata is read from this registry rather than trusted from browser input.

The pairing, session, and approval records retain `app_id`, allowing future per-dApp authorization, callbacks, and approval isolation without changing the QR protocol.
