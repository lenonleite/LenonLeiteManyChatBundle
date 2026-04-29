# ManyChat Connector for Mautic 5.2

Simple inbound connector that sends lead data from `ManyChat` into `Mautic` contacts.

## Quick Start

1. Install the plugin in `plugins/LenonLeiteManyChatBundle`.
2. Refresh plugins in Mautic.
3. Open `ManyChat Connector` settings in Mautic.
4. Enable the connector and set a `Webhook secret`.
5. In ManyChat, add an `External Request` step pointing to:
   `POST /manychat/webhook/contact-sync`
6. Send the same secret in the `X-ManyChat-Secret` header.
7. Send at least `email` or `phone` in the payload.

That is enough to start creating or updating Mautic contacts from ManyChat.

## Status

Current status:

- working first version for `ManyChat -> Mautic`
- focused on contact upsert, source tracking, and tagging
- intentionally not a two-way sync plugin yet

## Why This Exists

ManyChat is strong at lead capture inside chat flows.

Mautic is strong at:

- segmentation
- email nurture
- campaigns
- contact ownership inside your stack

This plugin connects those two jobs in the simplest practical way.

This plugin is intentionally small. It focuses on the most useful first workflow:

- capture a lead in ManyChat
- send that lead to Mautic
- create or update the contact
- store source data
- apply tags for segmentation and automation

It does not try to do full two-way sync yet.

## Best Use Cases

- capture leads from Instagram DM or chat flows into Mautic
- tag and segment ManyChat-origin contacts in Mautic
- push chat-captured leads into email nurture
- keep ManyChat source data on the Mautic contact

## What It Does

Current scope:

- one-way sync from `ManyChat -> Mautic`
- upsert Mautic contacts by `email` first or `phone`
- map core fields:
  - `firstname`
  - `lastname`
  - `email`
  - `phone`
- store ManyChat source fields:
  - `manychat_subscriber_id`
  - `manychat_channel`
  - `manychat_username`
  - `manychat_last_sync_at`
- import ManyChat tags into Mautic tags using a prefix such as `manychat:`
- create missing `manychat_*` contact fields automatically
- reject unauthorized inbound requests using a shared secret header

## What It Does Not Do Yet

Not implemented yet:

- two-way sync
- pushing Mautic contacts back to ManyChat
- automatic segment assignment after sync
- advanced field mapping UI
- timeline note creation on the contact
- native Mautic source field mapping like `leadsource = ManyChat`
- ManyChat API polling/import jobs

## How It Works

This plugin does not ask the user for data directly.

The flow is:

1. A contact interacts with your `ManyChat` flow.
2. ManyChat collects data such as name, email, phone, tags, or custom fields.
3. In the ManyChat flow, you add an `External Request` step.
4. ManyChat sends a `POST` request to this plugin endpoint in Mautic.
5. The plugin validates the request secret.
6. The plugin finds an existing Mautic contact or creates a new one.
7. The plugin stores ManyChat fields and tags on the contact.

So the sync can be automatic, but only after you configure ManyChat to send the request.

## Endpoint

Inbound webhook endpoint:

`POST /manychat/webhook/contact-sync`

Required header:

`X-ManyChat-Secret: <your-configured-secret>`

Accepted body formats:

- `application/json`
- form payload

## Installation

### 1. Copy the plugin into Mautic

Place the bundle inside your Mautic `plugins/` directory as:

`plugins/LenonLeiteManyChatBundle`

### 2. Refresh Mautic plugins

Use your usual Mautic plugin refresh/install process.

Typical Mautic installations require one or more of:

- clearing cache
- refreshing plugins
- reloading the Mautic admin

### 3. Open the plugin settings

In Mautic, go to the integration/config area and find:

`ManyChat Connector`

## Publishing Notes

If you publish this repository publicly, the clearest framing is:

- this is an open-source `ManyChat -> Mautic` connector
- it solves contact sync, tagging, and source tracking
- it is intentionally scoped to a stable first workflow

That positioning is stronger than claiming full ManyChat/Mautic sync before the harder cases are implemented.

## Configuration

Current settings:

- `Enable ManyChat connector`
- `ManyChat API key`
- `Webhook secret`
- `Primary contact match field`
- `Mautic tag prefix for ManyChat tags`
- `Sync direction`

Recommended values for the current implementation:

- `Enable ManyChat connector`: `Yes`
- `Webhook secret`: set a strong secret and reuse it in ManyChat
- `Primary contact match field`: `Email`
- `Mautic tag prefix`: `manychat:`
- `Sync direction`: `ManyChat -> Mautic`

Important note:

- the `ManyChat API key` setting exists in the UI for future expansion
- the current implemented flow does **not** use the API key yet
- current sync works through inbound requests from ManyChat, not outbound API calls from Mautic

## ManyChat Setup

The plugin is designed for a `ManyChat External Request` step.

Inside your ManyChat flow:

1. collect the data you want from the user
2. add an `External Request` action
3. point it to your Mautic endpoint
4. send the shared secret header
5. send the contact data as JSON

### Recommended request

URL:

```text
https://your-mautic-domain.com/manychat/webhook/contact-sync
```

Header:

```text
X-ManyChat-Secret: your-shared-secret
```

Body:

```json
{
  "subscriber_id": "{{user_id}}",
  "first_name": "{{first_name}}",
  "last_name": "{{last_name}}",
  "email": "{{email}}",
  "phone": "{{phone}}",
  "channel": "manychat",
  "username": "{{name}}",
  "tags": ["lead", "instagram"],
  "custom_fields": {
    "campaign_name": "spring launch"
  }
}
```

### Minimal payload

At least one identifier is required:

- `email`, or
- `phone`

