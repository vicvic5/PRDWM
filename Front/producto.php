<?php 
$titulo = "URBAN STYLE - Detalle Producto";
include 'header.php'; 

// Capturar datos enviados por URL
$nombre = isset($_GET['nombre']) ? $_GET['nombre'] : 'Poleron URBAN';
$precioRaw = isset($_GET['precio']) ? $_GET['precio'] : '$21.990';
$img = isset($_GET['img']) ? $_GET['img'] : 'img/big-logo.jpg';

// Switch de disponibilidad (1 = Agotado, 0 = Disponible)
$estaAgotado = (isset($_GET['agotado']) && $_GET['agotado'] === '1');

// Limpieza de formato de precio
$partesPrecio = explode('$', $precioRaw);
$precio = '$' . trim(end($partesPrecio));
$precioEntero = (int) preg_replace('/[^0-9]/', '', $precio);
if ($precioEntero <= 0) {
    $precioEntero = 21990;
    $precio = '$21.990';
}

// Ocultar tallas si es accesorio
$esAccesorio = (stripos($nombre, 'Bolso') !== false);
?>

<main class="producto-detalle-container" style="display: flex; gap: 40px; padding: 40px; max-width: 1200px; margin: 0 auto; margin-top: 80px; background-color: #111; color: #fff;">
    
    <div class="producto-galeria" style="flex: 1; display: flex; flex-direction: column; gap: 20px;">
        <div class="imagen-principal" style="background: #fff; padding: 20px; border-radius: 8px; position: relative;">
            <?php if ($estaAgotado): ?>
                <div style="position: absolute; top: 15px; left: 15px; background: #000; color: #fff; padding: 6px 14px; font-weight: bold; font-size: 0.85rem; border: 1px solid #444; border-radius: 4px;">AGOTADO</div>
            <?php endif; ?>
            <img src="<?php echo htmlspecialchars($img); ?>" alt="Producto" style="width: 100%; display: block; <?php echo $estaAgotado ? 'filter: grayscale(40%);' : ''; ?>">
        </div>
    </div>

    <div class="producto-info" style="flex: 1;">
        <h1 style="font-size: 2rem; margin-bottom: 10px; font-weight: bold;"><?php echo htmlspecialchars($nombre); ?></h1>
        <p style="font-size: 1.5rem; margin-bottom: 20px; color: <?php echo $estaAgotado ? '#777' : '#fff'; ?>;"><?php echo htmlspecialchars($precio); ?></p>

        <?php if (!$esAccesorio): ?>
        <div class="selector-talla" style="margin-bottom: 20px;">
            <p style="margin-bottom: 10px; font-weight: bold;">TALLA | <span id="talla-seleccionada">S</span></p>
            <div style="display: flex; gap: 10px;" id="contenedor-tallas">
                <button type="button" class="btn-talla" data-talla="XS" onclick="seleccionarTalla(this)" <?php echo $estaAgotado ? 'disabled' : ''; ?> style="padding: 10px 20px; border: 1px solid #444; background: transparent; color: #fff; cursor: <?php echo $estaAgotado ? 'not-allowed' : 'pointer'; ?>;">XS</button>
                <button type="button" class="btn-talla activo" data-talla="S" onclick="seleccionarTalla(this)" <?php echo $estaAgotado ? 'disabled' : ''; ?> style="padding: 10px 20px; border: 1px solid #fff; background: #000; color: #fff; cursor: <?php echo $estaAgotado ? 'not-allowed' : 'pointer'; ?>;">S</button>
                <button type="button" class="btn-talla" data-talla="M" onclick="seleccionarTalla(this)" <?php echo $estaAgotado ? 'disabled' : ''; ?> style="padding: 10px 20px; border: 1px solid #444; background: transparent; color: #fff; cursor: <?php echo $estaAgotado ? 'not-allowed' : 'pointer'; ?>;">M</button>
                <button type="button" class="btn-talla" data-talla="L" onclick="seleccionarTalla(this)" <?php echo $estaAgotado ? 'disabled' : ''; ?> style="padding: 10px 20px; border: 1px solid #444; background: transparent; color: #fff; cursor: <?php echo $estaAgotado ? 'not-allowed' : 'pointer'; ?>;">L</button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Selector de cantidad -->
        <div class="selector-cantidad" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; <?php echo $estaAgotado ? 'opacity: 0.3; pointer-events: none;' : ''; ?>">
            <button type="button" id="btn-menos" onclick="cambiarCantidad(-1)" style="padding: 10px 15px; border: 1px solid #444; background: transparent; color: #fff; cursor: pointer;">-</button>
            <span id="cantidad-display" style="font-weight: bold; font-size: 1.2rem; width: 30px; text-align: center;">1</span>
            <button type="button" id="btn-mas" onclick="cambiarCantidad(1)" style="padding: 10px 15px; border: 1px solid #444; background: transparent; color: #fff; cursor: pointer;">+</button>
        </div>

        <!-- Mensaje de inventario / agotado -->
        <?php if ($estaAgotado): ?>
            <p style="color: #ff4d4d; font-size: 0.95rem; font-weight: bold; margin-bottom: 20px;">ARTÍCULO AGOTADO TEMPORALMENTE</p>
        <?php else: ?>
            <p style="color: #888; font-size: 0.9rem; margin-bottom: 20px;">EXCLUSIVO - MÁXIMO 3 UNIDADES POR CLIENTE</p>
        <?php endif; ?>

        <!-- Botón Agregar al Carrito -->
        <button type="button" 
                id="btn-agregar-carrito" 
                onclick="agregarAlCarritoActual(true)" 
                <?php echo $estaAgotado ? 'disabled' : ''; ?>
                style="width: 100%; padding: 15px; font-weight: bold; margin-bottom: 10px; transition: 0.3s;
                <?php if ($estaAgotado): ?>
                    background: #1e1e1e; color: #666; border: 1px solid #333; opacity: 0.45; cursor: not-allowed; pointer-events: none;
                <?php else: ?>
                    background: #1a1a1a; color: #fff; border: 1px solid #fff; cursor: pointer;
                <?php endif; ?>">
            <?php echo $estaAgotado ? 'PRODUCTO AGOTADO' : 'AGREGAR AL CARRITO - ' . htmlspecialchars($precio); ?>
        </button>
        
        <!-- Botón Comprar Ahora -->
        <button type="button" 
                id="btn-comprar-ahora" 
                onclick="comprarAhoraActual()" 
                <?php echo $estaAgotado ? 'disabled' : ''; ?>
                style="width: 100%; padding: 15px; font-weight: bold;
                <?php if ($estaAgotado): ?>
                    background: #111; color: #444; border: 1px solid #222; opacity: 0.35; cursor: not-allowed; pointer-events: none;
                <?php else: ?>
                    background: #000; color: #fff; border: 1px solid #444; cursor: pointer;
                <?php endif; ?>">
            COMPRAR AHORA
        </button>

        <div class="detalles-adicionales" style="margin-top: 30px; border-top: 1px solid #333; padding-top: 20px;">
            <h3 style="font-size: 1.1rem; margin-bottom: 10px;">DESCRIPCIÓN</h3>
            <p style="color: #aaa; line-height: 1.6; font-size: 0.95rem;">Artículo exclusivo de edición limitada Urban Style. Máximo 3 unidades por pedido.</p>
        </div>
    </div>
