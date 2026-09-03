# Sender.net Integration Plan — MediaPitch Store

## Goal

Integrate Sender.net as MediaPitch Store's email marketing and transactional delivery provider while keeping the MediaPitch Store CMS as the operational control panel.

The admin/editorial team should be able to manage subscribers, interest groups, campaigns, product/article newsletters, automations, transactional sends and analytics from MediaPitch Store without routinely opening Sender.net.

Sender must remain replaceable; MediaPitch Store owns its subscriber, consent, campaign and analytics data.

---

# Why Sender fits MediaPitch Store

MediaPitch Store already combines editorial content, product discovery and affiliate-oriented publishing. Email should become another first-class publishing/distribution channel rather than a disconnected external dashboard.

The strongest use cases are:

- new article/product-review notifications
- category-specific newsletters
- weekly product/content digests
- buying-guide roundups
- seasonal campaigns
- price/deal notifications if we deliberately add that feature later
- editorial re-engagement campaigns
- transactional/admin messages

Email should connect directly to the existing CMS taxonomy and publishing workflow.

---

# Core principles

1. **MediaPitch Store owns subscriber and campaign state.**
2. **Sender is only the delivery/marketing provider.**
3. **All Sender API calls go through a provider abstraction.**
4. **No direct API calls from views.**
5. **Use queues/retries for external API work.**
6. **Use webhooks for engagement/bounce/unsubscribe events where the Sender plan permits.**
7. **Respect consent and suppression locally even if Sender is temporarily unavailable.**
8. **Keep marketing and transactional messages separate.**
9. **Use Asia/Kolkata in the editorial/admin UI.**
10. **Do not make Ollama/AI a dependency for sending mail.**

---

# Desired CMS navigation

Add an **Email** section:

- Dashboard
- Subscribers
- Audiences
- Campaigns
- Templates
- Digests
- Automations
- Transactional Log
- Analytics
- Settings

Potential future extension:

- Recommendations / AI Insights

---

# MediaPitch-specific audiences

The exact groups should come from the live category model rather than being permanently hard-coded.

Initial examples based on current editorial direction:

- Kitchen Appliances
- Cleaning Appliances
- Mobile Accessories
- Power & Charging
- Work & Study
- Printers & Printing Accessories
- Smart Home
- General / Weekly Digest

A subscriber may belong to multiple audiences.

Use existing categories/tags to suggest audience membership and campaign targeting where appropriate, but keep email consent distinct from mere site browsing.

---

# Publishing workflow integration

When an editor publishes a blog/review/guide, offer optional distribution actions such as:

```text
Publish

Email distribution
[ ] Add to next digest
[ ] Prepare campaign
[ ] Send notification now
[ ] Schedule notification

Audience
[ Kitchen Appliances v ]
```

Do not make email sending the default side effect of publishing. The editor should make an explicit send/schedule choice.

## Campaign creation from content

A campaign created from an article/product guide should prefill:

- internal campaign name
- suggested subject
- preheader
- hero image if suitable
- article title
- excerpt/summary
- CTA URL
- suggested audience from category

The editor can modify all of these before sending.

---

# Digest workflow

A strong MediaPitch feature should be a native digest builder.

Example:

1. choose date range or `since last digest`
2. CMS lists recently published eligible content
3. filter by category
4. reorder items
5. select a MediaPitch email template
6. preview desktop/mobile
7. send test
8. select audiences
9. send or schedule

Possible digest types:

- Weekly MediaPitch Picks
- Kitchen & Home
- Mobile & Charging
- Work & Study
- Cleaning & Smart Home
- Seasonal Buying Guide

---

# Architecture

Recommended boundary:

```text
MediaPitch Store CMS
       |
       v
Email application services
       |
 +-----+----------------+------------------+
 |                      |                  |
SubscriberService CampaignService TransactionalMailService
 |                      |                  |
 +----------------------+------------------+
       |
 EmailProviderInterface
       |
 SenderNetProvider
       |
       v
 Sender.net API
```

Supporting services:

- `EmailAudienceService`
- `EmailTemplateService`
- `EmailDigestService`
- `EmailWebhookService`
- `EmailAnalyticsService`
- `EmailSyncService`

Follow the repository's existing plain-PHP/service conventions rather than introducing a framework solely for this module.

---

# Provider abstraction

Conceptual contract:

