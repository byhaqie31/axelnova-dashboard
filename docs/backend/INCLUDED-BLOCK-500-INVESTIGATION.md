# Included-block 500 — investigation record

**Symptom.** `POST/PUT /v1/connector/quotations…` with a `detailed` payload of four
`included` blocks returns HTTP 500 **from nginx** when the fourth block carries a
`note`. Verified on production with two payloads byte-identical except that one
field: 4 blocks / 4th without note → 200 OK; 4 blocks / 4th with note → 500.
Blocks 1–3 all carry notes without issue. Prod trace window: **2026-08-14
~10:02 UTC**.

**Status: root cause NOT yet confirmed.** The production log read is blocked on
SSH access (see "Blocked on" below). Everything that could be verified without
the logs has been; both original hypotheses now have evidence **against** them.

## Hypotheses evaluated

### H1 — length cap on the serialised column: DISPROVEN at the schema level

The `detailed` input is not stored in its own column. It lands in two places,
both **MySQL `JSON` columns**, not `TEXT`:

- `quotations.form_payload` — `$table->json('form_payload')` in
  `2026_05_07_000002_create_quote_requests_table.php` (carries the full request
  as `form_payload.request`);
- `quotations.document` — `$table->json('document')` in
  `2026_06_22_000003_extend_quotations_for_admin_builder.php` (carries the built
  `document.payload.included`).

MySQL 8 `JSON` columns are bounded by `max_allowed_packet` (tens of MB), not the
65,535-byte `TEXT` cap. The validation caps (`items.* ≤ 300` chars, `note ≤ 300`,
≤ 4 blocks in practice) keep the whole payload in the tens of KB. Production ran
the same migrations. A one-field, ~300-byte delta cannot cross any column limit
here.

### H2 — off-by-one in the included-group renderer: NO LOCAL REPRODUCTION

`DetailedDocumentBuilder::buildIncluded()` has no index- or length-sensitive
logic (`note` is copied verbatim when non-empty). Reproduction was attempted
locally through the **full Laravel pipeline** (routing, validation,
`DetailedDocumentBuilder`, JSON persistence, activity log) with 4 included
blocks at realistic sizes, exercising:

- `POST /v1/connector/quotations/draft`, 4th block **without** note → 201
- `POST /v1/connector/quotations/draft`, 4th block **with** note → 201
- `PUT /v1/connector/quotations/{ref}` (reseed), both variants → 200

All passed (scratch test, not kept). The application code path handles the
failing shape correctly on an identical schema.

## What that leaves

The failure is production-environment-specific, in or in front of PHP:

1. **nginx ↔ PHP-FPM layer** — a PHP-FPM worker fatal (OOM/segfault) surfaces as
   nginx's own 500/502 page rather than a Laravel JSON error, and would explain
   "500 from nginx" with possibly *nothing* in `laravel.log`.
2. **A WAF / security module rule** on the system nginx matching the extra
   content.
3. **Byte-content sensitivity** — the local repro used synthetic text; the real
   payload's exact bytes (the note's actual characters) may matter, e.g. an
   encoding edge in a prod-only extension or config.

## Blocked on — the log read

The `vps` SSH alias (`byhaqie31@187.77.151.66`) has no usable key on this
machine (no `IdentityFile`, empty agent), so the trace could not be read.
Once on the VPS, check **all three** logs around 2026-08-14 10:02 UTC:

```bash
ssh vps
grep -n '2026-08-14 10:0' ~/data/axelnova-dashboard/storage/logs/laravel.log
sudo grep -n '10:0[0-9].*500' /var/log/nginx/error.log /var/log/nginx/access.log
docker compose -f ~/axelnova-dashboard/docker-compose.prod.yml logs backend \
  --since 2026-08-14T09:55:00 --until 2026-08-14T10:10:00
```

If `laravel.log` is silent for the window, the PHP-FPM/nginx error log is the
authoritative trace (worker crash / WAF), per the analysis above.

## Proposed next step

Read the logs (above), then fix at the layer the trace names. No application
code change is proposed yet — the app layer has been cleared locally, and the
brief's rule stands: read the trace, do not guess.