</main>

<script>
    const PRODUCTO_ACTUAL = {
        nombre: <?php echo json_encode($nombre); ?>,
        precioUnitario: <?php echo $precioEntero; ?>,
        img: <?php echo json_encode($img); ?>,
        esAccesorio: <?php echo $esAccesorio ? 'true' : 'false'; ?>,
        estaAgotado: <?php echo $estaAgotado ? 'true' : 'false'; ?>
    };

    let cantidadActual = 1;
    let tallaActual = PRODUCTO_ACTUAL.esAccesorio ? null : 'S';

    function seleccionarTalla(boton) {
        if (PRODUCTO_ACTUAL.estaAgotado) return;
        const botones = document.querySelectorAll('.btn-talla');
        botones.forEach(b => {
            b.classList.remove('activo');
            b.style.background = 'transparent';
            b.style.borderColor = '#444';
        });
        boton.classList.add('activo');
        boton.style.background = '#000';
        boton.style.borderColor = '#fff';
        tallaActual = boton.getAttribute('data-talla');
        const spanTalla = document.getElementById('talla-seleccionada');
        if (spanTalla) spanTalla.innerText = tallaActual;
    }

    function cambiarCantidad(cambio) {
        if (PRODUCTO_ACTUAL.estaAgotado) return;
        const nuevaCantidad = cantidadActual + cambio;
        if (nuevaCantidad >= 1 && nuevaCantidad <= 3) {
            cantidadActual = nuevaCantidad;
            document.getElementById('cantidad-display').innerText = cantidadActual;
            const subtotal = PRODUCTO_ACTUAL.precioUnitario * cantidadActual;
            document.getElementById('btn-agregar-carrito').innerText = 'AGREGAR AL CARRITO - $' + subtotal.toLocaleString('es-CL');
        }
    }

    function agregarAlCarritoActual(abrirPanel) {
        if (PRODUCTO_ACTUAL.estaAgotado) return;

        let carrito = [];
        try {
            carrito = JSON.parse(localStorage.getItem('carritoUrban')) || [];
            if (!Array.isArray(carrito)) carrito = [];
        } catch (e) {
            carrito = [];
        }

        const indiceExistente = carrito.findIndex(item => item.nombre === PRODUCTO_ACTUAL.nombre && item.talla === tallaActual);
        if (indiceExistente !== -1) {
            carrito[indiceExistente].cantidad = Math.min(3, carrito[indiceExistente].cantidad + cantidadActual);
        } else {
            carrito.push({
                nombre: PRODUCTO_ACTUAL.nombre,
                precioUnitario: PRODUCTO_ACTUAL.precioUnitario,
                cantidad: cantidadActual,
                talla: tallaActual,
                img: PRODUCTO_ACTUAL.img
            });
        }

        localStorage.setItem('carritoUrban', JSON.stringify(carrito));

        if (typeof window.renderizarCarritoGlobal === 'function') {
            window.renderizarCarritoGlobal();
        }

        if (abrirPanel) {
            const panel = document.querySelector('.carrito-lateral');
            if (panel) panel.classList.add('activo');
        }
    }

    function comprarAhoraActual() {
        if (PRODUCTO_ACTUAL.estaAgotado) return;
        agregarAlCarritoActual(false);
        window.location.href = 'checkout.php';
    }
</script>

<?php include 'footer.php'; ?>