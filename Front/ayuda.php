<?php 
$titulo = "URBAN STYLE - Ayuda / Contacto";
include 'header.php'; 
?>

<main class="contacto-container">
    <h1 class="contacto-titulo">CONTACTO</h1>
    <h2 class="contacto-correo">Urban-Style1@gmail.com</h2>

    <form class="contacto-form" action="#" method="POST">
        
        <div class="form-fila">
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="email" name="correo" placeholder="Correo electrónico" required>
        </div>
        
        <div class="form-fila-completa">
            <input type="tel" name="telefono" placeholder="Teléfono">
        </div>
        
        <div class="form-fila-completa">
            <textarea name="comentario" placeholder="Comentario" required></textarea>
        </div>
        
        <div class="form-boton">
            <button type="submit" class="btn-enviar">Enviar</button>
        </div>

    </form>
</main>

<?php include 'footer.php'; ?>

