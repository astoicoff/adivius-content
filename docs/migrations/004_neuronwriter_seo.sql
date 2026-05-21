-- NeuronWriter SEO score integration
-- Stores chosen NW project per user and NW query ID/URL per generation

ALTER TABLE user_settings
  ADD COLUMN IF NOT EXISTS nw_project_id text;

ALTER TABLE content_generations
  ADD COLUMN IF NOT EXISTS nw_query_id  text,
  ADD COLUMN IF NOT EXISTS nw_query_url text;
