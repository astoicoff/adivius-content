<?php
// GENERATED FILE — DO NOT EDIT.
// Source: scripts/hub-contract.mjs (constellation root)
// Regenerate: node scripts/sync-contracts.mjs

const HUB_CONTRACT_VERSION        = '1';
const HUB_CONTRACT_VERSION_HEADER = 'X-Nucleus-Contract-Version';
const HUB_TOOL_HEADER             = 'X-Nucleus-Tool';

const HUB_CONTENT_SUMMARY_SPEC = [
    'drafts_count' => ['kind' => 'number', 'nullable' => true],
    'in_review' => ['kind' => 'number', 'nullable' => true],
    'ready_to_publish' => ['kind' => 'number', 'nullable' => true],
    'last_handoff_at' => ['kind' => 'string', 'nullable' => true],
    'pipeline' => ['kind' => 'object', 'nullable' => true],
    'recent_publishes' => ['kind' => 'array'],
    'upcoming' => ['kind' => 'array'],
    'velocity_30d' => ['kind' => 'number', 'nullable' => true],
];

const HUB_KNOWN_IDS_SPEC = [
    'client_ids' => ['kind' => 'array'],
    'site_ids' => ['kind' => 'array'],
];

const HUB_NUCLEUS_SITE_ITEM_SPEC = [
    'id' => ['kind' => 'uuid'],
    'name' => ['kind' => 'string'],
    'domain' => ['kind' => 'string', 'nullable' => true],
    'client_id' => ['kind' => 'uuid', 'nullable' => true],
];

// POST /api/inbound/content-ready body: Content sends this (website roadmap §6.3).
const HUB_CONTENT_READY_SPEC = [
    'title' => ['kind' => 'string'],
    'site_id' => ['kind' => 'uuid', 'nullable' => true],
    'client_id' => ['kind' => 'uuid', 'nullable' => true],
    'body_html' => ['kind' => 'string', 'nullable' => true],
    'body_markdown' => ['kind' => 'string', 'nullable' => true],
    'slug' => ['kind' => 'string', 'nullable' => true],
    'excerpt' => ['kind' => 'string', 'nullable' => true],
    'source_ref' => ['kind' => 'string', 'nullable' => true],
    'meta_title' => ['kind' => 'string', 'nullable' => true],
    'meta_description' => ['kind' => 'string', 'nullable' => true],
    'author_slug' => ['kind' => 'string', 'nullable' => true],
    'pillar' => ['kind' => 'string', 'nullable' => true],
    'tags' => ['kind' => 'array'],
    'faq' => ['kind' => 'array'],
    'featured_image' => ['kind' => 'object', 'nullable' => true],
    'images' => ['kind' => 'array'],
];

const HUB_BLOG_PILLARS = ['ai-automation', 'ai-seo', 'digital-transformation'];

/**
 * Validate a decoded payload against a spec. Returns a list of problems;
 * empty means conformant. Mirrors validateShape() in hub-contract.mjs:
 * every spec key must be PRESENT, null only where nullable, then type.
 * Extra keys are allowed — additive fields do not bump the version.
 */
function hub_validate_shape($payload, array $spec, string $label = 'payload'): array {
    if (!is_array($payload) || array_is_list($payload)) {
        return ["$label: expected a JSON object"];
    }
    $problems = [];
    foreach ($spec as $key => $rule) {
        if (!array_key_exists($key, $payload)) {
            $problems[] = "$label.$key: MISSING (contract requires the key even when null)";
            continue;
        }
        $value = $payload[$key];
        if ($value === null) {
            if (empty($rule['nullable'])) { $problems[] = "$label.$key: null, but the contract says non-null"; }
            continue;
        }
        $ok = match ($rule['kind']) {
            'string'  => is_string($value),
            'number'  => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array'   => is_array($value) && array_is_list($value),
            'object'  => is_array($value) && !array_is_list($value),
            'uuid'    => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            default   => false,
        };
        if (!$ok) { $problems[] = "$label.$key: expected {$rule['kind']}"; }
    }
    return $problems;
}

/**
 * Compare a response's contract-version header against what we build against.
 * Returns null when fine, or a message describing the drift.
 */
function hub_check_contract_version($headerValue, string $label = 'response'): ?string {
    if ($headerValue === null || $headerValue === '') {
        return "$label: no " . HUB_CONTRACT_VERSION_HEADER . " header (expected '" . HUB_CONTRACT_VERSION . "')";
    }
    if ((string) $headerValue !== HUB_CONTRACT_VERSION) {
        return "$label: " . HUB_CONTRACT_VERSION_HEADER . " is '$headerValue', this build expects '" . HUB_CONTRACT_VERSION . "'";
    }
    return null;
}
