# Módulo de Previajes de Equipos (Multi-Flota)

Digitaliza el checklist de previaje que hoy se llena en un Google Form
("PRE VIAJE DE CABEZALES FLOTA HONDURAS"), generalizado a cualquier tipo de
equipo y a múltiples flotas.

Implementa los requerimientos de `requerimientos-modulo-previajes.md`. A lo
largo del código las decisiones se anotan con su referencia (RF-xx, RN-xx,
RNF-xx) para que se pueda rastrear cada regla hasta el documento.

## Versiones

| Componente | Versión |
|---|---|
| Laravel | **13.21.1** |
| PHP | 8.4 |
| MySQL | 9.7 |
| Inertia (Laravel) | 2.0 |
| React | 19 |
| Tailwind CSS | 4 |
| Pest | 4 |

RNF-08 pide fijar la última versión estable al iniciar. El starter kit oficial
de React todavía venía atado a Laravel 12, así que se subió a Laravel 13 y se
actualizaron `laravel/tinker` a 3.x y PHPUnit a 12.x, que eran las dos
dependencias que lo bloqueaban.

## Puesta en marcha

```bash
composer install && npm install
```

```bash
cp .env.example .env && php artisan key:generate
```

Configure la conexión en `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) y
cree la base:

```bash
mysql -u root -p -e "CREATE DATABASE flota CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
php artisan migrate --seed
```

```bash
composer run dev
```

`composer run dev` levanta el servidor, Vite y el worker de cola a la vez. El
worker importa: la compresión de fotos corre en cola (RF-11 / RNF-02).

### Usuario inicial

`UsuarioSeeder` crea el super administrador con lo que haya en el entorno:

```
PREVIAJES_SUPER_ADMIN_EMAIL=super@empresa.com
PREVIAJES_SUPER_ADMIN_PASSWORD=<contraseña fuerte>
```

**No hay auto-registro.** Las cuentas las crea el administrador (RF-18), porque
cada usuario debe nacer con un rol y una flota asignados; dejar `/register`
abierto permitiría que cualquiera entrara al módulo (§7).

Fuera de producción, `DemoSeeder` agrega un usuario por rol
(`mecanico@previajes.test`, `supervisor@previajes.test`,
`admin@previajes.test`, contraseña `password`) y equipos de ejemplo.

## Pruebas

```bash
php artisan test
```

Corren contra **MySQL** (`flota_test`), no SQLite: el modelo depende de columnas
`ENUM` y de integridad referencial (RNF-05), y en SQLite esas diferencias de
motor pasarían desapercibidas. Cree la base una vez:

```bash
mysql -u root -p -e "CREATE DATABASE flota_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Además del suite de PHP, el frontend se verifica con `npx tsc --noEmit`.

## Cómo está organizado

```
app/
  Enums/            RolUsuario, EstatusPreviaje
  Http/
    Requests/       PreviajeRequest: toda la validación condicional del checklist
    Middleware/     AsegurarUsuarioActivo, CabecerasDeSeguridad
  Jobs/             ComprimirFotoPreviaje (RF-11, en cola)
  Models/           Modelo de datos de la sección 9
    Concerns/       PerteneceAFlota: el scope multi-flota
  Policies/         Permisos por rol y por flota
  Services/         ChecklistService, PreviajeService, FotoPreviajeService,
                    NotificadorPreviaje, AuditoriaService
database/seeders/   ChecklistSeeder precarga el Anexo A
resources/js/pages/ previajes/, equipos/, llantas/, auditoria/, dashboard
```

### Decisiones que conviene conocer

- **El checklist no está en el código.** Secciones, ítems y opciones viven en
  catálogos (RF-05 a RF-07). `ChecklistService` es la única fuente de verdad:
  la usa tanto el formulario para renderizarse como el Form Request para
  validar, de modo que no puedan divergir.
- **Qué cuenta como hallazgo lo decide el dato, no el código.** Cada opción
  lleva `es_optima`; el estatus del previaje se recalcula a partir de eso
  (RN-04), sin comparar etiquetas como "Nivel bajo".
