"""Membuat dokumen Word materi rumus K-Means dari sumber Markdown."""
import re
from datetime import datetime, timezone
from pathlib import Path
from xml.etree import ElementTree
from xml.sax.saxutils import escape
from zipfile import ZIP_DEFLATED, ZipFile
ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "docs" / "materi_rumus_kmeans_sistem.md"
OUTPUT = ROOT / "Materi_Rumus_KMeans_Sistem.docx"
def run(text, *, bold=False, italic=False, font="Calibri", size=22, color=None):
    properties = [
        f'<w:rFonts w:ascii="{font}" w:hAnsi="{font}"/>',
        f'<w:sz w:val="{size}"/>',
        f'<w:szCs w:val="{size}"/>',
    ]
    if bold:
        properties.append("<w:b/>")
    if italic:
        properties.append("<w:i/>")
    if color:
        properties.append(f'<w:color w:val="{color}"/>')
    safe_text = escape(str(text)).replace(
        "\n",
        '</w:t><w:br/><w:t xml:space="preserve">',
    )
    return (
        f'<w:r><w:rPr>{"".join(properties)}</w:rPr>'
        f'<w:t xml:space="preserve">{safe_text}</w:t></w:r>'
    )
def extract_group(text, start):
    """Mengambil isi {...} mulai dari posisi kurung buka."""
    if start >= len(text) or text[start] != "{":
        return None, start
    depth = 0
    for index in range(start, len(text)):
        if text[index] == "{":
            depth += 1
        elif text[index] == "}":
            depth -= 1
            if depth == 0:
                return text[start + 1:index], index + 1
    return None, start
def replace_group_command(text, command, formatter):
    """Mengganti perintah LaTeX satu argumen seperti sqrt{...}."""
    marker = f"\\{command}"
    cursor = 0
    output = []
    while True:
        position = text.find(marker, cursor)
        if position < 0:
            output.append(text[cursor:])
            break
        output.append(text[cursor:position])
        group_start = position + len(marker)
        if group_start >= len(text) or text[group_start] != "{":
            output.append(marker)
            cursor = group_start
            continue
        content, end = extract_group(text, group_start)
        if content is None:
            output.append(marker)
            cursor = group_start
            continue
        output.append(formatter(latex_to_text(content)))
        cursor = end
    return "".join(output)
def replace_fractions(text):
    """Mengubah \frac{a}{b} menjadi (a)/(b), termasuk isi bersarang."""
    marker = "\\frac"
    cursor = 0
    output = []
    while True:
        position = text.find(marker, cursor)
        if position < 0:
            output.append(text[cursor:])
            break
        output.append(text[cursor:position])
        numerator_start = position + len(marker)
        numerator, after_numerator = extract_group(text, numerator_start)
        if numerator is None:
            output.append(marker)
            cursor = numerator_start
            continue
        denominator, after_denominator = extract_group(text, after_numerator)
        if denominator is None:
            output.append(marker)
            cursor = numerator_start
            continue
        output.append(
            f"({latex_to_text(numerator)})/({latex_to_text(denominator)})"
        )
        cursor = after_denominator
    return "".join(output)
def latex_to_text(value):
    """Mengubah notasi LaTeX menjadi notasi linear yang terbaca di Word."""
    text = value.strip()
    text = text.replace("\\left", "").replace("\\right", "")
    text = replace_fractions(text)
    text = replace_group_command(text, "sqrt", lambda item: f"√({item})")
    text = replace_group_command(text, "text", lambda item: item)
    text = replace_group_command(text, "bar", lambda item: f"rata-rata({item})")
    text = replace_group_command(
        text,
        "overline",
        lambda item: f"rata-rata({item})",
    )
    replacements = {
        "\\operatorname{cluster}": "cluster",
        "\\arg\\min": "arg min",
        "\\arg\\max": "arg max",
        "\\Delta": "Δ",
        "\\mu": "μ",
        "\\min": "min",
        "\\max": "max",
        "\\sum": "Σ",
        "\\lfloor": "⌊",
        "\\rfloor": "⌋",
        "\\lVert": "‖",
        "\\rVert": "‖",
        "\\in": "∈",
        "\\neq": "≠",
        "\\geq": "≥",
        "\\leq": "≤",
        "\\ldots": "…",
        "\\qquad": "    ",
        "\\approx": "≈",
        "\\times": "×",
        "\\cdot": "·",
        "\\begin{cases}": "",
        "\\end{cases}": "",
        "\\\\": "; ",
        "&": "",
    }
    for source, target in replacements.items():
        text = text.replace(source, target)
    text = re.sub(r"_\{([^{}]+)\}", r"_(\1)", text)
    text = re.sub(r"\^\{([^{}]+)\}", r"^(\1)", text)
    text = re.sub(r"_([A-Za-z0-9])", r"_\1", text)
    text = re.sub(r"\^([A-Za-z0-9+\-]+)", r"^(\1)", text)
    text = text.replace("{", "(").replace("}", ")")
    text = re.sub(r"\\([A-Za-z]+)", r"\1", text)
    text = re.sub(r"[ \t]+", " ", text)
    return text.strip()
