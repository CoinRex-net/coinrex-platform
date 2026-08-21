function registerRoutes(app, handlers, asyncRoute) {
  app.get('/health', asyncRoute(handlers.health));
  app.post('/api/rex-signer/create_pairing.php', asyncRoute(handlers.createPairing));
  app.post('/api/review-eligibility/create_rexlink_pairing.php', asyncRoute(handlers.createReviewPairing));
  app.post('/api/review-eligibility/rexlink_wallet.php', asyncRoute(handlers.reviewWalletStatus));
  app.post('/api/rex-signer/review_pairing_status.php', asyncRoute(handlers.reviewPairingStatus));
  app.get('/api/rex-signer/pairing_qr.php', asyncRoute(handlers.pairingQr));
  app.post('/api/rex-signer/complete_pairing.php', asyncRoute(handlers.completePairing));
  app.post('/api/rex-signer/cancel_pairing.php', asyncRoute(handlers.cancelPairing));
  app.get('/api/rex-signer/sessions.php', asyncRoute(handlers.listSessions));
  app.post('/api/rex-signer/revoke_session.php', asyncRoute(handlers.revokeSession));
  app.get('/api/rex-signer/realtime_auth.php', asyncRoute(handlers.realtimeAuth));
  app.post('/api/rex-signer/realtime_auth.php', asyncRoute(handlers.realtimeAuth));
  app.get('/api/rex-signer/networks.php', asyncRoute(handlers.networks));
  app.get('/api/rex-signer/assets.php', asyncRoute(handlers.assets));
  app.get('/api/rex-signer/external_history.php', asyncRoute(handlers.externalHistory));
  app.post('/api/rex-signer/create_approval_request.php', asyncRoute(handlers.createApprovalRequest));
  app.post('/api/rex-signer/create_claim_approval.php', asyncRoute(handlers.createClaimApproval));
  app.get('/api/rex-signer/approval_requests.php', asyncRoute(handlers.listApprovalRequests));
  app.get('/api/rex-signer/approval_status.php', asyncRoute(handlers.getApprovalStatus));
  app.post('/api/rex-signer/approval_decision.php', asyncRoute(handlers.decideApproval));
  app.post('/api/rex-signer/complete_claim_tx.php', asyncRoute(handlers.completeClaimTx));
  app.post('/api/rex-signer/auth/login_from_session.php', asyncRoute(handlers.loginFromSession));
}

module.exports = registerRoutes;
