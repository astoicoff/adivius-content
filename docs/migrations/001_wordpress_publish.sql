-- WordPress one-click publish integration
-- Adds WP credentials to content_groups and post URL tracking to content_generations

ALTER TABLE content_groups
  ADD COLUMN IF NOT EXISTS wp_site_url     text,
  ADD COLUMN IF NOT EXISTS wp_username     text,
  ADD COLUMN IF NOT EXISTS wp_app_password text;

ALTER TABLE content_generations
  ADD COLUMN IF NOT EXISTS wp_post_url text;
