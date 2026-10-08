import os
import struct
import zlib
from html import escape
def _png_chunk(chunk_type, data):
    return (
        struct.pack('>I', len(data))
        + chunk_type
        + data
        + struct.pack('>I', zlib.crc32(chunk_type + data) & 0xffffffff)
    )
def _set_pixel(pixels, width, height, x, y, color):
    if 0 <= x < width and 0 <= y < height:
        pixels[y][x] = color
def _draw_line(pixels, width, height, x1, y1, x2, y2, color):
    dx = abs(x2 - x1)
    dy = -abs(y2 - y1)
    sx = 1 if x1 < x2 else -1
    sy = 1 if y1 < y2 else -1
    err = dx + dy
    while True:
        _set_pixel(pixels, width, height, x1, y1, color)
        if x1 == x2 and y1 == y2:
            break
        err2 = 2 * err
        if err2 >= dy:
            err += dy
            x1 += sx
        if err2 <= dx:
            err += dx
            y1 += sy
def _draw_circle(pixels, width, height, cx, cy, radius, color):
    for y in range(cy - radius, cy + radius + 1):
        for x in range(cx - radius, cx + radius + 1):
            if (x - cx) ** 2 + (y - cy) ** 2 <= radius ** 2:
                _set_pixel(pixels, width, height, x, y, color)
def _write_png(path, pixels, width, height):
    raw = b''.join(
        b'\x00' + b''.join(bytes(pixel) for pixel in row)
        for row in pixels
    )
    png = (
        b'\x89PNG\r\n\x1a\n'
        + _png_chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 2, 0, 0, 0))
        + _png_chunk(b'IDAT', zlib.compress(raw, 9))
        + _png_chunk(b'IEND', b'')
    )
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'wb') as file:
        file.write(png)
def _nice_axis(min_value, max_value):
    padding = (max_value - min_value) * 0.12
    if padding == 0:
        padding = 1
    y_min = max(0, min_value - padding)
    y_max = max_value + padding
    return y_min, y_max
def _wrap_words(text, max_chars):
    lines = []
    current = ''
    for word in text.split():
        next_line = word if current == '' else current + ' ' + word
        if len(next_line) <= max_chars:
            current = next_line
        else:
            if current:
                lines.append(current)
            current = word
    if current:
        lines.append(current)
    return lines
