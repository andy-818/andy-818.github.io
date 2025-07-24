<?php
session_start();

// Configuración de la base de datos
$host = 'localhost';
$db = 'tienda1';
$user = 'root';
$pass = 'andy123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Manejo de login/registro
$error = '';
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = "Usuario y contraseña son obligatorios";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ];
            header("Location: indexxx.php");
            exit;
        } else {
            $error = "Usuario o contraseña incorrectos";
        }
    }
}

if (isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validaciones básicas
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Todos los campos son obligatorios";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El email no tiene un formato válido";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } else {
        try {
            // Verificar si el usuario o email ya existen
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "El nombre de usuario o email ya están registrados";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (username, email, password) VALUES (?, ?, ?)");
                
                if ($stmt->execute([$username, $email, $hashed_password])) {
                    // Obtener el ID del usuario recién insertado
                    $user_id = $pdo->lastInsertId();
                    
                    $_SESSION['user'] = [
                        'id' => $user_id,
                        'username' => $username,
                        'email' => $email
                    ];
                    
                    // Redirigir después del registro exitoso
                    header("Location: indexxx.php");
                    exit;
                } else {
                    $error = "Error al registrar el usuario. Inténtalo de nuevo.";
                }
            }
        } catch (PDOException $e) {
            // Mostrar mensaje de error específico
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = "El nombre de usuario o email ya están registrados";
            } else {
                $error = "Error en el servidor: " . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['user']);
    header("Location: indexxx.php");
    exit;
}

// Inicializar carrito
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Manejo del carrito
if (isset($_POST['producto'], $_POST['talla'], $_POST['cantidad'])) {
    $producto = $_POST['producto'];
    $talla = $_POST['talla'];
    $cantidad = max(1, intval($_POST['cantidad'])); // Asegurar que sea al menos 1
    $key = $producto . ' - Talla: ' . $talla;
    $nombre_base = explode(' - L', $producto)[0];

    if (isset($_SESSION['carrito'][$key])) {
        $_SESSION['carrito'][$key]['cantidad'] += $cantidad;
    } else {
        if (preg_match('/L(\d+(?:\.\d{1,2})?)/', $producto, $match)) {
            $_SESSION['carrito'][$key] = [
                'cantidad' => $cantidad,
                'precio' => floatval($match[1]),
                'nombre_base' => $nombre_base
            ];
        }
    }
}

if (isset($_POST['eliminar'])) {
    unset($_SESSION['carrito'][$_POST['eliminar']]);
}

if (isset($_POST['vaciar'])) {
    $_SESSION['carrito'] = [];
}

if (isset($_POST['actualizar_cantidad'])) {
    foreach ($_POST['cantidades'] as $key => $cantidad) {
        $cantidad = max(1, intval($cantidad));
        if (isset($_SESSION['carrito'][$key])) {
            $_SESSION['carrito'][$key]['cantidad'] = $cantidad;
        }
    }
}

// Finalizar compra (solo si está logueado)
if (isset($_POST['finalizar_compra']) && isset($_SESSION['user'])) {
    $usuario_id = $_SESSION['user']['id'];
    $total = 0;
    
    // Calcular total
    foreach ($_SESSION['carrito'] as $item) {
        $total += $item['precio'] * $item['cantidad'];
    }
    
    // Registrar cada producto en la base de datos
    foreach ($_SESSION['carrito'] as $nombre => $datos) {
        $producto = explode(' - Talla: ', $nombre)[0];
        $talla = explode(' - Talla: ', $nombre)[1] ?? 'Única';
        
        $stmt = $pdo->prepare("INSERT INTO compras (usuario, producto, talla, cantidad, precio, total) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user']['username'],
            $producto,
            $talla,
            $datos['cantidad'],
            $datos['precio'],
            $datos['precio'] * $datos['cantidad']
        ]);
    }
    
    $_SESSION['carrito'] = [];
    $compra_exitosa = true;
}

$busqueda = isset($_GET['buscar']) ? strtolower(trim($_GET['buscar'])) : '';