def normalize_inline(text):
    """Membersihkan tautan Markdown dan mengubah rumus inline."""
    text = re.sub(
        r"\[([^\]]+)\]\([^)]+\)",
        lambda match: match.group(1),
        text,
    )
    text = re.sub(
        r"\\\((.*?)\\\)",
        lambda match: latex_to_text(match.group(1)),
        text,
    )
    return text
def rich_runs(text, default_size=22, default_color=None):
    """Membuat run Word dari bold, italic, dan inline-code Markdown."""
    text = normalize_inline(text)
    pattern = re.compile(r"(`[^`]+`|\*\*[^*]+\*\*|\*[^*]+\*)")
    parts = pattern.split(text)
    output = []
    for part in parts:
        if not part:
            continue
        if part.startswith("`") and part.endswith("`"):
            output.append(
                run(
                    part[1:-1],
                    font="Consolas",
                    size=max(default_size - 2, 18),
                    color="7A3E00",
                )
            )
        elif part.startswith("**") and part.endswith("**"):
            output.append(
                run(
                    part[2:-2],
                    bold=True,
                    size=default_size,
                    color=default_color,
                )
            )
        elif part.startswith("*") and part.endswith("*"):
            output.append(
                run(
                    part[1:-1],
                    italic=True,
                    size=default_size,
                    color=default_color,
                )
            )
        else:
            output.append(
                run(part, size=default_size, color=default_color)
            )
    return "".join(output)
def paragraph(
    text="",
    *,
    style=None,
    bold=False,
    italic=False,
    size=22,
    color=None,
    before=0,
    after=120,
    align=None,
    left=0,
    hanging=0,
    shade=None,
    border=None,
    font="Calibri",
):
    properties = [
        (
            f'<w:spacing w:before="{before}" w:after="{after}" '
            'w:line="276" w:lineRule="auto"/>'
        )
    ]
    if style:
        properties.append(f'<w:pStyle w:val="{style}"/>')
    if align:
        properties.append(f'<w:jc w:val="{align}"/>')
    if left or hanging:
        properties.append(
            f'<w:ind w:left="{left}" w:hanging="{hanging}"/>'
        )
    if shade:
        properties.append(f'<w:shd w:fill="{shade}"/>')
    if border:
        properties.append(
            '<w:pBdr>'
            f'<w:left w:val="single" w:sz="18" w:space="8" '
            f'w:color="{border}"/>'
            '</w:pBdr>'
        )
    content = run(
        text,
        bold=bold,
        italic=italic,
        font=font,
        size=size,
        color=color,
    )
    return (
        f'<w:p><w:pPr>{"".join(properties)}</w:pPr>'
        f"{content}</w:p>"
    )
def rich_paragraph(text, **kwargs):
    size = kwargs.pop("size", 22)
    color = kwargs.pop("color", None)
    properties = [
        (
            f'<w:spacing w:before="{kwargs.pop("before", 0)}" '
            f'w:after="{kwargs.pop("after", 120)}" '
            'w:line="276" w:lineRule="auto"/>'
        )
    ]
    style = kwargs.pop("style", None)
    align = kwargs.pop("align", None)
    left = kwargs.pop("left", 0)
    hanging = kwargs.pop("hanging", 0)
    shade = kwargs.pop("shade", None)
    border = kwargs.pop("border", None)
    if style:
        properties.append(f'<w:pStyle w:val="{style}"/>')
    if align:
        properties.append(f'<w:jc w:val="{align}"/>')
    if left or hanging:
        properties.append(
            f'<w:ind w:left="{left}" w:hanging="{hanging}"/>'
        )
    if shade:
        properties.append(f'<w:shd w:fill="{shade}"/>')
    if border:
        properties.append(
            '<w:pBdr>'
            f'<w:left w:val="single" w:sz="18" w:space="8" '
            f'w:color="{border}"/>'
            '</w:pBdr>'
        )
    return (
        f'<w:p><w:pPr>{"".join(properties)}</w:pPr>'
        f"{rich_runs(text, size, color)}</w:p>"
    )
