# Jurnal Kelas — API access

The `/api/*` endpoints are a **token-authenticated JSON API** (Laravel Sanctum). They
return JSON and require a Bearer token, so opening one in a browser gives
`401 {"message":"Unauthenticated."}` — that's expected, not a bug. For a human-friendly
view of the same data, just use the website (log in at `http://localhost:8888`).

Base URL: `http://localhost:8888/api`

## Seeded logins (password: `password`)

| Role  | user (NIS/NIP or email)            | role field |
|-------|------------------------------------|------------|
| admin | `admin@jurnalkelas.app`            | `admin`    |
| guru  | `budi.santoso@jurnalkelas.app`     | `guru`     |
| siswa | `siswa20261001@jurnalkelas.app`    | `siswa`    |

## Option 1 — Postman

1. Import `docs/jurnal-kelas.postman_collection.json`.
2. **Just send any request.** A collection pre-request script **auto-logs-in as admin** the
   first time and stores the token, so you never get `401 Unauthenticated` — even if you
   forget to log in.
3. To browse as another role, run **Auth → Login (guru)** / **Login (siswa)** (they overwrite
   the saved token). Clear the `token` collection variable to fall back to admin auto-login.

Path ids use variables (`{{kelas_id}}`, `{{jurnal_id}}`, `{{user_id}}`, …) editable under
the collection's **Variables** tab.

> The auto-login uses Postman's pre-request scripting. In **Insomnia/Bruno**, run a **Login**
> request first. A plain **browser can't send a Bearer token at all** — opening `/api/...` in a
> browser will always show `Unauthenticated`; use the website, Postman, or `docs/api.sh`.

## Option 2 — terminal

```bash
source docs/api.sh
apilogin admin@jurnalkelas.app password admin   # stores the token
apic /dashboard
apic '/jurnal?q=basis+data&sort=tanggal&dir=desc'
apic /laporan/rekap-kelas
```
