-- Claude & Gemini model switching
-- Adds API keys to user_settings and records chosen model per generation

ALTER TABLE user_settings
  ADD COLUMN IF NOT EXISTS claude_key text,
  ADD COLUMN IF NOT EXISTS gemini_key text;

ALTER TABLE content_generations
  ADD COLUMN IF NOT EXISTS model text DEFAULT 'gpt-5';
