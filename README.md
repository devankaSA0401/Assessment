# DNET Vendor Assessment

Lightweight PHP + SQLite vendor assessment application based on the supplied assessment design.

## Stack
- PHP 8.2+
- SQLite / PDO
- Vanilla HTML/CSS/JavaScript
- No framework or npm dependency

## Setup
1. Ensure PHP has `pdo_sqlite` enabled.
2. Clone this repository.
3. Run `php scripts/setup.php` after the complete application source is present.
4. Start with `php -S 127.0.0.1:8000 -t public`.
5. Open `http://127.0.0.1:8000`.

Default development credentials if no environment variables are set:
- Email: `admin@example.com`
- Password: `REDACTED_ADMIN_PASSWORD`

Change these before production use.

## Assessment model
The source document contains 11 parameters and 110 controls. Status scoring is Compliant=100, Partially Compliant=50, Non-Compliant=0, and N/A is excluded from the applicable-score denominator.

The supplied parameter weights total 105%, although the document states a 100% total. The application preserves the declared weights and normalizes them to 100% for the final weighted score.