Minimal valid example:

```json
{
  "email": "john@example.com",
  "first_name": "John"
}
```

If both `email` and `phone` are missing, the plugin returns `400 Bad Request`.

## Data Mapping

### Standard fields

The plugin currently maps:

- `first_name` or `firstname` -> `firstname`
- `last_name` or `lastname` -> `lastname`
- `email` -> `email`
- `phone` or `whatsapp_phone` -> `phone`

### ManyChat source fields

The plugin also stores:

- `manychat_subscriber_id`
- `manychat_channel`
- `manychat_username`
- `manychat_last_sync_at`

These are useful for:

- segmentation
- filters
- troubleshooting
- proving the contact came from ManyChat

### Custom fields

If you send:

```json
{
  "custom_fields": {
    "campaign_name": "spring launch",
    "entry_point": "instagram dm"
  }
}
```

The plugin normalizes those into:

- `manychat_campaign_name`
- `manychat_entry_point`

If those contact fields do not exist in Mautic yet, the plugin creates them automatically as text fields.

## Contact Matching

The plugin supports two primary lookup strategies:

- `email`
- `phone`

Behavior:

- if you choose `email`, it tries email first and phone second
- if you choose `phone`, it tries phone first and email second

This helps reduce duplicate contacts when one identifier is missing in some requests.

## Tags

If ManyChat sends:

```json
{
  "tags": ["lead", "instagram"]
}
```

And your prefix is `manychat:`, the contact will receive:

- `manychat:lead`
- `manychat:instagram`

This makes it easy to build segments like:

- all contacts tagged `manychat:lead`
- all contacts tagged `manychat:instagram`

## Segmenting ManyChat Contacts in Mautic

Yes, you can already build segments for ManyChat-origin contacts.

Recommended segment rules:

- `manychat_subscriber_id` is not empty
- `manychat_channel` equals `manychat`
- tag contains `manychat:lead`
- tag contains `manychat:instagram`

The strongest current rule is usually:

`manychat_subscriber_id is not empty`

## Example Responses

### Success

```json
{
  "success": true,
  "contact_id": 123,
  "created": true,
  "updated_fields": [
    "email",
    "firstname",
    "manychat_subscriber_id",
    "manychat_channel",
    "manychat_last_sync_at"
  ],
  "ignored_fields": [],
  "applied_tags": [
    "manychat:lead",
    "manychat:instagram"
  ],
  "lookup_strategy": "email"
}
```

### Invalid secret

```json
{
  "success": false,
  "message": "Invalid webhook secret."
}
```

### Missing identifier

```json
{
  "success": false,
  "message": "At least one identifier is required: email or phone."
}
```

## Security

This plugin currently protects the endpoint using a shared secret header:

`X-ManyChat-Secret`

Recommendations:

- use a long random secret
- do not expose it publicly
- use HTTPS only
- rotate the secret if it leaks

This plugin does not currently implement an official ManyChat signed webhook verification flow. It is designed around `External Request` with a manual shared secret.

## Logging

The plugin logs:

- successful syncs
- invalid secret attempts
- unexpected sync failures

Log entries are prefixed with:

`[ManyChat]`

## Testing

### Quick test with curl

```bash
curl -X POST "https://your-mautic-domain.com/manychat/webhook/contact-sync" \
  -H "Content-Type: application/json" \
  -H "X-ManyChat-Secret: your-shared-secret" \
  -d '{
    "subscriber_id": "mc_123",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "channel": "manychat",
    "tags": ["lead", "demo"]
  }'
```

Expected result:

- contact created or updated in Mautic
- ManyChat source fields saved
- prefixed tags applied

## Suggested Screenshots For The Repo

If you want to make the repository easier to understand, add screenshots of:

- the Mautic plugin settings page
- a ManyChat flow with the `External Request` step
- a Mautic contact showing `manychat_*` fields
- a Mautic segment filtered by ManyChat-origin data

## Troubleshooting

### The endpoint returns `401 Invalid webhook secret`

Check:

- the header name is exactly `X-ManyChat-Secret`
- the value matches the plugin setting exactly
- there are no extra spaces

### The endpoint returns `400 Request payload is required`

Check:

- ManyChat is actually sending a body
- the request is `POST`
- JSON formatting is valid

### The endpoint returns `400 At least one identifier is required`

Check:

- your payload includes `email` or `phone`
- the variable placeholders in ManyChat are not empty

### Contacts are not being found correctly

Check:

- whether your primary lookup field is `email` or `phone`
- whether phone numbers are consistently formatted
- whether the same person sometimes enters different emails

### Tags are not what you expected

Check:

- the configured tag prefix
- the tag values sent by ManyChat

## Limitations

Current limitations:

- no deduplication beyond simple email/phone lookup order
- no consent sync logic
- no campaign enrollment action after sync
- no direct ManyChat API pull
- no UI for custom field mapping
- no functional test suite yet for live Mautic execution

## Roadmap

Best next steps:

1. add retry-safe deduplication rules for phone and email variants
2. add optional segment assignment after successful sync
3. add a simple selected-field mapping UI
4. add functional tests against a real Mautic test kernel
5. optionally add outbound ManyChat API support later

## Why This Approach

The first useful integration is not full automation in both directions.

The first useful integration is:

- ManyChat captures the lead
- Mautic stores and segments the contact
- Mautic runs email nurture and automation

That gives real business value with much less complexity.

## Contributing

If you use this plugin and hit an edge case, open an issue with:

- your payload example
- expected behavior
- actual behavior
- Mautic version
- PHP version

Remove personal data before sharing payloads.

## License

This project is licensed under `GPL-3.0-or-later`.
See [LICENSE](LICENSE).
