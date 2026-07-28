# Local demo accounts (SPIMS)

Unified password for **all** seeded demo users:

```
Spims@Test2026!
```

Super Admin (from `SuperAdminSeeder`, password from `.env` `SUPERADMIN_PASSWORD`, default `Spims@Dev2026!`):

| Email | Role |
|-------|------|
| `robeir.george@outlook.com` | `SUPER_ADMIN` |

Demo users (password above):

| Email | Role(s) |
|-------|---------|
| `adm@spims.test` | `ADMINISTRATIVE_ADMIN` |
| `aca@spims.test` | `ACADEMIC_ADMIN` |
| `fin@spims.test` | `FINANCIAL_ADMIN` |
| `ins1@spims.test` | `INSTRUCTOR` |
| `ins2@spims.test` | `INSTRUCTOR` |
| `ta1@spims.test` | `TA` |
| `student1@spims.test` … `student10@spims.test` | `STUDENT` |
| `dual@spims.test` | `INSTRUCTOR` + `STUDENT` |

## What else is seeded

- Programs: `DIP-THEO`, `CERT-LIT`, `DEG-BTH`, `CERT-BIB` (with credit / semester graduation caps)
- ~12 courses with credit hours and USD/EGP prices (+ free orientation)
- Academic years `2025/2026`, `2026/2027` with Fall/Spring semesters
- Open Fall cohort offerings + self-paced ethics offering + draft Spring offerings
- Application forms + mixed application statuses
- Accepted students enrolled into open offerings

## Re-seed locally

```bash
php artisan migrate:fresh --seed
# or
php artisan db:seed --class=DemoDataSeeder
php artisan permissions:sync
```

Disable with `SEED_DEMO_DATA=false` in `.env`.
