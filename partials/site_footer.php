<?php if (!defined('OBIT_APP')) { exit('Forbidden'); }
$base = ($PAGE['base'] ?? ''); ?>
    <footer id="contacto" class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="<?= $base ?>index.php" class="logo footer-logo">
                        <img src="<?= $base ?>logo-seal-footer.png" alt="Sello Funeraria del Zulia - Desde 1942" class="footer-logo-icon">
                        <span class="logo-text">Funeraria del Zulia</span>
                    </a>
                    <p>Una empresa de <strong>Legado Holding</strong>.<br>Uniendo familias, honrando vidas, preservando memorias.</p>
                    <p class="footer-address"><strong>Sede Principal:</strong> Calle 84 No. 3F-70, Edificio Funeraria del Zulia, Sector Valle Frío, Maracaibo 4001, Estado Zulia.</p>
                    <div class="footer-social" aria-label="Redes sociales">
                        <a href="https://www.instagram.com/funerariazulia/" target="_blank" rel="noopener" aria-label="Instagram de Funeraria del Zulia"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.2c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.21 15.58 2.2 15.2 2.2 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.21 8.8 2.2 12 2.2zm0 1.8c-3.15 0-3.5.01-4.74.07-.98.04-1.51.21-1.86.34-.47.18-.8.4-1.15.75-.35.35-.57.68-.75 1.15-.13.35-.3.88-.34 1.86C3.1 8.5 3.1 8.85 3.1 12s0 3.5.06 4.74c.04.98.21 1.51.34 1.86.18.47.4.8.75 1.15.35.35.68.57 1.15.75.35.13.88.3 1.86.34 1.24.06 1.59.07 4.74.07s3.5-.01 4.74-.07c.98-.04 1.51-.21 1.86-.34.47-.18.8-.4 1.15-.75.35-.35.57-.68.75-1.15.13-.35.3-.88.34-1.86.06-1.24.07-1.59.07-4.74s-.01-3.5-.07-4.74c-.04-.98-.21-1.51-.34-1.86a3.1 3.1 0 0 0-.75-1.15 3.1 3.1 0 0 0-1.15-.75c-.35-.13-.88-.3-1.86-.34C15.5 4.01 15.15 4 12 4zm0 3.06a4.94 4.94 0 1 1 0 9.88 4.94 4.94 0 0 1 0-9.88zm0 8.15a3.21 3.21 0 1 0 0-6.42 3.21 3.21 0 0 0 0 6.42zm6.29-8.35a1.15 1.15 0 1 1-2.3 0 1.15 1.15 0 0 1 2.3 0z"/></svg><span>Instagram</span></a>
                        <a href="https://www.facebook.com/funerariadelzuliaoficial/" target="_blank" rel="noopener" aria-label="Facebook de Funeraria del Zulia"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.52 1.49-3.91 3.78-3.91 1.1 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.63.78-1.63 1.57v1.88h2.78l-.44 2.91h-2.34V22c4.78-.76 8.43-4.92 8.43-9.94z"/></svg><span>Facebook</span></a>
                    </div>
                </div>
                <div>
                    <h4>Enlaces</h4>
                    <ul class="footer-links-list">
                        <li><a href="<?= $base ?>servicios/">Servicios</a></li>
                        <li><a href="<?= $base ?>crematorios-del-zulia.php">Crematorios del Zulia</a></li>
                        <?php if (site_section_enabled('nosotros')): ?>
                        <li><a href="<?= $base ?>nosotros.php">Nosotros</a></li>
                        <?php endif; ?>
                        <li><a href="<?= $base ?>planes/">Previsión Familiar</a></li>
                        <?php if (site_section_enabled('obituarios')): ?>
                        <li><a href="<?= $base ?>obituarios.php">Obituarios</a></li>
                        <?php endif; ?>
                        <?php if (site_section_enabled('directorio_medico')): ?>
                        <li><a href="<?= $base ?>directorio-medico.php">Directorio Médico</a></li>
                        <?php endif; ?>
                        <?php if (site_section_enabled('recursos')): ?>
                        <li><a href="<?= $base ?>recursos.php">Recursos de Lectura</a></li>
                        <?php endif; ?>
                        <?php if (site_section_enabled('faqs')): ?>
                        <li><a href="<?= $base ?>index.php#preguntas">Preguntas Frecuentes</a></li>
                        <?php endif; ?>
                        <?php if ($portalUrl = client_portal_url()): ?>
                        <li><a href="<?= esc($portalUrl) ?>" rel="noopener">Portal de Clientes</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4>Atención 24 Horas</h4>
                    <div class="footer-contact-item">
                        <svg viewBox="0 0 24 24" width="20" height="20"><path d="M20.01 15.38c-1.23 0-2.42-.2-3.53-.56a.977.977 0 0 0-1.01.24l-2.2 2.2c-2.83-1.44-5.15-3.75-6.59-6.59l2.2-2.2c.28-.28.36-.67.25-1.02-.37-1.11-.57-2.3-.57-3.53 0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1 0 9.39 7.61 17 17 17 .55 0 1-.45 1-1v-3.5c0-.55-.45-1-1-1z"/></svg>
                        <span><strong>Emergencias:</strong> <a href="tel:+584246950136" class="footer-tel">+58 424 695-0136</a></span>
                    </div>
                    <div class="footer-contact-item">
                        <svg viewBox="0 0 24 24" width="20" height="20"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        <span>contacto@funerariadelzulia.com</span>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?= date('Y') ?> Funeraria del Zulia. Dignidad y Respeto en Maracaibo. Todos los derechos reservados.</p>
                <a href="<?= $base ?>admin.html" class="footer-admin-link" rel="nofollow"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm3 11c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>Acceso administradores</a>
            </div>
        </div>
    </footer>

    <a href="https://api.whatsapp.com/send?phone=584246950136&amp;text=Hola%2C%20quisiera%20informaci%C3%B3n" target="_blank" rel="noopener" class="whatsapp-fab" aria-label="Contacto por WhatsApp">
        <svg viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005 0-3.973-.5-5.743-1.455L0 24zm6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654z"/></svg>
    </a>

    <div id="toast" class="toast">Notificación</div>
    <script src="<?= $base ?>app.js?v=20261102-2"></script>
</body>
</html>
