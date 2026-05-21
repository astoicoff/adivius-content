-- Multi-user / team sharing
-- Adds shared group membership and invite link tables, plus display name for member lists

-- Active members (owners stay in content_groups.user_id; only shared users go here)
CREATE TABLE IF NOT EXISTS content_group_members (
  id           uuid DEFAULT gen_random_uuid() PRIMARY KEY,
  group_id     uuid NOT NULL REFERENCES content_groups(id) ON DELETE CASCADE,
  user_id      uuid NOT NULL REFERENCES auth.users(id)     ON DELETE CASCADE,
  role         text NOT NULL CHECK (role IN ('moderator', 'viewer')),
  invited_by   uuid REFERENCES auth.users(id),
  joined_at    timestamptz DEFAULT now(),
  UNIQUE(group_id, user_id)
);

-- Pending invites (token-based, 7-day expiry)
CREATE TABLE IF NOT EXISTS content_group_invites (
  id           uuid DEFAULT gen_random_uuid() PRIMARY KEY,
  group_id     uuid NOT NULL REFERENCES content_groups(id) ON DELETE CASCADE,
  email        text NOT NULL,
  role         text NOT NULL CHECK (role IN ('moderator', 'viewer')),
  token        text NOT NULL UNIQUE DEFAULT encode(gen_random_bytes(32), 'hex'),
  invited_by   uuid NOT NULL REFERENCES auth.users(id),
  created_at   timestamptz DEFAULT now(),
  expires_at   timestamptz DEFAULT now() + interval '7 days',
  accepted_at  timestamptz,
  UNIQUE(group_id, email)
);

-- Display name shown in member lists (set by each user in their profile/settings)
ALTER TABLE user_settings
  ADD COLUMN IF NOT EXISTS display_name text;
