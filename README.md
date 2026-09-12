# DNET Vendor Assessment

Lightweight vendor security assessment application based on the supplied **DNET Mailing Vendor** questionnaire.

## Baseline
- 11 parameters
- 110 controls
- Weights total 100%
- Status scoring: Compliant 100, Partially Compliant 50, Non-Compliant 0, N/A excluded
- Parameter score = average of applicable control scores
- Weighted score = parameter score × parameter weight
- Final score = sum of all weighted scores
- Workflow: Assessment Question → Status → Evidence → Finding → Recommendation

The questionnaire data is imported from `database/questionnaire.json` generated from the supplied design document. The supplied source document defines the 110-control structure and the scoring formula. The supplied parameter weights add up to 105% even though the document states a 100% total; this implementation preserves the displayed weights and normalizes them to 100% for the final score so the result remains a 0–100 score.

## Stack
- PHP 8.2+
- SQLite + PDO
- Server-rendered PHP views
- Vanilla CSS/JS
- No framework or Node dependency

## Run locally

```bash
php scripts/setup.php
php -S 127.0.0.1:8000 -t public public/index.php
```

Open `http://127.0.0.1:8000/login`.

Default development credentials:
- Email: `admin@example.com`
- Password: Set during deployment

For a real deployment, set `ADMIN_EMAIL` and `ADMIN_PASSWORD` before running setup and change/remove the development credential.

## Modules
1. Authentication
2. Dashboard / assessment list
3. Assessment creation
4. 11-parameter questionnaire navigation
5. 110-control status assessment
6. Evidence upload (10 MB/file)
7. Conditional finding + recommendation fields
8. Weighted scoring and final rating
9. Critical findings summary
10. Audit log
11. Print-ready assessment report / browser Save as PDF

## Production notes
Use HTTPS, configure a proper PHP-FPM/Apache/Nginx virtual host, move uploads outside direct public access if evidence confidentiality requires it, and replace the simple local authentication with the organization's SSO/IAM when integrating into production.

## PHP extension
SQLite is intentionally chosen for a small footprint. The PHP runtime must have `pdo_sqlite` enabled. On Debian/Ubuntu this is typically provided by `php-sqlite3`.
