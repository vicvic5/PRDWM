<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($titulo) ? $titulo : 'Urban Style'; ?></title>
    <!-- Tu archivo CSS -->
    <link rel="stylesheet" href="style.css">
    <!-- Iconos para la lupa, el login y el carrito -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <header>
        <!-- Espacio para el logo 3D a futuro -->
        <a href="index.php" class="logo" style="text-decoration: none; color: var(--texto);">URBAN STYLE</a>
        
        <nav class="nav-links">
            <a href="index.php">INICIO</a>
            <a href="shop.php">SHOP</a>
            <a href="ayuda.php">AYUDA</a>
            <a href="redes.php">REDES</a>
        </nav>
        
        <div class="nav-icons">
            <i class="fa-solid fa-magnifying-glass" title="Búsqueda"></i>
            <i class="fa-regular fa-user" title="Login / Register"></i>
            <i class="fa-solid fa-bag-shopping" title="Carrito"></i>
        </div>
    </header>