<!-- ================= FOOTER ================= -->
    <footer>
        <div class="footer-left">
            <p>Contacto: Urban-Style1@gmail.com</p>
            <div>
                <a href="#">Política de reembolso</a>
                <a href="#">Términos del servicio</a>
                <a href="#">Políticas de envío</a>
            </div>
        </div>
        
        <div class="footer-right">
            <a href="https://instagram.com" target="_blank" style="color: inherit;"><i class="fa-brands fa-instagram"></i></a>
            <a href="https://tiktok.com" target="_blank" style="color: inherit;"><i class="fa-brands fa-tiktok"></i></a>
            <a href="https://youtube.com" target="_blank" style="color: inherit;"><i class="fa-brands fa-youtube"></i></a>
        </div>
    </footer>

    <!-- ================= MODAL LOGIN / REGISTRO ================= -->
    <div class="modal-overlay" id="modalLogin">
        <div class="modal-content">
            <span class="cerrar-modal" id="cerrarModalBtn">&times;</span>
            
            <div class="modal-tabs">
                <button class="tab-btn activo" id="tabLogin">Iniciar Sesión</button>
                <button class="tab-btn" id="tabRegistro">Registrarse</button>
            </div>
            
            <!-- Formulario Login -->
            <form id="formLogin" class="modal-form activo" action="login.php" method="POST">
                <input type="email" name="correo" placeholder="Correo electrónico" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit" class="btn-submit">Ingresar</button>
            </form>

            <!-- Formulario Registro -->
            <form id="formRegistro" class="modal-form" action="registro.php" method="POST">
                <input type="text" name="nombre" placeholder="Nombre completo" required>
                <input type="email" name="correo" placeholder="Correo electrónico" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit" class="btn-submit">Crear cuenta</button>
            </form>
        </div>
    </div>

    <!-- ================= MODAL BÚSQUEDA ================= -->
    <div class="modal-overlay" id="modalBusqueda">
        <div class="modal-search-content">
            <span class="cerrar-modal" id="cerrarBusquedaBtn">&times;</span>
            
            <!-- Barra de entrada de texto -->
            <div class="search-bar-container">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="inputBusqueda" placeholder="Buscar...">
            </div>

           <!-- Sección: Visto recientemente -->
            <div class="search-section">
                <div class="search-section-header">
                    <h4>Visto recientemente</h4>
                    <button class="btn-borrar">Borrar</button>
                </div>
                <div class="search-results-flex">
                    <div class="mini-tarjeta">
                        <img src="img/polera-sk.jpg" class="mini-imagen" style="object-fit: cover;" alt="Polera SK">
                        <span class="mini-nombre">Heavyweight Hoodie Star</span>
                        <span class="mini-precio">$64.990</span>
                    </div>
                </div>
            </div>

            <!-- Sección: Productos sugeridos -->
            <div class="search-section">
                <div class="search-section-header">
                    <h4>Productos</h4>
                </div>
                <div class="search-results-flex">
                    <div class="mini-tarjeta">
                        <img src="img/pant-gold.jpg" class="mini-imagen" style="object-fit: cover;" alt="Pant Gold">
                        <span class="mini-nombre">Pant Gold Edition</span>
                        <span class="mini-precio">$45.990</span>
                    </div>
                    <div class="mini-tarjeta">
                        <img src="img/bolso-y2k.jpg" class="mini-imagen" style="object-fit: cover;" alt="Bolso Y2K">
                        <span class="mini-nombre">Bolso Y2K Black</span>
                        <span class="mini-precio">$25.990</span>
                    </div>
                    <div class="mini-tarjeta">
                        <img src="img/polera-sk.jpg" class="mini-imagen" style="object-fit: cover;" alt="Polera SK">
                        <span class="mini-nombre">Hoodie Washed Grey</span>
                        <span class="mini-precio">$54.990</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Aquí conectamos el JS para las animaciones y modales -->
    <script src="app.js"></script>
</body>
</html>