```php
interface EmailProviderInterface
{
    public function testConnection(): array;

    public function upsertSubscriber(array $subscriber): array;
    public function deleteSubscriber(string $providerSubscriberId): void;
    public function addSubscriberToGroup(string $subscriberId, string $groupId): void;
    public function removeSubscriberFromGroup(string $subscriberId, string $groupId): void;

    public function createCampaign(array $campaign): array;
    public function sendCampaign(string $providerCampaignId): void;
    public function scheduleCampaign(string $providerCampaignId, DateTimeInterface $when): void;
    public function cancelCampaign(string $providerCampaignId): void;

    public function sendTransactional(array $message): array;
    public function fetchCampaignStats(string $providerCampaignId): array;
}
```

Names are illustrative; use existing project naming/style when implementing.

The rest of MediaPitch should call application services, not Sender methods directly.

---

# Proposed database model

Inspect the current schema before writing migrations/deploy SQL. Integrate with the repo's existing deployment scripts rather than creating a second migration mechanism.

## `email_subscribers`

Suggested fields:

- `id`
- `email`
- `name`
- `status`
- `marketing_consent`
- `consent_source`
- `consent_at`
- `source`
- `provider`
- `provider_subscriber_id`
- `provider_synced_at`
- timestamps

## `email_audiences`

- `id`
- `name`
- `slug`
- `description`
- optional related category id/slug
- `provider`
- `provider_group_id`
- timestamps

## `email_subscriber_audiences`

- `subscriber_id`
- `audience_id`
- timestamps

## `email_templates`

- `id`
- `name`
- `slug`
- `type`
- `subject_template`
- `preheader_template`
- `html_template`
- `text_template`
- `is_active`
- timestamps/version metadata

## `email_campaigns`

- `id`
- `name`
- `subject`
- `preheader`
- `html`
- `text`
- `status`
- `provider`
- `provider_campaign_id`
- `scheduled_at`
- `sent_at`
- `created_by`
- timestamps

## `email_campaign_audiences`

Maps campaign targeting to local audiences.

## `email_campaign_content`

Optional mapping between campaigns/digests and MediaPitch content items so reporting can answer questions such as:

- which article appeared in which email
- which campaigns promoted a category/product guide
- which content gets the strongest email engagement

Possible fields:

- `campaign_id`
- content/entity type
- content/entity id
- position
- CTA URL

## `email_messages`

Transactional message log with recipient, template/type, provider id, state and failure metadata.

## `email_events`

Normalized events:

- delivered
- opened
- clicked
- bounced
- unsubscribed
- complained
- failed

Store provider event ID when available for idempotency.

---

# Subscriber acquisition

Potential subscription entry points:

- site-wide newsletter form
- article pages
- category pages
- future user/account area
- targeted subscription modules such as `Get weekly kitchen-appliance picks`

Keep the initial form simple. Do not create excessive popups before there is evidence they improve subscriptions without harming UX.

## Subscribe flow

1. validate input
2. capture explicit consent
3. save locally
4. associate selected audiences
5. queue Sender sync
6. upsert subscriber remotely
7. sync group memberships
8. store provider IDs/status

Temporary Sender failures should not discard the local subscription.

## Unsubscribe / suppression

Local suppression is authoritative for our application.

- local unsubscribe -> stop marketing sends immediately -> sync Sender
- Sender webhook unsubscribe -> update local record
- hard bounce / complaint -> suppress locally

Do not silently resubscribe suppressed addresses.

---

# Campaign workflow

## Create locally first

A campaign should remain editable without needing a Sender campaign until it is ready for delivery.

States may include:

- draft
- ready
- scheduled
- sending
- sent
- cancelled
- failed

## Test and preview

Before send/schedule provide:

- browser preview
- mobile-width preview
- text fallback preview
- send test email
- audience count
- validation warnings
- broken/missing CTA warning where practical
- missing unsubscribe/footer warning

## Send

1. freeze/render final content version
2. create corresponding Sender campaign
3. store provider campaign id
4. send or schedule
5. update local status
6. continue receiving/reconciling analytics

## Schedule

Admin scheduling UI uses Asia/Kolkata.

Convert explicitly to Sender's expected timezone/format so server timezone cannot shift send times.

---

# Templates

Own the templates in MediaPitch Store rather than depending on Sender's drag-and-drop editor.

Initial templates:

1. MediaPitch Article Notification
2. MediaPitch Weekly Digest
3. Category Digest
4. Buying Guide / Roundup
5. Product Review Spotlight
6. Minimal Transactional Message

Template design goals:

- responsive
- clean editorial look
- clear CTA hierarchy
- strong image handling
- useful plain-text alternative
- consistent MediaPitch branding
- unsubscribe/footer for marketing email

The CMS can expose editable content areas while preserving reliable email structure.

