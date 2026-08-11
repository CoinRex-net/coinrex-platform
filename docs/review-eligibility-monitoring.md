# Review Eligibility Monitoring

CoinRex review eligibility uses forward monitoring. It does not calculate an average balance or convert token balances to USD.

## Verification Methods

CoinRex supports three verification methods for review eligibility:

- **Instant Verification**: Checks on-chain holding from the last 30 days. Fastest path if the reviewer already holds the required token. Uses `POST /api/review-eligibility/instant.php` and returns `eligible` or `not_eligible` immediately.
- **Live Verification (Forward Monitoring)**: Requires wallet ownership proof, confirms incoming transfer and current balance, then monitors the configured token threshold for the project-defined duration. A successful session becomes eligible for 24 hours and is consumed by one review submission.
- **Manual Verification**: Fallback when on-chain checks are not possible. Requires wallet address, TX hash, and screenshot proof. Submission stays pending until moderation reviews the evidence.

## Rule

1. The reviewer verifies wallet ownership.
2. The reviewer receives the configured project token.
3. `Start Verification` confirms the incoming transfer and required current balance.
4. CoinRex monitors the configured token threshold for the project-defined duration.
5. Any confirmed balance drop below the threshold stops verification.
6. A successful session becomes eligible for 24 hours and is consumed by one review submission.

Token amounts are compared as raw blockchain integers using the contract decimals.

## Required cron

Run the worker every minute from the deployed application root:

```cron
* * * * * /usr/local/bin/php /home/coinwsua/public_html/scripts/process-review-eligibility.php >/dev/null 2>&1
```

Adjust the PHP binary and deployment path for the host. The worker is idempotent and processes due sessions plus notification outbox deliveries.

## Required environment

- `ETHERSCAN_API_KEY` or `EXPLORER_API_KEY`
- Optional `ETHERSCAN_API_BASE_URL` (defaults to Etherscan V2)
- SMTP environment values for email delivery

## Deployment

Apply `database/migrations/2026_08_01_review_eligibility_monitoring.sql`, configure the cron, verify project contract symbols/decimals/rules, then test one native-token and one ERC-20 monitoring session before enabling the review feature publicly.

