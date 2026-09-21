<?php 
$titulo = "URBAN STYLE - Inicio";
include 'header.php'; 
?>

<!-- ================= HERO SECTION ================= -->
<section class="hero">
    <!-- Imagen principal manejada desde style.css -->
</section>

<!-- ================= GRID DE PRODUCTOS ================= -->
<h2 class="seccion-titulo">ÚLTIMOS LANZAMIENTOS</h2>

<section class="grid-productos">

    <!-- Producto 1: Polera SK -->
    <div class="tarjeta-producto">
        <div class="imagen-caja">
            <img src="img/polera-sk.jpg" alt="Poleron Urban">
        </div>
        <div class="info-producto">
            <span class="nombre-prenda">Poleron Urban</span>
            <span class="precio-prenda">$21.990</span>
        </div>
    </div>

    <!-- Producto 2: Bolso -->
    <div class="tarjeta-producto">
        <div class="imagen-caja">
            <div class="badge-agotado">AGOTADO</div>
            <img src="img/bolso-y2k.jpg" alt="Bolso Joi A">
        </div>
        <div class="info-producto">
            <span class="nombre-prenda">Bolso Joi A</span>
            <span class="precio-prenda">
                <span class="precio-antiguo">$25.990</span>
                $20.000
            </span>
        </div>
    </div>

    <!-- Producto 3: Pantalón -->
    <div class="tarjeta-producto">
        <div class="imagen-caja">
            <img src="img/pant-gold.jpg" alt="Pantalón Cargo">
        </div>
        <div class="info-producto">
            <span class="nombre-prenda">Pantalón Cargo</span>
            <span class="precio-prenda">$34.990</span>
        </div>
    </div>

    <!-- Producto 4: Polera Big Logo -->
    <div class="tarjeta-producto">
        <div class="imagen-caja">
            <img src="img/big-logo.jpg" alt="Polera Básica">
        </div>
        <div class="info-producto">
            <span class="nombre-prenda">Polera Básica</span>
            <span class="precio-prenda">$12.990</span>
        </div>
    </div>

</section>

<!-- ================= FOOTER ================= -->
<!-- ¡Esta es la línea clave que hace que funcionen los botones y las animaciones! -->
<?php include 'footer.php'; ?>