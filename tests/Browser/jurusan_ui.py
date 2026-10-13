"""Run against the disposable app on port 8890; never the school database."""

import json
import os
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

BASE = "http://127.0.0.1:8890"
OUT = Path(os.environ.get("LOCALAPPDATA", ".")) / "Temp" / "opencode" / "jurusan-ui-evidence"
OUT.mkdir(parents=True, exist_ok=True)
evidence = []
errors = []


def record(name):
    evidence.append(name)
    print("PASS:", name, flush=True)


def login(page):
    page.goto(BASE + "/login")
    assert page.locator('script[type="module"][src]').evaluate_all("els => els.length > 0 && els.every(el => el.src.includes('/build/assets/'))")
    page.locator('[name="user"]').fill("audit.admin")
    page.locator('[name="password"]').fill("password123")
    page.get_by_role("button", name="Masuk", exact=True).click()
    expect(page.locator(".avatar")).to_have_text("A")
    expect(page.locator(".topbar")).to_contain_text("Admin Audit")


def theme(page, value):
    page.get_by_role("button", name="Pengaturan tampilan").click()
    page.locator(f'.pref-seg[data-pref="theme"] [data-value="{value}"]').click()
    expect(page.locator("html")).to_have_attribute("data-theme", value)
    page.keyboard.press("Escape")
    page.evaluate("() => Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {})))")


LAYOUT = """() => {
    const visible = el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
    const controls = [...document.querySelectorAll('.content a, .content button, .content input:not([type="hidden"]):not([type="checkbox"]), .content select, .field-confirm, .sidebar-toggle, .topbar .user-chip, .pref-panel button, .pref-toggle, .topbar .dropdown-item')]
        .filter(visible).map(el => {const r=el.getBoundingClientRect(); return {tag:el.tagName,text:(el.textContent||el.name||'').trim().slice(0,60),width:r.width,height:r.height};});
    return {width:innerWidth,scrollWidth:document.documentElement.scrollWidth,small:controls.filter(el=>el.height<43.9 || el.width<43.9)};
}"""

CONTRAST = """() => {
    const canvas=document.createElement('canvas'), ctx=canvas.getContext('2d', {willReadFrequently:true});
    canvas.width=canvas.height=1;
    const rgba = s => {ctx.clearRect(0,0,1,1);ctx.fillStyle=s;ctx.fillRect(0,0,1,1);const a=[...ctx.getImageData(0,0,1,1).data];return [a[0],a[1],a[2],a[3]/255];};
    const over = (a,b) => {const alpha=a[3]+b[3]*(1-a[3]);return alpha ? [0,1,2].map(i=>(a[i]*a[3]+b[i]*b[3]*(1-a[3]))/alpha).concat(alpha) : b;};
    const bg = el => {const nodes=[];for(let p=el;p;p=p.parentElement)nodes.unshift(p);return nodes.reduce((c,p)=>over(rgba(getComputedStyle(p).backgroundColor),c),[255,255,255,1]);};
    const lum=c=>c.slice(0,3).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((s,v,i)=>s+v*[.2126,.7152,.0722][i],0);
    const result=[];
    for (const el of document.querySelectorAll('.content *, .topbar *, .sidebar *')) {
        const style=getComputedStyle(el), rect=el.getBoundingClientRect();
        if (!rect.width || !rect.height || style.visibility==='hidden' || el.closest('[inert]') || el.closest('[hidden]')) continue;
        if (!Array.from(el.childNodes).some(n=>n.nodeType===Node.TEXT_NODE && n.textContent.trim()) && !el.matches('input:not([type="checkbox"]):not([type="radio"]),textarea,select')) continue;
        if (el.closest('.visually-hidden') || style.opacity==='0' || el.matches('.tbl__sort-arrow,.is-disabled')) continue;
        const back=bg(el), front=over(rgba(style.color),back), ratio=(Math.max(lum(front),lum(back))+.05)/(Math.min(lum(front),lum(back))+.05);
        const large=parseFloat(style.fontSize)>=24 || (parseFloat(style.fontSize)>=18.66 && parseFloat(style.fontWeight)>=700);
        if(ratio < (large?3:4.5)-.01) result.push({text:(el.textContent||el.value||'').trim().slice(0,80),class:el.className,color:style.color,background:back,ratio});
        if (el.matches('input[placeholder],textarea[placeholder]') && !el.value) {
            const placeholder=over(rgba(getComputedStyle(el,'::placeholder').color),back);
            const placeholderRatio=(Math.max(lum(placeholder),lum(back))+.05)/(Math.min(lum(placeholder),lum(back))+.05);
            if(placeholderRatio<4.49) result.push({text:el.placeholder,ratio:placeholderRatio});
        }
    }
    return result;
}"""