// Lista completa de productos con precios individualizados en Lempiras
$productos = [
    ["Balón Profesional", "balon.jpg", 1200],
    ["Botas Elite", "botas.jpg", 2500],
    ["Camiseta del Madrid 2025", "camiseta.jpg", 900],
    ["Tacos Nike Tiempo", "tiempo.jpg", 1800],
    ["Espinilleras Nike", "chimpas.jpg", 600],
    ["Gafete Capitan Retro", "capi3.jpg", 450],
    ["Balon del mundial 2022", "mundial2022.jpg", 1500],
    ["Balon Jabulani", "Jabulani.jpg", 1300],
    ["Balon Mikasa", "micasa.jpg", 1100],
    ["Balon de la Premier", "balonpremier.jpg", 1400],
    ["Balon de la Euro 2024", "euro2024.jpg", 1600],
    ["Camiseta del Atletico 2025", "atletico.jpg", 850],
    ["Camiseta del Barsa 2025", "barsa.jpg", 850],
    ["Camiseta del Betis 2025", "betis.jpg", 800],
    ["Camiseta del Celta 2025", "celta.jpg", 800],
    ["Camiseta del Chelse 2025", "chelse.jpg", 850],
    ["Camiseta del Girona 2025", "girona.jpg", 800],
    ["Camiseta del Liverpool 2025", "liverpool.jpg", 900],
    ["Camiseta del Manchester City 2025", "cyti.jpg", 900],
    ["Camiseta del Manchesters United 2025", "United.jpg", 900],
    ["Camiseta del Porto 2025", "porto.jpg", 800],
    ["Camiseta del Newcastle 2025", "New.jpg", 800],
    ["Camiseta del Salvador 2019", "salvador.jpg", 750],
    ["Camiseta del Sevilla 2025", "sevilla.jpg", 850],
    ["Camiseta del Villareal 2025", "villa.jpg", 800],
    ["Camiseta de Alemania 2022", "alemania.jpg", 950],
    ["Camiseta de Argentina 2018", "argentina.jpg", 950],
    ["Camiseta de Brasil 2022", "brasil.png", 950],
    ["Camiseta de Costa Rica 2024", "costa.jpg", 750],
    ["Camiseta de Guatemala 2019", "GUATEMALA HOME 2019.jpg", 700],
    ["Camiseta de Honduras local 2023", "HONDURAS AWAY 2023.jpg", 750],
    ["Camiseta de Honduras Visita 2023", "HONDURAS HOME 2023.jpg", 750],
    ["Camiseta de Inglaterra 2022", "descargar.jpg", 950],
    ["Camiseta de Messi 2017/2008", "messi.jpg", 1200],
    ["Camiseta de Panama 2024", "pana.jpg", 750],
    ["Camiseta de Portugal 2022", "cr7.jpg", 1000],
    ["Camiseta del Real Madrid 2017", "cr71.jpg", 1000],
    ["Cintas", "cinta.jpg", 300],
    ["Espinilleras", "chimpas.jpg", 550],
    ["Espinilleras Nike", "chimpas2 nike.jpg", 650],
    ["Gafete Capitan", "capi.jpg", 400],
    ["Gafete Capitan Alternativo", "capi2.jpg", 400],
    ["Medias Antideslizantes Blancas", "antiblancas.jpg", 350],
    ["Medias Antideslizantes Negras", "antinegras.jpg", 350],
    ["Medias Precortadas", "mediaspre.jpg", 250],
    ["Tacos New Balance Tekela v3", "New Balance Release Laceless Tekela v3.jpg", 2000],
    ["Tacos Nike Air Zoom Mercurial Superfly", "Nike Air Zoom Mercurial Superfly.jpg", 2200],
    ["Tacos Puma Future 7 Ultimate MG", "Puma Future 7 Ultimate MG.jpg", 1900],
    ["Tacos Adidas F50 Blanco rosa", "F50 Blanco rosa.jpg", 1800],
    ["Tacos Adidas F50 elites SG negras", "adidas F50 Elite SG negras,.jpg", 2100],
    ["Tacos Future 7 Pro MG", "Botas de fútbol Puma Future 7 Pro MG.jpg", 1950],
    ["Tacos Puma Ultra Pro FG_AG", "Botas de fútbol con tacos Puma Ultra Pro FG_AG.jpg", 1850]
];

