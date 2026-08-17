# Amazon Associates / Creators API integration guardrails

This document records the implementation rules MediaPitch Store follows for the Amazon Associates / Creators API integration. Re-check the current Amazon Associates Operating Agreement, Participation Requirements and Creators API License/Documentation before materially changing these rules.

## Data freshness

- Creators API access tokens are reused until near their one-hour expiry.
- Offers/pricing data has a one-hour cache/freshness window.
- BrowseNodeInfo has a one-hour cache window.
- Other Creators API resources such as Images, ItemInfo and DetailPageURL have a one-day cache window.
- `composer refresh-amazon` is designed for a scheduled server-side refresh; run it hourly where practical.
- Public API-derived prices are suppressed by `public_product_price()` when `last_synced_at` is older than one hour.

## Pricing and availability display

- Every rendered `.price` receives a link to the fixed Amazon price/availability disclosure in the public footer.
- The required disclosure is not editable through Website Settings, so an editor cannot accidentally remove it.
- Amazon controls the final price and availability shown at purchase time.
- Do not build price tracking, price history charts, price-drop alerts or price-alert notifications for Amazon products unless Amazon has explicitly agreed to that functionality.
- `previous_price` must not be populated from Amazon offer history as a tracking mechanism.

## Product content

- Store ASINs as stable identifiers.
- Treat Creators API images, item information, detail-page URLs and other Product Advertising Content as refreshable API data rather than permanent editorial facts.
- Field-level manual overrides may protect MediaPitch-authored values from Amazon refreshes; those values become editorial content rather than API-managed fields.
- New Amazon imports remain inactive until an editor reviews them.

## Customer content

- Do not scrape or reproduce Amazon customer reviews or Amazon star ratings.
- MediaPitch review scores are independent editorial scores and must be clearly labelled as such.

## Links

- Creators API detail-page URLs / Special Links must retain the configured Associate partner tag.
- Do not cloak the originating MediaPitch site or intentionally create artificial clicks/sessions.
- Bot/headless/internal analytics filtering must not interfere with genuine user navigation to Amazon.

## Credentials

- Credential ID and secret are encrypted at rest with `APP_KEY`.
- Never expose Creators API credentials in client-side code, logs or Git.
- Amazon Settings and authentication testing remain administrator-only.

## Operations

Recommended hourly cron:

```cron
15 * * * * cd /path/to/mediapitch-store && /usr/bin/composer refresh-amazon --quiet >> /var/log/mediapitch-amazon-refresh.log 2>&1
```

Monitor non-zero exit codes. If refreshes fail for a prolonged period, public offer prices naturally disappear after the one-hour application freshness window rather than remaining indefinitely stale.

## Current official references reviewed

- Amazon.in Creators API: Best Programming Practices
- Amazon.in Associates Program Linking Requirements
- Amazon.in Associates Program Participation Requirements
- Amazon.in Associates Program Operating Agreement
- Amazon Creators API OffersV2 / Images / SearchItems documentation

Last reviewed: 2026-08-18.
