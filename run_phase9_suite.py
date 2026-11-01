import os
import glob
import subprocess
import json
import time

repo = "C:/Users/Tenma/Jurnal-Kelas-App"
out_dir = os.path.join(repo, "docs/phase9-evidence")
os.makedirs(out_dir, exist_ok=True)

# Temukan seluruh unit dan feature tests
pattern_feature = os.path.join(repo, "tests/Feature/*Test.php")
pattern_unit = os.path.join(repo, "tests/Unit/*Test.php")

files = sorted(glob.glob(pattern_feature) + glob.glob(pattern_unit))
rel_files = [os.path.relpath(f, repo).replace("\\", "/") for f in files]

batch_size = 4
batches = [rel_files[i:i + batch_size] for i in range(0, len(rel_files), batch_size)]

manifest = {
    "total_files": len(rel_files),
    "total_batches": len(batches),
    "files": rel_files,
    "batches": {f"batch-{i+1:02d}": b for i, b in enumerate(batches)}
}

with open(os.path.join(out_dir, "manifest.json"), "w") as f:
    json.dump(manifest, f, indent=2)

summary = {
    "total_files": len(rel_files),
    "total_batches": len(batches),
    "passed_tests": 0,
    "passed_assertions": 0,
    "failed_tests": 0,
    "batches": {}
}

print(f"Starting Phase 9 full regression: {len(rel_files)} files across {len(batches)} batches...")

for i, batch in enumerate(batches):
    batch_num = f"{i+1:02d}"
    batch_file = f"batch-{batch_num}.json"
    print(f"Running batch {batch_num}/{len(batches)} ({len(batch)} files)...")

    cmd = ["php", "tests/run.php"] + batch
    start = time.time()
    res = subprocess.run(cmd, cwd=repo, capture_output=True, text=True)
    duration = time.time() - start

    out_text = res.stdout.strip()
    try:
        parsed = json.loads(out_text)
    except Exception:
        parsed = {"raw": out_text, "error": res.stderr}

    with open(os.path.join(out_dir, batch_file), "w") as f:
        json.dump({
            "batch": batch_num,
            "files": batch,
            "duration_s": round(duration, 2),
            "output": parsed
        }, f, indent=2)

    tests = parsed.get("tests", 0) if isinstance(parsed, dict) else 0
    assertions = parsed.get("assertions", 0) if isinstance(parsed, dict) else 0
    failed = parsed.get("failed", 0) if isinstance(parsed, dict) else 0

    summary["passed_tests"] += (tests - failed)
    summary["passed_assertions"] += assertions
    summary["failed_tests"] += failed
    summary["batches"][batch_num] = {
        "tests": tests,
        "assertions": assertions,
        "failed": failed,
        "duration_s": round(duration, 2)
    }

with open(os.path.join(out_dir, "summary.json"), "w") as f:
    json.dump(summary, f, indent=2)

print("\nFull regression finished:")
print(f"Passed Tests: {summary['passed_tests']}")
print(f"Passed Assertions: {summary['passed_assertions']}")
print(f"Failed Tests: {summary['failed_tests']}")
