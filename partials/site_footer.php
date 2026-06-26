<?php if (!defined('OBIT_APP')) { exit('Forbidden'); } ?>
    <footer id="contacto" class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="index.php" class="logo footer-logo">
                        <img src="logo-seal-footer.png" alt="Sello Funeraria del Zulia - Desde 1942" class="footer-logo-icon">
                        <span class="logo-text">Funeraria del Zulia</span>
                    </a>
                    <p>Dignidad, respeto y acompañamiento incondicional para las familias zulianas desde 1942.</p>
                    <p class="footer-address"><strong>Sede Principal:</strong> Calle 84 No. 3F-70, Edificio Funeraria del Zulia, Sector Valle Frío, Maracaibo 4001, Estado Zulia.</p>
                </div>
                <div>
                    <h4>Enlaces</h4>
                    <ul class="footer-links-list">
                        <li><a href="index.php#servicios">Servicios</a></li>
                        <li><a href="obituarios.php">Obituarios</a></li>
                        <li><a href="directorio-medico.php">Directorio Médico</a></li>
                        <li><a href="recursos.php">Recursos de Lectura</a></li>
                        <li><a href="index.php#prevision">Previsión Familiar</a></li>
                        <li><a href="index.php#preguntas">Preguntas Frecuentes</a></li>
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
            </div>
        </div>
    </footer>

    <a href="https://api.whatsapp.com/send?phone=584246950136&amp;text=Hola%2C%20quisiera%20informaci%C3%B3n" target="_blank" rel="noopener" class="whatsapp-fab" aria-label="Contacto por WhatsApp">
        <svg viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005 0-3.973-.5-5.743-1.455L0 24zm6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654z"/></svg>
    </a>

    <div id="toast" class="toast">Notificación</div>
    <script src="app.js?v=20260628"></script>
</body>
</html>
