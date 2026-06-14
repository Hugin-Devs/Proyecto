import os
import re

ROOT_DIR = '/home/aegir/Documentos/GitHub/Proyecto'

KNOWN_FILES = {
    'index.php': 'index.php',
    'login.php': 'login.php',
    'register.php': 'register.php',
    'admin_panel.php': 'app/pages/admin_panel.php',
    'proveedor_panel.php': 'app/pages/proveedor_panel.php',
}

def get_rel_path(from_path, to_path):
    # Both paths are relative to ROOT_DIR
    from_dir = os.path.dirname(from_path)
    if from_dir == '':
        from_dir = '.'
    if to_path.startswith('/'):
        to_path = to_path[1:]
        
    rel = os.path.relpath(to_path, from_dir)
    return rel.replace('\\', '/')

for root, dirs, files in os.walk(ROOT_DIR):
    for file in files:
        if file.endswith('.php'):
            filepath = os.path.join(root, file)
            rel_filepath = os.path.relpath(filepath, ROOT_DIR)
            
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
            
            def replacer(match):
                # match.group(1) is the prefix: "header('Location: "
                # match.group(2) is the target: "login.php?error=1"
                prefix = match.group(1)
                target = match.group(2)
                
                # if target is a variable or complex expression, skip
                if target.startswith('$') or target.startswith("' ."):
                    return match.group(0)
                
                # Extract base filename
                parts = target.split('?', 1)
                base = parts[0].strip()
                query = '?' + parts[1] if len(parts) > 1 else ''
                
                if base in KNOWN_FILES:
                    new_rel = get_rel_path(rel_filepath, KNOWN_FILES[base])
                    return f"{prefix}{new_rel}{query}"
                return match.group(0)

            # Match header('Location: ...') or header("Location: ...")
            # We must handle both single and double quotes
            # Example: header('Location: login.php?error=1');
            # Example: header("Location: {$redirect_url}{$sep}verify_ok=1");
            
            # Using regex to capture the target inside Location: ...
            new_content = re.sub(r'(header\(\s*[\'"]Location:\s*)([^\'"]+)', replacer, content)
            
            if content != new_content:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print(f"Fixed redirects in {rel_filepath}")
