document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // 1. CARRITO DE COMPRAS LATERAL (Bolsa)
    // ==========================================
    const iconoCarrito = document.querySelector('.fa-bag-shopping');
    
    const panelCarrito = document.createElement('div');
    panelCarrito.classList.add('carrito-lateral');
    panelCarrito.innerHTML = `
        <div class="carrito-header">
            <h3>Tu Bolsa</h3>
            <i class="fa-solid fa-xmark cerrar-carrito" style="cursor:pointer; font-size: 1.2rem;"></i>
        </div>
        <div class="carrito-body"></div>
        <div class="carrito-footer">
            <a href="checkout.php" style="text-decoration: none;">
                <button type="button" class="btn-checkout" style="width: 100%; cursor: pointer;">Ir a Pagar</button>
            </a>
        </div>
    `;
    document.body.appendChild(panelCarrito);

    const btnCerrarCarrito = panelCarrito.querySelector('.cerrar-carrito');

    if (iconoCarrito) {
        iconoCarrito.addEventListener('click', () => {
            window.renderizarCarritoGlobal();
            panelCarrito.classList.add('activo');
        });
    }

    if (btnCerrarCarrito) {
        btnCerrarCarrito.addEventListener('click', () => {
            panelCarrito.classList.remove('activo');
        });
    }

    // ==========================================
    // 2. RENDERIZADO Y MATEMÁTICA DEL CARRITO
    // ==========================================
    window.renderizarCarritoGlobal = function() {
        const bodyCarrito = document.querySelector('.carrito-body');
        if (!bodyCarrito) return;

        let carrito = [];
        try {
            carrito = JSON.parse(localStorage.getItem('carritoUrban')) || [];
            if (!Array.isArray(carrito)) carrito = [];
        } catch (e) {
            carrito = [];
        }

        // Limpiar registros antiguos incompatibles si existen
        carrito = carrito.filter(item => item && typeof item.precioUnitario === 'number' && !isNaN(item.precioUnitario));
        localStorage.setItem('carritoUrban', JSON.stringify(carrito));

        if (carrito.length === 0) {
            bodyCarrito.innerHTML = '<p style="text-align:center; margin-top: 20px; color: var(--texto-secundario);">Tu bolsa está vacía.</p>';
            return;
        }

        let html = '';
        let totalGeneral = 0;
        let totalUnidades = 0;

        carrito.forEach((item, index) => {
            const cant = parseInt(item.cantidad) || 1;
            const unitario = parseInt(item.precioUnitario) || 0;
            const subtotal = unitario * cant;
            
            totalGeneral += subtotal;
            totalUnidades += cant;

            const detalleTalla = item.talla 
                ? `<span style="border: 1px solid #555; padding: 1px 6px; font-size: 0.75rem; border-radius: 3px; margin-left: 6px;">Talla: ${item.talla}</span>` 
                : '';

            html += `
                <div style="display: flex; gap: 12px; margin-bottom: 15px; border-bottom: 1px solid #333; padding-bottom: 12px; align-items: center;">
                    <img src="${item.img}" style="width: 55px; height: 55px; object-fit: cover; border-radius: 4px; background: #fff;">
                    <div style="flex: 1; color: var(--texto);">
                        <p style="margin: 0; font-weight: bold; font-size: 0.95rem;">${item.nombre}</p>
                        <p style="margin: 4px 0; color: #aaa; font-size: 0.85rem;">Productos: ${cant} ${detalleTalla}</p>
                        <p style="margin: 0; color: #fff; font-weight: bold; font-size: 0.95rem;">$${subtotal.toLocaleString('es-CL')}</p>
                    </div>
                    <button type="button" onclick="eliminarDelCarrito(${index})" style="background: none; border: none; color: #ff4d4d; cursor: pointer; font-size: 1rem;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;
        });

        html += `
            <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #555; color: var(--texto);">
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #aaa; margin-bottom: 5px;">
                    <span>Total de productos:</span>
                    <span>${totalUnidades}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.25rem;">
                    <span>TOTAL:</span>
                    <span>$${totalGeneral.toLocaleString('es-CL')}</span>
                </div>
            </div>
        `;

        bodyCarrito.innerHTML = html;
    };

    window.eliminarDelCarrito = function(index) {
        let carrito = JSON.parse(localStorage.getItem('carritoUrban')) || [];
        carrito.splice(index, 1);
        localStorage.setItem('carritoUrban', JSON.stringify(carrito));
        window.renderizarCarritoGlobal();
    };

    window.renderizarCarritoGlobal();

   // ==========================================
    // 3. BUSCADOR INTERACTIVO EN TIEMPO REAL
    // ==========================================
    const catalogoTienda = [
        {
            nombre: 'Poleron URBAN',
            precio: '$21.990',
            img: 'img/big-logo.jpg',
            agotado: false
        },
        {
            nombre: 'Bolso Joi A',
            precio: '$20.000',
            img: 'img/bolso-y2k.jpg',
            agotado: true
        },
        {
            nombre: 'Pantalón Cargo',
            precio: '$34.990',
            img: 'img/pant-gold.jpg',
            agotado: false
        },
        {
            nombre: 'Polera Básica',
            precio: '$12.990',
            img: 'img/polera-sk.jpg',
            agotado: false
        }
    ];

    const iconoBuscador = document.querySelector('.fa-magnifying-glass');
    const modalBusqueda = document.getElementById('modalBusqueda');
    const btnCerrarBusqueda = document.getElementById('cerrarBusquedaBtn');
    const inputBusqueda = document.getElementById('inputBusqueda');
    const contenedorResultados = document.getElementById('contenedorResultadosBusqueda');
    const tituloBusqueda = document.getElementById('tituloBusqueda');

    // Función auxiliar para ignorar tildes y mayúsculas (ej: "pantalon" encuentra "Pantalón")
    const normalizarTexto = (str) => {
        return str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
    };

    // Renderizar tarjetas en el modal
    function renderizarResultados(lista) {
        if (!contenedorResultados) return;

        if (lista.length === 0) {
            contenedorResultados.innerHTML = `
                <p style="color: #888; font-size: 0.95rem; margin: 20px 0; width: 100%;">
                    No se encontraron productos que coincidan con tu búsqueda.
                </p>
            `;
            return;
        }

        let html = '';
        lista.forEach(prod => {
            const badgeAgotado = prod.agotado 
                ? `<span style="background: #e63946; color: #fff; font-size: 0.7rem; font-weight: bold; padding: 2px 6px; border-radius: 3px; position: absolute; top: 6px; left: 6px;">AGOTADO</span>` 
                : '';

            html += `
                <div class="mini-tarjeta item-busqueda" 
                     data-nombre="${prod.nombre}" 
                     data-precio="${prod.precio}" 
                     data-img="${prod.img}" 
                     data-agotado="${prod.agotado ? '1' : '0'}" 
                     style="cursor: pointer; position: relative; transition: transform 0.2s;">
                    <div style="position: relative; width: 100%;">
                        <img src="${prod.img}" class="mini-imagen" style="object-fit: cover; width: 100%; border-radius: 6px; background: #fff;" alt="${prod.nombre}">
                        ${badgeAgotado}
                    </div>
                    <span class="mini-nombre" style="display: block; font-weight: bold; margin-top: 6px; font-size: 0.9rem;">${prod.nombre}</span>
                    <span class="mini-precio" style="display: block; color: #aaa; font-size: 0.85rem;">${prod.precio}</span>
                </div>
            `;
        });

        contenedorResultados.innerHTML = html;

        // Conectar clics para viajar a producto.php
        contenedorResultados.querySelectorAll('.item-busqueda').forEach(card => {
            card.addEventListener('click', () => {
                const nombre = card.getAttribute('data-nombre');
                const precio = card.getAttribute('data-precio');
                const img = card.getAttribute('data-img');
                const agotado = card.getAttribute('data-agotado');

                window.location.href = `producto.php?nombre=${encodeURIComponent(nombre)}&precio=${encodeURIComponent(precio)}&img=${encodeURIComponent(img)}&agotado=${agotado}`;
            });
        });
    }

    // Evento de escritura en la barra de búsqueda
    if (inputBusqueda) {
        inputBusqueda.addEventListener('input', (e) => {
            const busqueda = normalizarTexto(e.target.value);

            if (busqueda === '') {
                if (tituloBusqueda) tituloBusqueda.innerText = "Productos sugeridos";
                renderizarResultados(catalogoTienda);
                return;
            }

            if (tituloBusqueda) tituloBusqueda.innerText = "Resultados encontrados";
            const filtrados = catalogoTienda.filter(prod => normalizarTexto(prod.nombre).includes(busqueda));
            renderizarResultados(filtrados);
        });
    }

    // Abrir y cerrar el modal
    if (iconoBuscador && modalBusqueda) {
        iconoBuscador.addEventListener('click', () => {
            modalBusqueda.classList.add('activo');
            if (inputBusqueda) {
                inputBusqueda.value = '';
                if (tituloBusqueda) tituloBusqueda.innerText = "Productos sugeridos";
                renderizarResultados(catalogoTienda);
                inputBusqueda.focus();
            }
        });

        if (btnCerrarBusqueda) {
            btnCerrarBusqueda.addEventListener('click', () => modalBusqueda.classList.remove('activo'));
        }

        modalBusqueda.addEventListener('click', (e) => {
            if (e.target === modalBusqueda) modalBusqueda.classList.remove('activo');
        });
    }

    // Modal de Login
    const iconoLogin = document.querySelector('.fa-user');
    const modalLogin = document.getElementById('modalLogin');
    const btnCerrarModal = document.getElementById('cerrarModalBtn');
    const tabLogin = document.getElementById('tabLogin');
    const tabRegistro = document.getElementById('tabRegistro');
    const formLogin = document.getElementById('formLogin');
    const formRegistro = document.getElementById('formRegistro');

    if (iconoLogin && modalLogin) {
        iconoLogin.addEventListener('click', () => modalLogin.classList.add('activo'));
        if (btnCerrarModal) {
            btnCerrarModal.addEventListener('click', () => modalLogin.classList.remove('activo'));
        }
        modalLogin.addEventListener('click', (e) => {
            if (e.target === modalLogin) modalLogin.classList.remove('activo');
        });
        if (tabRegistro && tabLogin) {
            tabRegistro.addEventListener('click', () => {
                tabRegistro.classList.add('activo');
                tabLogin.classList.remove('activo');
                formRegistro.classList.add('activo');
                formLogin.classList.remove('activo');
            });
            tabLogin.addEventListener('click', () => {
                tabLogin.classList.add('activo');
                tabRegistro.classList.remove('activo');
                formLogin.classList.add('activo');
                formRegistro.classList.remove('activo');
            });
        }
    }

    // ==========================================
    // 4. INTERACCIÓN CON TARJETAS DE PRODUCTOS
    // ==========================================
    const tarjetasProductos = document.querySelectorAll('.tarjeta-producto');

    tarjetasProductos.forEach(tarjeta => {
        tarjeta.style.cursor = 'pointer';
        tarjeta.addEventListener('click', () => {
            const nombreEl = tarjeta.querySelector('.nombre-prenda');
            const precioEl = tarjeta.querySelector('.precio-prenda');
            const imgEl = tarjeta.querySelector('img');

            if (!nombreEl || !precioEl || !imgEl) return;

            const nombre = nombreEl.innerText.trim();
            const partesPrecio = precioEl.innerText.split('$');
            const precio = '$' + partesPrecio[partesPrecio.length - 1].trim();
            const img = imgEl.getAttribute('src');

            // SWITCH: Detecta si la tarjeta contiene el badge de agotado
            const estaAgotado = tarjeta.querySelector('.badge-agotado') !== null;

            window.location.href = `producto.php?nombre=${encodeURIComponent(nombre)}&precio=${encodeURIComponent(precio)}&img=${encodeURIComponent(img)}&agotado=${estaAgotado ? '1' : '0'}`;
        });
    });

    // ==========================================
    // 5. CHECKOUT (MEDIOS DE PAGO)
    // ==========================================
    const radioTarjeta = document.getElementById('radio-tarjeta');
    const radioTransferencia = document.getElementById('radio-transferencia');
    const formTarjeta = document.getElementById('form-tarjeta');
    const msgTransferencia = document.getElementById('msg-transferencia');
    const btnFinalizar = document.getElementById('btn-finalizar-compra');

    if (radioTarjeta && radioTransferencia) {
        function actualizarOpcionesPago() {
            if (radioTarjeta.checked) {
                formTarjeta.style.display = 'block';
                msgTransferencia.style.display = 'none';
            } else {
                formTarjeta.style.display = 'none';
                msgTransferencia.style.display = 'block';
            }
        }

        radioTarjeta.addEventListener('change', actualizarOpcionesPago);
        radioTransferencia.addEventListener('change', actualizarOpcionesPago);
        actualizarOpcionesPago();

        if (btnFinalizar) {
            btnFinalizar.addEventListener('click', (e) => {
                e.preventDefault();
                if (radioTransferencia.checked) {
                    window.location.href = 'pasarela.php';
                } else {
                    alert("Procesando pago con tarjeta de crédito...");
                }
            });
        }
    }

});