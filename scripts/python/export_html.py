import markdown
import re

files_to_export = [
    ('docs/analisis_sistema_servijob.md', 'docs/analisis_export.html'),
    ('docs/manual_despliegue.md', 'docs/manual_despliegue_export.html'),
    ('docs/manual_escalabilidad_servijob.md', 'docs/manual_escalabilidad_export.html')
]

# Get the base HTML structure from the existing export
with open('docs/analisis_export.html', 'r', encoding='utf-8') as f:
    export_html = f.read()

# Asegurar que siempre se usen las rutas locales correctas (docs/ -> ../public/libs/)
export_html = export_html.replace(
    '<script src="libs/mermaid.min.js"></script>',
    '<script src="../public/libs/mermaid.min.js"></script>'
)
export_html = export_html.replace(
    '<script src="libs/panzoom.min.js"></script>',
    '<script src="../public/libs/panzoom.min.js"></script>'
)
# Por si el archivo ya tuviera un CDN previo, también normalizarlo
export_html = export_html.replace(
    '<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>',
    '<script src="../public/libs/mermaid.min.js"></script>'
)
export_html = export_html.replace(
    '<script src="https://cdn.jsdelivr.net/npm/panzoom@9/dist/panzoom.min.js"></script>',
    '<script src="../public/libs/panzoom.min.js"></script>'
)

head = export_html[:export_html.find('<body>')+6]
tail = export_html[export_html.find('<script>'):]

for md_file, html_file in files_to_export:
    try:
        with open(md_file, 'r', encoding='utf-8') as f:
            md_text = f.read()
    except FileNotFoundError:
        print(f"Skipping {md_file} - file not found.")
        continue

    # Pre-process some markdown
    # Convert mermaid to div
    md_text = re.sub(r'```mermaid\n(.*?)\n```', r'<div class="mermaid">\n\1\n</div>', md_text, flags=re.DOTALL)

    # Convert Github alerts to styled divs
    md_text = md_text.replace('> [!NOTE]', '<div class="note">')
    md_text = md_text.replace('> [!TIP]', '<div class="tip">')
    md_text = md_text.replace('> [!WARNING]', '<div class="warning">')
    md_text = md_text.replace('> [!IMPORTANT]', '<div class="caution">')
    # Close div for blockquotes that were alerts
    md_text = re.sub(r'<div class="(note|tip|warning|caution)">\n>(.*?)(?:\n\n|\Z)', r'<div class="\1">\2</div>\n\n', md_text, flags=re.DOTALL)
    # A simple fix for the closing tags of alerts
    md_text = re.sub(r'> \[!NOTE\]\n(?:> ?(.*)\n)+', lambda m: '<div class="note">' + m.group(0).replace('> [!NOTE]\n', '').replace('> ', '') + '</div>\n', md_text)
    md_text = re.sub(r'> \[!TIP\]\n(?:> ?(.*)\n)+', lambda m: '<div class="tip">' + m.group(0).replace('> [!TIP]\n', '').replace('> ', '') + '</div>\n', md_text)
    md_text = re.sub(r'> \[!WARNING\]\n(?:> ?(.*)\n)+', lambda m: '<div class="warning">' + m.group(0).replace('> [!WARNING]\n', '').replace('> ', '') + '</div>\n', md_text)
    md_text = re.sub(r'> \[!IMPORTANT\]\n(?:> ?(.*)\n)+', lambda m: '<div class="caution">' + m.group(0).replace('> [!IMPORTANT]\n', '').replace('> ', '') + '</div>\n', md_text)

    html_content = markdown.markdown(md_text, extensions=['tables', 'fenced_code'])

    new_export = head + '\n' + html_content + '\n' + tail

    # Fix some specifics that markdown parser messes up with the mermaid class
    new_export = new_export.replace('<p><div class="mermaid">', '<div class="mermaid">')
    new_export = new_export.replace('</div></p>', '</div>')

    with open(html_file, 'w', encoding='utf-8') as f:
        f.write(new_export)

print("Export updated successfully")
