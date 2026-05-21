-- Webhook on completion
-- Adds webhook URL to content_groups and delivery status tracking to content_generations

ALTER TABLE content_groups
  ADD COLUMN IF NOT EXISTS webhook_url text;

ALTER TABLE content_generations
  ADD COLUMN IF NOT EXISTS webhook_delivered_at timestamptz,
  ADD COLUMN IF NOT EXISTS webhook_error        text;