---

# Sender API areas to use

Use Sender's current documented v2 API for:

- subscribers
- groups
- fields where useful
- segments where useful
- campaigns
- scheduling/cancellation
- campaign statistics
- transactional sends
- workflows where they fit
- custom events where they add value
- account webhooks

Do not shape internal architecture around undocumented features.

If Sender's API cannot create a complex visual workflow, implement the automation logic in MediaPitch Store and use Sender purely as the mail provider.

---

# Webhooks

Proposed route:

```text
POST /api/webhooks/sender
```

adapted to the repo's existing router.

Webhook handling should:

1. verify request authenticity using Sender's supported method
2. validate payload
3. deduplicate
4. acknowledge quickly
5. queue/process event
6. normalize provider event
7. update local subscriber/message/campaign state

Likely event classes:

- delivered
- open
- click
- bounce
- unsubscribe
- complaint
- failure

Only implement events actually supported by Sender.

---

# Queues, cron and retry strategy

The repository's Composer scripts show a deployment-oriented plain PHP application, so do not assume a long-running Laravel/Supervisor worker.

Use the hosting/runtime model already available to the project.

Potential approach:

- database-backed email jobs
- cron-triggered worker command
- bounded batches
- lock/lease per job
- retry count + next attempt timestamp
- dead/failed status after threshold

Jobs:

- subscriber sync
- audience sync
- transactional send
- campaign provider creation/send
- analytics reconciliation
- webhook follow-up processing

Retry rules:

- exponential backoff for network/`5xx`
- honor `429` and `Retry-After`
- do not repeatedly retry permanent validation/auth errors
- show unresolved failures in admin

Integrate required setup/deploy SQL into the existing database deployment flow rather than relying on manual production SQL.

---

# Settings page

Suggested UI:

```text
Email Provider

Provider             Sender.net
Connection           Connected / Error
API Token            ***************
Default From Name    MediaPitch
Default From Email   ...
Reply-to              ...
Timezone              Asia/Kolkata
Webhook               Active / Inactive

[Test Connection]
[Send Test Email]
```

Security:

- never display full token after save
- never commit token
- never log Authorization headers
- prefer environment/server secret storage where practical

---

# Analytics

MediaPitch should build a local analytics layer rather than merely embedding Sender stats.

## Campaign metrics

- sent
- delivered
- delivery rate
- bounced
- opens / unique opens where available
- clicks / unique clicks where available
- CTR
- unsubscribes
- complaints

## Editorial/content metrics

Because campaigns can map to content, we can later report:

- email clicks by article
- category CTR
- articles that perform better in email than onsite
- most effective CTA positions
- newsletter-assisted affiliate clicks where our own analytics can attribute them safely

Do not claim a sale/revenue attribution unless the underlying affiliate/platform data actually supports it.

## Audience metrics

- subscriber growth
- active subscribers
- category/audience sizes
- unsubscribe trend
- bounce/suppression trend
- engagement by audience

---

# Integration with existing MediaPitch analytics

Where feasible, use tagged campaign URLs / UTM parameters so existing site analytics can distinguish email traffic.

Suggested consistent parameters:

- `utm_source=mediapitch_email`
- `utm_medium=email`
- `utm_campaign=<stable campaign slug/id>`
- optional `utm_content=<content position/id>`

Generate these automatically rather than expecting editors to type them manually.

Do not alter third-party affiliate parameters in a way that breaks attribution.

---

# AI/Ollama opportunities — later

The existing MediaPitch AI functionality can eventually assist with:

- subject lines
- preheaders
- short newsletter summaries
- digest assembly
- category/audience suggestion
- CTA variants
- campaign performance summaries
- identifying topics whose email engagement is rising/falling

Example editor flow:

```text
Create campaign from article
        |
        v
AI proposes subject + preheader + 80-word intro
        |
        v
Human edits/approves
        |
        v
Test -> schedule/send
```

Important:

- AI does not receive the Sender API key
- AI calls our application service, never Sender directly
- human approval remains required before marketing sends unless a future explicitly approved automation is introduced

---

# Potential automations

Keep automation logic local unless Sender's native workflow clearly gives us an advantage.

Useful future examples:

## Welcome sequence

- subscriber joins
- send welcome message
- optionally follow with a curated `best of MediaPitch` email after a delay

## Category preference

- subscriber explicitly selects Kitchen Appliances
- join local audience
- sync Sender group

## Digest preparation

- cron identifies unsent recent posts
- creates a **draft** digest
- editor reviews
- editor sends/schedules

Do not auto-send editorial digests in the first implementation.

