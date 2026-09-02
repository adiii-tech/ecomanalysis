# MASTER BUILD PROMPT — "Brandstack-class" D2C Analytics Platform in Laravel

You are a senior full-stack architect. Build a production-grade, multi-tenant
**D2C + Marketplace Profitability & Analytics Platform** for Indian e-commerce brands.
Follow this spec exactly. Do not skip modules. Ask nothing — make sensible decisions and build.

---

## 0. NON-NEGOTIABLE PRODUCT PHILOSOPHY

The entire product exists to answer one question: **"Am I actually making money?"**

- Never show gross revenue alone. Every number resolves down the chain:
  `Gross Sales → − Discounts → − Cancelled → = Invoiced Sales → − Returns → − RTO → = Net Sales → − COGS → − Fees → − Logistics → = Contribution Margin`
- India-first: COD vs Prepaid economics, RTO by state, marketplace commission leakage,
  GST, Tally-compatible exports, ₹ formatting with Indian lakh/crore grouping (₹9,56,832).
- Every widget must give a **verdict**, not just a number ("Scale / Hold / Cut",
  "Below benchmark — focus retention", "Prepaid is 4.4% more profitable than COD").
- **Data honesty**: if a metric cannot be computed truthfully, print a caveat note
  under the widget instead of faking it. Never fabricate sentiment, attribution or estimates.

---

## 1. TECH STACK (use exactly this)

- **Laravel 12** (PHP 8.3+), strict types everywhere
- **Inertia.js v2 + React 19 + TypeScript** (SPA feel, Laravel routing/auth)
- **Tailwind CSS v4 + shadcn/ui** components
- **Recharts** for all charts (bar, line, area, stacked, combo dual-axis, heatmap via custom)
- **MySQL 8** primary (or PostgreSQL 16 — pick Postgres if you want window functions comfort)
- **Redis** — cache, queues, rate limiting, session
- **Laravel Horizon** — queue dashboard, all sync jobs on dedicated queues
- **Laravel Scheduler** — connector sync cadence
- **Laravel Sanctum** — SPA auth + API tokens
- **spatie/laravel-permission** — roles & granular widget permissions
- **spatie/laravel-activitylog** — audit trail
- **maatwebsite/excel** — CSV/XLSX export; **barryvdh/laravel-dompdf** or Browsershot — PDF export
- **OpenAI / Anthropic PHP SDK** — AI layer
- **Laravel Pennant** — feature flags & plan gating
- **Pest** for tests, **Larastan level 8**, **Laravel Pint**

Directory convention: Domain-driven modules under `app/Domain/{Sales,Marketing,Marketplace,Operations,Customers,Reports,AI,Connectors}` with `Actions/`, `DTOs/`, `Queries/`, `Models/`, `Services/`.

---

## 2. MULTI-TENANCY & AUTH

- Multi-tenant, **single database, `tenant_id` scoped** via a global Eloquent scope + `BelongsToTenant` trait.
- `tenants` table: name, slug, currency, timezone (default Asia/Kolkata), fiscal_year_start, plan, ai_credit_limit, is_demo, onboarding_state.
- Auth: email+password login, **MFA/TOTP** (verify + resend), forgot/reset password,
  invite flow (`accept-invite`, resend invite, revoke invite), user enable/disable, force password reset.
- User fields: name, email, role_id, permissions_version, accent_color, is_demo, ai_credits_used, ai_credit_limit.
- `/auth/me` returns user + flattened permission array; frontend caches it and busts on `permissions_version` change.

### RBAC — widget-level, this is critical
Permission naming: `module.widget.action` → e.g.
`dashboard.kpi_strip.view`, `marketing.campaign_table.view`, `reports.fee_leakage.view`,
`marketplace.zero_order_skus.view`, `customer_intelligence.churn.view`, `ai.chat.view`.