def save_elbow_chart_svg(k_values, inertia_values, path, best_k=None):
    width = 1536
    height = 1024
    chart_left = 130
    chart_right = 1518
    chart_top = 68
    chart_bottom = 760
    note_y = 875
    note_width = 780
    note_x = int((width - note_width) / 2)
    if not k_values or not inertia_values:
        k_values = [2]
        inertia_values = [0]
    if best_k is None:
        best_k = k_values[-1]
    min_k = min(k_values)
    max_k = max(k_values)
    min_inertia = min(inertia_values)
    max_inertia = max(inertia_values)
    y_min, y_max = _nice_axis(min_inertia, max_inertia)
    if max_k == min_k:
        max_k += 1
    if y_max == y_min:
        y_max += 1
    def scale_x(k):
        return chart_left + ((k - min_k) / (max_k - min_k)) * (chart_right - chart_left)
    def scale_y(value):
        return chart_bottom - ((value - y_min) / (y_max - y_min)) * (chart_bottom - chart_top)
    points = [
        (scale_x(k), scale_y(value), k, value)
        for k, value in zip(k_values, inertia_values)
    ]
    line_points = ' '.join(
        f'{x:.2f},{y:.2f}'
        for x, y, _, _ in points
    )
    grid_lines = []
    y_labels = []
    x_labels = []
    for index in range(6):
        y_value = y_min + ((y_max - y_min) / 5) * index
        y = scale_y(y_value)
        grid_lines.append(
            f'<line x1="{chart_left}" y1="{y:.2f}" x2="{chart_right}" y2="{y:.2f}" class="grid" />'
        )
        y_labels.append(
            f'<text x="{chart_left - 32}" y="{y + 8:.2f}" class="tick" text-anchor="end">{y_value:.0f}</text>'
        )
    for k in k_values:
        x = scale_x(k)
        grid_lines.append(
            f'<line x1="{x:.2f}" y1="{chart_top}" x2="{x:.2f}" y2="{chart_bottom}" class="grid" />'
        )
        x_labels.append(
            f'<text x="{x:.2f}" y="{chart_bottom + 43}" class="tick" text-anchor="middle">{k}</text>'
        )
    point_elements = []
    for x, y, _, value in points:
        text_x = min(max(x, chart_left + 20), chart_right - 60)
        point_elements.append(
            f'<circle cx="{x:.2f}" cy="{y:.2f}" r="10" class="point" />'
        )
        point_elements.append(
            f'<text x="{text_x:.2f}" y="{y - 30:.2f}" class="value" text-anchor="middle">{value:.6f}</text>'
        )
    best_x = scale_x(best_k)
    label_width = 220
    label_height = 46
    label_gap = 24
    if best_x - label_width - label_gap < chart_left:
        label_x = min(
            best_x + label_gap,
            chart_right - label_width
        )
        arrow_x1 = label_x
        arrow_x2 = best_x + 8
    else:
        label_x = max(
            chart_left,
            min(best_x - label_width - label_gap, chart_right - label_width)
        )
        arrow_x1 = label_x + label_width
        arrow_x2 = best_x - 8
    label_y = chart_top + 35
    note = (
        f'Terjadi penurunan inertia yang mulai melandai setelah k = {best_k} (titik elbow).'
        f' Sehingga jumlah cluster terbaik berdasarkan Elbow Method adalah {best_k}.'
    )
    note_lines = _wrap_words(note, 62)
    note_height = 24 + (len(note_lines) * 28)
    note_text = ''.join(
        f'<text x="{note_x + 24}" y="{note_y + 30 + (index * 28)}" class="note">{escape(line)}</text>'
        for index, line in enumerate(note_lines)
    )
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}">
<style>
    .title {{ font-family: Arial, sans-serif; font-size: 40px; fill: #000; }}
    .axis-label {{ font-family: Arial, sans-serif; font-size: 28px; fill: #000; }}
    .tick {{ font-family: Arial, sans-serif; font-size: 28px; fill: #000; }}
    .value {{ font-family: Arial, sans-serif; font-size: 22px; fill: #1f2f9b; }}
    .grid {{ stroke: #bdbdbd; stroke-width: 1; stroke-dasharray: 6 4; }}
    .axis {{ stroke: #000; stroke-width: 2; }}
    .line {{ fill: none; stroke: #1f2f9b; stroke-width: 4; }}
    .point {{ fill: #1f2f9b; stroke: #1f2f9b; }}
    .best-line {{ stroke: #ff1f1f; stroke-width: 4; stroke-dasharray: 12 6; }}
    .best-text {{ font-family: Arial, sans-serif; font-size: 28px; fill: #ff1f1f; }}
    .note {{ font-family: Arial, sans-serif; font-size: 24px; fill: #000; }}
</style>
<rect width="100%" height="100%" fill="#fff" />
<text x="{width / 2}" y="52" class="title" text-anchor="middle">Elbow Method </text>
{''.join(grid_lines)}
<line x1="{chart_left}" y1="{chart_top}" x2="{chart_left}" y2="{chart_bottom}" class="axis" />
<line x1="{chart_left}" y1="{chart_bottom}" x2="{chart_right}" y2="{chart_bottom}" class="axis" />
{''.join(y_labels)}
{''.join(x_labels)}
<text x="{width / 2}" y="{chart_bottom + 73}" class="axis-label" text-anchor="middle">Jumlah Cluster (K)</text>
<text x="42" y="{height / 2}" class="axis-label" text-anchor="middle" transform="rotate(-90 42 {height / 2})">Inertia </text>
<polyline points="{line_points}" class="line" />
{''.join(point_elements)}
<line x1="{best_x:.2f}" y1="{chart_top}" x2="{best_x:.2f}" y2="{chart_bottom}" class="best-line" />
<rect x="{label_x:.2f}" y="{label_y}" width="{label_width}" height="{label_height}" rx="6" fill="#fff" stroke="#ff1f1f" stroke-width="2" />
<text x="{label_x + 18:.2f}" y="{label_y + 32}" class="best-text">K Terbaik = {best_k}</text>
<line x1="{arrow_x1:.2f}" y1="{label_y + 23}" x2="{arrow_x2:.2f}" y2="{label_y + 23}" stroke="#ff1f1f" stroke-width="3" marker-end="url(#arrow)" />
<defs>
    <marker id="arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse">
        <path d="M 0 0 L 10 5 L 0 10 z" fill="#ff1f1f" />
    </marker>
</defs>
<rect x="{note_x}" y="{note_y}" width="{note_width}" height="{note_height}" rx="5" fill="#fff" stroke="#1f2f9b" stroke-width="3" />
{note_text}
</svg>'''
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, 'w', encoding='utf-8') as file:
        file.write(svg)
def save_elbow_chart(k_values, inertia_values, path, best_k=None):
    if path.lower().endswith('.svg'):
        save_elbow_chart_svg(k_values, inertia_values, path, best_k)
        return
    width = 800
    height = 500
    margin_left = 80
    margin_right = 40
    margin_top = 50
    margin_bottom = 70
    white = (255, 255, 255)
    grid = (226, 232, 240)
    axis = (51, 65, 85)
    line = (37, 99, 235)
    point = (239, 68, 68)
    pixels = [[white for _ in range(width)] for _ in range(height)]
    chart_left = margin_left
    chart_right = width - margin_right
    chart_top = margin_top
    chart_bottom = height - margin_bottom
    for i in range(6):
        y = chart_top + int((chart_bottom - chart_top) * i / 5)
        _draw_line(pixels, width, height, chart_left, y, chart_right, y, grid)
    _draw_line(pixels, width, height, chart_left, chart_top, chart_left, chart_bottom, axis)
    _draw_line(pixels, width, height, chart_left, chart_bottom, chart_right, chart_bottom, axis)
    if not k_values or not inertia_values:
        _write_png(path, pixels, width, height)
        return
    min_k = min(k_values)
    max_k = max(k_values)
    min_inertia = min(inertia_values)
    max_inertia = max(inertia_values)
    if max_k == min_k:
        max_k += 1
    if max_inertia == min_inertia:
        max_inertia += 1
    points = []
    for k, inertia in zip(k_values, inertia_values):
        x = chart_left + int((k - min_k) / (max_k - min_k) * (chart_right - chart_left))
        y = chart_bottom - int(
            (inertia - min_inertia) / (max_inertia - min_inertia)
            * (chart_bottom - chart_top)
        )
        points.append((x, y))
    for index in range(1, len(points)):
        _draw_line(
            pixels,
            width,
            height,
            points[index - 1][0],
            points[index - 1][1],
            points[index][0],
            points[index][1],
            line
        )
    for x, y in points:
        _draw_circle(pixels, width, height, x, y, 5, point)
    _write_png(path, pixels, width, height)
