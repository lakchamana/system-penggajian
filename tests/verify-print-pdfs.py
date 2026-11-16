import json
import re
from pathlib import Path

import pymupdf as fitz

output = Path(__file__).resolve().parents[1] / "storage" / "qa" / "print"
fixture = json.loads((output / "fixture.json").read_text(encoding="utf-8"))
expected_slips = {"single-slip": 1, "all-slips": 2, "department-slips": 1, "all-department-slips": 2, "reference-slip": 1}
results = []


def verify_identity_alignment(page, filename, page_number):
    words = page.get_text("words")
    positions = []
    for label in ("NIP", "Nama", "Jumlah"):
        anchor = next(word for word in words if word[4] == label)
        row = sorted((word for word in words if abs(word[1] - anchor[1]) < 0.25), key=lambda word: word[0])
        colon_index = next(index for index, word in enumerate(row) if word[4] == ":")
        positions.append((row[colon_index][0], row[colon_index + 1][0]))
    for coordinate in (0, 1):
        values = [position[coordinate] for position in positions]
        assert max(values) - min(values) <= 0.25, (filename, "identity alignment", page_number, positions)
    effective = next(word for word in words if word[4] == "Jumlah")
    incoming = next(word for word in words if word[4] == "Masuk")
    assert abs(effective[1] - incoming[1]) <= 0.25, (filename, "attendance baseline", page_number)
    return positions

for filename in sorted(output.glob("*.pdf")):
    document = fitz.open(filename)
    is_slip = filename.stem in expected_slips
    expected_size = (210, 297) if is_slip else (297, 210)
    if is_slip:
        assert len(document) == expected_slips[filename.stem], (filename.name, len(document))
    if filename.stem in ("rekap_periode", "rekap_periode_bagian", "detailed"):
        assert len(document) > 1, filename.name
    for index, page in enumerate(document):
        width, height = page.rect.width * 25.4 / 72, page.rect.height * 25.4 / 72
        assert abs(width - expected_size[0]) < 0.5 and abs(height - expected_size[1]) < 0.5, (filename.name, width, height)
        text = page.get_text()
        assert text.strip(), (filename.name, "blank page", index + 1)
        assert "YAYASAN MUTIARA HIKMAH" in text, (filename.name, "missing repeated title", index + 1)
        assert fixture["officer"] in text, (filename.name, "missing officer", index + 1)
        assert "31-Oct-2026, 16:30:25 WIB" in text, (filename.name, "missing print time", index + 1)
        if is_slip:
            verify_identity_alignment(page, filename.name, index + 1)
            person = fixture["reference"] if filename.stem == "reference-slip" else fixture["first" if index == 0 else "second"]
            normalized = " ".join(text.split())
            assert person["name"] in normalized, (filename.name, "truncated identity", index + 1)
            assert person["nip"] in text, (filename.name, "missing NIP", index + 1)
            for field, value in person["numbers"].items():
                # Money and attendance quantities must all survive the print-only template.
                expected = f"{int(value):,}".replace(",", ".")
                assert expected in text or str(value) in text, (filename.name, field, expected, index + 1)
            for excluded in ("Filter laporan", "Cetak / Simpan PDF", "Ruang kerja", "Pratinjau", "Keluar akun"):
                assert excluded not in text, (filename.name, "UI included in print", excluded)
            assert text.count("SLIP GAJI KARYAWAN YAYASAN MUTIARA HIKMAH") == 1, (filename.name, "duplicate screen slip printed")
            income = page.search_for("Penghasilan")[0]
            deductions = page.search_for("Potongan")[0]
            assert abs(income.y0 - deductions.y0) < 2 and income.x1 < deductions.x0, (filename.name, "columns")
            gross = page.search_for("Jumlah Kotor")[0]
            total = page.search_for("Total Potongan")[0]
            assert abs(gross.y0 - total.y0) < 2, (filename.name, "totals alignment")
            salary = page.search_for("Gaji Pokok")[0]
            allowance = page.search_for("T. Jabatan")[0]
            assert allowance.y0 - salary.y0 >= 6.5 * 72 / 25.4 - 1, (filename.name, "employee row spacing")
        margin = 12
        for x0, y0, x1, y1, word, *_ in page.get_text("words"):
            assert x0 >= margin * 72 / 25.4 - 2 and y0 >= margin * 72 / 25.4 - 2, (filename.name, "outside top/left", index + 1, word)
            assert x1 <= page.rect.width - margin * 72 / 25.4 + 2 and y1 <= page.rect.height - margin * 72 / 25.4 + 2, (filename.name, "outside bottom/right", index + 1, word)
        if index == 0 or index == len(document) - 1:
            page.get_pixmap(matrix=fitz.Matrix(1.8, 1.8), alpha=False).save(output / f"{filename.stem}-page-{index + 1}.png")
    if is_slip:
        normalized = re.sub(r"\s+", " ", document[0].get_text())
        person = fixture["reference"] if filename.stem == "reference-slip" else fixture["first"]
        assert person["name"] in normalized, (filename.name, "truncated name")
        if filename.stem != "reference-slip":
            assert "900.000.000.000.000.000" in normalized, (filename.name, "truncated amount")
    results.append({"file": filename.name, "pages": len(document), "paper_mm": expected_size})

(output / "verification.json").write_text(json.dumps(results, indent=2), encoding="utf-8")
print(json.dumps(results, indent=2))
print("PASS: A4 portrait slips and landscape recaps; page counts, employee spacing, columns, complete identities/amounts, no screen UI, officer, timestamp and 12 mm margins.")