- Seed permissions for EVERY module, EVERY widget, EVERY report (300+ permissions).
- Roles: `OWNER`, `ADMIN`, `FINANCE`, `MARKETING`, `OPERATIONS`, `ANALYST`, `DEMO`, plus custom role builder.
- Per-user permission **overrides** on top of role, with a "reset to role" action.
- Backend: middleware + policy guard on every endpoint.
- Frontend: `<PermissionGuard permission="dashboard.kpi_strip.view">` wrapper — hides widget entirely, no empty shells.
- Admin console: `/admin/users`, `/admin/users/{id}/permissions`, `/admin/roles`, `/admin/roles/{id}`, `/admin/audit`, `/admin/settings`, `/admin/demo-users`.

---

## 3. CONNECTOR / INGESTION ARCHITECTURE

Build a **pluggable connector framework**. Each connector implements:

```php
interface Connector {
    public function id(): string;               // 'shopify'
    public function label(): string;
    public function summary(): string;
    public function authType(): AuthType;       // oauth | token | key_secret
    public function connect(array $credentials): ConnectionResult;
    public function testConnection(): HealthResult;
    public function syncableEntities(): array;  // ['orders','products','customers',...]
    public function sync(SyncContext $ctx): SyncReport;
}
```

- `connectors` table: tenant_id, connector_id, status(disconnected|connected|error|syncing),
  auth_type, credentials (encrypted cast), account_label, last_connected_at, last_synced_at,
  last_error, sync_cursor (json), has_secret.
- `sync_runs` table: connector, entity, started_at, finished_at, status, records_fetched,
  records_upserted, error, cursor_before, cursor_after. Surface this in UI as sync health.
- Incremental sync with cursors + backfill jobs. Idempotent upserts on `(tenant_id, source, external_id)`.
- Rate-limit aware with exponential backoff; every sync is a queued job on its own queue.
- Scheduler: orders every 15 min, ads every 1 hr, analytics every 1 hr, inventory every 30 min, reviews every 6 hr.
- Manual `POST /api/sync/{connector}` to force a sync (rate limited, shows progress).
- Webhook receivers where the platform supports them (Shopify orders/create, orders/updated, refunds/create).

### Connectors to implement (Phase 1)
| id | Auth | Pulls |
|---|---|---|
| `shopify` | OAuth | orders, line items, customers, products/variants, inventory, refunds, transactions, payouts, discount codes, abandoned checkouts |
| `unicommerce` | Token | marketplace sale orders, shipments, returns, inventory (Uniware), channel mapping |
| `meta` | OAuth | ad accounts, campaigns, adsets, ads, insights (spend/impr/clicks/purchases/value), demographics, placements; Instagram media/insights/audience; FB Page insights |
| `google_ads` | OAuth | campaigns, spend, clicks, conversions, conv value, geo |
| `ga4` | OAuth | sessions, channel groups, ecommerce funnel, product views/ATC, pages, cities, countries, realtime, demographics, UTM |
| `ithink` | Token | AWB, shipment status, NDR, RTO, COD remittance |
| `judgeme` | Token | reviews, ratings, product mapping, reviewer, photos |

### Connectors to ADD beyond the reference product (Phase 2 — build the interface now, stub the drivers)
`shiprocket`, `delhivery`, `bluedart`, `razorpay`, `cashfree`, `easebuzz`, `gokwik`,
`amazon_sp_api` (direct), `flipkart_seller` (direct), `meesho`, `nykaa`,
`whatsapp_cloud_api` (Interakt/WATI), `klaviyo`, `mailchimp`, `tally`, `zoho_books`,
`woocommerce`, `tiktok_ads`, `pinterest_ads`, `microsoft_clarity`.

---

## 4. DATA MODEL (canonical, source-agnostic)

Normalise everything into one schema so Shopify and marketplace data live side by side.

