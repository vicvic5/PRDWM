<?php 
$titulo = "URBAN STYLE - Shop";
include 'header.php'; 
// Ya no necesitamos incluir conexion.php aquí porque la ropa es estática
?>

<div class="shop-container">
    
    <!-- ================= BARRA LATERAL (FILTROS) ================= -->
    <aside class="shop-sidebar">
        <h2>Filtros</h2>
        
        <div class="filtro-seccion">
            <h3>Disponibilidad</h3>
            <label class="checkbox-group"><input type="checkbox"> En existencia</label>
            <label class="checkbox-group"><input type="checkbox"> Agotado</label>
        </div>

        <div class="filtro-seccion">
            <h3>Precio</h3>
            <div class="precio-inputs">
                <span>$</span><input type="number" placeholder="0">
                <span>-</span>
                <span>$</span><input type="number" placeholder="64.990">
            </div>
        </div>

        <div class="filtro-seccion">
            <h3>Tipo de producto</h3>
            <label class="checkbox-group"><input type="checkbox"> HOODIE</label>
            <label class="checkbox-group"><input type="checkbox"> JEANS</label>
            <label class="checkbox-group"><input type="checkbox"> TEE</label>
        </div>
    </aside>

    <!-- ================= CONTENIDO PRINCIPAL ================= -->
    <main class="shop-content">
        
        <!-- Barra superior de ordenamiento -->
        <div class="shop-topbar">
            <span>4 artículos</span>
            <div>
                Ordenar: 
                <select>
                    <option>Destacados</option>
                    <option>Precio: menor a mayor</option>
                    <option>Precio: mayor a menor</option>
                    <option>Más recientes</option>
                </select>
            </div>
            <div class="view-icons">
                <i class="fa-solid fa-table-cells-large activo"></i>
                <i class="fa-solid fa-list"></i>
            </div>
        </div>

        <!-- Cuadrícula de ropa ESTÁTICA -->
        <div class="grid-shop">
            
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

        </div>
    </main>

</div>

<?php include 'footer.php'; ?>