# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A PHP application for Golestan Hospital (Ahvaz) cath-lab, deployed on cPanel shared hosting. There is **no build step, no framework, and no dependency manager** — each `.php` file at the repository root is a self-contained page served directly by Apache. The UI is entirely in Persian (Farsi), right-to-left, dark-themed, with HTML/CSS/vanilla-JS inlined into each PHP file.

The repo actually contains **two independent subsystems** that share only the Jalali-date helpers and the RTL styling:

1. **Cath-lab prescription & queue** (MySQL-backed) — `index.php`, `submit.php`, `submit-manual.php`, `admin.php`, `queue.php`, `queue-all.php`, `config.php`.
2. **Lab-order intake with online payment** (flat-JSON-backed) — `lab-order.php`, `lab-admin.php`. This is a separate product with its own auth, storage, and a ZarinPal payment gateway + a marketer/referral commission system. It is **not** described in the README.

## Commands

There is no test suite, linter config, or composer.json in the repo (the `.github/workflows/php.yml` CI runs `composer validate`/`install`, which currently no-op since there is no `composer.json`). Development is done by editing PHP and viewing pages in a browser against a PHP + MySQL environment.

```bash
# Syntax-check a file before committing (the only automated check available):
php -l admin.php

# Serve locally (requires a MySQL/MariaDB reachable per config.php):
php -S localhost:8000
```

To run locally you must set real DB credentials in `config.php` (`DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`). Tables are auto-created on first request by `createTablesIfNeeded()` in `config.php` — there are no migration files.

## Architecture & key conventions

### Data flows through tables, not just status columns (cath-lab system)
A patient record physically **moves between three MySQL tables** as it progresses, rather than staying in one table with a changing status. This is the single most important thing to understand before editing `admin.php`:

```
submit.php / submit-manual.php  →  prescriptions  (pending → confirmed)
   admin.php "admit"            →  admitted       (INSERT + DELETE from prescriptions)
   admin.php "complete"         →  completed      (with `outcome`; INSERT + DELETE from admitted)
   admin.php "return to queue"  →  back to prescriptions (sets returned_at)
```

Every workflow transition is a POST to `admin.php` that does an `INSERT` into the target table followed by a `DELETE` from the source table (see the action handlers near the top of `admin.php`). When adding a field to a patient record you must add the column to **all three** `CREATE TABLE` statements in `config.php` **and** carry it through every INSERT in the move handlers. `outcome` valid values are fixed: `['نرمال','PCI','درمان دارویی','مشاوره جراحی قلب باز']`.

Patient tracking in `index.php` searches all three tables (completed → admitted → prescriptions) to find the patient's current stage, and computes queue position from `prescriptions` ordered by `submitted_at`.

### Two different auth models
- **`admin.php`** — session + hardcoded passwords defined at the top of the file. Roles: `admin` (full), `viewer` (read-only, forced to the admitted tab), and `doctor`. A doctor logs in with a per-doctor password and every query is scoped by `doctor_filter` (their Persian name) so they only see their own patients. Role checks are the booleans `$is_admin` / `$is_viewer` / `$is_doctor`. **Note:** the README describes a DB-backed "users tab" with add/edit/delete — that does not exist in the current code; auth is hardcoded.
- **`lab-admin.php` / `submit-manual.php` / `lab-order.php`** — each has its own separate hardcoded password constant and its own session flag.

### Doctors are identified three different ways — keep them in sync
The four doctors appear as: Persian display names in `admin.php`/`submit.php` (e.g. `دکتر کردونی`), and as URL slugs in `queue.php`'s `$doctors` map (e.g. `kardooni`, `mohammadi`, `taghizadeh`, `hamid`, each with a color). The DB stores the Persian name in `doctor_name`. Adding or renaming a doctor means editing the `$DOCTOR_PASSWORDS` map (`admin.php`), the `$doctors` slug map (`queue.php`), and the submission form options.

### Jalali (Persian) calendar
All dates are displayed in the Jalali calendar. The `toJalali()` / `shamsiDate()` conversion functions are **duplicated** verbatim across `config.php`, `admin.php`, `lab-admin.php`, and `lab-order.php` (guarded by `function_exists` in `config.php`). If you fix a date bug, fix it in every copy. `submit-manual.php` does the reverse — it converts a user-entered Jalali date back to Gregorian before storing `submitted_at`.

### Lab-order subsystem (`lab-order.php`, `lab-admin.php`)
Stores everything in JSON files under `data/` (`lab_orders.json`, `lab_pending.json`, `marketers.json`, `counter_boost.json`) via `loadJson`/`saveJson` helpers — no database. Flow: order goes to `lab_pending.json` → ZarinPal payment request → on `?step=verify` callback it is verified and moved to `lab_orders.json`. Referral codes come in via `?ref=` and are tracked against marketers for commission. The public counter is inflated by a random daily boost (`getDisplayCount`). ZarinPal merchant ID and the callback URL are hardcoded constants at the top of `lab-order.php`.

### Queue displays / PWA
`queue.php?dr=<slug>` shows one doctor's live queue; `queue-all.php` aggregates all doctors with a Chart.js percentage chart. Both auto-refresh every 30s and are installable as a PWA via `queue-manifest.json` (the app icon is an inline SVG data-URI, no icon files).

### Uploads & Apache config
Prescription images/PDFs (jpeg/png/gif/webp/pdf, ≤10 MB, MIME-validated in `submit.php`) are stored in `uploads/` with generated timestamp+uniqid filenames. `uploads/` contents are gitignored. `.htaccess` denies direct access to `config.php`, disables directory indexes, redirects 403/404 to `index.php`, and sets upload size / PHP flags — anything relying on those limits must match `.htaccess`.

## Conventions to follow
- Match the existing style: raw PDO with the `dbAll` / `dbRow` / `dbExec` prepared-statement helpers, `htmlspecialchars` on output, and inline `<style>`/`<script>` per page. There is no shared layout, header, or asset pipeline.
- Keep code PHP 7.4-compatible (target the cPanel host). `admin.php` uses short array syntax and list destructuring; `config.php`/`queue.php` use older `array()` syntax — stay consistent with whichever file you are in.
- After any POST action, the pattern is `header('Location: ...'); exit;` (PRG) — preserve it to avoid resubmission.
- All user-facing strings are Persian; keep new UI text in Persian and RTL.