**Core**
- `channels` (id, tenant_id, name, type: d2c|marketplace, source_connector, is_active)
- `orders` — tenant_id, channel_id, source, external_id, order_number, placed_at, invoiced_at, status (placed|confirmed|shipped|delivered|cancelled|returned|rto), payment_mode (cod|prepaid), customer_id, shipping_state, shipping_city, shipping_pincode, currency, gross_amount, discount_amount, shipping_amount, tax_amount, invoiced_amount, net_amount, cogs_amount, fees_amount, logistics_amount, contribution_margin, is_first_order
- `order_items` — order_id, sku_id, qty, unit_price, discount, tax, cogs_unit, line_net, line_margin, status
- `products` / `variants` (`skus`) — sku_code, name, category, subcategory, brand, mrp, selling_price, **cost_price (COGS)**, weight, hsn, gst_rate, image_url, is_combo, combo_children
- `inventory` — sku_id, location_id, source, on_hand, reserved, available, updated_at
- `customers` — tenant_id, source, external_id, email_hash, masked_email, phone_hash, name, first_order_at, last_order_at, orders_count, total_spent, aov, ltv, city, state, rfm_r, rfm_f, rfm_m, rfm_segment, is_vip, churn_risk_score
- `shipments` — order_id, courier, awb, dispatched_at, delivered_at, status, attempts, ndr_reason, is_rto, rto_at, cod_collected_at, cod_remitted_at, shipping_cost, rto_cost
- `returns` — order_id, order_item_id, type (customer_return|rto|exchange), reason_code, reason_text, initiated_at, received_at, refund_amount, restock, channel_id
- `transactions` — order_id, gateway, method, kind (sale|capture|authorization|refund|void), amount, fee, status, processed_at
- `marketplace_fees` — order_id, channel_id, fee_type (commission|fixed|shipping|collection|settlement|penalty|other), amount, settlement_id, settled_at
- `discount_codes` / `order_discounts` — code, type, value, orders_count, revenue, margin_burn, aov_lift
- `reviews` — source, product_sku, rating, title, body, reviewer, verified, has_photos, created_at, sentiment (nullable — leave NULL until real NLP)

**Marketing**
- `ad_accounts`, `campaigns`, `ad_sets`, `ads`, `ad_creatives`
- `ad_insights_daily` — date, platform, campaign_id, adset_id, ad_id, spend, impressions, clicks, ctr, cpc, cpm, conversions, conversion_value, roas, placement, age, gender, country, state
- `analytics_daily` (GA4) — date, channel_group, source, medium, campaign, sessions, users, new_users, engaged_sessions, bounce_rate, add_to_carts, checkouts, purchases, revenue
- `analytics_pages`, `analytics_cities`, `analytics_products`, `analytics_realtime`
- `social_accounts`, `social_posts` (media_id, type: post|reel|story|carousel, published_at, reach, impressions, views, likes, comments, shares, **saves**, avg_watch_time, engagement_rate), `social_audience_daily`, `social_account_daily`

**Derived / performance**
- `daily_metrics_rollup` — one row per (tenant, date, channel, payment_mode) with every aggregate pre-computed. **All dashboards read from rollups, never raw orders.** Rebuild job on sync completion + nightly full recompute.
- `sku_daily_rollup`, `state_daily_rollup`, `cohort_snapshots`
- Rollups are the single source of truth for speed. Target: every dashboard endpoint < 300 ms.

**Costs & config**
- `cost_settings` — per tenant: default COGS method, packaging cost, per-order fixed cost, cod_charge, return_handling_cost, gst_mode, fixed monthly opex (for true P&L)
- `sku_cost_history` — sku_id, cost_price, effective_from (so historical margin is correct)
- `benchmarks` — tenant targets: target_roas, target_margin, target_repeat_rate, dispatch_sla_days, rto_threshold_pct

---

## 5. GLOBAL UX PRIMITIVES (build these once, use everywhere)

