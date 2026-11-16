# CleanPaste (CloakPaste)

Paste any screenshot or PDF. Auto-redact sensitive info in 2 seconds—100% inside your browser.

Images are never uploaded. OCR (Tesseract.js), PII detection and canvas redaction all run client-side. The PHP backend only handles accounts, saved custom rules and billing webhooks.

## Requirements

- PHP 8.0+ with `pdo_mysql` and `json`
- MySQL 8 / MariaDB 10.4+
- Composer is optional (the app ships its own autoloader)

## Setup

1. **Configure environment**

   ```bash
   cp .env.example .env
   ```

   Edit `.env` with your database credentials. Billing values are only needed for Pro (see below).

2. **Create the database and tables**

   ```bash
   mysql -u root -p -e "CREATE DATABASE cloakpaste CHARACTER SET utf8mb4"
   mysql -u root -p cloakpaste < schema.sql
   ```

3. **Run locally**

   ```bash
   php -S localhost:8000 -t public
   ```

   Open http://localhost:8000 and press `Ctrl+V` with a screenshot on your clipboard.

   The editor works without a database (free tier); accounts, Pro status and saved rules need it.

## Billing (Lemon Squeezy)

1. Create two products/variants: monthly ($7) and lifetime ($39).
2. In `.env`, set `LS_VARIANT_MONTHLY` and `LS_VARIANT_LIFETIME` to the variant IDs.
3. Set `LS_CHECKOUT_MONTHLY` and `LS_CHECKOUT_LIFETIME` to each product's hosted checkout (share) URL. The pricing buttons show "Coming soon" until these are set. Buying requires an account; the checkout email is prefilled from it.
4. Add a webhook pointing at `https://your-domain/api/webhook.php`, copy its signing secret into `LS_WEBHOOK_SECRET`, and enable these events:
   `order_created`, `order_refunded`, `subscription_created`, `subscription_updated`, `subscription_resumed`, `subscription_unpaused`, `subscription_expired`.

Requests are rejected unless the `X-Signature` HMAC matches. Purchases are stored by email, so they apply even if the buyer signs up afterwards. Use the same email at checkout and signup.

## Project layout

```text
public/            Web root (point your server here)
  index.php        UI: paste/drop, states, export
  assets/js/       ocr.js (PII detection), editor.js (canvas redaction)
  api/             auth.php, rules.php, webhook.php
src/               Config, Controllers, Services, Support (PSR-4: CloakPaste\)
schema.sql         Database schema
```

## API

| Endpoint | Method | Notes |
|---|---|---|
| `api/auth.php?action=me` | GET | Current user + CSRF token |
| `api/auth.php?action=register\|login\|logout` | POST | JSON body; needs `X-CSRF-Token` |
| `api/rules.php` | GET | List saved rules (Pro) |
| `api/rules.php` | POST | Create `{rule_name, pattern}` |
| `api/rules.php?id=N` | PUT / DELETE | Update / delete |
| `api/webhook.php` | POST | Lemon Squeezy, signature-verified |

## Production notes

- Serve only `public/` as the web root so `.env` and `src/` are not reachable, and use HTTPS (session cookies are `Secure` over HTTPS).
- Not yet implemented: email verification, login rate limiting, login/signup UI, rules manager UI.
- The free-tier daily limit is tracked in the browser only.