def inspect(page, label):
    layout = page.evaluate(LAYOUT)
    assert layout["scrollWidth"] <= layout["width"], (label, "page overflow", layout)
    assert not layout["small"], (label, "small touch targets", layout["small"])
    contrast = page.evaluate(CONTRAST)
    assert not contrast, (label, "text contrast", contrast)
    page.screenshot(path=str(OUT / (label + ".png")), full_page=True)
    record(label + ": no page overflow, controls >=44px, computed text contrast AA")


def inspect_jurusan_information(page, label):
    assert page.evaluate("document.documentElement.scrollWidth <= innerWidth"), label
    issues = page.evaluate(CONTRAST)
    assert not [issue for issue in issues if "Jurusan Siswa Pengisi" in issue["text"]
                or "Teknik Komputer dan Jaringan" in issue["text"]], (label, issues)
    page.screenshot(path=str(OUT / (label + ".png")), full_page=True)
    record(label + ": student jurusan information present, AA contrast, no page overflow")


with sync_playwright() as pw:
    browser = pw.chromium.launch(headless=True, channel="msedge")
    context = browser.new_context(viewport={"width": 1440, "height": 1000}, color_scheme="light")
    page = context.new_page()
    page.set_default_navigation_timeout(60000)
    page.on("pageerror", lambda error: errors.append(str(error)))
    page.on("console", lambda message: errors.append({"message": message.text, "location": message.location}) if message.type == "error" else None)
    login(page)
    page.locator('.sidebar__link[href$="/admin/jurusan"]').click()
    expect(page).to_have_url(BASE + "/admin/jurusan")
    record("Jurusan navigation opens list")

    for mode in ["light", "dark"]:
        theme(page, mode)
        for width in [1440, 768, 375, 320]:
            page.set_viewport_size({"width": width, "height": 900})
            for path, name in [("/admin/jurusan", "list"), ("/admin/jurusan/create", "create"), ("/admin/jurusan/TKJ/edit", "edit")]:
                page.goto(BASE + path)
                inspect(page, f"{mode}-{width}-{name}")
            page.goto(BASE + "/admin/jurusan")
            page.locator('.filter-bar [name="aktif"]').select_option("0")
            page.get_by_role("button", name="Terapkan Filter").click()
            expect(page.locator("tbody .chip")).to_have_count(15)
            page.locator(".tbl__sort").filter(has_text="Nama").click()
            page.get_by_role("link", name="Tambah Jurusan", exact=True).click()
            page.get_by_role("button", name="Simpan Jurusan", exact=True).click()
            assert page.locator("#kode").evaluate("el => !el.validity.valid")
            page.get_by_role("link", name="Batal", exact=True).click()
            record(f"{mode}-{width}: filter, sort, create navigation, validation and cancel work")
        page.get_by_role("button", name="Pengaturan tampilan").click()
        inspect(page, f"{mode}-320-preferences")
        page.keyboard.press("Escape")
        page.locator('.topbar .user-chip').filter(has=page.locator('.avatar')).click()
        inspect(page, f"{mode}-320-account-menu")
        page.keyboard.press("Escape")

    page.set_viewport_size({"width": 1440, "height": 1000})
    theme(page, "light")
    page.goto(BASE + "/admin/jurusan/create")
    page.get_by_role("button", name="Simpan Jurusan", exact=True).click()
    assert page.locator("#kode").evaluate("el => !el.validity.valid")
    expect(page.locator("[data-processing-status]")).to_be_hidden()
    record("Empty form blocked by native validation without false busy state")
    page.locator("#kode").fill("TK J")
    page.locator("#nama").fill("Jurusan audit")
    page.get_by_role("button", name="Simpan Jurusan", exact=True).click()
    expect(page.locator(".field__error")).to_contain_text("whitespace")
    inspect(page, "light-validation-error")
    theme(page, "dark")
    inspect(page, "dark-validation-error")
    theme(page, "light")
    record("Whitespace code displays server validation in both themes")

    page.locator("#kode").fill("browser")
    page.locator("#nama").fill("Jurusan Browser")
    page.locator("#bidang").fill("Bidang Browser")
    page.locator("#deskripsi").fill("Deskripsi pengujian browser.")
    page.locator("#aktif").select_option("0")

    page.evaluate("""() => {
        window.holdAuditSubmit = event => event.preventDefault();
        document.addEventListener('submit', window.holdAuditSubmit);
    }""")
    page.get_by_role("button", name="Simpan Jurusan", exact=True).click()
    expect(page.locator("[data-processing-status]")).to_have_text("Menyimpan jurusan...")
    expect(page.locator("form[data-processing]")).to_have_attribute("aria-busy", "true")
    expect(page.get_by_role("button", name="Memproses...", exact=True)).to_have_attribute("aria-disabled", "true")
    repeated = page.evaluate("""() => {
        const event = new SubmitEvent('submit', {bubbles: true, cancelable: true});
        document.removeEventListener('submit', window.holdAuditSubmit);
        const allowed = document.querySelector('form[data-processing]').dispatchEvent(event);
        window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted: true}));
        return allowed;
    }""")
    assert not repeated
    expect(page.get_by_role("button", name="Simpan Jurusan", exact=True)).not_to_have_attribute("aria-disabled", "true")
    expect(page.locator("[data-processing-status]")).to_be_hidden()
    page.get_by_role("button", name="Simpan Jurusan", exact=True).click()
    expect(page.locator(".flash--success")).to_contain_text("ditambahkan")
    record("Create succeeds, busy status shown, repeat submit guarded")

    page.locator('[name="q"]').fill("Jurusan Browser")
    page.locator('[name="bidang"]').select_option("Bidang Browser")
    page.locator('.filter-bar [name="aktif"]').select_option("0")
    page.get_by_role("button", name="Terapkan Filter").click()
    expect(page.locator("tbody tr")).to_have_count(1)
    expect(page.locator("tbody")).to_contain_text("BROWSER")
    record("Search, bidang and inactive filter narrow results")
    page.get_by_role("link", name="Ubah jurusan BROWSER").click()
    page.locator("#kode").fill("browser2")
    page.locator("#nama").fill("Jurusan Browser Diubah")
    page.locator("#aktif").select_option("1")
    page.get_by_role("button", name="Simpan Perubahan").click()
    expect(page.locator(".flash--success")).to_contain_text("diperbarui")
    record("Edit renames code and changes status")

    page.goto(BASE + "/admin/jurusan/BROWSER2/edit")
    page.get_by_role("button", name="Hapus Jurusan", exact=True).click()
    assert page.locator('input[type="checkbox"][required]').evaluate("el => !el.validity.valid")
    page.locator('input[type="checkbox"][required]').check()
    page.get_by_role("button", name="Hapus Jurusan", exact=True).click()
    expect(page.locator(".flash--success")).to_contain_text("dihapus")
    record("Delete requires confirmation and removes unused jurusan")
    page.goto(BASE + "/admin/jurusan/TKJ/edit")
    page.locator('input[type="checkbox"][required]').check()
    page.get_by_role("button", name="Hapus Jurusan", exact=True).click()
    expect(page.locator(".flash--error")).to_contain_text("masih dipakai")
    record("Referenced jurusan deletion displays refusal")

    page.locator('[name="q"]').fill("Tidak ada hasil audit")
    page.get_by_role("button", name="Terapkan Filter").click()
    expect(page.locator(".empty-state")).to_contain_text("Belum ada jurusan")
    record("No results displays empty state")
    page.goto(BASE + "/admin/jurusan")
    for name in ["Kode", "Nama", "Bidang", "Kelas", "Siswa", "Status"]:
        page.locator(".tbl__sort").filter(has_text=name).first.click()
        assert "sort=" in page.url
    record("Every sortable column navigates to sort URL")
    page.locator('select[name="per"]').select_option("50")
    expect(page.locator("tbody tr")).to_have_count(31)
    page.locator('select[name="per"]').select_option("25")
    page.locator('.pager a[rel="next"]').click()
    expect(page.locator(".pager .is-current")).to_have_text("2")
    page.locator('.pager a[rel="prev"]').click()
    expect(page.locator(".pager .is-current")).to_have_text("1")
    page.locator('.pager a').filter(has_text="2").click()
    expect(page.locator(".pager .is-current")).to_have_text("2")
    page.locator('.pager-bar__lompat [name="page"]').fill("1")
    page.locator('.pager-bar__lompat [name="page"]').press("Enter")
    expect(page.locator(".pager .is-current")).to_have_text("1")
    record("Page size, next page and keyboard page jump work")

    page.goto(BASE + "/admin/jurusan/create")
    for name in ["Batal", "Kembali"]:
        page.get_by_role("link", name=name, exact=True).click()
        expect(page).to_have_url(BASE + "/admin/jurusan")
        page.goto(BASE + "/admin/jurusan/create")
    page.locator("#kode").focus()
    for target in ["nama", "bidang", "aktif", "deskripsi"]:
        page.keyboard.press("Tab")
        assert page.evaluate("document.activeElement.id") == target
        outline = page.evaluate("getComputedStyle(document.activeElement).outlineStyle")
        assert outline not in ["none", "hidden"]
    page.keyboard.press("Shift+Tab")
    assert page.evaluate("document.activeElement.id") == "aktif"
    record("Cancel/back links and logical Tab/Shift+Tab with visible focus work")

    page.set_viewport_size({"width": 375, "height": 900})
    page.goto(BASE + "/admin/jurusan")
    expect(page.locator("#sidebar")).to_have_attribute("inert", "")
    page.locator("#sidebarToggle").focus()
    page.keyboard.press("Enter")
    expect(page.locator("#sidebarToggle")).to_have_attribute("aria-expanded", "true")
    page.keyboard.press("Shift+Tab")
    assert page.evaluate("document.activeElement.textContent.trim()") == "Logout"
    page.keyboard.press("Tab")
    assert page.evaluate("document.activeElement.className") == "sidebar__brand"
    page.keyboard.press("Escape")
    expect(page.locator("#sidebarToggle")).to_be_focused()
    expect(page.locator("#sidebar")).to_have_attribute("inert", "")
    record("Mobile menu keyboard open, focus wrap, Escape close and focus return work")
    page.get_by_role("button", name="Pengaturan tampilan").click()
    page.locator('[data-pref-toggle="contrast"]').check()
    expect(page.locator("html")).to_have_attribute("data-contrast", "more")
    page.locator('[data-pref-toggle="contrast"]').uncheck()
    page.locator('[data-pref-toggle="motion"]').check()
    expect(page.locator("html")).to_have_attribute("data-motion", "reduced")
    page.locator('[data-pref-toggle="motion"]').uncheck()
    for value in ["kecil", "besar", "ekstra", "normal"]:
        page.locator(f'.pref-seg[data-pref="font"] [data-value="{value}"]').click()
    page.locator('.pref-seg[data-pref="theme"] [data-value="system"]').click()
    expect(page.locator("html")).not_to_have_attribute("data-theme", "light")
    page.keyboard.press("Escape")
    record("Appearance theme, font, contrast and reduced-motion toggles update state")

    page.set_viewport_size({"width": 1440, "height": 1000})
    for mode in ["light", "dark"]:
        theme(page, mode)
        for width in [1440, 375, 320]:
            page.set_viewport_size({"width": width, "height": 900})
            for path, name in [
                ("/jurnal/01JAUDIT000000000000000001", "detail"),
                ("/jurnal", "histori"),
                ("/admin/laporan/jurnal", "report"),
            ]:
                page.goto(BASE + path)
                expect(page.locator(".content")).to_contain_text("Jurusan Siswa Pengisi")
                expect(page.locator(".content")).to_contain_text("Teknik Komputer dan Jaringan")
                inspect_jurusan_information(page, f"{mode}-{width}-journal-{name}")
    record("Student jurusan is shown on journal detail and report")
    assert not errors, errors
    record("No browser console errors or uncaught JavaScript errors")
    (OUT / "results.json").write_text(json.dumps({"browser": browser.version, "checks": evidence, "errors": errors}, indent=2), encoding="utf-8")
    context.close()
    browser.close()
print(f"{len(evidence)} browser checks passed; screenshots: {OUT}", flush=True)
