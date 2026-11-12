<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Seguro - Urban Style</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .pasarela-box { background: #fff; width: 100%; max-width: 500px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 40px; }
        .header-pasarela { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .titulo-tienda { font-weight: bold; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
        .monto { font-weight: bold; font-size: 1.2rem; }
        .opcion-pago { border: 1px solid #eaeaea; border-radius: 8px; padding: 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: 0.2s; text-decoration: none; color: inherit; }
        .opcion-pago:hover { border-color: #000; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .bancos-iconos { display: flex; gap: 5px; font-size: 1.2rem; color: #555; }
        .volver { display: block; text-align: center; margin-top: 30px; color: #666; text-decoration: none; font-size: 0.9rem; }
        .volver:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <div class="pasarela-box">
        <div class="header-pasarela">
            <div class="titulo-tienda"><i class="fa-solid fa-store"></i> URBAN STYLE</div>
            <div class="monto">CLP 63.990</div>
        </div>

        <h3 style="margin-bottom: 20px; font-size: 1.1rem; color: #333;">¿Cómo quieres pagar?</h3>

        <!-- Opción única: Transferencia -->
        <a href="#" class="opcion-pago">
            <span style="font-weight: bold; font-size: 1.1rem;">Transferencia bancaria</span>
            <div class="bancos-iconos">
                <i class="fa-solid fa-building-columns"></i>
            </div>
        </a>

        <a href="checkout.php" class="volver">Volver al comercio</a>
    </div>

</body>
</html>