1. **Global date-range picker** — presets (Today, Yesterday, Last 7/30/90 days, MTD, QTD, YTD, Last month, Custom) persisted in URL query + localStorage. Every request carries `from`, `to`.
2. **Automatic previous-period comparison** — every KPI returns `{value, prev_value, delta_pct, direction, is_good}`. Colour by *goodness*, not by up/down (a fall in RTO is green).
3. **Channel filter** — All / Shopify (D2C) / Marketplace / individual marketplace tabs.
4. **Returns basis toggle** — `return_date` vs `order_date` (cohort accounting). Affects every returns number.
5. **KpiCard** component — label, tooltip (metric definition), value, sparkline, delta chip, optional badge, click → drill-down drawer.
6. **ChartCard** component — title, info tooltip, subtitle/context line, filter tabs, "View detail →", AI-insight ✨ button, export menu.
7. **Drill-down Drawer** — right-side sheet with a paginated, sortable, filterable, searchable table + CSV/PDF export. Every widget drills to row-level truth.
8. **DataTable** — server-side pagination, multi-sort, column filters, sticky header, column tooltips, ⌘K search, saved views.
9. **Caveat note** slot under any widget for data-honesty disclosures.
10. **Empty / partial / error states** — "Connect Shopify to see this", "Needs GA4 Google Signals", "No loss-making orders in view 🎉".
11. **Skeleton loaders** — never spinners.
12. **Indian number formatting** — ₹ with lakh grouping, compact mode (₹9.6L, ₹1.2Cr).
13. **Command palette (⌘K)** — jump to any page, report, SKU, order, customer.
14. **Sidebar collapse**, per-user accent colour, light/dark theme.
15. **Sync status indicator** in header — green/amber/red dot, hover → per-connector last-synced + errors.

---

## 6. MODULE SPEC — build every widget listed

### 6.1 `/dashboard` — Command Centre
**KPI strip:** Gross Sales · Invoiced Sales · Net Sales · Orders (+fulfilled count) · Contribution Margin % · AOV — each with sparkline, delta, drill-down.

**Widgets:**
- Sales by Channel (Invoiced) — stacked daily bars + channel tabs
- **Sales Journey** — guided step-by-step animated walkthrough of the gross→net waterfall
- Net Sales vs Net Margin — dual-line, → Open Order P&L
- **Sales Summary waterfall table** — Gross, Discounts, Cancelled, Invoiced, Returned, RTO, Net Sales, Shipping, Return Fees (orders + amount columns), tabs All/Shopify/Marketplace
- Top Performing States (orders + sales)
- Top Performing Categories (% share + amount)
- **COD vs Prepaid Economics** — per mode: orders, net sales, COGS, net margin ₹ and %, share of sales, share of orders, RTO % + auto verdict line
- Channel Mix — net sales + margin % per channel → Open Scorecard
- Returns by Channel (returned/orders, return %)
- Top Return Reasons · Top Return SKUs
- **Biggest Loss Orders** — orders with negative margin
- Highest RTO States (RTO count + %)
- Recent Orders
- **Health Flags** — auto-generated red flags (stockouts, SLA breach, RTO spike, margin drop, ad anomaly)
- **Revenue Pacing** — MTD actual vs target vs run-rate projection
- **Morning Brief** — auto narrative summary of last 24h
- **SKU Pareto** — which 20% of SKUs make 80% of profit
- **State Action Matrix** — states plotted sales × RTO% with recommended action per state
- **LTV Cohort** mini-widget
- **Critical Inventory** — days-of-cover < threshold on bestsellers

### 6.2 `/finance`
- KPI strip: Gross Sales · Returning Customer Rate · Orders · Ads Spend · Attributed ROAS · **Net Profit**
- Net sales over time (current vs previous overlay)
- Geographic Sales — realised net sales by state, Shopify/Marketplace toggle, ranked top 10 + map view
- AOV over time (current vs prev)
- Top SKUs by Net Sales (units, orders, margin)
- Revenue breakdown (gross→net waterfall chart)
- Payment method split
- Refunds + refunds summary
- **Transaction ledger** — every payment/refund/failure with gateway, method, kind, status
- **[NEW] True P&L statement** — revenue, COGS, ad spend, logistics, marketplace fees, payment gateway fees, packaging, fixed opex → EBITDA. Monthly columns.
- **[NEW] Cash flow view** — money in vs money stuck in COD remittance vs returns liability

