<?php 
$titulo = "URBAN STYLE - Checkout";
include 'header.php'; 
?>

<main class="checkout-container" style="padding: 40px; max-width: 800px; margin: 0 auto; margin-top: 80px;">
    <h1 style="font-size: 1.8rem; margin-bottom: 30px;">Urban Style</h1>

    <section class="contacto-seccion" style="margin-bottom: 40px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="font-size: 1.3rem;">Contacto</h2>
            <a href="#" style="color: blue; text-decoration: underline; font-size: 0.9rem;">Iniciar sesión</a>
        </div>
        <input type="email" placeholder="Correo electrónico" style="width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px;">
        <label style="display: flex; align-items: center; gap: 10px; font-size: 0.9rem;">
            <input type="checkbox"> Enviarme novedades y ofertas por correo electrónico
        </label>
    </section>

    <section class="entrega-seccion">
        <h2 style="font-size: 1.3rem; margin-bottom: 15px;">Entrega</h2>
        
        <select style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px;">
            <option>Chile</option>
        </select>

        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <input type="text" placeholder="Nombre" style="flex: 1; padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
            <input type="text" placeholder="Apellidos" style="flex: 1; padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
        </div>

        <input type="text" placeholder="Comuna" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px;">
        <input type="text" placeholder="Dirección" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px;">
        <input type="text" placeholder="Casa, apartamento, etc. (opcional)" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px;">

        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <input type="text" placeholder="RUT" style="flex: 1; padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
            <input type="text" placeholder="Ciudad" style="flex: 1; padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
        </div>

        <select style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px;">
            <option>Región Metropolitana de Santiago</option>
        </select>

        <input type="tel" placeholder="Teléfono" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px;">
        
        <label style="display: flex; align-items: center; gap: 10px; font-size: 0.9rem; margin-bottom: 30px;">
            <input type="checkbox"> Guardar mi información y consultar más rápidamente la próxima vez
        </label>
    </section>

    <section class="metodos-envio">
        <h2 style="font-size: 1.3rem; margin-bottom: 15px;">Métodos de envío</h2>
        
        <div style="border: 1px solid blue; border-radius: 4px; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; background-color: #f0f8ff;">
            <label style="display: flex; align-items: center; gap: 10px; font-weight: bold;">
                <input type="radio" name="envio" checked> STARKEN POR PAGAR (Paga al recibir)
            </label>
            <span style="font-weight: bold;">GRATIS</span>
        </div>

        <div style="border: 1px solid #ccc; border-radius: 4px; padding: 15px; display: flex; align-items: center; justify-content: space-between;">
            <label style="display: flex; align-items: center; gap: 10px;">
                <input type="radio" name="envio"> Envío estándar a domicilio.
            </label>
            <span>$3.500</span>
        </div>
    </section>

    <section class="pago-seccion" style="margin-top: 40px;">
        <h2 style="font-size: 1.3rem; margin-bottom: 5px;">Pago</h2>
        <p style="color: #666; font-size: 0.9rem; margin-bottom: 20px;">Todas las transacciones son seguras y están encriptadas.</p>

        <div style="border: 1px solid #ccc; border-radius: 4px; overflow: hidden;">
            
            <!-- Opción: Tarjeta de crédito -->
            <div style="padding: 15px; border-bottom: 1px solid #ccc; background-color: #f8f9fa;">
                <label style="display: flex; align-items: center; gap: 10px; font-weight: bold; cursor: pointer;">
                    <input type="radio" name="metodo_pago" value="tarjeta" id="radio-tarjeta"> Tarjeta de crédito
                    <div style="margin-left: auto; display: flex; gap: 5px;">
                        <i class="fa-brands fa-cc-visa" style="color: #1a1f71; font-size: 1.5rem;"></i>
                        <i class="fa-brands fa-cc-mastercard" style="color: #eb001b; font-size: 1.5rem;"></i>
                        <i class="fa-brands fa-cc-amex" style="color: #2e77bc; font-size: 1.5rem;"></i>
                    </div>
                </label>
            </div>

            <!-- Formulario desplegable para Tarjeta -->
            <div id="form-tarjeta" style="padding: 20px; background-color: #f8f9fa; display: none; border-bottom: 1px solid #ccc;">
                <div style="position: relative; margin-bottom: 15px;">
                    <input type="text" placeholder="Número de tarjeta" style="width: 100%; padding: 12px; border: 1px solid #dc3545; border-radius: 4px;">
                    <i class="fa-solid fa-lock" style="position: absolute; right: 15px; top: 15px; color: #666;"></i>
                    <span style="color: #dc3545; font-size: 0.8rem; display: block; margin-top: 5px;">Introduce un número de tarjeta</span>
                </div>

                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <input type="text" placeholder="Fecha de vencimiento (MM / AA)" style="width: 100%; padding: 12px; border: 1px solid #dc3545; border-radius: 4px;">
                        <span style="color: #dc3545; font-size: 0.8rem; display: block; margin-top: 5px;">Introduce una fecha válida</span>
                    </div>
                    <div style="flex: 1; position: relative;">
                        <input type="text" placeholder="Código de seguridad" style="width: 100%; padding: 12px; border: 1px solid #dc3545; border-radius: 4px;">
                        <i class="fa-regular fa-circle-question" style="position: absolute; right: 15px; top: 15px; color: #666;"></i>
                        <span style="color: #dc3545; font-size: 0.8rem; display: block; margin-top: 5px;">Introduce el CVV</span>
                    </div>
                </div>

                <input type="text" value="victor bugueño" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px;">

                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <select style="width: 30%; padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
                        <option>RUT</option>
                    </select>
                    <div style="flex: 1;">
                        <input type="text" placeholder="Número de documento" style="width: 100%; padding: 12px; border: 1px solid #dc3545; border-radius: 4px;">
                        <span style="color: #dc3545; font-size: 0.8rem; display: block; margin-top: 5px;">El número es obligatorio</span>
                    </div>
                </div>

                <select style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px;">
                    <option>Cuotas</option>
                </select>
                
                <label style="display: flex; align-items: center; gap: 10px; font-size: 0.9rem; margin-top: 20px;">
                    <input type="checkbox" checked> Usar la dirección de envío como dirección de facturación
                </label>
            </div>

            <!-- Opción: Transferencia Bancaria -->
            <div style="padding: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; font-weight: bold; cursor: pointer;">
                    <input type="radio" name="metodo_pago" value="transferencia" id="radio-transferencia" checked> Paga con tu banco
                </label>
            </div>
            
            <!-- Mensaje Transferencia -->
            <div id="msg-transferencia" style="padding: 20px; background-color: #f8f9fa; border-top: 1px solid #ccc; text-align: center;">
                <i class="fa-solid fa-building-columns" style="font-size: 2rem; color: #666; margin-bottom: 15px;"></i>
                <p style="font-size: 0.9rem; color: #444;">Se te redirigirá a Paga con tu banco para completar tu compra de forma segura.</p>
            </div>

        </div>
        
        <button id="btn-finalizar-compra" style="width: 100%; padding: 15px; background: #000; color: #fff; border: none; font-weight: bold; cursor: pointer; margin-top: 30px; font-size: 1.1rem;">FINALIZAR COMPRA</button>

    </section>

</main>

<?php include 'footer.php'; ?>