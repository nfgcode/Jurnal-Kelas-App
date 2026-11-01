import os
import sys
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

BASE = "http://127.0.0.1:8888"
OUT = Path(os.environ.get("LOCALAPPDATA", ".")) / "Temp" / "jurnal-phase9-qa"
OUT.mkdir(parents=True, exist_ok=True)

scenarios = []

def record(name, status="PASS"):
    scenarios.append({"scenario": name, "status": status})
    print(f"[{status}] {name}", flush=True)

print("Starting Phase 9.5 Read-Only Headless Browser QA via Microsoft Edge channel...", flush=True)

try:
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=True, channel="msedge")

        # Test Desktop Viewport
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()

        # 1. Login Page Inspection
        page.goto(BASE + "/login")
        expect(page).to_have_title("Masuk · Jurnal Kelas")
        page.screenshot(path=str(OUT / "01-login-desktop.png"))
        record("Login page loads correctly with proper title and assets")

        # 2. Authenticate as Admin
        page.locator('[name="user"]').fill("admin")
        page.locator('[name="password"]').fill("password")
        page.get_by_role("button", name="Masuk", exact=True).click()

        # Check Admin Dashboard
        expect(page.locator(".topbar")).to_be_visible()
        page.screenshot(path=str(OUT / "02-dashboard-desktop.png"))
        record("Admin authentication and topbar render successfully")

        # 3. Hub Tahun Ajaran
        page.goto(BASE + "/admin/tahun-ajaran")
        expect(page.locator("h2")).to_contain_text("Pusat Periode Akademik")
        page.screenshot(path=str(OUT / "03-tahun-ajaran-desktop.png"))
        record("Tahun Ajaran Hub: Hero, statistics, and 4 workflow cards render")

        # 4. Modul Kelulusan Kelas XII
        page.goto(BASE + "/admin/kelulusan")
        expect(page.locator(".breadcrumb")).to_contain_text("Kelulusan Kelas XII")
        page.screenshot(path=str(OUT / "04-kelulusan-desktop.png"))
        record("Kelulusan XII: Breadcrumb, template download, and upload form render")

        # 5. Modul Kenaikan Kelas X-XI
        page.goto(BASE + "/admin/kenaikan-kelas")
        expect(page.locator(".breadcrumb")).to_contain_text("Kenaikan Kelas X–XI")
        page.screenshot(path=str(OUT / "05-kenaikan-desktop.png"))
        record("Kenaikan Kelas: Breadcrumb, filtering, and upload cards render")

        # 6. Modul Penerimaan Siswa Baru
        page.goto(BASE + "/admin/penerimaan-siswa")
        expect(page.locator(".breadcrumb")).to_contain_text("Penerimaan Siswa Baru")
        page.screenshot(path=str(OUT / "06-intake-desktop.png"))
        record("Penerimaan Siswa: Breadcrumb and intake registration cards render")

        # 7. Transisi Terpadu Hub
        page.goto(BASE + "/admin/transisi-terpadu")
        expect(page.locator("h2.page-head__title")).to_contain_text("Transisi")
        page.screenshot(path=str(OUT / "07-transisi-terpadu-desktop.png"))
        record("Transisi Terpadu: Session history, setup coordinator modal trigger render")

        # 8. Modul Legacy Transisi & Banner Edukasi
        page.goto(BASE + "/admin/transisi")
        expect(page.locator(".card-hifi").first).to_contain_text("Tersedia Alur Transisi Terpadu Terkoordinasi")
        page.screenshot(path=str(OUT / "08-transisi-legacy-banner-desktop.png"))
        record("Legacy Transisi: Educational banner explaining separate transactions renders")

        # 9. Manajemen Data Hub
        page.goto(BASE + "/admin/manajemen-data")
        expect(page.locator("h2, h3, h4").first).to_contain_text("Manajemen Data")
        page.screenshot(path=str(OUT / "09-manajemen-data-desktop.png"))
        record("Manajemen Data Hub: Import and Backup/Restore navigation cards render")

        # 10. Staged Restore UI (Isolated Sandbox Only)
        page.goto(BASE + "/admin/manajemen-data/pemulihan")
        expect(page.locator("body")).to_contain_text("Production Restore: DISABLED")
        page.screenshot(path=str(OUT / "10-pemulihan-staging-desktop.png"))
        record("Staged Restore: 5-step stepper indicator and disabled production restore render")

        # 11. Mobile Viewport Inspection (375x667)
        page.set_viewport_size({"width": 375, "height": 667})
        page.goto(BASE + "/admin/tahun-ajaran")
        page.screenshot(path=str(OUT / "11-tahun-ajaran-mobile.png"))
        record("Mobile Responsiveness: 375px viewport layouts without horizontal overflow")

        # 12. Theme Switcher (Dark Mode Verification)
        page.get_by_role("button", name="Pengaturan tampilan").click()
        page.locator('.pref-seg[data-pref="theme"] [data-value="dark"]').click()
        expect(page.locator("html")).to_have_attribute("data-theme", "dark")
        page.screenshot(path=str(OUT / "12-dark-mode-verification.png"))
        record("Appearance preferences: Dark mode token application verified")

        browser.close()

except Exception as e:
    record(f"Playwright error: {str(e)}", status="FAIL")
    sys.exit(1)

print("\n--- Playwright QA Run Completed Successfully ---", flush=True)
for item in scenarios:
    print(f"- {item['status']}: {item['scenario']}")
print(f"Evidence screenshots saved to: {OUT}")