### 6.3 `/marketing`
- KPI strip: Gross Sales · Total Ad Spend · **Blended ROAS** · Attributed Sales · Attributed ROAS · Ad Spend % of Revenue · **CAC** · **New-customer CAC**
- **Campaign Performance table** — spend, attributed sales, orders, ROAS, sortable, with auto **STATUS verdict: Scale / Hold / Cut** computed vs account-average ROAS and a configurable target
- **Marketing Insights cards** — Best Performer / Needs Attention / Trend / Opportunity, auto-written from data
- **Conversion Funnel** (GA4) — Sessions → Add to Cart → Checkout → Purchase, with each step rate + overall conversion
- **Abandoned Carts** — count, recoverable value, avg cart value → drill to list
- **Live Active Users** — realtime, refresh every 60s
- Marketing Trend — Meta spend / Google spend / sales / ROAS combo chart with **anomaly markers**
- Active Users by Country
- **Marketing Channel Performance** — Organic / Direct / Meta / Google: sessions, orders, sales, conv %, ad spend, **site ROAS**
- Product Marketing Performance — Views → ATC → Orders → Sales per product
- Top Cities · Top Pages & Screens (views, users, V/U, avg time, events, bounce)
- **Buyer Persona — Age × Gender** — GA4 visitors vs Meta paid buyers with spend/clicks/orders/sales/ROAS
- Creative fatigue · Spend by objective · Placement performance · UTM analysis · Attribution gap (platform-reported vs actual store orders)
- **[NEW] Ad creative library** — thumbnail grid, per-creative spend/ROAS/hook-rate/thumb-stop ratio
- **[NEW] Budget pacing** — planned vs actual spend, projected month-end
- **[NEW] MER (Marketing Efficiency Ratio)** = total revenue / total ad spend, tracked daily

### 6.4 `/instagram` (+ Facebook Page)
- KPIs: Followers (+new) · Reach · Profile Visits · Engagement Rate · Content Published · **Avg Reel Watch (s)** · FB Followers
- Growth & Reach (reach/views/followers)
- Engagement chart — likes, comments, shares, **saves flagged as buy-intent**
- Content Performance grid — top posts/reels: ENG %, reach, saves, likes, comments, shares, views; sort by reach/recent/engagement
- **Reels vs Feed** format effectiveness (avg reach, avg engagement, total saves)
- Stories table (reach, views, replies)
- Audience — top cities, countries, age buckets, gender split
- **[NEW] Best time to post** heatmap · **[NEW] Hashtag performance** · **[NEW] Organic → sales correlation** overlay

### 6.5 `/marketplace`
- **Date-filter-independent snapshot strip**: Today's Sales (invoiced), Today's Order Items, Total SKU Count, Out-of-Stock %, Inventory Value at cost — each with yesterday comparison
- Channel tabs: All / Amazon / Flipkart / Myntra / Meesho / Nykaa / Ajio / custom
- KPIs: Invoiced Sales · Net Sales · Total Orders · AOV · Cancelled (+% of cohort) · Returns · RTO (cohort)
- Sales trend per marketplace (Sales / Order-Item toggle)
- Channel comparison (orders, sales, share)
- Marketplace Sales Summary waterfall (incl. COD charges)
- Top Categories · Order Status distribution · COD vs Prepaid · Top States
- Top Performing Products (% of total sales)
- **Channel-wise Top Products matrix** (SKU rows × marketplace columns)
- Channel-wise Return % (units sold vs return rate, dual axis)
- Top Return Reasons
- **Products with Zero Orders** + inventory held (dead stock capital)
- Fast-moving SKUs · Inventory valuation by category
- Recent Orders table
- **[NEW] Buy Box / listing health** · **[NEW] Price competitiveness vs MRP across channels** · **[NEW] Settlement reconciliation** (expected vs received per settlement cycle)

### 6.6 `/operations`
- Logistics KPIs: Total Shipments (D2C + MP split) · Delivered % · In Transit · Returned %
- Returns & RTO: Returns · RTO Events · **Return Loss (₹ value)** — with return/order date basis toggle
- Returns by Reason · Returns by Channel (customer return vs RTO) · Returns Trend
- Shipment Status breakdown per courier + sync caveat note
- **RTO by State** — flags states above threshold, plotted against sales volume
- Marketplace Delivery funnel — Shipped → Delivered / In Transit / Returned
- **On-time delivery %**, avg dispatch→delivery days (+median), late deliveries (> SLA)
- **[NEW] Courier scorecard** — delivery %, RTO %, avg days, cost per shipment, NDR resolution rate
- **[NEW] NDR management queue** — undelivered attempts needing action
- **[NEW] Pincode serviceability & RTO risk score** — flag risky pincodes at checkout (expose as an API for the storefront)
- **[NEW] Order Aging live board** — unshipped orders bucketed 0-1d / 1-2d / 2-3d / >3d with SLA breach alerts

