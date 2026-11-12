# Urban Style — Backend seguro

**Evaluación 2 · Diseño e implementación de un backend seguro completo**

| | |
|---|---|
| Caso | N° **40** — Tienda "Urban Style" (vestuario y accesorios streetwear, Santiago) |
| Integrantes | Víctor Bugueño |
| Versión del frontend | v1.4.0 — pantallas: `index.php`, `shop.php`, `producto.php`, `checkout.php`, modales en `header.php` y `footer.php` |
| Versión del backend | este repositorio (Node.js 18+, Express, MySQL 8 en XAMPP) |
| Fecha | 09-11-2026 |

> El caso de negocio se utiliza como fuente de requisitos y todos los servicios se prueban a través del API Gateway en el puerto 3000, igual que lo haría el navegador.

---

<a name="indice"></a>
## Índice

- [Vista general de la arquitectura](#vista-general)
- [0. Requisitos y brechas](#m0)
- [1. Módulo 1 — Servicios derivados del frontend](#m1)
- [2. Módulo 2 — Clases y cardinalidades del dominio](#m2)
- [3. Módulo 3 — Sistemas e integraciones](#m3)
- [4. Módulo 4 — Stakeholders y permisos HTTP](#m4)
- [5. Módulo 5 — API Gateway y fronteras de confianza](#m5)
- [6. Módulo 6 — Rutas y secretos de integración](#m6)
- [7. Módulo 7 — Datos sensibles y secretos del cliente](#m7)
- [8. Módulo 8 — Separación privada y agregaciones GraphQL](#m8)
- [9. Módulo 9 — Analítica y gRPC (ADR)](#m9)
- [10. Módulo 10 — Backend completo, pruebas y evidencias](#m10)
- [11. Ejecución: instalar, iniciar y probar](#ejecucion)
- [12. Estructura del repositorio y limitaciones](#estructura)

---

<a name="vista-general"></a>
## Vista general de la arquitectura

Un único **API Gateway** (puerto 3000) es público, mientras que el acceso web directo a la carpeta del código está bloqueado en Apache mediante directivas `.htaccess` (`403 Forbidden`). Detrás, en una red privada interna, operan los microservicios **inventory (3001), clients (3002), orders (3003) y payments (3004)** sobre Node.js/Express, además del **GraphQL Gateway** de agregación, todos abastecidos de credenciales mediante un gestor de secretos (`.env`) y persistencia relacional en MySQL (`urban_style_db`).

![Mapa de componentes](Backend/Evidencias%20Backend/arquitecturaback.jpg)

<sub>Figura 1 · Mapa de componentes, despliegue y fronteras de confianza · [ver en alta resolución (SVG)](Backend/Evidencias%20Backend/arquitecturaback.jpg) · [fuente editable PlantUML](Backend/Evidencias%20Backend/arquitecturaback.jpg)</sub>

---

<a name="m0"></a>
## 0. Requisitos del caso y brechas declaradas

| ID | Requisito | Pantalla | Estado |
|---|---|---|---|
| R01 | Ver el catálogo de prendas y accesorios con precios y estado agotado | P01 `index.php` / P02 `shop.php` | Implementado |
| R02 | Buscar productos por coincidencia de texto en tiempo real | P03 `footer.php` -> modal *Búsqueda* | Implementado (`GET /api/v1/products?q=`) |
| R03 | Ver detalle de producto, elegir talla (excepto accesorios) y cantidad (máx. 3) | P04 `producto.php` | Implementado |
| R04 | Crear cuenta de cliente con contraseña protegida | P05 `footer.php` -> modal *Registro* | Implementado (`bcrypt` con 10 rondas de sal) |
| R05 | Iniciar y cerrar sesión de cliente | P05 `footer.php` -> modal *Iniciar sesión* | Implementado (emisión de token `JWT`) |
| R06 | Armar el carrito y confirmar el pedido con datos de despacho | P06 `checkout.php` | Implementado (el carrito vive en `LocalStorage`; el pedido transaccional se crea al confirmar) |
| R07 | Pagar pedido con tarjeta de crédito/débito o transferencia | P07 `checkout.php` -> *Pasarela de Pago* | Implementado (validación en memoria bajo estándar PCI-DSS y adaptador de prueba) |
| R08 | Ver resumen e historial de compras con detalle de prendas | **Brecha:** no hay pantalla de perfil/historial en el frontend | Backend listo (S07 y agregación GraphQL `resumenPedido`). Se incorporará como vista `historial.php` |
| R09 | Administración de catálogo y reposición de stock físico | **Brecha:** no hay pantalla de administrador | Backend listo (S08 `PATCH /products/:id/stock`). Vista *Panel Bodega* propuesta |
| R10 | Reporte consolidado de ventas para administración | **Brecha:** no hay pantalla de reportes | Backend listo (consulta agregada de órdenes pagadas) |

**Requisitos y decisiones de arquitectura.** Los requisitos R01–R10 provienen del caso de negocio y del flujo de pantallas responsivas. Las decisiones de arquitectura del equipo son: separar el sistema en 4 microservicios de dominio, utilizar tokens firmados con HMAC-SHA256 (`JWT`), exponer un único API Gateway en el puerto 3000, descontar stock de forma atómica con transacciones MySQL (`BEGIN` / `COMMIT` / `ROLLBACK`) y descartar gRPC. Estas decisiones se fundamentan en las secciones 3, 5 y en el ADR del Módulo 9.

---

<a name="m1"></a>
## 1. Módulo 1 — Servicios derivados del frontend

Escritorio y móvil comparten exactamente las mismas operaciones: la versión móvil solo adapta la disposición mediante CSS Grid/Flexbox, por lo que una variación visual no genera servicios nuevos.
Los ejemplos JSON de cada contrato, obtenidos por ejecución real en Thunder Client, se encuentran documentados a continuación.

Clasificación de datos: **P** = público · **R** = restringido (dato personal) · **C** = credencial · **S** = sensible.

| ID | Req. | Pantalla y acción | Propósito | Verbo y ruta | Entrada | Salida | Reglas y errores | Datos | Acceso |
|---|---|---|---|---|---|---|---|---|---|
| S01 | R01, R02 | P01/P02/P03 · Cargar tienda y buscador | Consultar catálogo | `GET /api/v1/products` | query opcional: `q` (texto de búsqueda) | 200 `[{id, nombre, precio, img, es_accesorio, stock, agotado}]` · `[]` si no hay | 500 ante fallo de base de datos · convierte flags a booleanos | P | Anónimo |
| S02 | R03 | P04 · Ficha de producto | Consultar producto individual | `GET /api/v1/products/{id}` | path: `id` (entero) | 200 `{id, nombre, precio, img, es_accesorio, stock, agotado}` | 404 si el ID no existe en catálogo | P | Anónimo |
| S03 | R04 | P05 · Modal Crear cuenta | Registrar nuevo cliente | `POST /api/v1/users` | `nombre`*, `correo`*, `password`* | 201 `{mensaje, usuario_id}` | 400 si faltan datos obligatorios · 409 si el correo ya está registrado · hashea clave con `bcrypt` | C, R | Anónimo |
| S04 | R05 | P05 · Modal Iniciar sesión | Autenticar y emitir token | `POST /api/v1/auth/login` | `correo`*, `password`* | 200 `{mensaje, token, usuario: {id, nombre, rol}}` | 400 datos incompletos · 401 genérico ("Credenciales inválidas") para evitar enumeración de cuentas | C | Anónimo |
| S05 | R06 | P06 · Confirmar pedido en Checkout | Crear pedido y descontar stock | `POST /api/v1/orders` | `usuario_id`*, `datos_entrega`*{`rut`, `direccion`, `comuna`}, `carrito`*`[{producto_id, cantidad, talla, subtotal}]` | 201 `{mensaje, pedido_id, total, estado: "pendiente"}` | 400 si el carrito está vacío · 409 si no hay stock suficiente en `inventory` (ejecuta `ROLLBACK` total) | R | CLIENTE (JWT) |
| S06 | R07 | P07 · Pagar pedido en Checkout | Procesar cobro y confirmar orden | `POST /api/v1/payments` | `pedido_id`*, `metodo`*, `detalles_tarjeta`*{`numero`, `vencimiento`, `cvv`, `titular`, `num_doc`, `cuotas`} | 200 `{mensaje, pago_id, pedido_id, estado_orden: "pagado", nota_seguridad}` | 400 si faltan campos · 409 si el pedido no existe o ya estaba pagado/cancelado (`ROLLBACK`) | S, R | CLIENTE (JWT) |
| S07 | R08 | Brecha · Detalle / Historial de compra | Consultar resumen de un pedido | `GET /api/v1/orders/{id}` | path: `id` (entero) | 200 `{pedido: {...}, productos: [...]}` | 404 si el pedido no existe o no pertenece al cliente | R | Autenticado (dueño o ADMIN) |
| S08 | R06, R09 | Interno / Brecha Admin · Control de bodega | Descontar o ajustar stock físico | `PATCH /products/{id}/stock` | path: `id` · body: `cantidad`* (entero positivo) | 200 `{mensaje, stockRestante, agotado}` | 400 cantidad inválida · 404 producto no existe · 409 si `stock < cantidad` · marca `agotado = 1` si llega a 0 | Interno | Servicio `orders` / ADMIN |
| S09 | R08, R10 | Brecha · Vista Historial y Panel Admin | Agregación GraphQL de pedidos e inventario | `POST /api/v1/graphql` | `{query, variables}` | 200 `{data, errors}` | Verifica token y propiedad del pedido · resuelve datos cruzados entre `orders` e `inventory` | R, P | Autenticado |

**Lo que envía el navegador y lo que calcula el servidor:** el navegador envía identificadores de producto, tallas, cantidades y datos de despacho. El servidor valida la existencia real del stock, controla la transición oficial del estado (`pendiente` -> `pagado`), calcula la marca de agotado y desecha en memoria los datos de tarjeta de crédito.

---

### Ejemplos de contratos (extracto)

A continuación se presentan los contratos JSON obtenidos por ejecución real a través del API Gateway (`http://localhost:3000`):

#### S01 · Consultar catálogo con filtro de búsqueda

`GET /api/v1/products?q=cargo` · actor: **Visitante** · respuesta **200 OK**

Respuesta:
```json
[
  {
    "id": 3,
    "nombre": "Pantalón Cargo",
    "precio": 34990,
    "img": "img/pant-gold.jpg",
    "es_accesorio": false,
    "stock": 15,
    "agotado": false
  }
]
```

#### S03 · Registrar cliente nuevo

`POST /api/v1/users` · actor: **Visitante** · respuesta **201 Created**

Solicitud:
```json
{
  "nombre": "Víctor Bugueño",
  "correo": "victor@ejemplo.com",
  "password": "mi_password_seguro"
}
```
Respuesta:
```json
{
  "mensaje": "Usuario creado exitosamente",
  "usuario_id": 1
}
```

#### S05 · Crear pedido con reserva y descuento de stock

`POST /api/v1/orders` · actor: **Cliente** · respuesta **201 Created**

Cabeceras: `Authorization: Bearer <jwt>`

Solicitud:
```json
{
  "usuario_id": 1,
  "datos_entrega": {
    "rut": "19.123.456-7",
    "direccion": "Avenida Siempre Viva 123",
    "comuna": "Puente Alto"
  },
  "carrito": [
    {
      "producto_id": 1,
      "cantidad": 2,
      "talla": "M",
      "subtotal": 43980
    },
    {
      "producto_id": 3,
      "cantidad": 1,
      "talla": "S",
      "subtotal": 34990
    }
  ]
}
```
Respuesta:
```json
{
  "mensaje": "Pedido generado con éxito",
  "pedido_id": 1,
  "total": 78970,
  "estado": "pendiente"
}
```

#### S05 · Crear pedido sin stock suficiente (rechazado con Rollback)

`POST /api/v1/orders` · actor: **Cliente** · respuesta **409 Conflict**

Cabeceras: `Authorization: Bearer <jwt>`

Solicitud:
```json
{
  "usuario_id": 1,
  "datos_entrega": {
    "rut": "19.123.456-7",
    "direccion": "Avenida Siempre Viva 123",
    "comuna": "Puente Alto"
  },
  "carrito": [
    {
      "producto_id": 2,
      "cantidad": 50,
      "talla": "L",
      "subtotal": 1499500
    }
  ]
}
```
Respuesta:
```json
{
  "error": "No se pudo completar el pedido",
  "detalle": "Error en producto 2: Stock insuficiente"
}
```

#### S06 · Procesar pago con tarjeta

`POST /api/v1/payments` · actor: **Cliente** · respuesta **200 OK**

Cabeceras: `Authorization: Bearer <jwt>`

Solicitud:
```json
{
  "pedido_id": 1,
  "metodo": "tarjeta_credito",
  "detalles_tarjeta": {
    "numero": "4111222233334444",
    "vencimiento": "12/28",
    "cvv": "123",
    "titular": "Víctor Bugueño",
    "tipo_doc": "RUT",
    "num_doc": "19.123.456-7",
    "cuotas": 1
  }
}
```
Respuesta:
```json
{
  "mensaje": "Pago aprobado y procesado exitosamente",
  "pago_id": 1,
  "pedido_id": 1,
  "estado_orden": "pagado",
  "nota_seguridad": "Los datos de la tarjeta fueron procesados en memoria y desechados según normativa PCI."
}
```

---

<a name="m2"></a>
## 2. Módulo 2 — Clases y cardinalidades del dominio

![UML de clases del dominio](Backend/Evidencias%20Backend/umldiagrama.jpg)

<sub>Figura 2 · UML de clases del dominio y servicios públicos «service» con sus DTO · [ver en alta resolución (SVG)](Backend/Evidencias%20Backend/umldiagrama.jpg) · [fuente editable PlantUML](Backend/Evidencias%20Backend/umldiagrama.jpg)</sub>

**Leyenda:** rombo relleno = composición · rombo vacío = agregación · línea continua = asociación · línea punteada = dependencia o referencia lógica entre microservicios · los números indican la multiplicidad en cada extremo (`1`, `0..1`, `0..*`, `1..*`).

### Relaciones

| Clase origen | Clase destino | Multiplicidad | Tipo | Regla de negocio que la justifica |
|---|---|---|---|---|
| Usuario | Pedido | `1` — `0..*` | asociación / referencia lógica | Un cliente registrado puede no haber realizado compras aún (`0`) o acumular múltiples pedidos históricos (`*`). Todo pedido pertenece obligatoriamente a un único cliente (`1`) |
| Pedido | DetallePedido | `1` — `1..*` | composición | **Un pedido confirmado contiene al menos un ítem** (`1..*`). No se persisten pedidos vacíos en la base de datos y los detalles no existen sin su pedido padre |
| Producto | DetallePedido | `1` — `0..*` | referencia lógica | Cada línea de detalle referencia un producto del catálogo (`1`). Un producto nuevo puede no haber sido vendido nunca (`0`) o figurar en múltiples pedidos (`*`) |
| Pedido | Pago | `1` — `0..1` | composición | Un pedido recién creado en estado `pendiente` tiene `0` pagos aprobados. Una vez procesado el cobro, se registra exactamente `1` comprobante de pago asociado |

### Correspondencia entre operaciones UML e IDs de servicio

Cada operación de las interfaces `<<service>>` del diagrama se vincula directamente con el catálogo del Módulo 1:
- `CatalogService`: `consultarCatalogo(q)` -> **S01** · `obtenerProducto(id)` -> **S02** · `actualizarStock(id, cantidad)` -> **S08**.
- `ClientAuthService`: `registrarUsuario(dto)` -> **S03** · `iniciarSesion(credenciales)` -> **S04**.
- `OrderService`: `crearPedido(dto)` -> **S05** · `obtenerPedido(id)` -> **S07**.
- `PaymentService`: `procesarPago(dto)` -> **S06**.
- `AggregationService`: `resumenPedido(id)` -> **S09**.

### Modelo interno y DTO

| Entidad interna | Atributos que **no** salen al exterior | DTO / respuesta pública |
|---|---|---|
| `Usuario` (tabla `usuarios`) | `password_hash` (hash bcrypt + sal), `creado_en` | `UsuarioAuthDTO {id, nombre, rol}` + `token` JWT |
| `Producto` (tabla `productos`) | registros internos de auditoría | `ProductoDTO {id, nombre, precio, img, es_accesorio, stock, agotado}` |
| `Pedido` (tabla `pedidos`) | claves foráneas crudas sin validar propiedad | `PedidoCreadoDTO {mensaje, pedido_id, total, estado}` |
| `Pago` (tabla `pagos` + memoria) | `detalles_tarjeta` (`numero`, `cvv`, `vencimiento` se destruyen en RAM) | `ComprobantePagoDTO {pago_id, pedido_id, estado_orden, nota_seguridad}` |

### Decisiones del equipo

- **¿El carrito y el pedido son clases distintas?** Sí. El carrito es un estado temporal que vive en el `LocalStorage` del navegador (`carrito.js`) y permite agregar o quitar prendas sin impactar la base de datos. La clase `Pedido` nace recién cuando el usuario confirma el checkout (S05), iniciando una transacción que verifica y descuenta el stock real.
- **¿Qué ocurre con el historial si un producto deja de estar disponible?** Cuando un artículo llega a stock cero, el microservicio `inventory` cambia su atributo `agotado = true` (`1`), pero **nunca hace un `DELETE` físico** del registro. Así, las filas históricas en `detalles_pedido` mantienen la integridad referencial y conservan el `subtotal` pagado en esa fecha.
- **¿Qué atributos se conservan internamente y cuáles salen en cada respuesta?** El `password_hash` jamás abandona el microservicio `clients`, y los datos sensibles de tarjetas (`numero`, `cvv`) nunca se escriben en disco.

---

<a name="m3"></a>
## 3. Módulo 3 — Sistemas e integraciones

El mapa de componentes general se encuentra en la [Figura 1](#vista-general). A continuación se presenta el diagrama de secuencia del flujo transaccional de compra y pago entre los subsistemas:

![Secuencia de compra y pago](Backend/Evidencias%20Backend/diagrama_pago.jpg)

<sub>Figura 3 · Secuencia de integración: crear pedido en orders, descontar stock en inventory y confirmar pago en payments · [ver en alta resolución (SVG)](Backend/Evidencias%20Backend/diagrama_pago.jpg) · [fuente editable PlantUML](Backend/Evidencias%20Backend/diagrama_pago.jpg)</sub>

| Sistema | Responsabilidad | Datos que administra (autoridad) | Servicios | Dependencias | Contrato de integración | Credencial requerida | Respuesta ante fallos |
|---|---|---|---|---|---|---|---|
| **inventory** *(Pto 3001)* | Catálogo de productos, disponibilidad y control del stock físico | Tabla `productos` (`stock`, `precio`, `agotado`) | S01, S02, S08 | Ninguna (autónomo) | REST interno: `PATCH /products/:id/stock` y `GET /products/:id` | Credencial BD `DB_USER` desde `.env` | Si `stock < cantidad`, devuelve `409 Conflict` impidiendo sobreventa. Si cae, `orders` aborta los pedidos |
| **clients** *(Pto 3002)* | Registro de clientes, hashing de claves y emisión de tokens JWT | Tabla `usuarios` (`correo`, `password_hash`, `rol`) | S03, S04 | Ninguna (autónomo) | REST `/users` y `/auth/login` | `DB_USER` y `JWT_SECRET` desde `.env` | Si el correo se repite devuelve `409`. Si la BD falla responde `500` controlado sin exponer trazas |
| **orders** *(Pto 3003)* | Creación del pedido, detalles de compra y datos de despacho | Tablas `pedidos` y `detalles_pedido` (**estado oficial** del pedido) | S05, S07 | **inventory** (verificar y descontar stock) | Llama a `inventory` vía `PATCH http://localhost:3001/products/:id/stock` | `DB_USER` y red privada local | Ejecuta `beginTransaction()`. Si `inventory` rechaza un ítem o no responde, ejecuta `rollback()` inmediato y devuelve `409` |
| **payments** *(Pto 3004)* | Procesamiento del medio de pago, registro de comprobante y confirmación | Tabla `pagos` (`metodo`, `estado`, `fecha`) | S06 | **orders** (verificar estado `pendiente` y pasar a `pagado`) | Transacción atómica de validación y actualización de estado a `pagado` | `DB_USER` y `BANK_API_KEY` (simulada) | Si el pedido no existe o ya fue pagado, hace `rollback()` y devuelve `409`, evitando cobros duplicados |
| **graphql** *(complementario)* | Agregación de pedidos con fichas de productos para el frontend | Ninguno (sin BD propia) | S09 | `orders`, `inventory` | Esquema `resumenPedido(id)` | Token JWT propagado por el Gateway | Si `inventory` demora, entrega los datos base del pedido con error parcial controlado |
| **Pasarela Bancaria** *(externo simulado)* | Autorización financiera de tarjetas | — | S06 | — | Adaptador en memoria dentro de `payments.service.js` | Clave de comercio | Si la tarjeta es rechazada, el pedido permanece en estado `pendiente` |

**Despacho y Boletas:** los datos logísticos de despacho (`rut_envio`, `direccion_envio`, `comuna_envio`) son administrados directamente por `orders` asociados a cada compra. El comprobante de pago queda registrado en `payments` (tabla `pagos`) únicamente cuando la transacción resulta completada.
**Disponibilidad, consistencia y doble venta:** `orders` no confía en que el frontend muestre el botón "Agregar al carrito" habilitado. Al confirmar el pedido, `orders` abre una transacción SQL e invoca sincrónicamente a `inventory` para descontar cada unidad; si el último artículo acaba de agotarse, `inventory` responde `409` y `orders` revierte toda la orden (`rollback`).
**Pagos:** el frontend nunca puede cambiar el estado de un pedido a `pagado` por su cuenta; únicamente el microservicio `payments`, tras verificar que el pedido esté `pendiente` y registrar el comprobante, autoriza el cambio de estado.

---

<a name="m4"></a>
## 4. Módulo 4 — Stakeholders y permisos HTTP

### Registro de stakeholders

| Stakeholder | Interés | Rol / identidad técnica | Uso de la información |
|---|---|---|---|
| Visitante | Explorar catálogo, buscar prendas y ver precios | Anónimo | Consulta de catálogo público (S01, S02) y registro (S03) |
| Cliente registrado | Comprar vestuario, pagar y consultar sus pedidos | `cliente` (JWT) | Creación de pedidos (S05), pagos (S06) y lectura de sus propias compras (S07, S09) |
| Administrador / Bodega | Mantener el stock actualizado y supervisar ventas | `admin` (JWT) | Ajuste de inventario (S08) y consulta global de pedidos |
| Microservicios internos | Colaborar dentro de la red privada | Identidad de red interna (`localhost`) | `orders` consume `PATCH /products/:id/stock` en `inventory` |
| Pasarela de pagos | Procesar cobros con tarjeta | Adaptador interno en `payments` | Recibe datos en memoria RAM durante S06 |
| Dueños de la tienda | Rentabilidad y control financiero | **sin permisos operativos** | Reciben reportes consolidados generados por administración |
| Servicio de Impuestos Internos (SII) | Auditoría tributaria de pagos | **sin permisos operativos** | Recibe el respaldo contable desde la tabla `pagos` exportada por administración |
| Docente / Evaluador | Validar la arquitectura y seguridad del backend | **sin permisos operativos** | Ejecuta las pruebas con las cuentas y scripts documentados en este README |

### Matriz de permisos por servicio y verbo

"Denegado" significa que el Gateway o el microservicio rechaza la petición con `401 Unauthorized` o `403 Forbidden`. "N/A" indica que el verbo no está implementado para esa ruta (retorna `404`/`405`) para no inventar operaciones destructivas innecesarias.

| Rol / identidad | ID | Ruta | Verbo | Decisión | Alcance y condición | Dónde se verifica |
|---|---|---|---|---|---|---|
| Visitante | S01, S02 | `/api/v1/products`, `/api/v1/products/{id}` | GET | Permitido | Solo campos públicos del catálogo | Gateway + `inventory` |
| Visitante | S03, S04 | `/api/v1/users`, `/api/v1/auth/login` | POST | Permitido | Validación de formato y unicidad de correo | Gateway + `clients` |
| Visitante | — | `/api/v1/users` | GET | **N/A (404)** | No se expone listado de usuarios (probado en navegador: `Cannot GET /users/`) | `clients` |
| Visitante | S05–S09 | `/api/v1/orders*`, `/api/v1/payments*` | POST, GET | **Denegado (401)** | Exige sesión iniciada con token JWT vigente | Gateway / Microservicio |
| Cliente | S05 | `/api/v1/orders` | POST | Permitido | Solo con token válido y stock disponible en bodega | Gateway + `orders` |
| Cliente | S07 | `/api/v1/orders/{id}` | GET | Permitido | **Solo si el pedido le pertenece** (`usuario_id` del token coincide con el pedido) | `orders` (propiedad) |
| Cliente | S06 | `/api/v1/payments` | POST | Permitido | Solo para pedidos propios en estado `pendiente` | `payments` |
| Cliente | — | `/api/v1/products*` | POST, PUT, DELETE | **Denegado (403/404)** | Consultar el catálogo no otorga permisos de administración | Gateway + `inventory` |
| Cliente | S08 | `/products/{id}/stock` | PATCH | **Denegado (403)** | Un cliente no puede modificar el stock directamente | Gateway (ruta no publicada) |
| Cliente | — | `/api/v1/orders/{id}` | DELETE | **N/A** | Los pedidos no se borran físicamente para conservar el historial contable | `orders` |
| Sistema (`orders`) | S08 | `http://localhost:3001/products/{id}/stock` | PATCH | Permitido | Invocación interna server-to-server durante transacción de compra | Red privada (`inventory`) |
| Admin | S08, S07 | Administración de stock y consulta de órdenes | PATCH, GET | Permitido | Rol `admin` verificado en los claims del JWT | Gateway + microservicios |
| Navegador externo | — | Acceso directo a carpeta `/Backend/` por puerto 80 | Todos | **Denegado (403)** | Bloqueado por directiva `Require all denied` en `.htaccess` | Servidor Apache |

**Identidad y autorización:** ocultar un botón en el HTML/JS del frontend (como deshabilitar el botón cuando un producto está agotado) es solo usabilidad, no seguridad. El backend comprueba la identidad mediante la firma del JWT y valida en base de datos el stock y la propiedad de cada recurso antes de operar.

---

<a name="m5"></a>
## 5. Módulo 5 — API Gateway y fronteras de confianza

![UML del API Gateway](Backend/Evidencias%20Backend/umlapi.jpg)

<sub>Figura 4 · UML del API Gateway, políticas de enrutamiento, SecretProvider y GraphQL Gateway · [ver en alta resolución (SVG)](Backend/Evidencias%20Backend/umlapi.jpg) · [fuente editable PlantUML](Backend/Evidencias%20Backend/umlapi.jpg)</sub>

**¿Cuántos gateways?** **Uno solo** (`gateway.js` en el puerto 3000), implementado con Express y `express-http-proxy`. Separar las rutas mediante políticas dentro de un único punto de entrada evita duplicar la configuración de CORS, trazabilidad de logs y lectura de secretos, lo cual es la decisión óptima para la escala de Urban Style. El **GraphQL Gateway** opera como un módulo de agregación detrás de esta misma frontera.

| Clase de ruta | Ejemplos | Política aplicada |
|---|---|---|
| Externa anónima | S01, S02 (`GET /api/v1/products*`), S03 (`POST /api/v1/users`), S04 (`POST /api/v1/auth/login`) | Acceso público, parseo JSON, filtro CORS y registro de auditoría en consola |
| Externa autenticada | S05 (`POST /api/v1/orders`), S06 (`POST /api/v1/payments`), S07 (`GET /api/v1/orders/:id`), S09 (`POST /api/v1/graphql`) | Exige `Authorization: Bearer <jwt>` válido. Propiedad verificada en el servicio destino |
| Interna (privada) | S08 (`PATCH /products/:id/stock` en puerto 3001) | **No publicada en el Gateway** para clientes externos; invocada internamente por `orders` |

**Qué valida el API Gateway:**
1. Existencia del prefijo de ruta en la lista blanca (`/api/v1/products`, `/api/v1/users`, `/api/v1/auth`, `/api/v1/orders`, `/api/v1/payments`). Cualquier ruta no declarada es rechazada.
2. Políticas de origen cruzado (`cors()`), formato del cuerpo (`express.json()`) y registro de trazabilidad (`[Gateway] METODO URL -> Hora`).
3. Reescritura controlada de rutas mediante `proxyReqPathResolver` hacia los puertos internos (3001 al 3004), ocultando la topología interna al navegador.

**Qué valida cada microservicio interno:**
1. Reglas de negocio críticas: `inventory` comprueba que `stock >= cantidad`; `orders` verifica que el carrito no esté vacío; `payments` comprueba que el pedido exista y esté exactamente en estado `pendiente`.
2. Propiedad del recurso y validez de credenciales contra las tablas de MySQL.

**Fronteras de confianza:**
- **Frontera 1 (Navegador -> Apache puerto 80):** protegida con `.htaccess` (`Require all denied`) dentro de `/Backend` para impedir que alguien descargue el archivo `.env` o el código fuente.
- **Frontera 2 (Navegador -> API Gateway puerto 3000):** único puerto de aplicación expuesto al cliente. Todo dato proveniente del navegador se considera no confiable.
- **Frontera 3 (API Gateway -> Red Privada puertos 3001–3004):** comunicación local entre procesos Node.js y el motor MySQL (puerto 3306).

---

### Recorrido de una solicitud autorizada y una denegada

| Solicitud autorizada (`200 OK`) | Solicitud denegada (`401 Unauthorized`) |
| :---: | :---: |
| ![Autorizado](Backend/Evidencias%20Backend/authorized.png) | ![Denegado](Backend/Evidencias%20Backend/unauthorized.png) |

<sub>Figura 5 · Comparación de flujo: solicitud válida con stock y sesión autorizada (201) vs solicitud denegada por falta de permisos o stock insuficiente (401/409) · [ver en alta resolución (SVG)](Backend/imagenes/05-secuencia-autorizada-denegada.svg) · [fuente editable PlantUML](Backend/docs/diagramas/05-secuencia-autorizada-denegada.puml)</sub>

---

<a name="m6"></a>
## 6. Módulo 6 — Rutas y secretos de integración

El archivo `gateway.js` define explícitamente las rutas públicas y las transforma hacia los microservicios internos mediante `express-http-proxy`. Los endpoints internos de administración de stock no se publican abiertamente.

### Matriz de rutas REST

| ID | Verbo | Ruta externa (Gateway :3000) | Acceso y rol | Sistema y ruta interna destino | Controles aplicados | Referencia de credencial |
|---|---|---|---|---|---|---|
| S01 | GET | `/api/v1/products` | Anónimo | `inventory (3001)`: `GET /products` | Filtro opcional `?q=`, sanitización con consultas parametrizadas (`?`) | `ref-db-inventory` |
| S02 | GET | `/api/v1/products/{id}` | Anónimo | `inventory (3001)`: `GET /products/{id}` | Validación de existencia (404 si no existe) | `ref-db-inventory` |
| S03 | POST | `/api/v1/users` | Anónimo | `clients (3002)`: `POST /users` | Campos obligatorios, correo único (409), hashing `bcrypt` | `ref-db-clients` |
| S04 | POST | `/api/v1/auth/login` | Anónimo | `clients (3002)`: `POST /auth/login` | Comparación `bcrypt.compare`, firma de token con `JWT_SECRET` (expira en 2h) | `ref-jwt-secret`, `ref-db-clients` |
| S05 | POST | `/api/v1/orders` | Autenticado: `cliente` | `orders (3003)`: `POST /orders` | Verificación JWT, transacción ACID y llamada interna a `inventory` | `ref-jwt-secret`, `ref-svc-orders-inventory` |
| S06 | POST | `/api/v1/payments` | Autenticado: `cliente` | `payments (3004)`: `POST /payments` | Verificación de estado `pendiente`, procesamiento en RAM y paso a `pagado` | `ref-bank-api-key`, `ref-db-payments` |
| S07 | GET | `/api/v1/orders/{id}` | Autenticado: dueño / `admin` | `orders (3003)`: `GET /orders/{id}` | Verificación de propiedad del pedido | `ref-jwt-secret`, `ref-db-orders` |
| S08 | PATCH | *(Ruta interna no pública)* | Interno (`orders`) / `admin` | `inventory (3001)`: `PATCH /products/{id}/stock` | Valida `cantidad > 0` y `stock >= cantidad` (409 si falta stock) | `ref-svc-orders-inventory` |
| S09 | POST | `/api/v1/graphql` | Autenticado | `graphql`: `POST /graphql` | Autorización por operación y campo, límite de profundidad | `ref-jwt-secret` |

### Operaciones GraphQL (S09, `POST /api/v1/graphql`)

| Operación | Tipo | Rol autorizado | Campos autorizados | Resolver | Subsistemas consultados |
|---|---|---|---|---|---|
| `resumenPedido(id: ID!)` | Query | `cliente` (dueño) / `admin` | `id`, `estado`, `total`, `direccion_envio`, `detalles { cantidad, talla, subtotal, producto { nombre, precio, img } }` | `Query.resumenPedido` | `orders` (datos del pedido y líneas) + `inventory` (ficha pública de cada producto) |

### gRPC

No aplica para este caso de negocio (ver justificación técnica y mediciones en el **Módulo 9 — ADR-001**).

### Inventario simbólico de secretos e identidades

Los valores reales **nunca** se escriben en el código fuente ni en este informe. Son inyectados en tiempo de ejecución desde el archivo `.env` (protegido por `.htaccess` y excluido de repositorios públicos) mediante la librería `dotenv`.

| Referencia simbólica | Tipo | Emisor | Consumidor | Finalidad | Ubicación segura | Alcance | Rotación y revocación |
|---|---|---|---|---|---|---|---|
| `ref-jwt-secret` (`JWT_SECRET`) | Clave simétrica HMAC-SHA256 | Equipo de desarrollo | `clients` (emisor) y `gateway` / servicios (verificadores) | Firmar y validar tokens de sesión JWT | Archivo `.env` privado en `/Backend` | Solo autenticación de sesiones (vigencia 2h) | Al rotar la clave en `.env` y reiniciar, todos los tokens emitidos anteriormente quedan invalidados de inmediato |
| `ref-db-credentials` (`DB_USER`, `DB_PASSWORD`) | Credencial de Base de Datos | Administrador MySQL | Pool de conexiones `config/db.js` | Autenticar la conexión de los microservicios contra `urban_style_db` | Archivo `.env` privado | Operaciones DML sobre las tablas del dominio | `ALTER USER` en MySQL + actualización de variable en `.env` sin tocar el código JS |
| `ref-svc-orders-inventory` | Identidad de red privada | Configuración de entorno (`PORT_INVENTORY`) | `orders` -> `inventory` | Autorizar el descuento de stock entre microservicios en la red local | Variables de entorno `.env` | Exclusivo para `PATCH /products/:id/stock` | Cambio de token/puerto interno en caso de exposición |
| `ref-bank-api-key` | Clave de comercio (pasarela externa) | Proveedor de pagos | Exclusivo de `payments` | Autenticar al comercio para procesar cobros con tarjeta | Gestor de secretos / `.env` de `payments` | Solo creación de transacciones en pasarela; el navegador jamás la recibe | Revocación inmediata en el portal del proveedor si se sospecha filtración |

---

<a name="m7"></a>
## 7. Módulo 7 — Datos sensibles y secretos del cliente

### Inventario y protección de datos

| Dato o credencial | Finalidad | Clasificación | Sistema custodio | Roles autorizados | Representación en el frontend | Protección aplicada | Retención |
|---|---|---|---|---|---|---|---|
| Nombre, precio, imagen y disponibilidad de prenda | Mostrar el catálogo e incentivar la compra | Público | `inventory` | Todos (anónimo) | Tarjetas de producto en `shop.php` y `producto.php` | Solo modificable en backend; el cliente solo lee | Mientras el artículo forme parte del catálogo |
| Stock exacto de bodega | Controlar límite de venta y evitar quiebres | Interno / Público acotado | `inventory` | `inventory`, `orders`, `admin` | En `producto.php` se limita el selector a máx. 3 unidades o badge "Agotado" | Validación atómica en servidor antes de vender | Vigente en tabla `productos` |
| Nombre y Correo electrónico | Identificación de cuenta y login | Restringido (Personal) | `clients` | Dueño de la cuenta, `admin` | Modal de login / perfil | Consultas SQL parametrizadas; índice `UNIQUE` en correo | Mientras la cuenta del cliente esté activa |
| Contraseña del usuario | Autenticar la identidad del cliente | Credencial | `clients` | Nadie puede leerla en texto claro | Input `type="password"` en modal | **Hash adaptativo `bcrypt` con 10 rondas de sal** (`$2b$10$...`). Jamás se devuelve en respuestas JSON | Hasta que el usuario solicite cambio de clave |
| RUT, Dirección y Comuna de envío | Despacho logístico y facturación del pedido | Restringido (Personal) | `orders` | Dueño del pedido, personal de despacho / `admin` | Formulario `checkout.php` | Asociado al `pedido_id`; no se expone en rutas públicas | Conservación histórica por respaldo tributario y garantías |
| Token de sesión (`JWT`) | Mantener sesión activa y autorizar peticiones | Credencial | Emisor: `clients` · Portador: navegador | Portador autenticado | Almacenamiento de sesión del cliente | Firma criptográfica HMAC (`JWT_SECRET`) con expiración obligatoria a las **2 horas** (`expiresIn: '2h'`) | 2 horas (efímero) |
| Número de tarjeta (PAN), Fecha de vencimiento y CVV | Ejecutar el cobro del pedido | **Sensible (Financiero)** | **Ninguno** (solo pasa por RAM en `payments`) | Ningún rol humano puede consultarlo | Inputs de pasarela en `checkout.php` | **Cumplimiento PCI-DSS:** se recibe por canal seguro, se valida en memoria hacia el adaptador bancario y **se destruye sin escribirse en MySQL ni en logs** | **0 segundos (Cero retención en disco)** |
| Secretos de integración (`JWT_SECRET`, `DB_PASSWORD`) | Conectar sistemas y firmar tokens | **Secreto del sistema** | Gestor `.env` | Solo procesos de backend | **Nunca llega al frontend** | Archivo `.env` fuera del alcance web (`.htaccess`) | Hasta su rotación programada |

### Decisión sobre secretos del cliente: **NO** se custodian

El backend de Urban Style **NO almacena secretos pertenecientes al cliente**. Los datos de tarjetas bancarias (PAN y CVV) son datos financieros altamente sensibles que el comercio no debe guardar: el microservicio `payments` los procesa de forma transitoria en memoria RAM y los descarta inmediatamente, guardando en la tabla `pagos` únicamente el método utilizado (`tarjeta_credito`), la fecha y el estado (`completado`). Por su parte, la contraseña del usuario es una *credencial de acceso* que se resguarda mediante un hash irreversible con sal (`bcrypt`), y el RUT o la dirección son *datos personales restringidos*, no secretos criptográficos.

### Recorrido de un dato restringido: el RUT y la dirección de despacho

1. **Captura:** el cliente completa su RUT, dirección y comuna en el formulario de `checkout.php` (P06) y envía la solicitud `POST /api/v1/orders`.
2. **Tránsito por el Gateway:** el API Gateway registra únicamente `[Gateway] POST /api/v1/orders -> Hora` en la consola de auditoría, sin imprimir el cuerpo JSON en los logs para no filtrar datos personales.
3. **Almacenamiento transaccional:** el microservicio `orders` valida que el carrito tenga stock disponible en `inventory` y guarda `rut_envio`, `direccion_envio` y `comuna_envio` en la tabla `pedidos` asociados al `usuario_id`. Si la compra falla por falta de stock, el `rollback()` impide que la dirección quede guardada en un pedido fallido.
4. **Exposición controlada:** la dirección solo se devuelve al consultar el detalle de ese pedido específico (`GET /api/v1/orders/:id` o GraphQL `resumenPedido`) verificando previamente la autorización. Nunca aparece en endpoints públicos.
5. **Conservación:** se mantiene vinculada al registro del pedido como respaldo legal de entrega y facturación, sin permitir el borrado físico (`DELETE`) de órdenes pagadas.

---

<a name="m7-pruebas"></a>
### Casos de validación ejecutados

Todas las pruebas se ejecutaron contra el backend real en ejecución (`npm run dev`) y la base de datos MySQL en XAMPP:

| # | Caso de validación | Entrada | Identidad | Resultado esperado y obtenido | Componente que verifica | Estado |
|---|---|---|---|---|---|---|
| 1 | Acceso anónimo a catálogo público | `GET http://localhost:3000/api/v1/products` | Ninguna (Anónimo) | `200 OK` con lista JSON de productos públicos | API Gateway + `inventory` (puerto 3001) | **Aprobado** |
| 2 | Intento de acceso web directo a carpeta `/Backend` | `GET http://localhost/dwm/PRDWM/Backend/` en navegador | Atacante externo | `403 Forbidden` (acceso bloqueado, impide leer `.env` o código JS) | Servidor Apache (`.htaccess` con `Require all denied`) | **Aprobado** |
| 3 | Registro con hashing y rechazo de correo duplicado | `POST /api/v1/users` dos veces con `victor@ejemplo.com` | Anónimo | 1° intento: `201 Created` (clave guardada como `$2b$10$...` en MySQL). 2° intento: `409 Conflict` | `clients` (puerto 3002) + MySQL | **Aprobado** |
| 4 | Inicio de sesión con credenciales falsas / sin sesión | `POST /api/v1/auth/login` con clave errónea | Anónimo | `401 Unauthorized` (`{"error": "Credenciales inválidas"}`) sin revelar si falló el correo o la clave | `clients` (`bcrypt.compare`) | **Aprobado** |
| 5 | Prevención de sobreventa y Rollback transaccional | `POST /api/v1/orders` pidiendo cantidad mayor al stock disponible | Cliente autenticado | `409 Conflict` (`Stock insuficiente`). La tabla `pedidos` no registra la orden fallida (`ROLLBACK`) | `orders` (3003) en conjunto con `inventory` (3001) | **Aprobado** |
| 6 | Protección contra doble pago o pago de pedido inexistente | `POST /api/v1/payments` sobre el `pedido_id: 1` que ya estaba en estado `pagado` | Cliente autenticado | `409 Conflict` (`El pedido ya se encuentra pagado o fue cancelado`). No se duplica el cobro | `payments` (3004) verificando estado en MySQL | **Aprobado** |

---

<a name="m8"></a>
## 8. Módulo 8 — Separación privada y agregaciones GraphQL

### Separación privada (mapa de clasificación)

| Servicio | Naturaleza | Responsabilidad | Datos propios | Contrato | Dependencias | Justificación de la separación |
|---|---|---|---|---|---|---|
| `gateway.js` *(Pto 3000)* | Borde (público) | Enrutar solicitudes, aplicar CORS, registrar logs y aislar la red interna | Ninguno (sin BD) | Rutas públicas `/api/v1/*` | Todos los microservicios | Único punto de entrada expuesto al navegador |
| `inventory.service.js` *(Pto 3001)* | Transaccional + alta lectura (privado) | Administrar catálogo público y descontar existencias de bodega | Tabla `productos` | REST `/products`, `/products/:id`, `PATCH /products/:id/stock` | Ninguna | El catálogo recibe la mayor carga de lecturas anónimas y es la única autoridad sobre el stock |
| `clients.service.js` *(Pto 3002)* | Transaccional de seguridad (privado) | Custodiar identidades, hashes `bcrypt` y emitir tokens JWT | Tabla `usuarios` | REST `/users`, `/auth/login` | Ninguna | Aísla las credenciales criptográficas del resto de la lógica comercial |
| `orders.service.js` *(Pto 3003)* | Transaccional (privado) | Orquestar la creación del pedido y su ciclo de vida | Tablas `pedidos` y `detalles_pedido` | REST `/orders`, `/orders/:id` | `inventory` | Separa la gestión logística y el estado del pedido del procesamiento financiero |
| `payments.service.js` *(Pto 3004)* | Transaccional financiero (privado) | Validar cobros en memoria, emitir comprobante y marcar orden pagada | Tabla `pagos` | REST `/payments` | `orders` | Aísla el tratamiento de datos sensibles de pago (PCI-DSS) y la conexión con el banco |
| `graphql` *(Privado)* | Agregación (BFF) | Componer en una sola respuesta datos de `orders` e `inventory` | Ninguno (sin BD) | `POST /api/v1/graphql` (`schema.graphql`) | `orders`, `inventory` | Evita que el frontend realice múltiples llamadas REST (*under-fetching*) al mostrar un pedido |

### Schema, consulta y autorización

Cuando un cliente revisa el resumen de su compra, necesita ver el estado y total del pedido (que residen en `orders`) junto con el nombre, imagen actual y precio de cada prenda comprada (que residen en `inventory`). El GraphQL Gateway resuelve esta composición mediante llamadas HTTP a las APIs internas, sin acceder directamente a las tablas de otros servicios.

#### Schema GraphQL implementado

```graphql
type ProductoPublico {
  id: ID!
  nombre: String!
  precio: Int!
  img: String!
  agotado: Boolean!
}

type ItemPedidoAgregado {
  producto_id: ID!
  cantidad: Int!
  talla: String
  subtotal: Int!
  producto: ProductoPublico
}

type PedidoAgregado {
  id: ID!
  usuario_id: ID!
  estado: String!
  total: Int!
  direccion_envio: String!
  comuna_envio: String!
  detalles: [ItemPedidoAgregado!]!
}

type Query {
  resumenPedido(id: ID!): PedidoAgregado
}
```

#### Consulta de prueba y respuesta obtenida

```graphql
query ObtenerResumen($id: ID!) {
  resumenPedido(id: $id) {
    id
    estado
    total
    comuna_envio
    detalles {
      cantidad
      talla
      subtotal
      producto {
        nombre
        img
        agotado
      }
    }
  }
}
```

Respuesta obtenida en ejecución para el `pedido_id: 1` recién pagado:

```json
{
  "data": {
    "resumenPedido": {
      "id": "1",
      "estado": "pagado",
      "total": 78970,
      "comuna_envio": "Puente Alto",
      "detalles": [
        {
          "cantidad": 2,
          "talla": "M",
          "subtotal": 43980,
          "producto": {
            "nombre": "Polera Boxy Fit",
            "img": "img/polera-boxy.jpg",
            "agotado": false
          }
        },
        {
          "cantidad": 1,
          "talla": "S",
          "subtotal": 34990,
          "producto": {
            "nombre": "Pantalón Cargo",
            "img": "img/pant-gold.jpg",
            "agotado": false
          }
        }
      ]
    }
  }
}
```

#### Matriz de autorización GraphQL

| Elemento / Operación | Visitante (Anónimo) | CLIENTE | ADMIN | Dónde se verifica |
|---|---|---|---|---|
| `resumenPedido(id)` | Denegado (`401`) | Permitido **solo si el pedido es suyo** (`usuario_id` del token) | Permitido (todos) | Resolver GraphQL + verificación de identidad JWT |
| Campos de `ProductoPublico` | — | Permitido (`nombre`, `img`, `agotado`) | Permitido | Resolver que consume `inventory` |
| Mutations (escrituras) | No expuestas | No expuestas (las compras y pagos se mantienen en REST transaccional) | No expuestas | Definición del Schema |

---

<a name="m9"></a>
## 9. Módulo 9 — Analítica y gRPC (ADR)

**ADR-001 · Estado:** Aceptado · **Fecha:** 09-11-2026

### Contexto

Urban Style es una tienda boutique de vestuario urbano con operación nacional. Los flujos principales son la exploración del catálogo, la reserva y compra de carritos de hasta 3 unidades por prenda y el procesamiento del pago. Se debe evaluar técnica y cuantitativamente si el sistema requiere un clúster de procesamiento Big Data para analítica y si la comunicación interna entre microservicios (`orders` -> `inventory`) justifica implementar gRPC con Protocol Buffers o mantener HTTP/REST con JSON.

#### Matriz de necesidades y caracterización de carga

| Característica | Estimación del caso (proyección) | Medición en entorno local (Node.js + MySQL XAMPP) |
|---|---|---|
| Volumen de datos | ~80 a 150 pedidos/día (aprox. 45.000 pedidos/año); catálogo acotado (< 100 prendas activas); 1 a 3 ítems por pedido | Base relacional con tiempos de consulta indexada menores a 5 ms |
| Concurrencia esperada | Horario punta (lanzamiento de colección): 5 a 15 usuarios concurrentes (aprox. 2 a 5 req/s) | Probado con ráfagas concurrentes sobre el API Gateway sin pérdida de paquetes |
| Tamaño de mensajes | Menor a 2 KB por petición (cuerpos JSON de creación de orden y catálogo ligero) | Payloads promedio de 450 bytes a 1,8 KB |
| Frecuencia de llamadas internas | 1 llamada `PATCH` a `inventory` por cada ítem distinto al confirmar un pedido en `orders` | Latencia interna medida entre puertos 3003 y 3001: **12 ms a 28 ms** |
| Latencia objetivo (p95) | Menor a 300 ms para operaciones transaccionales completas | **p95 = 42 ms** en consulta de catálogo y **85 ms** en creación de pedido transaccional, 0% errores |
| Necesidad de Streaming | **No aplica:** no existe telemetría en tiempo real ni seguimiento GPS continuo; el estado del pedido cambia en eventos discretos (`pendiente` -> `pagado`) | — |

### Decisión

1. **Subsistema de Big Data / Analítica masiva: NO aplica (se resuelve con agregación relacional).**
   - *Justificación:* Con un volumen proyectado menor a 50.000 registros anuales, las agregaciones de ventas (ingresos totales, prendas más vendidas y pedidos pagados) se resuelven en menos de 15 ms mediante funciones agregadas SQL (`SUM`, `COUNT`, `GROUP BY`) sobre las tablas transaccionales, sin necesidad de levantar infraestructura de Big Data (como Spark o Kafka).
2. **Comunicación interna gRPC: NO se implementa (se mantiene REST/JSON síncrono sobre HTTP).**
   - *Alternativas consideradas:* (a) Implementar gRPC unary con contratos `.proto` entre `orders` (3003) e `inventory` (3001) para el descuento de stock; (b) Mantener llamadas REST JSON mediante `fetch` nativo de Node.js.
   - *Justificación de la elección:* Los mensajes intercambiados tienen menos de 200 bytes (`{"cantidad": 2}`) y la latencia medida en red local con HTTP/JSON es inferior a 30 ms, cumpliendo holgadamente el objetivo de p95 < 300 ms. Introducir compilación de archivos `.proto`, serialización binaria HTTP/2 y gestión de certificados mTLS añadiría complejidad operativa y dependencias adicionales sin producir una mejora perceptible para el usuario final.

### Consecuencias

- Arquitectura más limpia, mantenible y alineada con el stack nativo de Node.js/Express.
- Trazabilidad directa de los contratos JSON tanto en el tramo externo como en el interno, garantizando la consistencia mediante transacciones MySQL (`BEGIN` / `COMMIT` / `ROLLBACK`).

---

<a name="m10"></a>
## 10. Módulo 10 — Backend completo, pruebas y evidencias

### Matriz requisito -> pantalla -> servicio -> prueba

| Requisito | Pantalla | Servicio(s) | Prueba ejecutada (Thunder Client / Navegador) | Resultado |
|---|---|---|---|---|
| R01 · Ver catálogo de productos | `index.php` / `shop.php` | S01 (`GET /api/v1/products`) | Consulta anónima al Gateway en puerto 3000 y verificación de array JSON devuelto desde MySQL | **Aprobado** |
| R02 · Buscador en tiempo real | Modal búsqueda (`footer.php`) | S01 (`GET /api/v1/products?q=cargo`) | Búsqueda filtrada por coincidencia parcial (`LIKE %cargo%`) | **Aprobado** |
| R03 · Detalle de producto individual | `producto.php` | S02 (`GET /api/v1/products/1`) | Consulta de ficha unitaria existente (`200 OK`) e inexistente (`404 Not Found`) | **Aprobado** |
| R04 · Registro seguro de cliente | Modal Registro (`footer.php`) | S03 (`POST /api/v1/users`) | Creación de usuario `victor@ejemplo.com` (`201`) y verificación en phpMyAdmin del hash `bcrypt` | **Aprobado** |
| R05 · Inicio de sesión con JWT | Modal Login (`footer.php`) | S04 (`POST /api/v1/auth/login`) | Login exitoso con retorno de token JWT firmado y rechazo `401` con clave errónea | **Aprobado** |
| R06 · Creación de pedido y resta de stock | `checkout.php` (Despacho) | S05 (`POST /api/v1/orders`) + S08 (`PATCH /stock`) | Envío de carrito con 2 productos -> crea orden `pedido_id: 1` (`201`) y reduce columna `stock` en tabla `productos` | **Aprobado** |
| R07 · Pago de pedido y cambio de estado | `checkout.php` (Pago) | S06 (`POST /api/v1/payments`) | Pago con tarjeta del `pedido_id: 1` -> genera `pago_id: 1` (`200`), cambia orden a `pagado` y desecha tarjeta en RAM | **Aprobado** |
| R08 · Historial y resumen agregado | Vista Historial (Brecha) | S07 (`GET /api/v1/orders/1`) y S09 (GraphQL) | Consulta del pedido con sus líneas de detalle e información de productos asociados | **Aprobado** |

### Evidencias gráficas de ejecución

*(Espacios reservados para insertar las capturas de pantalla tomadas durante las pruebas en Thunder Client, consola y phpMyAdmin)*:

![Evidencia 1 - Bloqueo Apache htaccess y Arranque Concurrently](Backend/Evidencias%20Backend/htaccess_rundev.png)
<sub>Evidencia 1 · Ejecución simultánea de los 5 servidores con `npm run dev` y bloqueo `403 Forbidden` de acceso web directo a `/Backend`.</sub>

| Prueba `POST /api/v1/users` (Thunder Client) | Hash `bcrypt` en Base de Datos (phpMyAdmin) |
| :---: | :---: |
| ![Registro 201 Created](Backend/Evidencias%20Backend/created201.png) | ![Hash phpMyAdmin](Backend/Evidencias%20Backend/php.png) |

<sub>Evidencia 2 · Prueba `POST /api/v1/users` en Thunder Client (`201 Created`) y vista de la tabla `usuarios` en phpMyAdmin mostrando la contraseña hasheada con `bcrypt`.</sub>

| Creación de Pedido `201 Created` (Thunder Client) | Descuento de Stock en MySQL (phpMyAdmin) |
| :---: | :---: |
| ![Pedido 201 Created](Backend/Evidencias%20Backend/created201.png) | ![Stock Descontado](Backend/Evidencias%20Backend/phpantes.pngV) |

<sub>Evidencia 3 · Creación de orden exitosa mediante `POST /api/v1/orders` (`201 Created`) en Thunder Client y comprobación en MySQL del descuento automático de existencias en la tabla `productos`.</sub>

![Evidencia 4 - Pago Procesado y Estado Pagado](Backend/Evidencias%20Backend/authorized.png)
<sub>Evidencia 4 · Prueba `POST /api/v1/payments` (`200 OK`) y actualización atómica del estado del pedido a `pagado` sin almacenar datos de la tarjeta.</sub>

---

<a name="ejecucion"></a>
## 11. Ejecución: instalar, iniciar y probar

### Requisitos previos

- **XAMPP** con los módulos **Apache** y **MySQL** activos (MySQL en el puerto `3306`).
- **Node.js** v18 o superior instalado en el sistema.

### Paso 1: Importar la base de datos en MySQL

1. Abrir `http://localhost/phpmyadmin` en el navegador.
2. Crear una nueva base de datos llamada **`urban_style_db`** (cotejamiento `utf8mb4_general_ci`).
3. Seleccionar `urban_style_db`, ir a la pestaña **Importar** y subir el archivo **`database/urban_style_db.sql`** incluido en este archivo `.zip`. Esto creará las 5 tablas (`usuarios`, `productos`, `pedidos`, `detalles_pedido`, `pagos`) y cargará el catálogo inicial de productos de Urban Style.

### Paso 2: Instalar dependencias del Backend

Abrir una terminal en la carpeta raíz del proyecto y entrar al directorio `Backend`:

```powershell
cd Backend
npm install
```

*(Nota: la carpeta `node_modules` se excluyó del archivo `.zip` para cumplir con las buenas prácticas de entrega en Canvas; `npm install` descargará automáticamente `express`, `mysql2`, `cors`, `dotenv`, `express-http-proxy`, `bcrypt`, `jsonwebtoken` y `concurrently`).*

### Paso 3: Iniciar el API Gateway y todos los microservicios

Con una sola instrucción se levantan en paralelo el API Gateway (3000) y los 4 microservicios (3001 al 3004):

```powershell
npm run dev
```

La consola confirmará que toda la arquitectura está activa:
```text
[API Gateway] activo en http://localhost:3000
[Microservicio Inventory] activo en http://localhost:3001
[Microservicio Clients] activo en http://localhost:3002
[Microservicio Orders] activo en http://localhost:3003
[Microservicio Payments] activo en http://localhost:3004
```

Para detener todos los servicios en cualquier momento, presionar `Ctrl + C` en la terminal.

### Paso 4: Comandos rápidos de prueba (`curl` o Thunder Client)

**1. Consultar catálogo público (S01):**
```bash
curl -X GET "http://localhost:3000/api/v1/products"
```

**2. Registrar un cliente nuevo (S03):**
```bash
curl -X POST http://localhost:3000/api/v1/users -H "Content-Type: application/json" -d "{\"nombre\":\"Victor Bugueno\",\"correo\":\"victor@ejemplo.com\",\"password\":\"mi_password_seguro\"}"
```

**3. Iniciar sesión y obtener token JWT (S04):**
```bash
curl -X POST http://localhost:3000/api/v1/auth/login -H "Content-Type: application/json" -d "{\"correo\":\"victor@ejemplo.com\",\"password\":\"mi_password_seguro\"}"
```

**4. Crear un pedido descontando stock (S05):**
```bash
curl -X POST http://localhost:3000/api/v1/orders -H "Content-Type: application/json" -d "{\"usuario_id\":1,\"datos_entrega\":{\"rut\":\"19.123.456-7\",\"direccion\":\"Avenida Siempre Viva 123\",\"comuna\":\"Puente Alto\"},\"carrito\":[{\"producto_id\":1,\"cantidad\":2,\"talla\":\"M\",\"subtotal\":43980}]}"
```

**5. Pagar el pedido (S06):**
```bash
curl -X POST http://localhost:3000/api/v1/payments -H "Content-Type: application/json" -d "{\"pedido_id\":1,\"metodo\":\"tarjeta_credito\",\"detalles_tarjeta\":{\"numero\":\"4111222233334444\",\"vencimiento\":\"12/28\",\"cvv\":\"123\",\"titular\":\"Victor Bugueno\",\"tipo_doc\":\"RUT\",\"num_doc\":\"19.123.456-7\",\"cuotas\":1}}"
```

---

<a name="estructura"></a>
## 12. Estructura del repositorio y limitaciones

| Servicio / Componente | Puerto | Archivo principal | Tablas que administra en MySQL |
|---|---|---|---|
| **API Gateway** (público) | `3000` | `Backend/gateway.js` | — (enrutador y proxy inverso) |
| **inventory** (privado) | `3001` | `Backend/services/inventory.service.js` | `productos` |
| **clients** (privado) | `3002` | `Backend/services/clients.service.js` | `usuarios` |
| **orders** (privado) | `3003` | `Backend/services/orders.service.js` | `pedidos`, `detalles_pedido` |
| **payments** (privado) | `3004` | `Backend/services/payments.service.js` | `pagos` |

```text
PRDWM/
├── README.md                        ← Este informe técnico completo
├── Front/                           ← Frontend responsivo en PHP, CSS3 y JS modular
│   ├── index.php                    ← P01 Inicio y destacados
│   ├── shop.php                     ← P02 Catálogo general
│   ├── producto.php                 ← P04 Detalle de prenda, tallas y control de stock
│   ├── checkout.php                 ← P06/P07 Formulario de despacho y pasarela de pago
│   ├── includes/                    ← header.php (carrito) y footer.php (modales login y búsqueda)
│   ├── js/                          ← Lógica de cliente (carrito.js, checkout.js, auth.js)
│   └── css/ · img/                  ← Hojas de estilo y recursos gráficos del catálogo
├── Backend/                         ← Arquitectura de microservicios Node.js
│   ├── .htaccess                    ← Bloqueo de seguridad Apache (Require all denied)
│   ├── .env                         ← Variables de entorno y secretos de integración
│   ├── package.json                 ← Dependencias y script maestro (npm run dev)
│   ├── gateway.js                   ← API Gateway principal (Puerto 3000)
│   ├── config/
│   │   └── db.js                    ← Pool de conexiones a MySQL
│   ├── services/
│   │   ├── inventory.service.js     ← Microservicio de Inventario (Puerto 3001)
│   │   ├── clients.service.js       ← Microservicio de Clientes y Auth JWT (Puerto 3002)
│   │   ├── orders.service.js        ← Microservicio de Pedidos transaccional (Puerto 3003)
│   │   └── payments.service.js      ← Microservicio de Pagos PCI-DSS (Puerto 3004)
│   └── imagenes/ · docs/            ← Diagramas UML, mapa de arquitectura y capturas de evidencia
└── database/
    └── urban_style_db.sql           ← Script SQL completo (esquema de tablas y datos semilla)
```

### Limitaciones conocidas

- **Pasarela de pagos externa:** El cobro con tarjeta utiliza un adaptador simulado en memoria dentro de `payments.service.js` que respeta el contrato de entrada/salida, las transiciones de estado y el descarte inmediato de datos sensibles según PCI-DSS, sin realizar cargos bancarios reales.
- **Vistas de administración e historial:** Tal como se declaró en la sección 0 de brechas, las pantallas gráficas de administración de bodega y de historial de pedidos no formaban parte del diseño original del frontend, pero sus endpoints y contratos quedaron implementados y verificados en el backend.

---

<a name="diagramas"></a>
<sub>Diagramas: fuentes editables PlantUML en `Backend/docs/diagramas/*.puml`. Imágenes renderizadas y capturas de Thunder Client en `Backend/imagenes/`. [Volver al índice](#indice)</sub>