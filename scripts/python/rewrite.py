import re

with open('proveedor_panel.php', 'r') as f:
    content = f.read()

# Replace wrapper start
wrapper_pattern = re.compile(r'<div class="wrapper">.*?<!-- TITULO -->.*?</div>\s*<!-- TOAST -->\s*<\?php if \(\$msg\): \?>\s*<div class="toast.*?</\?php endif; \?>\s*<!-- STATS -->', re.DOTALL)
content = wrapper_pattern.sub("""<!-- TOAST -->
    <?php if ($msg): ?>
        <div class="toast <?= $msg_type ?>" style="margin: 20px 40px 0;"><?= $msg ?></div>
    <?php endif; ?>

    <!-- ── TAB: SERVICIOS ── -->
    <div id="tab-servicios" class="tab-content <?= $tab == 'servicios' ? 'active' : '' ?>">
        <div class="page-header">
            <h1>Mis <span>Servicios</span></h1>
            <p>Gestiona tus servicios ofrecidos y sube imágenes</p>
        </div>
        <!-- STATS -->""", content)

# Find Seccion de chats and replace with closing div for tab-servicios and opening tab-chats
chat_section = re.compile(r'<!-- ══ SECCIÓN DE CHATS ════════════════════════════════ -->\s*<div class="chat-section".*?>\s*<div class="chat-section-title">.*?</div>', re.DOTALL)
new_chat_section = """
    </div> <!-- fin tab-servicios -->

    <!-- ── TAB: CHATS ── -->
    <div id="tab-chats" class="tab-content <?= $tab == 'chats' ? 'active' : '' ?>">
        <div class="page-header">
            <h1>Mensajes <span>de Clientes</span></h1>
            <p>Gestiona tus conversaciones con los clientes</p>
        </div>
"""
content = chat_section.sub(new_chat_section, content)

end_wrapper = re.compile(r'</div><!-- fin \.wrapper -->')

profile_tab = """
    </div> <!-- fin tab-chats -->

    <!-- ── TAB: PERFIL ── -->
    <div id="tab-perfil" class="tab-content <?= $tab == 'perfil' ? 'active' : '' ?>">
        <div class="page-header">
            <h1>Mi <span>Perfil</span></h1>
            <p>Gestiona tu cuenta y seguridad</p>
        </div>

        <?php if (!empty($_GET['ok']) && $_GET['tab'] == 'perfil'): ?>
            <div class="toast success">✔ Contraseña actualizada correctamente.</div>
        <?php endif; ?>
        <?php if (!empty($_GET['err']) && $_GET['tab'] == 'perfil'): ?>
            <div class="toast error">✗ La contraseña actual es incorrecta o hubo un error.</div>
        <?php endif; ?>

        <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:12px; padding:24px; max-width:500px; margin-bottom:24px;">
            <h3 style="margin-bottom:20px; font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700;">Cambiar Contraseña</h3>
            <form action="proveedor_actions.php" method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="field">
                    <label>Contraseña Actual</label>
                    <input type="password" name="current_password" required placeholder="Ingresa tu contraseña actual">
                </div>
                
                <div class="field">
                    <label>Nueva Contraseña</label>
                    <input type="password" name="new_password" required placeholder="Mínimo 6 caracteres">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Actualizar Contraseña</button>
            </form>
        </div>
    </div> <!-- fin tab-perfil -->

    <!-- BOTON FLOTANTE IR ARRIBA -->
    <button id="fabBtn" onclick="document.querySelector('.main-content').scrollTo({top:0, behavior:'smooth'})" 
            style="display:none; position:fixed; bottom:28px; right:28px; width:56px; height:56px; background:linear-gradient(135deg,var(--blue-mid),var(--blue-light)); border:none; border-radius:50%; color:#fff; font-size:24px; cursor:pointer; box-shadow:0 8px 28px rgba(45,91,227,0.5); z-index:499; align-items:center; justify-content:center; transition:transform .2s;">
        ↑
    </button>
"""
content = end_wrapper.sub(profile_tab, content)

with open('proveedor_panel.php', 'w') as f:
    f.write(content)
