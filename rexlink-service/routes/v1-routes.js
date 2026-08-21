function registerV1Routes(app, handlers, asyncRoute) {
  // Health
  app.get('/api/v1/health', asyncRoute(handlers.health));

  // Pairing
  app.post('/api/v1/pairing/create', asyncRoute(handlers.createPairing));
  app.post('/api/v1/pairing/cancel', asyncRoute(handlers.cancelPairing));
  app.get('/api/v1/pairing/qr', asyncRoute(handlers.pairingQr));
  app.post('/api/v1/pairing/complete', asyncRoute(handlers.completePairing));

  // Sessions
  app.post('/api/v1/sessions/status', asyncRoute(handlers.loginFromSession));
  app.get('/api/v1/sessions', asyncRoute(handlers.listSessions));
  app.post('/api/v1/sessions/revoke', asyncRoute(handlers.revokeSession));

  // Review
  app.post('/api/v1/review/pairing/create', asyncRoute(handlers.createReviewPairing));
  app.post('/api/v1/review/pairing/status', asyncRoute(handlers.reviewPairingStatus));
  app.post('/api/v1/review/wallet/status', asyncRoute(handlers.reviewWalletStatus));

  // Claims
  app.post('/api/v1/claims/approval', asyncRoute(handlers.createClaimApproval));
  app.get('/api/v1/claims/status', asyncRoute(handlers.getApprovalStatus));
  app.post('/api/v1/claims/decision', asyncRoute(handlers.decideApproval));
  app.post('/api/v1/claims/complete', asyncRoute(handlers.completeClaimTx));

  // Realtime auth
  app.get('/api/v1/realtime/auth', asyncRoute(handlers.realtimeAuth));
  app.post('/api/v1/realtime/auth', asyncRoute(handlers.realtimeAuth));

  // Networks / Assets
  app.get('/api/v1/networks', asyncRoute(handlers.networks));
  app.get('/api/v1/assets', asyncRoute(handlers.assets));
  app.get('/api/v1/assets/history', asyncRoute(handlers.externalHistory));
}

module.exports = registerV1Routes;
