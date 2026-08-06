#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
pdf_songs.py — extraktor piesní zo spevníkových PDF pre songbook_manager import.

Použitie:  python pdf_songs.py <subor.pdf>
Výstup:    JSON na stdout: {"ok": true, "mode": "...", "songs": [{"title","author","category_hint","body"}]}
           body = text s inline chord markermi \x01AKORD\x01 (spracuje PHP imp_marked_to_ir).

Podporované formáty (deterministicky podľa fontov):
  score mode  — notové spevníky (napr. Diecézny mládežnícky spevník 2016):
                akordy vo fonte OpusChordsStd, slabiky s '-' rozdeľovníkmi pod notami.
  text mode   — textové spevníky (napr. Spievatko Beluša):
                akordy = krátke bold spany zlepené s textom, tituly = bold riadky.

Vyžaduje: pip install pymupdf
"""
import sys, json, re

try:
    import fitz  # PyMuPDF
except ImportError:
    print(json.dumps({"ok": False, "error": "PyMuPDF (pymupdf) nie je nainštalované: pip install pymupdf"}))
    sys.exit(0)

CHORD_MARK = "\x01"
CHORD_RE = re.compile(r"^[A-Ha-h][#b©’']?(m|mi|maj|min|dim|aug|sus|add)?\d*(sus\d)?(/[A-Ha-h][#b]?)?$")
# slovenská/nemecká notácia: cis = C#m (lowercase = mol), Cis = C#
GERMAN_RE = re.compile(r"^([A-Ha-h])(is)\d*$")

def is_chord_text(t):
    t = t.strip()
    if not t or len(t) > 8:
        return False
    return bool(CHORD_RE.match(t)) or bool(GERMAN_RE.match(t))

def norm_chord(t):
    # Opus chord font glyph mapping (zistené z DMS 2016):
    # © = #, ¨ = b, ‹ = m(mol), Œ„Š = maj, ‡…‹ = dim, ƒ‰ = maj, „ˆˆ = add, “ = sus
    t = t.strip()
    for src, dst in (("Œ„Š", "maj"), ("‡…‹", "dim"), ("ƒ‰", "maj"), ("„ˆˆ", "add"),
                     ("©", "#"), ("¨", "b"), ("‹", "m"), ("“", "sus"), ("”", "")):
        t = t.replace(src, dst)
    # nemecká notácia: cis -> C#m (lowercase=mol), Cis -> C#
    m = GERMAN_RE.match(t)
    if m:
        root = m.group(1)
        minor = root.islower()
        t = root.upper() + "#" + ("m" if minor else "") + t[len(root) + 2:]
    return t

def spans_of_page(page):
    d = page.get_text("dict")
    out = []
    for b in d.get("blocks", []):
        for l in b.get("lines", []):
            for s in l.get("spans", []):
                if s["text"].strip() == "":
                    continue
                x0, y0, x1, y1 = s["bbox"]
                out.append({
                    "text": s["text"], "font": s["font"], "size": round(s["size"], 1),
                    "flags": s["flags"], "x": x0, "y": (y0 + y1) / 2, "y0": y0, "y1": y1,
                })
    return out

def group_lines(spans, tol=3.0):
    """Zoskup spany do riadkov podľa y (tolerancia), v riadku zoraď podľa x."""
    lines = []
    for s in sorted(spans, key=lambda s: (s["y"], s["x"])):
        placed = False
        for ln in lines:
            if abs(ln["y"] - s["y"]) <= tol:
                ln["spans"].append(s)
                ln["y"] = (ln["y"] * ln["n"] + s["y"]) / (ln["n"] + 1)
                ln["n"] += 1
                placed = True
                break
        if not placed:
            lines.append({"y": s["y"], "n": 1, "spans": [s]})
    for ln in lines:
        ln["spans"].sort(key=lambda s: s["x"])
    lines.sort(key=lambda ln: ln["y"])
    return lines

# ======================================================================
# SCORE MODE (notový spevník — OpusChordsStd)
# ======================================================================

def extract_score_mode(doc):
    songs = []
    cur = None
    for page in doc:
        spans = spans_of_page(page)
        # klasifikácia
        titles   = [s for s in spans if s["size"] >= 15 and "Times" in s["font"]]
        cat_hdr  = [s for s in spans if "MyriadPro" in s["font"]]
        chords   = [s for s in spans if "Chords" in s["font"]]
        sylls    = [s for s in spans if "Times" in s["font"] and 9.0 <= s["size"] <= 12.5
                    and "Italic" not in s["font"] and s["size"] < 15]
        author_c = [s for s in spans if "Times" in s["font"] and 9.0 <= s["size"] <= 11.5]

        if titles:
            # nová pieseň
            if cur:
                songs.append(cur)
            title = " ".join(t["text"].strip() for t in sorted(titles, key=lambda s: (s["y"], s["x"])))
            # autor: prvý Times riadok tesne pod titulom, nie slabiky
            ty = max(t["y"] for t in titles)
            auth = [s for s in author_c if ty < s["y"] < ty + 25 and not is_chord_text(s["text"])
                    and len(s["text"].strip()) > 3]
            author = auth[0]["text"].strip() if auth else ""
            cat = ""
            if cat_hdr:
                m = re.search(r"–\s*(.+)$", cat_hdr[0]["text"])
                if m:
                    cat = m.group(1).strip()
            cur = {"title": title, "author": author, "category_hint": cat, "lines": []}
        if cur is None:
            continue  # strany pred prvou piesňou (obsah, tiráž)

        # rekonštrukcia: slabikové riadky + akordy nad nimi
        syl_lines = group_lines([s for s in sylls if s["size"] < 15], tol=3.5)
        chord_sp = sorted(chords, key=lambda s: (s["y"], s["x"]))
        prev_bottom = 0.0
        for ln in syl_lines:
            top = min(s["y0"] for s in ln["spans"])
            # akordy medzi predchádzajúcim riadkom a týmto
            band = [c for c in chord_sp if prev_bottom <= c["y"] < top + 2]
            prev_bottom = max(s["y1"] for s in ln["spans"])
            text = build_marked_line(ln["spans"], band)
            if text.strip():
                cur["lines"].append(text)
    if cur:
        songs.append(cur)
    return songs

def build_marked_line(sylls, chords):
    """Spoj slabiky ('-' = spájací rozdeľovník) a vlož akordy pred najbližšiu slabiku (podľa x)."""
    chords = sorted(chords, key=lambda c: c["x"])
    out = []
    ci = 0
    pending_join = False
    for s in sylls:
        t = s["text"].strip()
        if t == "-":
            pending_join = True
            continue
        # akordy ktorých x <= koniec tejto slabiky patria pred ňu
        pre = ""
        while ci < len(chords) and chords[ci]["x"] <= s["x"] + 4:
            pre += CHORD_MARK + norm_chord(chords[ci]["text"]) + CHORD_MARK
            ci += 1
        sep = "" if pending_join else (" " if out else "")
        # sekčné značky na začiatku ('1.', 'R.') -> vlastný marker
        if re.match(r"^(\d+\.|R\.|B\.)$", t) and not out:
            t = t.replace("R.", "R:").replace("B.", "B:")
            out.append(pre + t)
            pending_join = False
            continue
        out.append(sep + pre + t)
        pending_join = False
    # zvyšné akordy na koniec
    for c in chords[ci:]:
        out.append(" " + CHORD_MARK + norm_chord(c["text"]) + CHORD_MARK)
    return "".join(out)

# ======================================================================
# TEXT MODE (textový spevník — bold inline akordy)
# ======================================================================

def extract_text_mode(doc):
    """Textový spevník (Beluša štýl): akordy = bold spany s chord tokenom, môžu mať
    posunuté y (vlastný "riadok"). Priraďujú sa k najbližšiemu textovému riadku podľa y
    a vkladajú podľa x pred najbližší span."""
    from collections import Counter
    font_chars = Counter()
    for page in doc:
        for s in spans_of_page(page):
            font_chars[(s["font"], s["size"], s["flags"])] += len(s["text"])
    if not font_chars:
        return []
    lyric_key = font_chars.most_common(1)[0][0]
    lyric_size = lyric_key[1]

    songs = []
    cur = None
    for page in doc:
        spans = spans_of_page(page)
        # 2-stĺpcový layout: rozdeľ podľa stredu strany, spracuj stĺpce zľava doprava
        mid = page.rect.width / 2
        left  = [s for s in spans if s["x"] < mid - 5]
        right = [s for s in spans if s["x"] >= mid - 5]
        columns = [c for c in (left, right) if c]
        for colspans in columns:
            cur = _text_mode_column(colspans, lyric_key, lyric_size, songs, cur)
    if cur:
        songs.append(cur)
    return songs

def _text_mode_column(spans, lyric_key, lyric_size, songs, cur):
        # chord span = chord token v INOM štýle než lyric font
        chord_sp, other = [], []
        for s in spans:
            key = (s["font"], s["size"], s["flags"])
            if key != lyric_key and is_chord_text(s["text"]) and abs(s["size"] - lyric_size) < 3:
                chord_sp.append(s)
            else:
                other.append(s)
        lines = group_lines(other, tol=3.0)
        # priraď akordy k najbližšiemu riadku podľa y (max 12px)
        for c in chord_sp:
            best, bd = None, 1e9
            for ln in lines:
                d = abs(ln["y"] - c["y"])
                if d < bd:
                    bd, best = d, ln
            if best is not None and bd <= 12:
                best.setdefault("chords", []).append(c)
        for ln in lines:
            sp = ln["spans"]
            joined = "".join(s["text"] for s in sp).strip()
            if not joined:
                continue
            allbold = all((s["flags"] & 16) and not (s["flags"] & 2) for s in sp)
            # titul = číslovaný riadok "49. Názov" ALEBO celý bold riadok (nie akordy/číslo strany)
            numbered = re.match(r"^\d{1,3}\.\s+\S", joined) and CHORD_MARK not in joined \
                       and not ln.get("chords")
            boldtitle = allbold and not ln.get("chords") and len(joined) > 3 \
                        and not re.match(r"^\d+\.?$", joined) and not is_chord_text(joined) \
                        and "/:" not in joined and ":/" not in joined
            if numbered or boldtitle:
                if cur:
                    songs.append(cur)
                title = re.sub(r"^\d+\.\s*", "", joined).strip()
                cur = {"title": title, "author": "", "category_hint": "", "lines": []}
                continue
            if cur is None:
                continue  # pred prvým titulom (obálka, obsah)
            if re.match(r"^\d+$", joined):
                continue  # číslo strany
            # vlož akordy podľa x pred najbližší span
            chords = sorted(ln.get("chords", []), key=lambda c: c["x"])
            parts = []
            ci = 0
            for s in sp:
                while ci < len(chords) and chords[ci]["x"] <= s["x"] + 1:
                    parts.append(CHORD_MARK + norm_chord(chords[ci]["text"]) + CHORD_MARK)
                    ci += 1
                parts.append(s["text"])
            for c in chords[ci:]:
                parts.append(CHORD_MARK + norm_chord(c["text"]) + CHORD_MARK)
            cur["lines"].append("".join(parts).rstrip())
        return cur

# ======================================================================

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "chýba cesta k PDF"}))
        return
    path = sys.argv[1]
    try:
        doc = fitz.open(path)
    except Exception as e:
        print(json.dumps({"ok": False, "error": f"PDF sa nedá otvoriť: {e}"}))
        return

    # auto-detekcia módu: notový font?
    has_chordfont = False
    for pno in range(min(len(doc), 30)):
        if any("Chords" in s["font"] for s in spans_of_page(doc[pno])):
            has_chordfont = True
            break

    if has_chordfont:
        mode = "score"
        songs = extract_score_mode(doc)
    else:
        mode = "text"
        songs = extract_text_mode(doc)

    out = []
    for s in songs:
        lines = s["lines"]
        # zahoď vedúce metadata riadky (autor, "úprava X", "= 110", rok, súbor)
        # = krátke riadky bez akordov a bez sekčného markera na začiatku tela
        while lines:
            l0 = lines[0].strip()
            if l0 == "" or (CHORD_MARK not in l0 and len(l0) < 45
                            and not re.match(r"^(\d+\.|R:|B:|/:)", l0)):
                lines.pop(0)
            else:
                break
        body = "\n".join(lines).strip()
        if not body:
            continue
        out.append({
            "title": s["title"].strip(),
            "author": s.get("author", "").strip(),
            "category_hint": s.get("category_hint", "").strip(),
            "body": body,
        })
    sys.stdout.buffer.write(json.dumps(
        {"ok": True, "mode": mode, "songs": out}, ensure_ascii=False
    ).encode("utf-8"))

if __name__ == "__main__":
    main()
