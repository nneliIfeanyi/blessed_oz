# Offline & Online Sync with Paid Pro Plan - Implementation Roadmap

## 1. Pricing Structure
- **Monthly**: $10 / month (₦15,000 / month)
- **6 Months (20% OFF)**: $48 (₦72,000)
- **Yearly (40% OFF)**: $72 (₦108,000)

---

## 2. Implementation Steps

### Step 1: Database & Subscription Logic Layer
- Add columns to `user` / `store` table:
  - `subscription_plan` (enum: `'free'`, `'pro'`)
  - `subscription_cycle` (enum: `'monthly'`, `'6months'`, `'yearly'`)
  - `subscription_expires_at` (datetime)
- Add verification helper in `inc/auth.php` (`isProActive()`).
- Populate session values in `model/login/checkLogin.php`.

### Step 2: Pro Features & Comparison Page (`upgrade.php`)
- Feature comparison table (Free vs Pro).
- Interactive billing cycle toggle (Monthly, 6-Month with 20% discount, Yearly with 40% discount).
- Currency toggle (USD `$` / NGN `₦`).
- Direct "Upgrade to Pro" CTA button.

### Step 3: IndexedDB Storage & Offline Engine (`assets/js/db-sync.js`)
- Stores:
  - `outbox_sales`: offline sales queue.
  - `outbox_purchases`: offline purchases queue.
  - `cached_catalog`: local product/price cache for offline lookup.
- Hook into form submissions (`insertSale.php` / `insertPurchase.php`).

### Step 4: Status Indicator & Pro Gating
- Navbar status badge in `inc/navigation.php`:
  - 🟢 **Online / Synced**
  - 🟡 **Pending Sync (Count)** + "Sync Now" button
  - 🔴 **Offline Mode**
- Free plan restriction: Displays modern upgrade modal when an offline sale/purchase attempt is made.

### Step 5: Backend Idempotent Sync Endpoints
- `model/sale/syncSale.php` & `model/purchase/syncPurchase.php`.
- Validate Pro subscription on the server.
- Support `client_reference_id` (UUID) to prevent duplicate transactions.

---

## 3. Resume Command
When ready, prompt:
> **"Continue with offline sync implementation"**
