"""Validate synthetic employee PDFs produced by node tests/employee-portal.js."""
import json
from pathlib import Path

import fitz

root = Path(__file__).resolve().parents[1] / "storage/qa/employee-portal"
expected = json.loads((root / "portrait-expected.json").read_text(encoding="utf-8"))
for filename in ["employee-slip-a4.pdf", "employee-slip-a4-long.pdf", "employee-slip-download.pdf", "employee-slip-download-long.pdf"]:
    document = fitz.open(root / filename)
    assert len(document) == 1, f"{filename}: expected one page, got {len(document)}"
    page = document[0]
    assert abs(page.rect.width - 595.28) < 3 and abs(page.rect.height - 841.89) < 3, page.rect
    text = page.get_text()
    for label in ["DUMMY001", "Accounting", "Penerima", "Gaji Bersih", "Penghasilan", "Potongan", "Jumlah Hari Efektif"]:
        assert label in text, (filename, label)
    for excluded in ["Periode terbaru", "Ubah Password Awal", "Cetak / Simpan PDF", "Slip Gaji Saya", "Ruang kerja", "belum tersedia"]:
        assert excluded not in text, (filename, excluded)
    if "long" in filename:
        flat = " ".join(text.split())
        assert " ".join(expected["name"].split()) in flat
        assert " ".join(expected["department"].split()) in flat
        for value in expected["numbers"].values():
            number = str(value)
            formatted = f"{int(number):,}".replace(",", ".")
            assert formatted in text or number in text, (filename, formatted)
    else:
        assert "3.000.000" in text
    income = page.search_for("Penghasilan")[0]
    deductions = page.search_for("Potongan")[0]
    assert abs(income.y0 - deductions.y0) < 2, (filename, "legacy columns must align")
    assert income.x1 < deductions.x0, (filename, "legacy columns must be side by side")
    gross = page.search_for("Jumlah Kotor")[0]
    total_deductions = page.search_for("Total Potongan")[0]
    assert abs(gross.y0 - total_deductions.y0) < 2, (filename, "legacy totals must align")
    words = page.get_text("words")
    nip = next(word for word in words if word[4] == "NIP")
    identity_row = sorted((word for word in words if abs(word[1] - nip[1]) < 1), key=lambda word: word[0])
    colon_index = next(index for index, word in enumerate(identity_row) if word[4] == ":")
    assert identity_row[colon_index + 1][0] - identity_row[colon_index][2] < 12, (filename, "identity value must follow colon")
    for word in page.get_text("words"):
        assert word[0] >= 30 and word[2] <= page.rect.width - 30, (filename, word)
        assert word[1] >= 30 and word[3] <= page.rect.height - 30, (filename, word)
    page.get_pixmap(matrix=fitz.Matrix(1.2, 1.2)).save(root / filename.replace(".pdf", "-render.png"))
    print(f"PASS: {filename}; one A4 portrait page; complete identities, amounts, and margins.")
