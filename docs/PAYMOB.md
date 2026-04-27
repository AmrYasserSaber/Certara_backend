# Paymob (Egypt) — Credentials & Dashboard Setup

This project integrates Paymob in **Egypt** using **Quick Link** for checkout URLs and **Transaction Processed webhooks** as the source of truth for payment status updates.

Base URL (Egypt): `https://accept.paymob.com/`

## Required credentials

You will need the following from the Paymob **Accept Dashboard**:

- **Secret Key** (server-side API requests)
- **Public Key** (some checkout flows use it)
- **API Key** (Paymob dashboard key; Paymob states it is the same for test/live)
- **HMAC Secret** (to verify callbacks)
- **Integration ID(s)** for enabled methods:
    - Card
    - Wallets
    - Kiosk

## Where to find each credential (current Paymob docs)

Reference: [Getting Integration Credentials](https://developers.paymob.com/paymob-docs/need-help/faq/getting-integration-credentials)

### Secret Key

- Accept Dashboard → **Settings**
- Select mode: **Test** or **Live** (must match the Integration IDs you plan to use)
- Click **View** next to **Secret Key** and copy it

### Public Key

- Accept Dashboard → **Settings**
- Select mode: **Test** or **Live**
- Click **View** next to **Public Key** and copy it

### API Key

- Accept Dashboard → **Settings**
- Click **View** next to **API Key** and copy it

### Integration IDs (Payment Integrations)

- Accept Dashboard → **Developers** → **Payment Integrations**
- Select mode: **Test** or **Live**
- Copy the Integration ID for each enabled method you need (Card/Wallet/Kiosk)

### Callback URLs (per Integration ID)

- Accept Dashboard → **Developers** → **Payment Integrations**
- Select mode: **Test** or **Live**
- Select the Integration ID you want to edit
- Click **Edit**
- Set:
    - **Transaction Processed Callback URL**: `https://<your-domain>/api/payment/callback`
    - **Transaction Response Callback URL**: optional (used only for showing UX status pages; not source of truth)
- Click **Submit**

### HMAC secret

Paymob includes an `hmac` query parameter with callbacks and requires verifying it using **SHA-512**.

Reference: [HMAC Transaction Callback](https://developers.paymob.com/paymob-docs/developers/webhook-callbacks-and-hmac/hmac/hmac-transaction-callback)

In the dashboard, the HMAC secret is typically visible/recreatable under **Settings** (same area as keys). If you rotate it, you must update `PAYMOB_HMAC_SECRET` immediately.

## Environment variables used by this project

Add these to `backend/.env`:

- `PAYMOB_ENV` (`test` or `live`)
- `PAYMOB_API_SECRET_KEY`
- `PAYMOB_PUBLIC_KEY`
- `PAYMOB_API_KEY`
- `PAYMOB_HMAC_SECRET`
- `PAYMOB_ENABLED_METHODS` (default: `card,wallet,kiosk`)
- `PAYMOB_TRANSACTION_RESPONSE_URL` (optional)
