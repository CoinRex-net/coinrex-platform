const crypto = require('crypto');

function jsonOk(res, payload = {}, status = 200) {
  res.status(status).json({ success: true, ...payload });
}

function jsonError(res, status, message, extra = {}) {
  res.status(status).json({ success: false, message: String(message || 'Request failed.'), ...extra });
}

function sha256(value) {
  return crypto.createHash('sha256').update(String(value).trim()).digest('hex');
}

function randomToken(bytes = 32) {
  return crypto.randomBytes(bytes).toString('base64url');
}

function pairCode() {
  return String(crypto.randomInt(0, 1000000)).padStart(6, '0');
}

function normalizePairCode(code) {
  return String(code || '').replace(/\D+/g, '').slice(0, 6);
}

function formatPairCode(code) {
  const digits = normalizePairCode(code);
  if (!/^\d{6}$/.test(digits)) throw new Error('Pairing code must be 6 digits.');
  return `REX-${digits.slice(0, 3)}-${digits.slice(3)}`;
}

function clampDuration(value) {
  const minutes = Number(value || 10);
  if (!Number.isFinite(minutes) || minutes <= 0) return 10;
  return Math.max(5, Math.min(Math.floor(minutes), 60));
}

function normalizeWallet(value) {
  const wallet = String(value || '').trim().toLowerCase();
  if (!/^0x[a-f0-9]{40}$/.test(wallet)) throw new Error('A valid wallet address is required.');
  return wallet;
}

function decimalToWei(amount, decimals = 18) {
  const text = String(amount || '').trim();
  if (!/^\d+(\.\d+)?$/.test(text)) throw new Error('Invalid claim amount.');
  const [whole, frac = ''] = text.split('.');
  const fraction = (frac + '0'.repeat(decimals)).slice(0, decimals);
  const combined = `${whole}${fraction}`.replace(/^0+/, '');
  return combined || '0';
}

function parseCookies(header = '') {
  const cookies = {};
  for (const part of String(header || '').split(';')) {
    const index = part.indexOf('=');
    if (index <= 0) continue;
    cookies[part.slice(0, index).trim()] = decodeURIComponent(part.slice(index + 1).trim());
  }
  return cookies;
}

module.exports = {
  jsonOk,
  jsonError,
  sha256,
  randomToken,
  pairCode,
  normalizePairCode,
  formatPairCode,
  clampDuration,
  normalizeWallet,
  decimalToWei,
  parseCookies,
};