// Ordenar productos alfabéticamente
usort($productos, function($a, $b) {
    return strcmp($a[0], $b[0]);
});
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tienda de Fútbol - Lo mejor en equipamiento deportivo</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Todos los estilos CSS anteriores permanecen igual */
    /* ... */
    
    /* Agregamos estilos para la búsqueda dinámica */
    .search-results {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-radius: 0 0 5px 5px;
      max-height: 300px;
      overflow-y: auto;
      z-index: 100;
      display: none;
    }
    
    .search-results a {
      display: block;
      padding: 10px 15px;
      color: var(--dark);
      text-decoration: none;
      transition: background 0.2s;
    }
    
    .search-results a:hover {
      background: #f5f5f5;
    }
    
    .search-container {
      position: relative;
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <div class="header-container">
      <div class="logo">
        <img src="https://via.placeholder.com/40" alt="Logo Tienda">
      </div>
      <nav>
        <ul>
          <li><a href="#inicio">Inicio</a></li>
          <li><a href="#productos">Productos</a></li>
          <li><a href="#carrito">Carrito</a></li>
          <?php if (isset($_SESSION['user'])): ?>
            <li><a href="?logout=true">Cerrar Sesión (<?= htmlspecialchars($_SESSION['user']['username']) ?>)</a></li>
          <?php else: ?>
            <li><a href="#" onclick="document.getElementById('loginModal').style.display='block'">Iniciar Sesión</a></li>
          <?php endif; ?>
          <li>
            <a href="#carrito" class="cart-icon">
              <i class="fas fa-shopping-cart"></i>
              <span class="cart-count"><?= array_sum(array_column($_SESSION['carrito'], 'cantidad')) ?></span>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="hero" id="inicio">
    <div class="hero-content">
      <h2>Equipamiento de Fútbol de Primera Calidad</h2>
      <p>Encuentra todo lo que necesitas para destacar en el campo de juego</p>
      <a href="#productos" class="btn btn-primary">Ver Productos</a>
    </div>
  </section>

  <!-- Search -->
  <div class="search-container">
    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="search-box" id="searchForm">
      <input type="text" name="buscar" placeholder="Buscar productos..." value="<?php echo htmlspecialchars($busqueda); ?>" id="searchInput" autocomplete="off">
      <button type="submit"><i class="fas fa-search"></i></button>
      <div class="search-results" id="searchResults"></div>
    </form>
  </div>

  <!-- Products -->
  <section class="products-container" id="productos">
    <div class="section-title">
      <h2>Nuestros Productos</h2>
      <?php if ($busqueda !== ''): ?>
        <p style="margin-top: 10px;">Resultados de búsqueda para: "<?= htmlspecialchars($busqueda) ?>"</p>
        <a href="indexxx.php" style="display: inline-block; margin-top: 10px; color: var(--primary); text-decoration: none;">
          <i class="fas fa-times"></i> Limpiar búsqueda
        </a>
      <?php endif; ?>
    </div>
    
    <div class="products-grid">
      <?php
      $encontrado = false;
      foreach ($productos as [$nombre, $imagen, $precio]) {
          if ($busqueda === '' || stripos(strtolower($nombre), strtolower($busqueda)) !== false) {
              $encontrado = true;
              echo '<form method="POST" class="product-card">';
              echo '<div class="product-image">';
              echo '<img src="' . htmlspecialchars($imagen) . '" alt="' . htmlspecialchars($nombre) . '">';
              echo '</div>';
              echo '<div class="product-info">';
              echo '<h3 class="product-title">' . htmlspecialchars($nombre) . '</h3>';
              echo '<p class="product-price">L' . number_format($precio, 2) . '</p>';
              echo '<input type="hidden" name="producto" value="' . htmlspecialchars($nombre) . ' - L' . number_format($precio, 2) . '">';
              
              if (stripos($nombre, 'Tacos') !== false || stripos($nombre, 'Botas') !== false) {
                  echo '<div class="product-sizes">';
                  echo '<select name="talla" required>';
                  echo '<option value="">Selecciona talla</option>';
                  for ($i = 38; $i <= 43; $i++) {
                      echo '<option value="' . $i . '">' . $i . '</option>';
                  }
                  echo '</select>';
                  echo '</div>';
              } else {
                  echo '<input type="hidden" name="talla" value="Única">';
              }
              
              echo '<div class="product-quantity">';
              echo '<label for="cantidad_' . htmlspecialchars($nombre) . '">Cantidad:</label>';
              echo '<input type="number" id="cantidad_' . htmlspecialchars($nombre) . '" name="cantidad" value="1" min="1" max="10" required>';
              echo '</div>';
              
              echo '<button type="submit" class="add-to-cart">Añadir al carrito</button>';
              echo '</div>';
              echo '</form>';
          }
      }
      
      if ($busqueda !== '' && !$encontrado) {
          echo '<p style="grid-column: 1 / -1; text-align: center;">No se encontraron productos que coincidan con "' . htmlspecialchars($busqueda) . '"</p>';
      }
      ?>
    </div>
  </section>

  <!-- Carrito -->
  <section class="cart-container" id="carrito">
    <div class="section-title">
      <h2>Tu Carrito de Compras</h2>
    </div>
    
    <div class="cart">
      <?php if (empty($_SESSION['carrito'])): ?>
        <p style="text-align: center;">Tu carrito está vacío</p>
      <?php else: ?>
        <form method="POST">
          <div class="cart-header">
            <h3 class="cart-title">Productos seleccionados</h3>
          </div>
          
          <div class="cart-items">
            <?php 
            $total = 0;
            foreach ($_SESSION['carrito'] as $key => $item): 
              $subtotal = $item['precio'] * $item['cantidad'];
              $total += $subtotal;
            ?>
              <div class="cart-item">
                <div class="cart-item-info">
                  <div class="cart-item-image">
                    <?php 
                    // Buscar la imagen correspondiente al producto
                    $imagen = '';
                    foreach ($productos as $producto) {
                        if (strpos($item['nombre_base'], $producto[0]) !== false) {
                            $imagen = $producto[1];
                            break;
                        }
                    }
                    ?>
                    <img src="<?= htmlspecialchars($imagen) ?>" alt="<?= htmlspecialchars($item['nombre_base']) ?>">
                  </div>
                  <div class="cart-item-details">
                    <h4><?= htmlspecialchars($item['nombre_base']) ?></h4>
                    <p><?= htmlspecialchars(explode(' - Talla: ', $key)[1] ?? 'Talla única') ?></p>
                    <p class="cart-item-price">L<?= number_format($item['precio'], 2) ?> c/u</p>
                  </div>
                </div>
                
                <div class="cart-item-quantity">
                  <input type="number" name="cantidades[<?= htmlspecialchars($key) ?>]" value="<?= $item['cantidad'] ?>" min="1">
                  <button type="submit" name="eliminar" value="<?= htmlspecialchars($key) ?>" class="remove-item" title="Eliminar">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          
          <div class="cart-summary">
            <div class="cart-total">
              <span>Total:</span>
              <span>L<?= number_format($total, 2) ?></span>
            </div>
            
            <div class="cart-actions">
              <button type="submit" name="vaciar" class="btn btn-outline">Vaciar Carrito</button>
              <button type="submit" name="actualizar_cantidad" class="btn btn-outline">Actualizar</button>
              <?php if (isset($_SESSION['user'])): ?>
                <button type="submit" name="finalizar_compra" class="btn btn-primary">Finalizar Compra</button>
              <?php else: ?>
                <button type="button" onclick="document.getElementById('loginModal').style.display='block'" class="btn btn-primary">Iniciar Sesión para Comprar</button>
              <?php endif; ?>
            </div>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <div class="footer-container">
      <div class="footer-column">
        <h3>Sobre Nosotros</h3>
        <p>Tienda especializada en equipamiento de fútbol de la más alta calidad.</p>
      </div>
      <div class="footer-column">
        <h3>Contacto</h3>
        <ul>
          <li><i class="fas fa-map-marker-alt"></i> Dirección: Calle Fútbol, #123</li>
          <li><i class="fas fa-phone"></i> Teléfono: +123 456 7890</li>
          <li><i class="fas fa-envelope"></i> Email: info@tiendafutbol.com</li>
        </ul>
      </div>
      <div class="footer-column">
        <h3>Enlaces Rápidos</h3>
        <ul>
          <li><a href="#inicio">Inicio</a></li>
          <li><a href="#productos">Productos</a></li>
          <li><a href="#carrito">Carrito</a></li>
          <li><a href="#">Términos y Condiciones</a></li>
        </ul>
      </div>
    </div>
    <div class="copyright">
      <p>&copy; <?= date('Y') ?> Tienda de Fútbol. Todos los derechos reservados.</p>
    </div>
  </footer>

  <!-- Modal Login -->
  <div id="loginModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('loginModal').style.display='none'">&times;</span>
      <div class="modal-header">
        <h2>Iniciar Sesión</h2>
        <p>Ingresa tus credenciales para acceder</p>
      </div>
      <?php if ($error): ?>
        <div class="error">
          <i class="fas fa-exclamation-circle"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label for="username">Usuario</label>
          <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="modal-footer">
          <button type="submit" name="login" class="btn btn-primary">Ingresar</button>
          <p>¿No tienes una cuenta? <a href="#" onclick="switchModal('loginModal', 'registerModal')">Regístrate aquí</a></p>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Register -->
  <div id="registerModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('registerModal').style.display='none'">&times;</span>
      <div class="modal-header">
        <h2>Registrarse</h2>
        <p>Crea una cuenta para comenzar</p>
      </div>
      <?php if ($error): ?>
        <div class="error">
          <i class="fas fa-exclamation-circle"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>
      <form method="POST">
        <div class="form-group">
          <label for="reg_username">Usuario</label>
          <input type="text" id="reg_username" name="username" required>
        </div>
        <div class="form-group">
          <label for="reg_email">Email</label>
          <input type="email" id="reg_email" name="email" required>
        </div>
        <div class="form-group">
          <label for="reg_password">Contraseña</label>
          <input type="password" id="reg_password" name="password" required>
        </div>
        <div class="form-group">
          <label for="reg_confirm_password">Confirmar Contraseña</label>
          <input type="password" id="reg_confirm_password" name="confirm_password" required>
        </div>
        <div class="modal-footer">
          <button type="submit" name="register" class="btn btn-primary">Registrarse</button>
          <p>¿Ya tienes una cuenta? <a href="#" onclick="switchModal('registerModal', 'loginModal')">Inicia sesión aquí</a></p>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Compra Exitosa -->
  <?php if (isset($compra_exitosa) && $compra_exitosa): ?>
    <div id="successModal" class="modal" style="display: block;">
      <div class="modal-content">
        <div class="modal-header">
          <h2>¡Compra Exitosa!</h2>
        </div>
        <div class="success">
          <i class="fas fa-check-circle"></i>
          <span>Tu compra se ha realizado con éxito. Gracias por tu preferencia.</span>
        </div>
        <div class="modal-footer">
          <button onclick="document.getElementById('successModal').style.display='none'" class="btn btn-primary">Aceptar</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script>
    // Función para cambiar entre modales
    function switchModal(fromId, toId) {
      document.getElementById(fromId).style.display = 'none';
      document.getElementById(toId).style.display = 'block';
    }
    
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
      if (event.target.className === 'modal') {
        event.target.style.display = 'none';
      }
    }
    
    // Búsqueda dinámica
    document.getElementById('searchInput').addEventListener('input', function() {
      const searchTerm = this.value.toLowerCase();
      const searchResults = document.getElementById('searchResults');
      
      if (searchTerm.length < 1) {
        searchResults.style.display = 'none';
        return;
      }
      
      // Filtrar productos que coincidan con el término de búsqueda
      const filteredProducts = <?php echo json_encode(array_column($productos, 0)); ?>.filter(product => 
        product.toLowerCase().includes(searchTerm)
      );
      
      // Mostrar resultados
      if (filteredProducts.length > 0) {
        searchResults.innerHTML = filteredProducts.map(product => 
          `<a href="?buscar=${encodeURIComponent(product)}">${product}</a>`
        ).join('');
        searchResults.style.display = 'block';
      } else {
        searchResults.innerHTML = '<div style="padding: 10px 15px;">No se encontraron productos</div>';
        searchResults.style.display = 'block';
      }
    });
    
    // Ocultar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
      if (!document.getElementById('searchForm').contains(e.target)) {
        document.getElementById('searchResults').style.display = 'none';
      }
    });
  </script>
</body>
</html>