def page_break():
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'
def code_block(lines):
    text = "\n".join(lines)
    return paragraph(
        text,
        font="Consolas",
        size=18,
        color="1F2937",
        before=80,
        after=160,
        left=240,
        shade="F3F4F6",
        border="9CA3AF",
    )
def equation_block(lines):
    formula = latex_to_text(" ".join(line.strip() for line in lines))
    return paragraph(
        formula,
        font="Cambria Math",
        size=23,
        color="1F4E78",
        before=80,
        after=160,
        align="center",
        shade="EEF5FC",
    )
def table_xml(rows):
    column_count = max(len(row) for row in rows)
    page_width = 9360
    column_width = max(int(page_width / column_count), 900)
    output = [
        '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/>'
        '<w:tblW w:w="0" w:type="auto"/>'
        '<w:tblLook w:val="04A0"/></w:tblPr>'
    ]
    for row_index, row in enumerate(rows):
        output.append("<w:tr>")
        padded = row + [""] * (column_count - len(row))
        for value in padded:
            shade = (
                '<w:shd w:fill="D9EAF7"/>'
                if row_index == 0
                else ""
            )
            output.append(
                '<w:tc><w:tcPr>'
                f'<w:tcW w:w="{column_width}" w:type="dxa"/>'
                f"{shade}<w:vAlign w:val=\"center\"/>"
                "</w:tcPr>"
            )
            output.append(
                rich_paragraph(
                    value,
                    size=19,
                    color="1F2937",
                    after=50,
                )
            )
            output.append("</w:tc>")
        output.append("</w:tr>")
    output.append("</w:tbl>")
    output.append(paragraph("", after=80))
    return "".join(output)
def split_markdown_table_row(line):
    """Memisahkan sel tabel tanpa memotong tanda | di dalam rumus."""
    content = line.strip().strip("|")
    cells = []
    current = []
    in_math = False
    in_code = False
    index = 0
    while index < len(content):
        pair = content[index:index + 2]
        if pair == "\\(" and not in_code:
            in_math = True
            current.append(pair)
            index += 2
            continue
        if pair == "\\)" and not in_code:
            in_math = False
            current.append(pair)
            index += 2
            continue
        if content[index] == "`":
            in_code = not in_code
            current.append(content[index])
            index += 1
            continue
        if content[index] == "|" and not in_math and not in_code:
            cells.append("".join(current).strip())
            current = []
            index += 1
            continue
        current.append(content[index])
        index += 1
    cells.append("".join(current).strip())
    return cells