### 6.7 `/customers-reviews`
**Customer Intelligence (be honest: D2C only, marketplaces anonymise buyers)**
- KPIs: Total Customers · New · Returning · **Repeat Rate** (vs benchmark) · **Avg LTV** · Store Rating
- Top Customers (masked email, location, last order, orders, AOV, total spent)
- **Customer 360** page — timeline of orders, returns, reviews, ad touchpoints, LTV, RFM, churn risk
- **Cohort retention heatmap** — acquisition month × M0..M12 repeat %, CSV/PDF export
- Repeat metrics: repeat purchase rate, avg orders/customer, 2nd order ≤ 90 days, avg days to 2nd order
- **RFM segmentation** — Champions, Loyal, Potential, New, At Risk, Hibernating, Lost (with counts + revenue per segment)
- LTV distribution · VIP list · **Churn risk scoring** · Purchase interval distribution · Per-customer returns (serial returners) · Geo distribution · **Customer Explorer** (query builder / segment builder)
- **[NEW] Segment export to Meta Custom Audience / Klaviyo / WhatsApp**
- **[NEW] Serial-returner blocklist** feeding the RTO risk API

**Reviews**
- Rating summary — score, star distribution, % positive/negative, % with photos, trend
- Top rated / worst rated products
- Recent reviews feed with verified badge, filters, sort
- **[NEW] Real sentiment + theme extraction via LLM** (do it properly — this is the reference product's admitted gap)
- **[NEW] Review → return correlation** (products with good reviews but high returns = sizing/expectation problem)

### 6.8 `/reports` — Report Library
Library UX: KPI strip of counts per category, category filter, A–Z / recently-used sort, ⌘K search,
pagination, **favourite**, **share link**, **schedule email delivery**, per-report permission gate.

Build all 20 reports below, each as a full page with its own KPI strip, filters, table(s), chart(s), CSV + PDF export:

**Executive**
1. `owner-business-review` — one-screen exec scorecard vs previous period

**Profit & Margin**
2. `channel-scorecard` — revenue, orders, AOV, margin %, RTO rate ranked by channel
3. `discount-impact` — per code: orders, revenue, margin burn, discount depth, AOV lift → "did the promo buy volume or burn margin?"
4. `fee-leakage` — commission / fixed / shipping / settlement fees per channel, ₹ and % of GMV
5. `net-realisation` — per-marketplace gross → net waterfall after fees, discounts, GST
6. `order-profitability` — order-level P&L: revenue − COGS − fees − logistics − returns, filters (order type / channel / payment / state), CSV + PDF

**Marketing & Customers**
7. `cohort-retention` · 8. `geo-cities` (revenue & orders by state/city) · 9. `channel-cac` (CAC & ROAS by source)
10. `new-vs-repeat` (conversion & ROAS split) · 11. `state-roi` (revenue, spend, ROAS, CAC by state) · 12. `top-customers` (LTV)

**Operations & Inventory**
13. `inventory-health` — stock levels, turnover, reorder signals by SKU
14. `logistics-performance` — shipment status, courier performance, RTO by state
15. `order-aging` — unshipped orders by age, SLA breach flags
16. `reorder-replenishment` — days of cover, suggested reorder qty, **ABC class**, dead stock
17. `stockout` — estimated revenue lost to out-of-stock per SKU

**Returns & Cash**
18. `cod-cash-flow` — collected vs remitted, settlement delays, reconciliation
19. `returns-rto-register` — order/line-item level, downloadable, **Tally-matching column format**
20. `transaction-ledger` — every payment, refund, failure with gateway, method, kind, status

**[NEW] reports to add**
21. `pnl-statement` — full monthly P&L to EBITDA
22. `gst-summary` — output tax, HSN-wise, B2C/B2B split
23. `sku-margin-waterfall` — MRP → discount → net → COGS → fees → margin per SKU
24. `contribution-by-cohort` — profit per acquisition cohort over time
25. `forecast` — 30/60/90-day sales & inventory projection

---

## 7. AI LAYER

- **Ask AI** (`/ask-ai`) — chat over live tenant data.
  Implement as a **tool-calling agent**: expose read-only, permission-filtered SQL/metric tools
  (`get_kpis`, `get_channel_breakdown`, `get_sku_performance`, `get_returns`, `get_campaigns`,
  `get_customers`, `run_metric_query`). NEVER let the LLM write raw SQL against the DB — it picks
  from a whitelisted metric registry with typed params. Return charts + tables inline in the answer.
- Suggested starter prompts: "How's this month's sales vs last month?", "Which channel is most profitable right now?",
  "How are returns and RTO trending?", "What should I fix first to grow next month?"
- **Chat history / sessions**, rename, delete, and **shareable public answer links** (`/ask-ai/shared/{token}`, revocable, read-only snapshot).
- **Per-chart AI insight** — ✨ button on every widget → 2-3 sentence "story behind this chart", cached per (widget, date-range, tenant) for 24h.
- **Panel insight** with manual refresh.
- **AI Insights feed** + **AI Opportunities feed** — proactively generated ranked findings with estimated ₹ impact.
- **AI credit metering** per tenant/user (`ai_credits_used` / `ai_credit_limit`), enforced in middleware, shown as "N credits left".
- **Morning Brief** — scheduled daily generation, delivered in-app + email + WhatsApp.
- **[NEW] Anomaly detection engine** — statistical (z-score / STL decomposition) on daily metrics; feeds AI insights and alerts.

---

## 8. ALERTS & NOTIFICATIONS  *(the reference product has none — this is your edge)*

- Rule builder: metric + condition + threshold + window + channel.
  e.g. "RTO % in any state > 25% over 7 days", "Campaign ROAS < 1.5 for 3 days",
  "SKU days-of-cover < 7", "Order unshipped > 3 days", "Daily net margin drops > 20% WoW".
- Delivery: in-app bell, email, **WhatsApp (Cloud API)**, Slack webhook, push.
- Digest scheduler: daily morning brief, weekly business review, monthly P&L — with PDF attached.
- Notification centre with read/unread, snooze, mute-rule.

---

## 9. ONBOARDING & MONETISATION (copy these mechanics)

1. **Demo tenant with realistic seeded fake data on signup** — the entire app is explorable in
   30 seconds with zero connectors. Persistent top banner: *"You're exploring sample data.
   Tell us about your business and we'll set this up with your numbers. [Build mine →]"*.
   Ship a rich seeder: ~120 orders, 47 SKUs, 5 channels, 30 days of ads/GA4/IG data, reviews, returns.
2. **Paywall the interpretation, not the data** — charts render free; the AI insight strip on each
   chart shows *"Chart insights are part of the full plan — upgrade to see the story behind every chart."*
3. **AI credit metering** on free/demo ("1 of 2 demo AI credits left").
4. Plans via Laravel Pennant: `demo`, `starter`, `growth`, `scale`, `custom` — gate by
   connector count, report set, AI credits, seats, data history window, alert rules.
5. Connector-led onboarding wizard: connect Shopify → first sync progress screen → dashboard populated.
6. **Verdict language everywhere** — the product should feel like an advisor, not a spreadsheet.

---

## 10. API SURFACE

Prefix `/api`. Sanctum-guarded, tenant-scoped, permission-gated, all accepting `from`, `to`, `channel`, `returns_basis`.
Response envelope: `{success, statusCode, message, data, meta:{cached_at, period, prev_period}}`.

```
auth/            login logout me me/accent mfa/verify mfa/resend forgot-password reset-password accept-invite verify-token
dashboard/       kpis hero-kpis marquee morning-brief revenue-trend revenue-vs-spend sales-by-channel-daily
                 order-summary top-products top-states top-categories top-return-reasons high-return-products
                 logistics shipment-rows shipments-trend recent-orders campaigns conversion-funnel abandoned-carts
                 payment-mode-economics channel-mix revenue-margin-trend health-flags top-loss-orders top-loss-channels
                 critical-inventory returns-by-channel top-rto-states reputation revenue-pacing ltv-cohort
                 state-action-matrix sku-pareto
finance/         kpis sales-over-time sales-summary sales-by-channel sales-by-product revenue-breakdown
                 payment-method-split aov-trend geographic-sales top-skus refunds refunds/summary transactions pnl gst
marketing/       kpis campaigns campaign-detail trend insights conversion-funnel abandoned-carts creative-fatigue
                 spend-by-objective platform-revenue placements buyer-persona top-cities top-pages acquisition-channels
                 realtime-active-users active-users-by-country product-performance attribution-gap budget-pacing mer
instagram/       kpis account-trend engagement-trend content reels-vs-feed audience stories fb-page best-time hashtags
marketplace/     summary summary-prev today-snapshot sales-summary revenue-trend channel-comparison top-categories
                 top-products top-products-by-channel order-status payment-split top-states recent-orders order-rows
                 shipment-rows returns channel-returns delivery-analytics inventory/{summary,valuation,fast-moving,zero-orders}
                 settlements buybox
operations/      kpis shipment-status rto-by-state returns returns/events returns/rows delivery-funnel courier-scorecard
                 ndr-queue order-aging pincode-risk
customers/       kpis overview list customer/{key} rfm cohorts vip churn purchase-interval per-customer-returns
                 geo explorer segments segments/export
reviews/         summary trend recent top-rated worst-rated sentiment themes
catalog/         kpis products products/{id} best-sellers slow-movers inventory stockouts reorder margin locations
reports/         index {reportKey} {reportKey}/export favourite share schedule
ai/              ask/chat ask/history ask/session/{id} ask/session/{id}/share ask/shared/{token}
                 chart-insight panel-insight panel-insight/refresh insights opportunities usage
alerts/          rules rules/{id} events test channels
connectors/      index {id}/connect {id}/disconnect {id}/test sync/{id} sync-runs
admin/           users users/{id} users/{id}/permissions users/{id}/permissions/reset users/{id}/enable
                 users/{id}/disable users/{id}/password users/{id}/resend-invite users/{id}/revoke-invite
                 roles roles/{id} audit settings demo-users
platform/        geo/currency health config
```

---

## 11. PERFORMANCE & QUALITY BARS

- Every dashboard endpoint served from `daily_metrics_rollup` + Redis cache keyed by
  `tenant:module:widget:from:to:channel:basis`, TTL 5 min, busted on sync completion.
- Target p95 < 300 ms for dashboard, < 1 s for report tables.
- Queue everything slow. Never block a request on an external API.
- All money in **integer paise** (bigint), formatted at the edge. Never float.
- All timestamps stored UTC, rendered in tenant timezone.
- Feature tests for every endpoint (permission denied, empty data, partial connector, happy path).
- Larastan level 8 clean, Pint formatted.
- Full audit log on every mutation and every data export.
- Encrypt connector credentials at rest; never log tokens; mask PII (emails/phones) in UI by default with a `pii.view` permission to unmask.

---

## 12. DELIVERY ORDER

1. Foundation: Laravel + Inertia + auth + MFA + tenancy + RBAC + admin console + UI primitives
2. Connector framework + Shopify + rollup engine + Dashboard + Finance
3. Unicommerce + Marketplace + Operations + catalog
4. Meta + Google Ads + GA4 + Marketing + Instagram
5. Judge.me + Customers & Reviews + RFM/cohorts/churn
6. Reports library (all 25)
7. AI layer + insights + Ask AI
8. Alerts & notifications + digests
9. Demo seeder, onboarding, plan gating, billing
10. Phase-2 connectors (Shiprocket, Razorpay, direct Amazon/Flipkart, WhatsApp, Tally)

---

**Start now.** Scaffold the Laravel app, set up tenancy + RBAC + the connector framework +
the rollup engine + the UI primitives first, then build modules in the delivery order above.
Produce migrations, models, actions, queries, jobs, policies, React pages/components, and tests
as you go. Keep every widget behind its own permission from day one.
