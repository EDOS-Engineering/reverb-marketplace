# Changelog

## 0.1.1 - 2026-09-21

No change to the client's behaviour. Documentation corrected against a live
production account, using `scripts/live-probe.php`:

- Publishing is asynchronous: a create with `publish` true returns a draft that
  is live about a second later.
- Reverb explains a listing it will not publish only in `warnings`. A Brand New
  item needs a valid `upc` or `upc_does_not_apply`.
- Deleting a published listing is a 400, not the 406 Reverb's guide names.
  `end()` answers with an empty body.
- An ended, unsold listing revives with `publish` true, used conditions included.
- `inventory` 0 leaves a used listing `ended` and an inventory listing `sold`.
- `mine()` and `drafts()` are search indexes that trail a change by seconds.
- `negotiations()->active()` rows are under `listings`; the sales listing calls
  are for Preferred Sellers only.

## 0.1.0 - 2026-09-21

- First version: client, resources for every endpoint in Reverb's guides,
  production and sandbox environments, typed exceptions, lazy pagination,
  cached reference data, webhook receiver, `reverb-marketplace:check`.
- Supports Laravel 12 and 13. Laravel 11 is end-of-life and Composer blocks
  every 11.x release over unfixed security advisories, so it is not supported.
