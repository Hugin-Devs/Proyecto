import os
import re

ROOT_DIR = '/home/aegir/Documentos/GitHub/Proyecto'

for root, dirs, files in os.walk(ROOT_DIR):
    for file in files:
        if file.endswith('.php'):
            filepath = os.path.join(root, file)
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # Replace __DIR__ . 'something' with __DIR__ . '/something'
            # Be careful not to replace __DIR__ . '/something' to __DIR__ . '//something'
            # Look for __DIR__ . ' followed by anything except /
            new_content = re.sub(r'__DIR__\s*\.\s*([\'"])(?!/)', r'__DIR__ . \g<1>/', content)
            
            if content != new_content:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print(f"Fixed {filepath}")
