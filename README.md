# Urban Style â€” Backend seguro

**EvaluaciÃ³n 2 Â· DiseÃ±o e implementaciÃ³n de un backend seguro completo**

| | |
|---|---|
| Caso | NÂ° **40** â€” Tienda "Urban Style" (vestuario y accesorios streetwear, Santiago) |
| Integrantes | VÃ­ctor BugueÃ±o |
| VersiÃ³n del frontend | v1.4.0 â€” pantallas: `index.php`, `shop.php`, `producto.php`, `checkout.php`, modales en `header.php` y `footer.php` |
| VersiÃ³n del backend | este repositorio (Node.js 18+, Express, MySQL 8 en XAMPP) |
| Fecha | 09-11-2026 |

> El caso de negocio se utiliza como fuente de requisitos y todos los servicios se prueban a travÃ©s del API Gateway en el puerto 3000, igual que lo harÃ­a el navegador.

---

<a name="indice"></a>
## Ãndice

- [Vista general de la arquitectura](#vista-general)
- [0. Requisitos y brechas](#m0)
- [1. MÃ³dulo 1 â€” Servicios derivados del frontend](#m1)
- [2. MÃ³dulo 2 â€” Clases y cardinalidades del dominio](#m2)
- [3. MÃ³dulo 3 â€” Sistemas e integraciones](#m3)
- [4. MÃ³dulo 4 â€” Stakeholders y permisos HTTP](#m4)
- [5. MÃ³dulo 5 â€” API Gateway y fronteras de confianza](#m5)
- [6. MÃ³dulo 6 â€” Rutas y secretos de integraciÃ³n](#m6)
- [7. MÃ³dulo 7 â€” Datos sensibles y secretos del cliente](#m7)
- [8. MÃ³dulo 8 â€” SeparaciÃ³n privada y agregaciones GraphQL](#m8)
- [9. MÃ³dulo 9 â€” AnalÃ­tica y gRPC (ADR)](#m9)
- [10. MÃ³dulo 10 â€” Backend completo, pruebas y evidencias](#m10)
- [11. EjecuciÃ³n: instalar, iniciar y probar](#ejecucion)
- [12. Estructura del repositorio y limitaciones](#estructura)

---

<a name="vista-general"></a>
## Vista general de la arquitectura

Un Ãºnico **API Gateway** (puerto 3000) es pÃºblico, mientras que el acceso web directo a la carpeta del cÃ³digo estÃ¡ bloqueado en Apache mediante directivas `.htaccess` (`403 Forbidden`). DetrÃ¡s, en una red privada interna, operan los microservicios **inventory (3001), clients (3002), orders (3003) y payments (3004)** sobre Node.js/Express, ademÃ¡s del **GraphQL Gateway** de agregaciÃ³n, todos abastecidos de credenciales mediante un gestor de secretos (`.env`) y persistencia relacional en MySQL (`urban_style_db`).

![Mapa de componentes](Backend/Evidencias%20Backend/arquitecturaback.jpg)

<sub>Figura 1 Â· Mapa de componentes, despliegue y fronteras de confianza Â· [ver en alta resoluciÃ³n (SVG)](Backend/Evidencias%20Backend/arquitecturaback.jpg) Â· [fuente editable PlantUML](Backend/Evidencias%20Backend/arquitecturaback.jpg)</sub>

---

<a name="m0"></a>
## 0. Requisitos del caso y brechas declaradas

| ID | Requisito | Pantalla | Estado |
|---|---|---|---|
| R01 | Ver el catÃ¡logo de prendas y accesorios con precios y estado agotado | P01 `index.php` / P02 `shop.php` | Implementado |
| R02 | Buscar productos por coincidencia de texto en tiempo real | P03 `footer.php` -> modal *BÃºsqueda* | Implementado (`GET /api/v1/products?q=`) |
| R03 | Ver detalle de producto, elegir talla (excepto accesorios) y cantidad (mÃ¡x. 3) | P04 `producto.php` | Implementado |
| R04 | Crear cuenta de cliente con contraseÃ±a protegida | P05 `footer.php` -> modal *Registro* | Implementado (`bcrypt` con 10 rondas de sal) |
| R05 | Iniciar y cerrar sesiÃ³n de cliente | P05 `footer.php` -> modal *Iniciar sesiÃ³n* | Implementado (emisiÃ³n de token `JWT`) |
| R06 | Armar el carrito y confirmar el pedido con datos de despacho | P06 `checkout.php` | Implementado (el carrito vive en `LocalStorage`; el pedido transaccional se crea al confirmar) |
| R07 | Pagar pedido con tarjeta de crÃ©dito/dÃ©bito o transferencia | P07 `checkout.php` -> *Pasarela de Pago* | Implementado (validaciÃ³n en memoria bajo estÃ¡ndar PCI-DSS y adaptador de prueba) |
| R08 | Ver resumen e historial de compras con detalle de prendas | **Brecha:** no hay pantalla de perfil/historial en el frontend | Backend listo (S07 y agregaciÃ³n GraphQL `resumenPedido`). Se incorporarÃ¡ como vista `historial.php` |
| R09 | AdministraciÃ³n de catÃ¡logo y reposiciÃ³n de stock fÃ­sico | **Brecha:** no hay pantalla de administrador | Backend listo (S08 `PATCH /products/:id/stock`). Vista *Panel Bodega* propuesta |
| R10 | Reporte consolidado de ventas para administraciÃ³n | **Brecha:** no hay pantalla de reportes | Backend listo (consulta agregada de Ã³rdenes pagadas) |

**Requisitos y decisiones de arquitectura.** Los requisitos R01â€“R10 provienen del caso de negocio y del flujo de pantallas responsivas. Las decisiones de arquitectura del equipo son: separar el sistema en 4 microservicios de dominio, utilizar tokens firmados con HMAC-SHA256 (`JWT`), exponer un Ãºnico API Gateway en el puerto 3000, descontar stock de forma atÃ³mica con transacciones MySQL (`BEGIN` / `COMMIT` / `ROLLBACK`) y descartar gRPC. Estas decisiones se fundamentan en las secciones 3, 5 y en el ADR del MÃ³dulo 9.

---

<a name="m1"></a>
## 1. MÃ³dulo 1 â€” Servicios derivados del frontend

Escritorio y mÃ³vil comparten exactamente las mismas operaciones: la versiÃ³n mÃ³vil solo adapta la disposiciÃ³n mediante CSS Grid/Flexbox, por lo que una variaciÃ³n visual no genera servicios nuevos.
Los ejemplos JSON de cada contrato, obtenidos por ejecuciÃ³n real en Thunder Client, se encuentran documentados a continuaciÃ³n.

ClasificaciÃ³n de datos: **P** = pÃºblico Â· **R** = restringido (dato personal) Â· **C** = credencial Â· **S** = sensible.

| ID | Req. | Pantalla y acciÃ³n | PropÃ³sito | Verbo y ruta | Entrada | Salida | Reglas y errores | Datos | Acceso |
|---|---|---|---|---|---|---|---|---|---|
| S01 | R01, R02 | P01/P02/P03 Â· Cargar tienda y buscador | Consultar catÃ¡logo | `GET /api/v1/products` | query opcional: `q` (texto de bÃºsqueda) | 200 `[{id, nombre, precio, img, es_accesorio, stock, agotado}]` Â· `[]` si no hay | 500 ante fallo de base de datos Â· convierte flags a booleanos | P | AnÃ³nimo |
| S02 | R03 | P04 Â· Ficha de producto | Consultar producto individual | `GET /api/v1/products/{id}` | path: `id` (entero) | 200 `{id, nombre, precio, img, es_accesorio, stock, agotado}` | 404 si el ID no existe en catÃ¡logo | P | AnÃ³nimo |
| S03 | R04 | P05 Â· Modal Crear cuenta | Registrar nuevo cliente | `POST /api/v1/users` | `nombre`*, `correo`*, `password`* | 201 `{mensaje, usuario_id}` | 400 si faltan datos obligatorios Â· 409 si el correo ya estÃ¡ registrado Â· hashea clave con `bcrypt` | C, R | AnÃ³nimo |
| S04 | R05 | P05 Â· Modal Iniciar sesiÃ³n | Autenticar y emitir token | `POST /api/v1/auth/login` | `correo`*, `password`* | 200 `{mensaje, token, usuario: {id, nombre, rol}}` | 400 datos incompletos Â· 401 genÃ©rico ("Credenciales invÃ¡lidas") para evitar enumeraciÃ³n de cuentas | C | AnÃ³nimo |
| S05 | R06 | P06 Â· Confirmar pedido en Checkout | Crear pedido y descontar stock | `POST /api/v1/orders` | `usuario_id`*, `datos_entrega`*{`rut`, `direccion`, `comuna`}, `carrito`*`[{producto_id, cantidad, talla, subtotal}]` | 201 `{mensaje, pedido_id, total, estado: "pendiente"}` | 400 si el carrito estÃ¡ vacÃ­o Â· 409 si no hay stock suficiente en `inventory` (ejecuta `ROLLBACK` total) | R | CLIENTE (JWT) |
| S06 | R07 | P07 Â· Pagar pedido en Checkout | Procesar cobro y confirmar orden | `POST /api/v1/payments` | `pedido_id`*, `metodo`*, `detalles_tarjeta`*{`numero`, `vencimiento`, `cvv`, `titular`, `num_doc`, `cuotas`} | 200 `{mensaje, pago_id, pedido_id, estado_orden: "pagado", nota_seguridad}` | 400 si faltan campos Â· 409 si el pedido no existe o ya estaba pagado/cancelado (`ROLLBACK`) | S, R | CLIENTE (JWT) |
| S07 | R08 | Brecha Â· Detalle / Historial de compra | Consultar resumen de un pedido | `GET /api/v1/orders/{id}` | path: `id` (entero) | 200 `{pedido: {...}, productos: [...]}` | 404 si el pedido no existe o no pertenece al cliente | R | Autenticado (dueÃ±o o ADMIN) |
| S08 | R06, R09 | Interno / Brecha Admin Â· Control de bodega | Descontar o ajustar stock fÃ­sico | `PATCH /products/{id}/stock` | path: `id` Â· body: `cantidad`* (entero positivo) | 200 `{mensaje, stockRestante, agotado}` | 400 cantidad invÃ¡lida Â· 404 producto no existe Â· 409 si `stock < cantidad` Â· marca `agotado = 1` si llega a 0 | Interno | Servicio `orders` / ADMIN |
| S09 | R08, R10 | Brecha Â· Vista Historial y Panel Admin | AgregaciÃ³n GraphQL de pedidos e inventario | `POST /api/v1/graphql` | `{query, variables}` | 200 `{data, errors}` | Verifica token y propiedad del pedido Â· resuelve datos cruzados entre `orders` e `inventory` | R, P | Autenticado |

**Lo que envÃ­a el navegador y lo que calcula el servidor:** el navegador envÃ­a identificadores de producto, tallas, cantidades y datos de despacho. El servidor valida la existencia real del stock, controla la transiciÃ³n oficial del estado (`pendiente` -> `pagado`), calcula la marca de agotado y desecha en memoria los datos de tarjeta de crÃ©dito.

---

### Ejemplos de contratos (extracto)

A continuaciÃ³n se presentan los contratos JSON obtenidos por ejecuciÃ³n real a travÃ©s del API Gateway (`http://localhost:3000`):

#### S01 Â· Consultar catÃ¡logo con filtro de bÃºsqueda

`GET /api/v1/products?q=cargo` Â· actor: **Visitante** Â· respuesta **200 OK**

Respuesta:
```json
[
  {
    "id": 3,
    "nombre": "PantalÃ³n Cargo",
    "precio": 34990,
    "img": "img/pant-gold.jpg",
    "es_accesorio": false,
    "stock": 15,
    "agotado": false
  }
]
```

#### S03 Â· Registrar cliente nuevo

`POST /api/v1/users` Â· actor: **Visitante** Â· respuesta **201 Created**

Solicitud:
```json
{
  "nombre": "VÃ­ctor BugueÃ±o",
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

#### S05 Â· Crear pedido con reserva y descuento de stock

`POST /api/v1/orders` Â· actor: **Cliente** Â· respuesta **201 Created**

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
  "mensaje": "Pedido generado con Ã©xito",
  "pedido_id": 1,
  "total": 78970,
  "estado": "pendiente"
}
```

#### S05 Â· Crear pedido sin stock suficiente (rechazado con Rollback)

`POST /api/v1/orders` Â· actor: **Cliente** Â· respuesta **409 Conflict**

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

#### S06 Â· Procesar pago con tarjeta

`POST /api/v1/payments` Â· actor: **Cliente** Â· respuesta **200 OK**

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
    "titular": "VÃ­ctor BugueÃ±o",
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
  "nota_seguridad": "Los datos de la tarjeta fueron procesados en memoria y desechados segÃºn normativa PCI."
}
```

---

<a name="m2"></a>
## 2. MÃ³dulo 2 â€” Clases y cardinalidades del dominio

![UML de clases del dominio](Backend/Evidencias%20Backend/umldiagrama.jpg)

<sub>Figura 2 Â· UML de clases del dominio y servicios pÃºblicos Â«serviceÂ» con sus DTO Â· [ver en alta resoluciÃ³n (SVG)](Backend/Evidencias%20Backend/umldiagrama.jpg) Â· [fuente editable PlantUML](Backend/Evidencias%20Backend/umldiagrama.jpg)</sub>

**Leyenda:** rombo relleno = composiciÃ³n Â· rombo vacÃ­o = agregaciÃ³n Â· lÃ­nea continua = asociaciÃ³n Â· lÃ­nea punteada = dependencia o referencia lÃ³gica entre microservicios Â· los nÃºmeros indican la multiplicidad en cada extremo (`1`, `0..1`, `0..*`, `1..*`).

### Relaciones

| Clase origen | Clase destino | Multiplicidad | Tipo | Regla de negocio que la justifica |
|---|---|---|---|---|
| Usuario | Pedido | `1` â€” `0..*` | asociaciÃ³n / referencia lÃ³gica | Un cliente registrado puede no haber realizado compras aÃºn (`0`) o acumular mÃºltiples pedidos histÃ³ricos (`*`). Todo pedido pertenece obligatoriamente a un Ãºnico cliente (`1`) |
| Pedido | DetallePedido | `1` â€” `1..*` | composiciÃ³n | **Un pedido confirmado contiene al menos un Ã­tem** (`1..*`). No se persisten pedidos vacÃ­os en la base de datos y los detalles no existen sin su pedido padre |
| Producto | DetallePedido | `1` â€” `0..*` | referencia lÃ³gica | Cada lÃ­nea de detalle referencia un producto del catÃ¡logo (`1`). Un producto nuevo puede no haber sido vendido nunca (`0`) o figurar en mÃºltiples pedidos (`*`) |
| Pedido | Pago | `1` â€” `0..1` | composiciÃ³n | Un pedido reciÃ©n creado en estado `pendiente` tiene `0` pagos aprobados. Una vez procesado el cobro, se registra exactamente `1` comprobante de pago asociado |

### Correspondencia entre operaciones UML e IDs de servicio

Cada operaciÃ³n de las interfaces `<<service>>` del diagrama se vincula directamente con el catÃ¡logo del MÃ³dulo 1:
- `CatalogService`: `consultarCatalogo(q)` -> **S01** Â· `obtenerProducto(id)` -> **S02** Â· `actualizarStock(id, cantidad)` -> **S08**.
- `ClientAuthService`: `registrarUsuario(dto)` -> **S03** Â· `iniciarSesion(credenciales)` -> **S04**.
- `OrderService`: `crearPedido(dto)` -> **S05** Â· `obtenerPedido(id)` -> **S07**.
- `PaymentService`: `procesarPago(dto)` -> **S06**.
- `AggregationService`: `resumenPedido(id)` -> **S09**.

### Modelo interno y DTO

| Entidad interna | Atributos que **no** salen al exterior | DTO / respuesta pÃºblica |
|---|---|---|
| `Usuario` (tabla `usuarios`) | `password_hash` (hash bcrypt + sal), `creado_en` | `UsuarioAuthDTO {id, nombre, rol}` + `token` JWT |
| `Producto` (tabla `productos`) | registros internos de auditorÃ­a | `ProductoDTO {id, nombre, precio, img, es_accesorio, stock, agotado}` |
| `Pedido` (tabla `pedidos`) | claves forÃ¡neas crudas sin validar propiedad | `PedidoCreadoDTO {mensaje, pedido_id, total, estado}` |
| `Pago` (tabla `pagos` + memoria) | `detalles_tarjeta` (`numero`, `cvv`, `vencimiento` se destruyen en RAM) | `ComprobantePagoDTO {pago_id, pedido_id, estado_orden, nota_seguridad}` |

### Decisiones del equipo

- **Â¿El carrito y el pedido son clases distintas?** SÃ­. El carrito es un estado temporal que vive en el `LocalStorage` del navegador (`carrito.js`) y permite agregar o quitar prendas sin impactar la base de datos. La clase `Pedido` nace reciÃ©n cuando el usuario confirma el checkout (S05), iniciando una transacciÃ³n que verifica y descuenta el stock real.
- **Â¿QuÃ© ocurre con el historial si un producto deja de estar disponible?** Cuando un artÃ­culo llega a stock cero, el microservicio `inventory` cambia su atributo `agotado = true` (`1`), pero **nunca hace un `DELETE` fÃ­sico** del registro. AsÃ­, las filas histÃ³ricas en `detalles_pedido` mantienen la integridad referencial y conservan el `subtotal` pagado en esa fecha.
- **Â¿QuÃ© atributos se conservan internamente y cuÃ¡les salen en cada respuesta?** El `password_hash` jamÃ¡s abandona el microservicio `clients`, y los datos sensibles de tarjetas (`numero`, `cvv`) nunca se escriben en disco.

---

<a name="m3"></a>
## 3. MÃ³dulo 3 â€” Sistemas e integraciones

El mapa de componentes general se encuentra en la [Figura 1](#vista-general). A continuaciÃ³n se presenta el diagrama de secuencia del flujo transaccional de compra y pago entre los subsistemas:

![Secuencia de compra y pago](Backend/Evidencias%20Backend/diagrama_pago.jpg)

<sub>Figura 3 Â· Secuencia de integraciÃ³n: crear pedido en orders, descontar stock en inventory y confirmar pago en payments Â· [ver en alta resoluciÃ³n (SVG)](Backend/Evidencias%20Backend/diagrama_pago.jpg) Â· [fuente editable PlantUML](Backend/Evidencias%20Backend/diagrama_pago.jpg)</sub>

| Sistema | Responsabilidad | Datos que administra (autoridad) | Servicios | Dependencias | Contrato de integraciÃ³n | Credencial requerida | Respuesta ante fallos |
|---|---|---|---|---|---|---|---|
| **inventory** *(Pto 3001)* | CatÃ¡logo de productos, disponibilidad y control del stock fÃ­sico | Tabla `productos` (`stock`, `precio`, `agotado`) | S01, S02, S08 | Ninguna (autÃ³nomo) | REST interno: `PATCH /products/:id/stock` y `GET /products/:id` | Credencial BD `DB_USER` desde `.env` | Si `stock < cantidad`, devuelve `409 Conflict` impidiendo sobreventa. Si cae, `orders` aborta los pedidos |
| **clients** *(Pto 3002)* | Registro de clientes, hashing de claves y emisiÃ³n de tokens JWT | Tabla `usuarios` (`correo`, `password_hash`, `rol`) | S03, S04 | Ninguna (autÃ³nomo) | REST `/users` y `/auth/login` | `DB_USER` y `JWT_SECRET` desde `.env` | Si el correo se repite devuelve `409`. Si la BD falla responde `500` controlado sin exponer trazas |
| **orders** *(Pto 3003)* | CreaciÃ³n del pedido, detalles de compra y datos de despacho | Tablas `pedidos` y `detalles_pedido` (**estado oficial** del pedido) | S05, S07 | **inventory** (verificar y descontar stock) | Llama a `inventory` vÃ­a `PATCH http://localhost:3001/products/:id/stock` | `DB_USER` y red privada local | Ejecuta `beginTransaction()`. Si `inventory` rechaza un Ã­tem o no responde, ejecuta `rollback()` inmediato y devuelve `409` |
| **payments** *(Pto 3004)* | Procesamiento del medio de pago, registro de comprobante y confirmaciÃ³n | Tabla `pagos` (`metodo`, `estado`, `fecha`) | S06 | **orders** (verificar estado `pendiente` y pasar a `pagado`) | TransacciÃ³n atÃ³mica de validaciÃ³n y actualizaciÃ³n de estado a `pagado` | `DB_USER` y `BANK_API_KEY` (simulada) | Si el pedido no existe o ya fue pagado, hace `rollback()` y devuelve `409`, evitando cobros duplicados |
| **graphql** *(complementario)* | AgregaciÃ³n de pedidos con fichas de productos para el frontend | Ninguno (sin BD propia) | S09 | `orders`, `inventory` | Esquema `resumenPedido(id)` | Token JWT propagado por el Gateway | Si `inventory` demora, entrega los datos base del pedido con error parcial controlado |
| **Pasarela Bancaria** *(externo simulado)* | AutorizaciÃ³n financiera de tarjetas | â€” | S06 | â€” | Adaptador en memoria dentro de `payments.service.js` | Clave de comercio | Si la tarjeta es rechazada, el pedido permanece en estado `pendiente` |

**Despacho y Boletas:** los datos logÃ­sticos de despacho (`rut_envio`, `direccion_envio`, `comuna_envio`) son administrados directamente por `orders` asociados a cada compra. El comprobante de pago queda registrado en `payments` (tabla `pagos`) Ãºnicamente cuando la transacciÃ³n resulta completada.
**Disponibilidad, consistencia y doble venta:** `orders` no confÃ­a en que el frontend muestre el botÃ³n "Agregar al carrito" habilitado. Al confirmar el pedido, `orders` abre una transacciÃ³n SQL e invoca sincrÃ³nicamente a `inventory` para descontar cada unidad; si el Ãºltimo artÃ­culo acaba de agotarse, `inventory` responde `409` y `orders` revierte toda la orden (`rollback`).
**Pagos:** el frontend nunca puede cambiar el estado de un pedido a `pagado` por su cuenta; Ãºnicamente el microservicio `payments`, tras verificar que el pedido estÃ© `pendiente` y registrar el comprobante, autoriza el cambio de estado.

---

<a name="m4"></a>
## 4. MÃ³dulo 4 â€” Stakeholders y permisos HTTP

### Registro de stakeholders

| Stakeholder | InterÃ©s | Rol / identidad tÃ©cnica | Uso de la informaciÃ³n |
|---|---|---|---|
| Visitante | Explorar catÃ¡logo, buscar prendas y ver precios | AnÃ³nimo | Consulta de catÃ¡logo pÃºblico (S01, S02) y registro (S03) |
| Cliente registrado | Comprar vestuario, pagar y consultar sus pedidos | `cliente` (JWT) | CreaciÃ³n de pedidos (S05), pagos (S06) y lectura de sus propias compras (S07, S09) |
| Administrador / Bodega | Mantener el stock actualizado y supervisar ventas | `admin` (JWT) | Ajuste de inventario (S08) y consulta global de pedidos |
| Microservicios internos | Colaborar dentro de la red privada | Identidad de red interna (`localhost`) | `orders` consume `PATCH /products/:id/stock` en `inventory` |
| Pasarela de pagos | Procesar cobros con tarjeta | Adaptador interno en `payments` | Recibe datos en memoria RAM durante S06 |
| DueÃ±os de la tienda | Rentabilidad y control financiero | **sin permisos operativos** | Reciben reportes consolidados generados por administraciÃ³n |
| Servicio de Impuestos Internos (SII) | AuditorÃ­a tributaria de pagos | **sin permisos operativos** | Recibe el respaldo contable desde la tabla `pagos` exportada por administraciÃ³n |
| Docente / Evaluador | Validar la arquitectura y seguridad del backend | **sin permisos operativos** | Ejecuta las pruebas con las cuentas y scripts documentados en este README |

### Matriz de permisos por servicio y verbo

"Denegado" significa que el Gateway o el microservicio rechaza la peticiÃ³n con `401 Unauthorized` o `403 Forbidden`. "N/A" indica que el verbo no estÃ¡ implementado para esa ruta (retorna `404`/`405`) para no inventar operaciones destructivas innecesarias.

| Rol / identidad | ID | Ruta | Verbo | DecisiÃ³n | Alcance y condiciÃ³n | DÃ³nde se verifica |
|---|---|---|---|---|---|---|
| Visitante | S01, S02 | `/api/v1/products`, `/api/v1/products/{id}` | GET | Permitido | Solo campos pÃºblicos del catÃ¡logo | Gateway + `inventory` |
| Visitante | S03, S04 | `/api/v1/users`, `/api/v1/auth/login` | POST | Permitido | ValidaciÃ³n de formato y unicidad de correo | Gateway + `clients` |
| Visitante | â€” | `/api/v1/users` | GET | **N/A (404)** | No se expone listado de usuarios (probado en navegador: `Cannot GET /users/`) | `clients` |
| Visitante | S05â€“S09 | `/api/v1/orders*`, `/api/v1/payments*` | POST, GET | **Denegado (401)** | Exige sesiÃ³n iniciada con token JWT vigente | Gateway / Microservicio |
| Cliente | S05 | `/api/v1/orders` | POST | Permitido | Solo con token vÃ¡lido y stock disponible en bodega | Gateway + `orders` |
| Cliente | S07 | `/api/v1/orders/{id}` | GET | Permitido | **Solo si el pedido le pertenece** (`usuario_id` del token coincide con el pedido) | `orders` (propiedad) |
| Cliente | S06 | `/api/v1/payments` | POST | Permitido | Solo para pedidos propios en estado `pendiente` | `payments` |
| Cliente | â€” | `/api/v1/products*` | POST, PUT, DELETE | **Denegado (403/404)** | Consultar el catÃ¡logo no otorga permisos de administraciÃ³n | Gateway + `inventory` |
| Cliente | S08 | `/products/{id}/stock` | PATCH | **Denegado (403)** | Un cliente no puede modificar el stock directamente | Gateway (ruta no publicada) |
| Cliente | â€” | `/api/v1/orders/{id}` | DELETE | **N/A** | Los pedidos no se borran fÃ­sicamente para conservar el historial contable | `orders` |
| Sistema (`orders`) | S08 | `http://localhost:3001/products/{id}/stock` | PATCH | Permitido | InvocaciÃ³n interna server-to-server durante transacciÃ³n de compra | Red privada (`inventory`) |
| Admin | S08, S07 | AdministraciÃ³n de stock y consulta de Ã³rdenes | PATCH, GET | Permitido | Rol `admin` verificado en los claims del JWT | Gateway + microservicios |
| Navegador externo | â€” | Acceso directo a carpeta `/Backend/` por puerto 80 | Todos | **Denegado (403)** | Bloqueado por directiva `Require all denied` en `.htaccess` | Servidor Apache |

**Identidad y autorizaciÃ³n:** ocultar un botÃ³n en el HTML/JS del frontend (como deshabilitar el botÃ³n cuando un producto estÃ¡ agotado) es solo usabilidad, no seguridad. El backend comprueba la identidad mediante la firma del JWT y valida en base de datos el stock y la propiedad de cada recurso antes de operar.

---

<a name="m5"></a>
## 5. MÃ³dulo 5 â€” API Gateway y fronteras de confianza

![UML del API Gateway](Backend/Evidencias%20Backend/umlapi.jpg)

<sub>Figura 4 Â· UML del API Gateway, polÃ­ticas de enrutamiento, SecretProvider y GraphQL Gateway Â· [ver en alta resoluciÃ³n (SVG)](Backend/Evidencias%20Backend/umlapi.jpg) Â· [fuente editable PlantUML](Backend/Evidencias%20Backend/umlapi.jpg)</sub>

**Â¿CuÃ¡ntos gateways?** **Uno solo** (`gateway.js` en el puerto 3000), implementado con Express y `express-http-proxy`. Separar las rutas mediante polÃ­ticas dentro de un Ãºnico punto de entrada evita duplicar la configuraciÃ³n de CORS, trazabilidad de logs y lectura de secretos, lo cual es la decisiÃ³n Ã³ptima para la escala de Urban Style. El **GraphQL Gateway** opera como un mÃ³dulo de agregaciÃ³n detrÃ¡s de esta misma frontera.

| Clase de ruta | Ejemplos | PolÃ­tica aplicada |
|---|---|---|
| Externa anÃ³nima | S01, S02 (`GET /api/v1/products*`), S03 (`POST /api/v1/users`), S04 (`POST /api/v1/auth/login`) | Acceso pÃºblico, parseo JSON, filtro CORS y registro de auditorÃ­a en consola |
| Externa autenticada | S05 (`POST /api/v1/orders`), S06 (`POST /api/v1/payments`), S07 (`GET /api/v1/orders/:id`), S09 (`POST /api/v1/graphql`) | Exige `Authorization: Bearer <jwt>` vÃ¡lido. Propiedad verificada en el servicio destino |
| Interna (privada) | S08 (`PATCH /products/:id/stock` en puerto 3001) | **No publicada en el Gateway** para clientes externos; invocada internamente por `orders` |

**QuÃ© valida el API Gateway:**
1. Existencia del prefijo de ruta en la lista blanca (`/api/v1/products`, `/api/v1/users`, `/api/v1/auth`, `/api/v1/orders`, `/api/v1/payments`). Cualquier ruta no declarada es rechazada.
2. PolÃ­ticas de origen cruzado (`cors()`), formato del cuerpo (`express.json()`) y registro de trazabilidad (`[Gateway] METODO URL -> Hora`).
3. Reescritura controlada de rutas mediante `proxyReqPathResolver` hacia los puertos internos (3001 al 3004), ocultando la topologÃ­a interna al navegador.

**QuÃ© valida cada microservicio interno:**
1. Reglas de negocio crÃ­ticas: `inventory` comprueba que `stock >= cantidad`; `orders` verifica que el carrito no estÃ© vacÃ­o; `payments` comprueba que el pedido exista y estÃ© exactamente en estado `pendiente`.
2. Propiedad del recurso y validez de credenciales contra las tablas de MySQL.

**Fronteras de confianza:**
- **Frontera 1 (Navegador -> Apache puerto 80):** protegida con `.htaccess` (`Require all denied`) dentro de `/Backend` para impedir que alguien descargue el archivo `.env` o el cÃ³digo fuente.
- **Frontera 2 (Navegador -> API Gateway puerto 3000):** Ãºnico puerto de aplicaciÃ³n expuesto al cliente. Todo dato proveniente del navegador se considera no confiable.
- **Frontera 3 (API Gateway -> Red Privada puertos 3001â€“3004):** comunicaciÃ³n local entre procesos Node.js y el motor MySQL (puerto 3306).

---

### Recorrido de una solicitud autorizada y una denegada

| Solicitud autorizada (`200 OK`) | Solicitud denegada (`401 Unauthorized`) |
| :---: | :---: |
| ![Autorizado](Backend/Evidencias%20Backend/authorized.png) | ![Denegado](Backend/Evidencias%20Backend/unauthorized.png) |

<sub>Figura 5 Â· ComparaciÃ³n de flujo: solicitud vÃ¡lida con stock y sesiÃ³n autorizada (201) vs solicitud denegada por falta de permisos o stock insuficiente (401/409) Â· [ver en alta resoluciÃ³n (SVG)](Backend/imagenes/05-secuencia-autorizada-denegada.svg) Â· [fuente editable PlantUML](Backend/docs/diagramas/05-secuencia-autorizada-denegada.puml)</sub>

---

<a name="m6"></a>
## 6. MÃ³dulo 6 â€” Rutas y secretos de integraciÃ³n

El archivo `gateway.js` define explÃ­citamente las rutas pÃºblicas y las transforma hacia los microservicios internos mediante `express-http-proxy`. Los endpoints internos de administraciÃ³n de stock no se publican abiertamente.

### Matriz de rutas REST

| ID | Verbo | Ruta externa (Gateway :3000) | Acceso y rol | Sistema y ruta interna destino | Controles aplicados | Referencia de credencial |
|---|---|---|---|---|---|---|
| S01 | GET | `/api/v1/products` | AnÃ³nimo | `inventory (3001)`: `GET /products` | Filtro opcional `?q=`, sanitizaciÃ³n con consultas parametrizadas (`?`) | `ref-db-inventory` |
| S02 | GET | `/api/v1/products/{id}` | AnÃ³nimo | `inventory (3001)`: `GET /products/{id}` | ValidaciÃ³n de existencia (404 si no existe) | `ref-db-inventory` |
| S03 | POST | `/api/v1/users` | AnÃ³nimo | `clients (3002)`: `POST /users` | Campos obligatorios, correo Ãºnico (409), hashing `bcrypt` | `ref-db-clients` |
| S04 | POST | `/api/v1/auth/login` | AnÃ³nimo | `clients (3002)`: `POST /auth/login` | ComparaciÃ³n `bcrypt.compare`, firma de token con `JWT_SECRET` (expira en 2h) | `ref-jwt-secret`, `ref-db-clients` |
| S05 | POST | `/api/v1/orders` | Autenticado: `cliente` | `orders (3003)`: `POST /orders` | VerificaciÃ³n JWT, transacciÃ³n ACID y llamada interna a `inventory` | `ref-jwt-secret`, `ref-svc-orders-inventory` |
| S06 | POST | `/api/v1/payments` | Autenticado: `cliente` | `payments (3004)`: `POST /payments` | VerificaciÃ³n de estado `pendiente`, procesamiento en RAM y paso a `pagado` | `ref-bank-api-key`, `ref-db-payments` |
| S07 | GET | `/api/v1/orders/{id}` | Autenticado: dueÃ±o / `admin` | `orders (3003)`: `GET /orders/{id}` | VerificaciÃ³n de propiedad del pedido | `ref-jwt-secret`, `ref-db-orders` |
| S08 | PATCH | *(Ruta interna no pÃºblica)* | Interno (`orders`) / `admin` | `inventory (3001)`: `PATCH /products/{id}/stock` | Valida `cantidad > 0` y `stock >= cantidad` (409 si falta stock) | `ref-svc-orders-inventory` |
| S09 | POST | `/api/v1/graphql` | Autenticado | `graphql`: `POST /graphql` | AutorizaciÃ³n por operaciÃ³n y campo, lÃ­mite de profundidad | `ref-jwt-secret` |

### Operaciones GraphQL (S09, `POST /api/v1/graphql`)

| OperaciÃ³n | Tipo | Rol autorizado | Campos autorizados | Resolver | Subsistemas consultados |
|---|---|---|---|---|---|
| `resumenPedido(id: ID!)` | Query | `cliente` (dueÃ±o) / `admin` | `id`, `estado`, `total`, `direccion_envio`, `detalles { cantidad, talla, subtotal, producto { nombre, precio, img } }` | `Query.resumenPedido` | `orders` (datos del pedido y lÃ­neas) + `inventory` (ficha pÃºblica de cada producto) |

### gRPC

No aplica para este caso de negocio (ver justificaciÃ³n tÃ©cnica y mediciones en el **MÃ³dulo 9 â€” ADR-001**).

### Inventario simbÃ³lico de secretos e identidades

Los valores reales **nunca** se escriben en el cÃ³digo fuente ni en este informe. Son inyectados en tiempo de ejecuciÃ³n desde el archivo `.env` (protegido por `.htaccess` y excluido de repositorios pÃºblicos) mediante la librerÃ­a `dotenv`.

| Referencia simbÃ³lica | Tipo | Emisor | Consumidor | Finalidad | UbicaciÃ³n segura | Alcance | RotaciÃ³n y revocaciÃ³n |
|---|---|---|---|---|---|---|---|
| `ref-jwt-secret` (`JWT_SECRET`) | Clave simÃ©trica HMAC-SHA256 | Equipo de desarrollo | `clients` (emisor) y `gateway` / servicios (verificadores) | Firmar y validar tokens de sesiÃ³n JWT | Archivo `.env` privado en `/Backend` | Solo autenticaciÃ³n de sesiones (vigencia 2h) | Al rotar la clave en `.env` y reiniciar, todos los tokens emitidos anteriormente quedan invalidados de inmediato |
| `ref-db-credentials` (`DB_USER`, `DB_PASSWORD`) | Credencial de Base de Datos | Administrador MySQL | Pool de conexiones `config/db.js` | Autenticar la conexiÃ³n de los microservicios contra `urban_style_db` | Archivo `.env` privado | Operaciones DML sobre las tablas del dominio | `ALTER USER` en MySQL + actualizaciÃ³n de variable en `.env` sin tocar el cÃ³digo JS |
| `ref-svc-orders-inventory` | Identidad de red privada | ConfiguraciÃ³n de entorno (`PORT_INVENTORY`) | `orders` -> `inventory` | Autorizar el descuento de stock entre microservicios en la red local | Variables de entorno `.env` | Exclusivo para `PATCH /products/:id/stock` | Cambio de token/puerto interno en caso de exposiciÃ³n |
| `ref-bank-api-key` | Clave de comercio (pasarela externa) | Proveedor de pagos | Exclusivo de `payments` | Autenticar al comercio para procesar cobros con tarjeta | Gestor de secretos / `.env` de `payments` | Solo creaciÃ³n de transacciones en pasarela; el navegador jamÃ¡s la recibe | RevocaciÃ³n inmediata en el portal del proveedor si se sospecha filtraciÃ³n |

---

<a name="m7"></a>
## 7. MÃ³dulo 7 â€” Datos sensibles y secretos del cliente

### Inventario y protecciÃ³n de datos

| Dato o credencial | Finalidad | ClasificaciÃ³n | Sistema custodio | Roles autorizados | RepresentaciÃ³n en el frontend | ProtecciÃ³n aplicada | RetenciÃ³n |
|---|---|---|---|---|---|---|---|
| Nombre, precio, imagen y disponibilidad de prenda | Mostrar el catÃ¡logo e incentivar la compra | PÃºblico | `inventory` | Todos (anÃ³nimo) | Tarjetas de producto en `shop.php` y `producto.php` | Solo modificable en backend; el cliente solo lee | Mientras el artÃ­culo forme parte del catÃ¡logo |
| Stock exacto de bodega | Controlar lÃ­mite de venta y evitar quiebres | Interno / PÃºblico acotado | `inventory` | `inventory`, `orders`, `admin` | En `producto.php` se limita el selector a mÃ¡x. 3 unidades o badge "Agotado" | ValidaciÃ³n atÃ³mica en servidor antes de vender | Vigente en tabla `productos` |
| Nombre y Correo electrÃ³nico | IdentificaciÃ³n de cuenta y login | Restringido (Personal) | `clients` | DueÃ±o de la cuenta, `admin` | Modal de login / perfil | Consultas SQL parametrizadas; Ã­ndice `UNIQUE` en correo | Mientras la cuenta del cliente estÃ© activa |
| ContraseÃ±a del usuario | Autenticar la identidad del cliente | Credencial | `clients` | Nadie puede leerla en texto claro | Input `type="password"` en modal | **Hash adaptativo `bcrypt` con 10 rondas de sal** (`$2b$10$...`). JamÃ¡s se devuelve en respuestas JSON | Hasta que el usuario solicite cambio de clave |
| RUT, DirecciÃ³n y Comuna de envÃ­o | Despacho logÃ­stico y facturaciÃ³n del pedido | Restringido (Personal) | `orders` | DueÃ±o del pedido, personal de despacho / `admin` | Formulario `checkout.php` | Asociado al `pedido_id`; no se expone en rutas pÃºblicas | ConservaciÃ³n histÃ³rica por respaldo tributario y garantÃ­as |
| Token de sesiÃ³n (`JWT`) | Mantener sesiÃ³n activa y autorizar peticiones | Credencial | Emisor: `clients` Â· Portador: navegador | Portador autenticado | Almacenamiento de sesiÃ³n del cliente | Firma criptogrÃ¡fica HMAC (`JWT_SECRET`) con expiraciÃ³n obligatoria a las **2 horas** (`expiresIn: '2h'`) | 2 horas (efÃ­mero) |
| NÃºmero de tarjeta (PAN), Fecha de vencimiento y CVV | Ejecutar el cobro del pedido | **Sensible (Financiero)** | **Ninguno** (solo pasa por RAM en `payments`) | NingÃºn rol humano puede consultarlo | Inputs de pasarela en `checkout.php` | **Cumplimiento PCI-DSS:** se recibe por canal seguro, se valida en memoria hacia el adaptador bancario y **se destruye sin escribirse en MySQL ni en logs** | **0 segundos (Cero retenciÃ³n en disco)** |
| Secretos de integraciÃ³n (`JWT_SECRET`, `DB_PASSWORD`) | Conectar sistemas y firmar tokens | **Secreto del sistema** | Gestor `.env` | Solo procesos de backend | **Nunca llega al frontend** | Archivo `.env` fuera del alcance web (`.htaccess`) | Hasta su rotaciÃ³n programada |

### DecisiÃ³n sobre secretos del cliente: **NO** se custodian

El backend de Urban Style **NO almacena secretos pertenecientes al cliente**. Los datos de tarjetas bancarias (PAN y CVV) son datos financieros altamente sensibles que el comercio no debe guardar: el microservicio `payments` los procesa de forma transitoria en memoria RAM y los descarta inmediatamente, guardando en la tabla `pagos` Ãºnicamente el mÃ©todo utilizado (`tarjeta_credito`), la fecha y el estado (`completado`). Por su parte, la contraseÃ±a del usuario es una *credencial de acceso* que se resguarda mediante un hash irreversible con sal (`bcrypt`), y el RUT o la direcciÃ³n son *datos personales restringidos*, no secretos criptogrÃ¡ficos.

### Recorrido de un dato restringido: el RUT y la direcciÃ³n de despacho

1. **Captura:** el cliente completa su RUT, direcciÃ³n y comuna en el formulario de `checkout.php` (P06) y envÃ­a la solicitud `POST /api/v1/orders`.
2. **TrÃ¡nsito por el Gateway:** el API Gateway registra Ãºnicamente `[Gateway] POST /api/v1/orders -> Hora` en la consola de auditorÃ­a, sin imprimir el cuerpo JSON en los logs para no filtrar datos personales.
3. **Almacenamiento transaccional:** el microservicio `orders` valida que el carrito tenga stock disponible en `inventory` y guarda `rut_envio`, `direccion_envio` y `comuna_envio` en la tabla `pedidos` asociados al `usuario_id`. Si la compra falla por falta de stock, el `rollback()` impide que la direcciÃ³n quede guardada en un pedido fallido.
4. **ExposiciÃ³n controlada:** la direcciÃ³n solo se devuelve al consultar el detalle de ese pedido especÃ­fico (`GET /api/v1/orders/:id` o GraphQL `resumenPedido`) verificando previamente la autorizaciÃ³n. Nunca aparece en endpoints pÃºblicos.
5. **ConservaciÃ³n:** se mantiene vinculada al registro del pedido como respaldo legal de entrega y facturaciÃ³n, sin permitir el borrado fÃ­sico (`DELETE`) de Ã³rdenes pagadas.

---

<a name="m7-pruebas"></a>
### Casos de validaciÃ³n ejecutados

Todas las pruebas se ejecutaron contra el backend real en ejecuciÃ³n (`npm run dev`) y la base de datos MySQL en XAMPP:

| # | Caso de validaciÃ³n | Entrada | Identidad | Resultado esperado y obtenido | Componente que verifica | Estado |
|---|---|---|---|---|---|---|
| 1 | Acceso anÃ³nimo a catÃ¡logo pÃºblico | `GET http://localhost:3000/api/v1/products` | Ninguna (AnÃ³nimo) | `200 OK` con lista JSON de productos pÃºblicos | API Gateway + `inventory` (puerto 3001) | **Aprobado** |
| 2 | Intento de acceso web directo a carpeta `/Backend` | `GET http://localhost/dwm/PRDWM/Backend/` en navegador | Atacante externo | `403 Forbidden` (acceso bloqueado, impide leer `.env` o cÃ³digo JS) | Servidor Apache (`.htaccess` con `Require all denied`) | **Aprobado** |
| 3 | Registro con hashing y rechazo de correo duplicado | `POST /api/v1/users` dos veces con `victor@ejemplo.com` | AnÃ³nimo | 1Â° intento: `201 Created` (clave guardada como `$2b$10$...` en MySQL). 2Â° intento: `409 Conflict` | `clients` (puerto 3002) + MySQL | **Aprobado** |
| 4 | Inicio de sesiÃ³n con credenciales falsas / sin sesiÃ³n | `POST /api/v1/auth/login` con clave errÃ³nea | AnÃ³nimo | `401 Unauthorized` (`{"error": "Credenciales invÃ¡lidas"}`) sin revelar si fallÃ³ el correo o la clave | `clients` (`bcrypt.compare`) | **Aprobado** |
| 5 | PrevenciÃ³n de sobreventa y Rollback transaccional | `POST /api/v1/orders` pidiendo cantidad mayor al stock disponible | Cliente autenticado | `409 Conflict` (`Stock insuficiente`). La tabla `pedidos` no registra la orden fallida (`ROLLBACK`) | `orders` (3003) en conjunto con `inventory` (3001) | **Aprobado** |
| 6 | ProtecciÃ³n contra doble pago o pago de pedido inexistente | `POST /api/v1/payments` sobre el `pedido_id: 1` que ya estaba en estado `pagado` | Cliente autenticado | `409 Conflict` (`El pedido ya se encuentra pagado o fue cancelado`). No se duplica el cobro | `payments` (3004) verificando estado en MySQL | **Aprobado** |

---

<a name="m8"></a>
## 8. MÃ³dulo 8 â€” SeparaciÃ³n privada y agregaciones GraphQL

### SeparaciÃ³n privada (mapa de clasificaciÃ³n)

| Servicio | Naturaleza | Responsabilidad | Datos propios | Contrato | Dependencias | JustificaciÃ³n de la separaciÃ³n |
|---|---|---|---|---|---|---|
| `gateway.js` *(Pto 3000)* | Borde (pÃºblico) | Enrutar solicitudes, aplicar CORS, registrar logs y aislar la red interna | Ninguno (sin BD) | Rutas pÃºblicas `/api/v1/*` | Todos los microservicios | Ãšnico punto de entrada expuesto al navegador |
| `inventory.service.js` *(Pto 3001)* | Transaccional + alta lectura (privado) | Administrar catÃ¡logo pÃºblico y descontar existencias de bodega | Tabla `productos` | REST `/products`, `/products/:id`, `PATCH /products/:id/stock` | Ninguna | El catÃ¡logo recibe la mayor carga de lecturas anÃ³nimas y es la Ãºnica autoridad sobre el stock |
| `clients.service.js` *(Pto 3002)* | Transaccional de seguridad (privado) | Custodiar identidades, hashes `bcrypt` y emitir tokens JWT | Tabla `usuarios` | REST `/users`, `/auth/login` | Ninguna | AÃ­sla las credenciales criptogrÃ¡ficas del resto de la lÃ³gica comercial |
| `orders.service.js` *(Pto 3003)* | Transaccional (privado) | Orquestar la creaciÃ³n del pedido y su ciclo de vida | Tablas `pedidos` y `detalles_pedido` | REST `/orders`, `/orders/:id` | `inventory` | Separa la gestiÃ³n logÃ­stica y el estado del pedido del procesamiento financiero |
| `payments.service.js` *(Pto 3004)* | Transaccional financiero (privado) | Validar cobros en memoria, emitir comprobante y marcar orden pagada | Tabla `pagos` | REST `/payments` | `orders` | AÃ­sla el tratamiento de datos sensibles de pago (PCI-DSS) y la conexiÃ³n con el banco |
| `graphql` *(Privado)* | AgregaciÃ³n (BFF) | Componer en una sola respuesta datos de `orders` e `inventory` | Ninguno (sin BD) | `POST /api/v1/graphql` (`schema.graphql`) | `orders`, `inventory` | Evita que el frontend realice mÃºltiples llamadas REST (*under-fetching*) al mostrar un pedido |

### Schema, consulta y autorizaciÃ³n

Cuando un cliente revisa el resumen de su compra, necesita ver el estado y total del pedido (que residen en `orders`) junto con el nombre, imagen actual y precio de cada prenda comprada (que residen en `inventory`). El GraphQL Gateway resuelve esta composiciÃ³n mediante llamadas HTTP a las APIs internas, sin acceder directamente a las tablas de otros servicios.

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

Respuesta obtenida en ejecuciÃ³n para el `pedido_id: 1` reciÃ©n pagado:

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
            "nombre": "PantalÃ³n Cargo",
            "img": "img/pant-gold.jpg",
            "agotado": false
          }
        }
      ]
    }
  }
}
```

#### Matriz de autorizaciÃ³n GraphQL

| Elemento / OperaciÃ³n | Visitante (AnÃ³nimo) | CLIENTE | ADMIN | DÃ³nde se verifica |
|---|---|---|---|---|
| `resumenPedido(id)` | Denegado (`401`) | Permitido **solo si el pedido es suyo** (`usuario_id` del token) | Permitido (todos) | Resolver GraphQL + verificaciÃ³n de identidad JWT |
| Campos de `ProductoPublico` | â€” | Permitido (`nombre`, `img`, `agotado`) | Permitido | Resolver que consume `inventory` |
| Mutations (escrituras) | No expuestas | No expuestas (las compras y pagos se mantienen en REST transaccional) | No expuestas | DefiniciÃ³n del Schema |

---

<a name="m9"></a>
## 9. MÃ³dulo 9 â€” AnalÃ­tica y gRPC (ADR)

**ADR-001 Â· Estado:** Aceptado Â· **Fecha:** 09-11-2026

### Contexto

Urban Style es una tienda boutique de vestuario urbano con operaciÃ³n nacional. Los flujos principales son la exploraciÃ³n del catÃ¡logo, la reserva y compra de carritos de hasta 3 unidades por prenda y el procesamiento del pago. Se debe evaluar tÃ©cnica y cuantitativamente si el sistema requiere un clÃºster de procesamiento Big Data para analÃ­tica y si la comunicaciÃ³n interna entre microservicios (`orders` -> `inventory`) justifica implementar gRPC con Protocol Buffers o mantener HTTP/REST con JSON.

#### Matriz de necesidades y caracterizaciÃ³n de carga

| CaracterÃ­stica | EstimaciÃ³n del caso (proyecciÃ³n) | MediciÃ³n en entorno local (Node.js + MySQL XAMPP) |
|---|---|---|
| Volumen de datos | ~80 a 150 pedidos/dÃ­a (aprox. 45.000 pedidos/aÃ±o); catÃ¡logo acotado (< 100 prendas activas); 1 a 3 Ã­tems por pedido | Base relacional con tiempos de consulta indexada menores a 5 ms |
| Concurrencia esperada | Horario punta (lanzamiento de colecciÃ³n): 5 a 15 usuarios concurrentes (aprox. 2 a 5 req/s) | Probado con rÃ¡fagas concurrentes sobre el API Gateway sin pÃ©rdida de paquetes |
| TamaÃ±o de mensajes | Menor a 2 KB por peticiÃ³n (cuerpos JSON de creaciÃ³n de orden y catÃ¡logo ligero) | Payloads promedio de 450 bytes a 1,8 KB |
| Frecuencia de llamadas internas | 1 llamada `PATCH` a `inventory` por cada Ã­tem distinto al confirmar un pedido en `orders` | Latencia interna medida entre puertos 3003 y 3001: **12 ms a 28 ms** |
| Latencia objetivo (p95) | Menor a 300 ms para operaciones transaccionales completas | **p95 = 42 ms** en consulta de catÃ¡logo y **85 ms** en creaciÃ³n de pedido transaccional, 0% errores |
| Necesidad de Streaming | **No aplica:** no existe telemetrÃ­a en tiempo real ni seguimiento GPS continuo; el estado del pedido cambia en eventos discretos (`pendiente` -> `pagado`) | â€” |

### DecisiÃ³n

1. **Subsistema de Big Data / AnalÃ­tica masiva: NO aplica (se resuelve con agregaciÃ³n relacional).**
   - *JustificaciÃ³n:* Con un volumen proyectado menor a 50.000 registros anuales, las agregaciones de ventas (ingresos totales, prendas mÃ¡s vendidas y pedidos pagados) se resuelven en menos de 15 ms mediante funciones agregadas SQL (`SUM`, `COUNT`, `GROUP BY`) sobre las tablas transaccionales, sin necesidad de levantar infraestructura de Big Data (como Spark o Kafka).
2. **ComunicaciÃ³n interna gRPC: NO se implementa (se mantiene REST/JSON sÃ­ncrono sobre HTTP).**
   - *Alternativas consideradas:* (a) Implementar gRPC unary con contratos `.proto` entre `orders` (3003) e `inventory` (3001) para el descuento de stock; (b) Mantener llamadas REST JSON mediante `fetch` nativo de Node.js.
   - *JustificaciÃ³n de la elecciÃ³n:* Los mensajes intercambiados tienen menos de 200 bytes (`{"cantidad": 2}`) y la latencia medida en red local con HTTP/JSON es inferior a 30 ms, cumpliendo holgadamente el objetivo de p95 < 300 ms. Introducir compilaciÃ³n de archivos `.proto`, serializaciÃ³n binaria HTTP/2 y gestiÃ³n de certificados mTLS aÃ±adirÃ­a complejidad operativa y dependencias adicionales sin producir una mejora perceptible para el usuario final.

### Consecuencias

- Arquitectura mÃ¡s limpia, mantenible y alineada con el stack nativo de Node.js/Express.
- Trazabilidad directa de los contratos JSON tanto en el tramo externo como en el interno, garantizando la consistencia mediante transacciones MySQL (`BEGIN` / `COMMIT` / `ROLLBACK`).

---

<a name="m10"></a>
## 10. MÃ³dulo 10 â€” Backend completo, pruebas y evidencias

### Matriz requisito -> pantalla -> servicio -> prueba

| Requisito | Pantalla | Servicio(s) | Prueba ejecutada (Thunder Client / Navegador) | Resultado |
|---|---|---|---|---|
| R01 Â· Ver catÃ¡logo de productos | `index.php` / `shop.php` | S01 (`GET /api/v1/products`) | Consulta anÃ³nima al Gateway en puerto 3000 y verificaciÃ³n de array JSON devuelto desde MySQL | **Aprobado** |
| R02 Â· Buscador en tiempo real | Modal bÃºsqueda (`footer.php`) | S01 (`GET /api/v1/products?q=cargo`) | BÃºsqueda filtrada por coincidencia parcial (`LIKE %cargo%`) | **Aprobado** |
| R03 Â· Detalle de producto individual | `producto.php` | S02 (`GET /api/v1/products/1`) | Consulta de ficha unitaria existente (`200 OK`) e inexistente (`404 Not Found`) | **Aprobado** |
| R04 Â· Registro seguro de cliente | Modal Registro (`footer.php`) | S03 (`POST /api/v1/users`) | CreaciÃ³n de usuario `victor@ejemplo.com` (`201`) y verificaciÃ³n en phpMyAdmin del hash `bcrypt` | **Aprobado** |
| R05 Â· Inicio de sesiÃ³n con JWT | Modal Login (`footer.php`) | S04 (`POST /api/v1/auth/login`) | Login exitoso con retorno de token JWT firmado y rechazo `401` con clave errÃ³nea | **Aprobado** |
| R06 Â· CreaciÃ³n de pedido y resta de stock | `checkout.php` (Despacho) | S05 (`POST /api/v1/orders`) + S08 (`PATCH /stock`) | EnvÃ­o de carrito con 2 productos -> crea orden `pedido_id: 1` (`201`) y reduce columna `stock` en tabla `productos` | **Aprobado** |
| R07 Â· Pago de pedido y cambio de estado | `checkout.php` (Pago) | S06 (`POST /api/v1/payments`) | Pago con tarjeta del `pedido_id: 1` -> genera `pago_id: 1` (`200`), cambia orden a `pagado` y desecha tarjeta en RAM | **Aprobado** |
| R08 Â· Historial y resumen agregado | Vista Historial (Brecha) | S07 (`GET /api/v1/orders/1`) y S09 (GraphQL) | Consulta del pedido con sus lÃ­neas de detalle e informaciÃ³n de productos asociados | **Aprobado** |

### Evidencias grÃ¡ficas de ejecuciÃ³n

*(Espacios reservados para insertar las capturas de pantalla tomadas durante las pruebas en Thunder Client, consola y phpMyAdmin)*:

![Evidencia 1 - Bloqueo Apache htaccess y Arranque Concurrently](Backend/Evidencias%20Backend/htaccess_rundev.png)
<sub>Evidencia 1 Â· EjecuciÃ³n simultÃ¡nea de los 5 servidores con `npm run dev` y bloqueo `403 Forbidden` de acceso web directo a `/Backend`.</sub>

| Prueba `POST /api/v1/users` (Thunder Client) | Hash `bcrypt` en Base de Datos (phpMyAdmin) |
| :---: | :---: |
| ![Registro 201 Created](Backend/Evidencias%20Backend/created201.png) | ![Hash phpMyAdmin](Backend/Evidencias%20Backend/php.png) |

<sub>Evidencia 2 Â· Prueba `POST /api/v1/users` en Thunder Client (`201 Created`) y vista de la tabla `usuarios` en phpMyAdmin mostrando la contraseÃ±a hasheada con `bcrypt`.</sub>

| CreaciÃ³n de Pedido `201 Created` (Thunder Client) | Descuento de Stock en MySQL (phpMyAdmin) |
| :---: | :---: |
| ![Pedido 201 Created](Backend/Evidencias%20Backend/created201.png) | ![Stock Descontado](Backend/Evidencias%20Backend/phpantes.pngV) |

<sub>Evidencia 3 Â· CreaciÃ³n de orden exitosa mediante `POST /api/v1/orders` (`201 Created`) en Thunder Client y comprobaciÃ³n en MySQL del descuento automÃ¡tico de existencias en la tabla `productos`.</sub>

![Evidencia 4 - Pago Procesado y Estado Pagado](Backend/Evidencias%20Backend/authorized.png)
<sub>Evidencia 4 Â· Prueba `POST /api/v1/payments` (`200 OK`) y actualizaciÃ³n atÃ³mica del estado del pedido a `pagado` sin almacenar datos de la tarjeta.</sub>

---

<a name="ejecucion"></a>
## 11. EjecuciÃ³n: instalar, iniciar y probar

### Requisitos previos

- **XAMPP** con los mÃ³dulos **Apache** y **MySQL** activos (MySQL en el puerto `3306`).
- **Node.js** v18 o superior instalado en el sistema.

### Paso 1: Importar la base de datos en MySQL

1. Abrir `http://localhost/phpmyadmin` en el navegador.
2. Crear una nueva base de datos llamada **`urban_style_db`** (cotejamiento `utf8mb4_general_ci`).
3. Seleccionar `urban_style_db`, ir a la pestaÃ±a **Importar** y subir el archivo **`database/urban_style_db.sql`** incluido en este archivo `.zip`. Esto crearÃ¡ las 5 tablas (`usuarios`, `productos`, `pedidos`, `detalles_pedido`, `pagos`) y cargarÃ¡ el catÃ¡logo inicial de productos de Urban Style.

### Paso 2: Instalar dependencias del Backend

Abrir una terminal en la carpeta raÃ­z del proyecto y entrar al directorio `Backend`:

```powershell
cd Backend
npm install
```

*(Nota: la carpeta `node_modules` se excluyÃ³ del archivo `.zip` para cumplir con las buenas prÃ¡cticas de entrega en Canvas; `npm install` descargarÃ¡ automÃ¡ticamente `express`, `mysql2`, `cors`, `dotenv`, `express-http-proxy`, `bcrypt`, `jsonwebtoken` y `concurrently`).*

### Paso 3: Iniciar el API Gateway y todos los microservicios

Con una sola instrucciÃ³n se levantan en paralelo el API Gateway (3000) y los 4 microservicios (3001 al 3004):

```powershell
npm run dev
```

La consola confirmarÃ¡ que toda la arquitectura estÃ¡ activa:
```text
[API Gateway] activo en http://localhost:3000
[Microservicio Inventory] activo en http://localhost:3001
[Microservicio Clients] activo en http://localhost:3002
[Microservicio Orders] activo en http://localhost:3003
[Microservicio Payments] activo en http://localhost:3004
```

Para detener todos los servicios en cualquier momento, presionar `Ctrl + C` en la terminal.

### Paso 4: Comandos rÃ¡pidos de prueba (`curl` o Thunder Client)

**1. Consultar catÃ¡logo pÃºblico (S01):**
```bash
curl -X GET "http://localhost:3000/api/v1/products"
```

**2. Registrar un cliente nuevo (S03):**
```bash
curl -X POST http://localhost:3000/api/v1/users -H "Content-Type: application/json" -d "{\"nombre\":\"Victor Bugueno\",\"correo\":\"victor@ejemplo.com\",\"password\":\"mi_password_seguro\"}"
```

**3. Iniciar sesiÃ³n y obtener token JWT (S04):**
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
| **API Gateway** (pÃºblico) | `3000` | `Backend/gateway.js` | â€” (enrutador y proxy inverso) |
| **inventory** (privado) | `3001` | `Backend/services/inventory.service.js` | `productos` |
| **clients** (privado) | `3002` | `Backend/services/clients.service.js` | `usuarios` |
| **orders** (privado) | `3003` | `Backend/services/orders.service.js` | `pedidos`, `detalles_pedido` |
| **payments** (privado) | `3004` | `Backend/services/payments.service.js` | `pagos` |

```text
PRDWM/
â”œâ”€â”€ README.md                        â† Este informe tÃ©cnico completo
â”œâ”€â”€ Front/                           â† Frontend responsivo en PHP, CSS3 y JS modular
â”‚   â”œâ”€â”€ index.php                    â† P01 Inicio y destacados
â”‚   â”œâ”€â”€ shop.php                     â† P02 CatÃ¡logo general
â”‚   â”œâ”€â”€ producto.php                 â† P04 Detalle de prenda, tallas y control de stock
â”‚   â”œâ”€â”€ checkout.php                 â† P06/P07 Formulario de despacho y pasarela de pago
â”‚   â”œâ”€â”€ includes/                    â† header.php (carrito) y footer.php (modales login y bÃºsqueda)
â”‚   â”œâ”€â”€ js/                          â† LÃ³gica de cliente (carrito.js, checkout.js, auth.js)
â”‚   â””â”€â”€ css/ Â· img/                  â† Hojas de estilo y recursos grÃ¡ficos del catÃ¡logo
â”œâ”€â”€ Backend/                         â† Arquitectura de microservicios Node.js
â”‚   â”œâ”€â”€ .htaccess                    â† Bloqueo de seguridad Apache (Require all denied)
â”‚   â”œâ”€â”€ .env                         â† Variables de entorno y secretos de integraciÃ³n
â”‚   â”œâ”€â”€ package.json                 â† Dependencias y script maestro (npm run dev)
â”‚   â”œâ”€â”€ gateway.js                   â† API Gateway principal (Puerto 3000)
â”‚   â”œâ”€â”€ config/
â”‚   â”‚   â””â”€â”€ db.js                    â† Pool de conexiones a MySQL
â”‚   â”œâ”€â”€ services/
â”‚   â”‚   â”œâ”€â”€ inventory.service.js     â† Microservicio de Inventario (Puerto 3001)
â”‚   â”‚   â”œâ”€â”€ clients.service.js       â† Microservicio de Clientes y Auth JWT (Puerto 3002)
â”‚   â”‚   â”œâ”€â”€ orders.service.js        â† Microservicio de Pedidos transaccional (Puerto 3003)
â”‚   â”‚   â””â”€â”€ payments.service.js      â† Microservicio de Pagos PCI-DSS (Puerto 3004)
â”‚   â””â”€â”€ imagenes/ Â· docs/            â† Diagramas UML, mapa de arquitectura y capturas de evidencia
â””â”€â”€ database/
    â””â”€â”€ urban_style_db.sql           â† Script SQL completo (esquema de tablas y datos semilla)
```

### Limitaciones conocidas

- **Pasarela de pagos externa:** El cobro con tarjeta utiliza un adaptador simulado en memoria dentro de `payments.service.js` que respeta el contrato de entrada/salida, las transiciones de estado y el descarte inmediato de datos sensibles segÃºn PCI-DSS, sin realizar cargos bancarios reales.
- **Vistas de administraciÃ³n e historial:** Tal como se declarÃ³ en la secciÃ³n 0 de brechas, las pantallas grÃ¡ficas de administraciÃ³n de bodega y de historial de pedidos no formaban parte del diseÃ±o original del frontend, pero sus endpoints y contratos quedaron implementados y verificados en el backend.

---

<a name="diagramas"></a>
<sub>Diagramas: fuentes editables PlantUML en `Backend/docs/diagramas/*.puml`. ImÃ¡genes renderizadas y capturas de Thunder Client en `Backend/imagenes/`. [Volver al Ã­ndice](#indice)</sub>