def parse_markdown(markdown):
    lines = markdown.splitlines()
    body = []
    index = 0
    paragraph_lines = []
    def flush_paragraph():
        if paragraph_lines:
            body.append(
                rich_paragraph(
                    " ".join(item.strip() for item in paragraph_lines),
                    after=130,
                )
            )
            paragraph_lines.clear()
    while index < len(lines):
        line = lines[index]
        stripped = line.strip()
        if not stripped:
            flush_paragraph()
            index += 1
            continue
        if stripped == "---":
            flush_paragraph()
            index += 1
            continue
        if stripped.startswith("```"):
            flush_paragraph()
            index += 1
            code_lines = []
            while index < len(lines) and not lines[index].strip().startswith(
                "```"
            ):
                code_lines.append(lines[index])
                index += 1
            body.append(code_block(code_lines))
            index += 1
            continue
        if stripped == "\\[":
            flush_paragraph()
            index += 1
            equation_lines = []
            while index < len(lines) and lines[index].strip() != "\\]":
                equation_lines.append(lines[index])
                index += 1
            body.append(equation_block(equation_lines))
            index += 1
            continue
        if stripped.startswith("|"):
            flush_paragraph()
            table_lines = []
            while index < len(lines) and lines[index].strip().startswith("|"):
                table_lines.append(lines[index].strip())
                index += 1
            rows = []
            for table_line in table_lines:
                cells = split_markdown_table_row(table_line)
                if all(re.fullmatch(r":?-{3,}:?", cell) for cell in cells):
                    continue
                rows.append([normalize_inline(cell) for cell in cells])
            if rows:
                body.append(table_xml(rows))
            continue
        heading = re.match(r"^(#{1,4})\s+(.+)$", stripped)
        if heading:
            flush_paragraph()
            level = len(heading.group(1))
            title = normalize_inline(heading.group(2))
            if level == 1:
                index += 1
                continue
            style = {
                2: ("Heading1", 28, "1F4E78", 240, 100),
                3: ("Heading2", 24, "2F5597", 180, 80),
                4: ("Heading3", 22, "365F91", 140, 70),
            }[level]
            body.append(
                rich_paragraph(
                    title,
                    style=style[0],
                    size=style[1],
                    color=style[2],
                    before=style[3],
                    after=style[4],
                )
            )
            index += 1
            continue
        ordered = re.match(r"^(\d+)\.\s+(.+)$", stripped)
        bullet = re.match(r"^[-*]\s+(.+)$", stripped)
        if ordered or bullet:
            flush_paragraph()
            prefix = f"{ordered.group(1)}." if ordered else "•"
            content = ordered.group(2) if ordered else bullet.group(1)
            body.append(
                rich_paragraph(
                    f"{prefix} {content}",
                    left=500,
                    hanging=300,
                    after=65,
                )
            )
            index += 1
            continue
        if stripped.startswith(">"):
            flush_paragraph()
            quote_lines = []
            while index < len(lines) and lines[index].strip().startswith(">"):
                quote_lines.append(lines[index].strip().lstrip(">").strip())
                index += 1
            body.append(
                rich_paragraph(
                    " ".join(quote_lines),
                    size=21,
                    color="374151",
                    left=420,
                    border="2F75B5",
                    shade="F4F8FC",
                    after=160,
                )
            )
            continue
        paragraph_lines.append(line)
        index += 1
    flush_paragraph()
    return body
