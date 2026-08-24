# Autonomous AI Content Worker

MediaPitch Store can use an Ollama server as an optional editorial worker. The worker researches a topic, creates a CMS draft, and emails configured administrators. It never publishes content.

## Enable it

1. Deploy the database migrations.
2. Open **Admin → AI Content** (`/admin/settings/ai`).
3. Enter the Ollama URL and installed model name.
4. Click **Test Ollama connection**.
5. Configure notification email addresses and generation limits.
6. Enable **Autonomous AI content generation**.

## Run the worker

Run one worker iteration with:

```bash
composer ai-worker
```

For autonomous operation, schedule that command in cron every few minutes. Each invocation claims at most one queued job. If automatic topic discovery is enabled and the daily limit has not been reached, an idle worker can commission a new topic itself.

Example cron (adjust paths for the server):

```cron
*/5 * * * * cd /path/to/mediapitch-store && /usr/bin/php database/ai-worker.php >> storage-ai-worker.log 2>&1
```

## Workflow

1. Check the master enable switch.
2. Discover a non-duplicate topic from CMS categories, products, and existing content when automatic discovery is enabled.
3. Ask Ollama for multiple research queries.
4. Search the public web and save readable research sources to `ai_research_sources`.
5. Give Ollama the research and CMS catalogue snapshot.
6. Generate structured editorial fields.
7. Save through `ContentRepository` with `status=draft` and `robots_index=0`.
8. Record the job as ready for review.
9. Send the draft and review link to configured notification addresses.

## Publishing safeguard

The worker does not expose or call a publish action. Its content write is hard-coded to `status=draft`. A CMS user must open the draft and publish it manually.

## Research safety

Research accepts only public HTTP(S) URLs and rejects private/reserved IP ranges. Source fetches do not follow redirects, preventing a public URL from redirecting the worker into internal network resources.

## Email

The first implementation uses PHP `mail()`. The host therefore needs a working local mail transport. Notification failures do not discard a completed draft; they are written to the audit log. A future SMTP/provider adapter can replace the mail transport without changing the AI pipeline.