## Re-engagement

Potential later feature only after we have enough reliable engagement history.

Avoid aggressive or spam-like automation.

---

# Security / compliance checklist

- API token never in Git
- admin secrets masked
- campaign send/schedule permission restricted
- explicit marketing consent stored
- local unsubscribe/suppression enforced
- CSRF protection on admin actions
- webhook verification
- webhook idempotency
- public subscribe/unsubscribe rate limiting
- HTML/template sanitization appropriate to admin trust model
- no header injection
- test/staging cannot accidentally send to full production list
- send/schedule/cancel actions audited
- no API credentials in logs
- minimal retention of raw provider payloads

---

# Rollout plan

## Phase 0 — Provider/account preparation

One-time Sender work:

- account/plan setup
- sending-domain authentication
- SPF/DKIM/DMARC configuration as required
- sender identity
- API credentials
- webhook configuration when supported by plan

Do not store credentials in this document.

## Phase 1 — Foundation

- email provider configuration
- `EmailProviderInterface`
- Sender client/provider
- connection testing
- normalized exceptions
- retry/rate-limit strategy

**Done when:** CMS can securely test Sender connectivity.

## Phase 2 — Database and deployment

- email tables
- indexes/constraints
- integrate schema changes into existing `database/composer-deploy.php` / deployment conventions as appropriate
- safe idempotent deployment behavior

**Done when:** production deployment can create/upgrade the email schema without manual intervention.

## Phase 3 — Subscribers and audiences

- subscription storage
- subscriber admin
- audience/category mapping
- Sender synchronization
- unsubscribe
- suppression
- sync status/errors

**Done when:** subscriber management can be done from MediaPitch Store.

## Phase 4 — Templates and transactional mail

- MediaPitch email templates
- test message
- generic transactional service
- outbound message log

**Done when:** the provider abstraction sends a reliable test/transactional message.

## Phase 5 — Campaigns

- campaign CRUD
- create from article/guide
- audience selection
- preview
- test send
- send now
- schedule
- cancel

**Done when:** an editor can execute a normal newsletter entirely inside MediaPitch Store.

## Phase 6 — Digest builder

- select recent content
- reorder
- category filtering
- generate campaign draft
- schedule/send

**Done when:** weekly/category digests require no manual HTML assembly.

## Phase 7 — Webhooks and analytics

- webhook receiver
- event normalization/deduplication
- local suppression updates
- campaign reporting
- audience reporting
- reconciliation job

**Done when:** MediaPitch reporting is trustworthy without opening Sender analytics.

## Phase 8 — Content attribution

- automatic UTM generation
- email traffic in existing analytics
- content/campaign mapping
- click performance by category/content

## Phase 9 — Optional AI assistance

- subject/preheader generation
- digest suggestions
- performance analysis
- audience suggestions

Keep AI optional and reviewable.

---

# Acceptance criteria for first production-ready release

- [ ] Sender credentials are secure and uncommitted
- [ ] admin can test provider connection
- [ ] subscribers live locally
- [ ] subscribers sync to Sender
- [ ] audiences/groups live locally
- [ ] category-based audience suggestions work where appropriate
- [ ] unsubscribe propagates and is locally enforced
- [ ] bounce/complaint suppression is locally enforced
- [ ] test/transactional mail works
- [ ] templates are owned by MediaPitch Store
- [ ] campaign can be created from CMS content
- [ ] campaign can be previewed
- [ ] test campaign email can be sent
- [ ] campaign can send immediately
- [ ] campaign can be scheduled from an IST-facing UI
- [ ] scheduled campaign can be cancelled
- [ ] webhook handling is idempotent
- [ ] campaign analytics are visible locally
- [ ] provider failures are visible/retryable
- [ ] routine email marketing work does not require Sender.net dashboard access

---

# What may still require Sender.net

It is fine to retain Sender's dashboard for infrastructure/account administration such as:

- billing
- plan upgrades
- API credential creation/rotation
- domain/DNS authentication
- account security
- Sender-native advanced workflow configuration when equivalent API functionality does not exist

These are infrequent provider-admin tasks, not normal editorial tasks.

---

# Implementation rule

Do **not** begin by wiring Sender calls directly into the blog publishing controller/page.

First establish:

1. local schema
2. provider abstraction
3. subscriber/consent model
4. test/transactional sending
5. queues/retries

Then integrate campaigns into publishing.

Before each implementation phase, inspect the current MediaPitch Store router, database helpers, admin permission model, analytics code and deploy scripts and fit the module into those conventions rather than forcing a parallel architecture.