- **El umbral de días sin previaje es por tipo de equipo**, no global: es un
  campo de `tipos_equipo` (RF-16.1 / RN-12). `configuraciones` queda para los
  parámetros que sí aplican a todo el sistema, como el peso máximo de foto.
- **La fecha la pone el servidor.** Nunca hay un input de fecha; se usa
  `created_at` y al editar no cambia (RF-10).
- **Los previajes no se borran, se anulan** (RF-12), y cada edición notifica al
  supervisor de la flota y al administrador, y queda en la bitácora (RN-05).
- **Las fotos son privadas.** Van a un disco no público y se sirven por
  `PreviajeFotoController`, que verifica que el usuario pueda ver ese previaje.
  Publicarlas en `storage/app/public` las dejaría accesibles a quien adivinara
  la URL. Al comprimirlas se descartan los metadatos EXIF, que suelen traer la
  geolocalización del teléfono.
- **La auditoría se recorta en la consulta, no en la vista** (`AuditoriaService`).
  Supervisor → su(s) flota(s); administrador → todas, menos la actividad del
  super administrador; super administrador → todo (RN-09).
- **Se usa `spatie/laravel-activitylog` en vez de la tabla `auditorias`**
  propuesta, como recomienda la sección 9. En la versión 5 del paquete los
  cambios viven en la columna `attribute_changes`, no en `properties`.
- **La tabla de usuarios se llama `users`**, no `usuarios`, por convención de
  Laravel y compatibilidad con el andamiaje de autenticación (RNF-06). Las
  columnas de negocio sí siguen la nomenclatura del documento.

## Estado frente al roadmap

**Fase 1 y Fase 2 implementadas**, salvo lo indicado abajo:

| Requerimiento | Estado |
|---|---|
| RF-01 a RF-04 — autenticación, roles, flotas, equipos | Completo (alta de equipos y usuarios aún sin UI) |
| RF-05 a RF-08 — checklist administrable y galones | Completo en modelo y seeders; **falta la UI de administración** |
| RF-09 a RF-14 — previaje, fotos, edición, alertas | Completo |
| RF-15, RF-16, RF-16.1 — historial, equipos, umbral | Completo |
| RF-17, RF-17.1 — dashboard, consumos, llantas | Completo |
| RF-18 — CRUD de catálogos | **Pendiente** (ver abajo) |
| RF-19, RF-20 — auditoría y pantalla de monitoreo | Completo |
| RNF-00 a RNF-10 | Completo |

### Pendiente: RF-18, la UI de administración de catálogos

El modelo, las políticas y la auditoría de los catálogos ya están; lo que falta
son las pantallas para editarlos desde el navegador. Mientras tanto se
administran por seeder o por `php artisan tinker`. Es el siguiente trabajo
natural, y no bloquea el uso del módulo: el checklist del Anexo A ya viene
precargado.

## Decisiones a confirmar con negocio

Se tomaron para no bloquear el desarrollo; cambiarlas es editar un dato, no
código:

1. **"Nivelación GLNS" se sembró como hallazgo** (`es_optima = false`), porque
   implica que el nivel estaba bajo y hubo que nivelarlo. Si el negocio la
   considera un estado normal, se cambia desde el catálogo de opciones.
2. **Umbrales de días sin previaje** (RF-16.1): Cabezal 2, Reach Stacker y Top
   Loader 3, Chasis y Genset 7. El documento sólo sugería 2 para cabezales.
3. **Qué secciones aplican a cada tipo de equipo**: el cabezal lleva las tres
   del formulario actual; el chasis sólo CHASIS y el genset sólo MOTOR. Los
   tipos distintos del cabezal no estaban definidos en el documento.
4. **Foto máxima 400 KB y lado mayor 1600 px** (el documento sugería 300–500 KB).
5. **Anular un previaje queda reservado a supervisor y superiores.** El
   documento dice que los previajes se anulan en vez de borrarse, pero no
   precisa quién puede hacerlo.
6. **La actividad que tiene al super administrador como sujeto también se
   oculta**, no sólo la que él realiza. RF-20 habla de actividad "realizada
   por", pero mostrar el alta o el cambio de correo de esa cuenta delataría lo
   mismo que la regla protege.
