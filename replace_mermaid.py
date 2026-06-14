import sys

with open("analisis_export.html", "r", encoding="utf-8") as f:
    content = f.read()

old_str = """    I --> I4[👤 Tab: Mi Perfil]

    I1 --> I1a["Tarjeta con métricas propias\\n(total, pendientes, valoración media)"]
    I3 --> I3a["Vista Maestro: tarjetas por servicio"]
    I3a --> I3b["Vista Detalle: contrataciones del servicio\\n(acepar/rechazar/completar + ver valoración)"]

    J --> J1[Gestión de Usuarios]"""

new_str = """    I --> I4[👤 Tab: Mi Perfil]
    I --> I5["🔍 Modo Cliente → index.php"]

    I1 --> I1a["Tarjeta con métricas propias\\n(total, pendientes, valoración media)"]
    I3 --> I3a["Vista Maestro: tarjetas por servicio"]
    I3a --> I3b["Vista Detalle: contrataciones del servicio\\n(acepar/rechazar/completar + ver valoración)"]
    I5 --> I5a["index.php con botón Volver a Modo Proveedor"]
    I5a --> I5b["Servicios propios: botones contactar/contratar ocultos"]

    J --> J1[Gestión de Usuarios]"""

# Since the file has some garbled characters (), let's find the exact block using regex
import re
pattern = re.compile(r"I --> I4\[.*?\].*?I1 --> I1a\[.*?\].*?I3 --> I3a\[.*?\].*?I3a --> I3b\[.*?\].*?J --> J1\[.*?\]", re.DOTALL)
match = pattern.search(content)
if match:
    # Need to keep the exact emoji chars from the original string
    original = match.group(0)
    lines = original.split('\n')
    
    # We construct the new lines by inserting I5 after I4, and I5 nodes after I3b
    new_lines = []
    for line in lines:
        new_lines.append(line)
        if "I --> I4[" in line:
            new_lines.append('    I --> I5["🔍 Modo Cliente → index.php"]')
        elif "I3a --> I3b[" in line:
            new_lines.append('    I5 --> I5a["index.php con botón Volver a Modo Proveedor"]')
            new_lines.append('    I5a --> I5b["Servicios propios: botones contactar/contratar ocultos"]')
    
    content = content.replace(original, '\n'.join(new_lines))
    
    with open("analisis_export.html", "w", encoding="utf-8") as f:
        f.write(content)
    print("Replaced successfully")
else:
    print("Pattern not found")
