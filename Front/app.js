// Esperamos a que todo el HTML (DOM) esté cargado antes de ejecutar el script
document.addEventListener('DOMContentLoaded', () => {


    // ==========================================
    // 2. CARRITO DE COMPRAS LATERAL (Bolsa)
    // ==========================================
    const iconoCarrito = document.querySelector('.fa-bag-shopping');
    
    // Creamos el panel del carrito dinámicamente con JS
    const panelCarrito = document.createElement('div');
    panelCarrito.classList.add('carrito-lateral');
    panelCarrito.innerHTML = `
        <div class="carrito-header">
            <h3>Tu Bolsa</h3>
            <i class="fa-solid fa-xmark cerrar-carrito" style="cursor:pointer; font-size: 1.2rem;"></i>
        </div>
        <div class="carrito-body">
            <p style="text-align:center; margin-top: 20px; color: var(--texto-secundario);">Tu bolsa está vacía.</p>
        </div>
        <div class="carrito-footer">
            <button class="btn-checkout">Ir a Pagar</button>
        </div>
    `;
    document.body.appendChild(panelCarrito);

    const btnCerrarCarrito = document.querySelector('.cerrar-carrito');

    // Funciones para abrir y cerrar
    if (iconoCarrito) {
        iconoCarrito.addEventListener('click', () => {
            panelCarrito.classList.add('activo');
        });
    }

    if (btnCerrarCarrito) {
        btnCerrarCarrito.addEventListener('click', () => {
            panelCarrito.classList.remove('activo');
        });
    }

    // ==========================================
    // 3. ICONO DE BÚSQUEDA Y MODAL LOGIN
    // ==========================================
    const iconoBuscador = document.querySelector('.fa-magnifying-glass');
    const modalBusqueda = document.getElementById('modalBusqueda');
    const btnCerrarBusqueda = document.getElementById('cerrarBusquedaBtn');

    if (iconoBuscador && modalBusqueda) {
        // Abrir el modal de búsqueda
        iconoBuscador.addEventListener('click', () => {
            modalBusqueda.classList.add('activo');
            document.getElementById('inputBusqueda').focus(); // Auto-selecciona la barra para escribir de inmediato
        });

        // Cerrar el modal con la "X"
        btnCerrarBusqueda.addEventListener('click', () => {
            modalBusqueda.classList.remove('activo');
        });

        // Cerrar el modal haciendo clic afuera del recuadro
        modalBusqueda.addEventListener('click', (e) => {
            if (e.target === modalBusqueda) {
                modalBusqueda.classList.remove('activo');
            }
        });
    
    }

    // Lógica del Modal de Login
    const iconoLogin = document.querySelector('.fa-user');
    const modalLogin = document.getElementById('modalLogin');
    const btnCerrarModal = document.getElementById('cerrarModalBtn');
    
    // Variables de las Pestañas (Tabs)
    const tabLogin = document.getElementById('tabLogin');
    const tabRegistro = document.getElementById('tabRegistro');
    const formLogin = document.getElementById('formLogin');
    const formRegistro = document.getElementById('formRegistro');

    if (iconoLogin && modalLogin) {
        // Abrir el modal
        iconoLogin.addEventListener('click', () => {
            modalLogin.classList.add('activo');
        });

        // Cerrar el modal con la "X"
        btnCerrarModal.addEventListener('click', () => {
            modalLogin.classList.remove('activo');
        });

        // Cerrar el modal haciendo clic afuera del recuadro
        modalLogin.addEventListener('click', (e) => {
            if (e.target === modalLogin) {
                modalLogin.classList.remove('activo');
            }
        });

        // Intercambiar a Registro
        tabRegistro.addEventListener('click', () => {
            tabRegistro.classList.add('activo');
            tabLogin.classList.remove('activo');
            formRegistro.classList.add('activo');
            formLogin.classList.remove('activo');
        });

        // Intercambiar a Login
        tabLogin.addEventListener('click', () => {
            tabLogin.classList.add('activo');
            tabRegistro.classList.remove('activo');
            formLogin.classList.add('activo');
            formRegistro.classList.remove('activo');
        });
    }

    // ==========================================
    // 4. INTERACCIÓN CON PRODUCTOS
    // ==========================================
    const tarjetasProductos = document.querySelectorAll('.tarjeta-producto');
    
    tarjetasProductos.forEach(tarjeta => {
        tarjeta.addEventListener('click', () => {
            const nombrePrenda = tarjeta.querySelector('.nombre-prenda');
            if(nombrePrenda) {
                console.log(`Viendo detalles de: ${nombrePrenda.innerText}`);
                // Aquí a futuro redirigirás a la página del producto específico (ej: producto.php?id=X)
            }
        });
    });

});