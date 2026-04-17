---
name: roadmap
description: Feature ideas and next-phase improvements for the Content Creator web app
---

# Content Creator — Feature Roadmap

## Phase 1 · Quick Wins (Low effort, High impact)

- [x] **Rendered preview on view-content**
  Toggle between raw HTML source and a live rendered preview so users can see how the content actually looks before publishing.

- [x] **Regenerate button**
  On any completed generation, allow the user to re-run the full 3-phase pipeline for the same keyword + group without starting from scratch.

- [x] **Word count + reading time**
  Display a word count and estimated reading time badge on each completed generation card (in History, Content Groups, and view-content).

- [x] **Search & filter in History**
  Filter by content group, status (pending / completed / failed), or keyword text. Useful once content volume grows.

---

## Phase 2 · Core Features (Medium effort)

- [x] **Inline content editing**
  Edit generated HTML directly on the view-content page and save back to Supabase (Edit mode + Save button).

- [x] **Bulk keyword generation**
  Up to 10 keywords (one per line) in Bulk mode on New Content. Keywords appear as removable chips; generates sequentially with auto-brief, shows per-keyword progress.

- [x] **Dashboard (landing page)**
  Dedicated `/dashboard` users land on after login. 4 stat cards (total, this month, groups, most active group) + last 5 recent generations. Replaces New Content as post-login redirect.

- [x] **Content duplication**
  Clone an existing generation's keyword + group settings and create a new job, without retyping everything.

---

## Phase 3 · Power Features (Higher effort)

- [ ] **WordPress / CMS publish**
  One-click push to a WordPress site via the WP REST API. Fields: site URL, application password, post status (draft / publish).

- [x] **Group-level analytics**
  Inside a content group detail view, show a simple chart (bar or line) of content generated per week/month.

- [x] **Content scheduling**
  Schedule a generation to run at a specific date/time rather than immediately. Requires a cron job or background queue.

- [ ] **Webhook on completion**
  After a generation finishes, POST the result to a user-configured URL (Zapier, Make, custom endpoint), enabling downstream automation.

---

## Backlog / Under Consideration

- SEO score / readability analysis on generated content (Flesch, keyword density)
- Tag system on individual generations (beyond groups)
- Multi-user / team support with shared content groups
- Claude and Gemini as active generation models (keys stored; wiring pending)
- Export as Word (.docx) or PDF