def build_document():
    markdown = SOURCE.read_text(encoding="utf-8")
    body = []
    body.append(paragraph("", after=1000))
    body.append(
        paragraph(
            "MATERI PEMBELAJARAN",
            bold=True,
            size=38,
            color="1F4E78",
            align="center",
            after=100,
        )
    )
    body.append(
        paragraph(
            "RUMUS K-MEANS PADA SISTEM",
            bold=True,
            size=36,
            color="2F5597",
            align="center",
            after=100,
        )
    )
    body.append(
        paragraph(
            "CLUSTERING NILAI SISWA",
            bold=True,
            size=36,
            color="2F5597",
            align="center",
            after=400,
        )
    )
    body.append(
        paragraph(
            "Normalisasi Min–Max • Jarak Euclidean • Centroid • "
            "Inertia • Elbow Method • Silhouette Score",
            italic=True,
            size=22,
            color="4B5563",
            align="center",
            after=1200,
        )
    )
    body.append(
        paragraph(
            "Disusun berdasarkan implementasi sistem skripsi K-Means",
            size=20,
            color="6B7280",
            align="center",
        )
    )
    body.append(page_break())
    headings = re.findall(r"^##\s+(.+)$", markdown, flags=re.MULTILINE)
    body.append(
        paragraph(
            "DAFTAR MATERI",
            bold=True,
            size=30,
            color="1F4E78",
            after=220,
        )
    )
    for heading in headings:
        body.append(
            rich_paragraph(
                normalize_inline(heading),
                left=360,
                hanging=220,
                after=70,
            )
        )
    body.append(page_break())
    body.extend(parse_markdown(markdown))
    body.append(
        '<w:sectPr>'
        '<w:pgSz w:w="11906" w:h="16838"/>'
        '<w:pgMar w:top="1134" w:right="1134" '
        'w:bottom="1134" w:left="1134"/>'
        '<w:cols w:space="708"/>'
        '<w:docGrid w:linePitch="360"/>'
        '</w:sectPr>'
    )
    document = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document '
        'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        f'<w:body>{"".join(body)}</w:body>'
        '</w:document>'
    )
    content_types = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        '<Default Extension="rels" '
        'ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        '<Default Extension="xml" ContentType="application/xml"/>'
        '<Override PartName="/word/document.xml" '
        'ContentType="application/vnd.openxmlformats-officedocument.'
        'wordprocessingml.document.main+xml"/>'
        '<Override PartName="/word/styles.xml" '
        'ContentType="application/vnd.openxmlformats-officedocument.'
        'wordprocessingml.styles+xml"/>'
        '<Override PartName="/docProps/core.xml" '
        'ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        '<Override PartName="/docProps/app.xml" '
        'ContentType="application/vnd.openxmlformats-officedocument.'
        'extended-properties+xml"/>'
        '</Types>'
    )
    rels = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships '
        'xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        '<Relationship Id="rId1" '
        'Type="http://schemas.openxmlformats.org/officeDocument/2006/'
        'relationships/officeDocument" Target="word/document.xml"/>'
        '<Relationship Id="rId2" '
        'Type="http://schemas.openxmlformats.org/package/2006/'
        'relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        '<Relationship Id="rId3" '
        'Type="http://schemas.openxmlformats.org/officeDocument/2006/'
        'relationships/extended-properties" Target="docProps/app.xml"/>'
        '</Relationships>'
    )
    document_rels = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships '
        'xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
    )
    styles = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:styles '
        'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        '<w:docDefaults><w:rPrDefault><w:rPr>'
        '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>'
        '<w:sz w:val="22"/><w:szCs w:val="22"/>'
        '</w:rPr></w:rPrDefault></w:docDefaults>'
        '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
        '<w:name w:val="Normal"/></w:style>'
        '<w:style w:type="paragraph" w:styleId="Heading1">'
        '<w:name w:val="heading 1"/><w:qFormat/><w:keepNext/></w:style>'
        '<w:style w:type="paragraph" w:styleId="Heading2">'
        '<w:name w:val="heading 2"/><w:qFormat/><w:keepNext/></w:style>'
        '<w:style w:type="paragraph" w:styleId="Heading3">'
        '<w:name w:val="heading 3"/><w:qFormat/><w:keepNext/></w:style>'
        '<w:style w:type="table" w:styleId="TableGrid">'
        '<w:name w:val="Table Grid"/>'
        '<w:tblPr><w:tblBorders>'
        '<w:top w:val="single" w:sz="4" w:color="9FBAD0"/>'
        '<w:left w:val="single" w:sz="4" w:color="9FBAD0"/>'
        '<w:bottom w:val="single" w:sz="4" w:color="9FBAD0"/>'
        '<w:right w:val="single" w:sz="4" w:color="9FBAD0"/>'
        '<w:insideH w:val="single" w:sz="4" w:color="C9D8E4"/>'
        '<w:insideV w:val="single" w:sz="4" w:color="C9D8E4"/>'
        '</w:tblBorders></w:tblPr></w:style>'
        '</w:styles>'
    )
    now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    core = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<cp:coreProperties '
        'xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/'
        'core-properties" '
        'xmlns:dc="http://purl.org/dc/elements/1.1/" '
        'xmlns:dcterms="http://purl.org/dc/terms/" '
        'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        '<dc:title>Materi Rumus K-Means pada Sistem Clustering Nilai Siswa</dc:title>'
        '<dc:creator>Codex</dc:creator>'
        '<dc:subject>Materi pembelajaran rumus K-Means</dc:subject>'
        f'<dcterms:created xsi:type="dcterms:W3CDTF">{now}</dcterms:created>'
        '</cp:coreProperties>'
    )
    app = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Properties '
        'xmlns="http://schemas.openxmlformats.org/officeDocument/2006/'
        'extended-properties">'
        '<Application>Microsoft Office Word</Application>'
        '</Properties>'
    )
    with ZipFile(OUTPUT, "w", ZIP_DEFLATED) as docx:
        docx.writestr("[Content_Types].xml", content_types)
        docx.writestr("_rels/.rels", rels)
        docx.writestr("word/document.xml", document)
        docx.writestr("word/styles.xml", styles)
        docx.writestr("word/_rels/document.xml.rels", document_rels)
        docx.writestr("docProps/core.xml", core)
        docx.writestr("docProps/app.xml", app)
def validate_document():
    required = {
        "[Content_Types].xml",
        "_rels/.rels",
        "word/document.xml",
        "word/styles.xml",
        "word/_rels/document.xml.rels",
        "docProps/core.xml",
        "docProps/app.xml",
    }
    with ZipFile(OUTPUT) as docx:
        names = set(docx.namelist())
        missing = required - names
        if missing:
            raise RuntimeError(
                "Bagian dokumen Word tidak lengkap: "
                + ", ".join(sorted(missing))
            )
        if docx.testzip() is not None:
            raise RuntimeError("Arsip DOCX rusak")
        for name in required:
            if name.endswith(".xml") or name.endswith(".rels"):
                ElementTree.fromstring(docx.read(name))
if __name__ == "__main__":
    build_document()
    validate_document()
    print(OUTPUT)
