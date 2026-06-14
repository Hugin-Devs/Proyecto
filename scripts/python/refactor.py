import os
import re

ROOT_DIR = '/home/aegir/Documentos/GitHub/Proyecto'

def extract_css(filepath, css_filename):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    style_pattern = re.compile(r'<style[^>]*>(.*?)</style>', re.DOTALL | re.IGNORECASE)
    match = style_pattern.search(content)
    
    if match:
        css_content = match.group(1).strip()
        css_path = os.path.join(ROOT_DIR, 'public', 'css', css_filename)
        with open(css_path, 'w', encoding='utf-8') as f:
            f.write(css_content)
        
        # Replace the style block with the link tag
        link_tag = f'<link rel="stylesheet" href="public/css/{css_filename}">'
        if 'app/pages' in filepath:
             link_tag = f'<link rel="stylesheet" href="../../public/css/{css_filename}">'
        elif 'app/' in filepath:
             link_tag = f'<link rel="stylesheet" href="../../public/css/{css_filename}">'
        # Wait, if index.php is at root, 'public/css/index.css' is correct.
        # But admin_panel.php and proveedor_panel.php are moved to app/pages/
        # However, they might be accessed directly? Actually, auth_guard and login redirects them. 
        # But wait, the browser accesses them directly? If they are in app/pages/admin_panel.php, the browser URL is /app/pages/admin_panel.php.
        # So the relative path to public would be ../../public/css/...
        # Let's adjust paths based on depth.
        
        rel_path = os.path.relpath(os.path.join(ROOT_DIR, 'public', 'css', css_filename), os.path.dirname(filepath))
        # But for root files, it's public/css/filename
        # Let's use absolute path from root for simplicity if possible, but relative is safer.
        link_tag = f'<link rel="stylesheet" href="{rel_path}">'
        
        new_content = style_pattern.sub(link_tag, content, count=1)
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f'Extracted CSS from {filepath} to {css_path}')

# Files to extract CSS from
css_extractions = {
    'home.php': 'home.css',
    'index.php': 'index.css',
    'login.php': 'login.css',
    'register.php': 'register.css',
    'app/pages/admin_panel.php': 'admin_panel.css',
    'app/pages/proveedor_panel.php': 'proveedor_panel.css'
}

for php_file, css_file in css_extractions.items():
    full_path = os.path.join(ROOT_DIR, php_file)
    if os.path.exists(full_path):
        extract_css(full_path, css_file)

# Path replacements mapping
# For php includes/requires
php_files = []
for root, dirs, files in os.walk(ROOT_DIR):
    for file in files:
        if file.endswith('.php'):
            php_files.append(os.path.join(root, file))

# We need to replace string literals in requires/includes and fetches
# A naive regex might break things, let's be careful.
# Map of old paths to new paths relative to root
path_map = {
    'db.php': 'app/core/db.php',
    'auth_guard.php': 'app/core/auth_guard.php',
    'auth_login.php': 'app/auth/auth_login.php',
    'auth_register.php': 'app/auth/auth_register.php',
    'admin_actions.php': 'app/actions/admin_actions.php',
    'proveedor_actions.php': 'app/actions/proveedor_actions.php',
    'cliente_actions.php': 'app/actions/cliente_actions.php',
    'contratacion_actions.php': 'app/actions/contratacion_actions.php',
    'chat_get.php': 'app/api/chat_get.php',
    'chat_send.php': 'app/api/chat_send.php',
    'chat_archivar.php': 'app/api/chat_archivar.php',
    'get_lists.php': 'app/api/get_lists.php',
    'get_reviews.php': 'app/api/get_reviews.php',
    'admin_panel.php': 'app/pages/admin_panel.php',
    'proveedor_panel.php': 'app/pages/proveedor_panel.php',
    'logic.php': 'app/pages/logic.php',
    'verify.php': 'app/pages/verify.php',
    'ver_doc.php': 'app/pages/ver_doc.php',
    'style_backend.css': 'public/css/style_backend.css',
    'fonts.css': 'public/css/fonts.css',
}

import urllib.parse

def replace_paths(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()
    
    new_content = content
    
    # Calculate relative paths for this specific file
    file_dir = os.path.dirname(filepath)
    
    for old_path, new_root_path in path_map.items():
        # Calculate the relative path from the current file's directory to the target file
        target_abs_path = os.path.join(ROOT_DIR, new_root_path)
        rel_path = os.path.relpath(target_abs_path, file_dir)
        
        # Replace requires and includes
        # e.g. require 'db.php'; -> require 'path/to/db.php';
        # e.g. require_once("auth_guard.php"); -> ...
        # We look for require|include 'old_path' or "old_path"
        pattern_require = re.compile(r'(require|include|require_once|include_once)\s*[\'"]\s*' + re.escape(old_path) + r'\s*[\'"]')
        replacement_require = r'\1 \'' + rel_path.replace('\\', '/') + r'\''
        new_content = pattern_require.sub(replacement_require, new_content)
        
        # Replace fetches (JS) and hrefs/actions (HTML)
        # e.g. fetch('chat_get.php') -> fetch('rel_path')
        # action="admin_actions.php" -> action="rel_path"
        # window.location.href = 'admin_panel.php' -> ...
        pattern_fetch = re.compile(r'([\'"])(/?)' + re.escape(old_path) + r'([?\'"])')
        
        def fetch_repl(m):
            return m.group(1) + rel_path.replace('\\', '/') + m.group(3)
            
        new_content = pattern_fetch.sub(fetch_repl, new_content)

    # Specific replacements for libs and fonts
    # href="fonts/fonts.css" -> href="rel_path_to_public/css/fonts.css"
    fonts_css_rel = os.path.relpath(os.path.join(ROOT_DIR, 'public', 'css', 'fonts.css'), file_dir).replace('\\', '/')
    new_content = re.sub(r'[\'"]fonts/fonts\.css[\'"]', f'"{fonts_css_rel}"', new_content)
    
    # libs/jspdf... -> rel_path/public/libs/jspdf...
    public_libs_rel = os.path.relpath(os.path.join(ROOT_DIR, 'public', 'libs'), file_dir).replace('\\', '/')
    new_content = re.sub(r'[\'"]libs/(.*?)[\'"]', f'"{public_libs_rel}/\\1"', new_content)
    
    # uploads/ -> rel_path/uploads/
    uploads_rel = os.path.relpath(os.path.join(ROOT_DIR, 'uploads'), file_dir).replace('\\', '/')
    new_content = re.sub(r'[\'"]uploads/(.*?)[\'"]', f'"{uploads_rel}/\\1"', new_content)

    if content != new_content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f'Updated paths in {filepath}')

for php_file in php_files:
    replace_paths(php_file)

