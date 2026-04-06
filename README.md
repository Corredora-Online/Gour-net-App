# Gournet Dashboard

Plugin de WordPress que proporciona un dashboard de ventas en tiempo real para locales Gournet. Incluye soporte PWA para instalarse como app nativa en cualquier dispositivo.

---

## Características principales

- **Dashboard de ventas en tiempo real**: Visualiza ventas del dia actual con auto-refresh cada 60 segundos.
- **Comparacion con semana anterior**: Muestra las ventas del mismo dia de la semana pasada para comparar rendimiento.
- **Multi-local**: Pestanas para alternar entre sucursales o ver un resumen global de todos los locales.
- **KPIs clave**: Venta del dia, venta de la semana anterior, hora pico, ticket promedio y total de transacciones.
- **Graficos interactivos**: Ventas por hora (linea) y comparacion entre locales (barras), usando Chart.js.
- **Ranking de locales**: Tabla con posicion, ventas, variacion porcentual y barra visual.
- **PWA (Progressive Web App)**: Manifest, Service Worker con cache offline, banner de instalacion e instrucciones para iOS.
- **Login seguro**: Autenticacion via AJAX con formateo de RUT chileno, honeypot anti-bot, rate limiting por IP y bloqueo temporal.
- **Modo claro / oscuro**: Toggle de tema desde el menu de usuario.
- **Auto-updater**: Actualizaciones automaticas desde GitHub Releases.

---

## Requisitos

- WordPress 5.0 o superior
- PHP 7.4 o superior
- Acceso al webhook de datos de ventas (configurado internamente)

---

## Instalacion

1. Descarga o clona este repositorio.
2. Sube la carpeta `gournet-dashboard` a `wp-content/plugins/`.
3. Activa el plugin desde el escritorio de WordPress en **Plugins**.
4. Usa el shortcode `[gournet_dashboard]` en cualquier pagina para mostrar el dashboard.

---

## Uso

### Shortcode

```php
[gournet_dashboard]
```

Inserta el dashboard completo en cualquier pagina o entrada de WordPress. Si el usuario no ha iniciado sesion, muestra un formulario de login. Una vez autenticado, muestra el panel de ventas.

### Estructura de la interfaz

| Seccion | Descripcion |
|---------|-------------|
| **Header** | Logo, boton de refresh, hora de ultima actualizacion y menu de usuario |
| **Tabs** | Pestanas para seleccionar un local especifico o ver todos |
| **KPIs** | 5 tarjetas con metricas clave del dia |
| **Grafico por hora** | Curva de ventas hora a hora del local seleccionado |
| **Grafico comparativo** | Barras comparando ventas actuales vs semana anterior por local |
| **Ranking** | Tabla ordenada por ventas con variacion y barra visual |

---

## Estructura de archivos

```
gournet-dashboard/
├── gournet-dashboard.php   # Archivo principal del plugin (PHP)
├── updater.php             # Auto-updater desde GitHub Releases
├── assets/
│   ├── dashboard.css       # Estilos del dashboard
│   ├── dashboard.js        # Logica del frontend (fetch, charts, UI)
│   ├── sw.js               # Service Worker para PWA y cache offline
│   └── offline.html        # Pagina offline de fallback
└── README.md
```

---

## Actualizaciones automaticas

El plugin incluye un sistema de auto-update conectado a este repositorio de GitHub. Cuando se crea una nueva **Release**:

1. WordPress detecta automaticamente la nueva version disponible.
2. Muestra el aviso de "Actualizar" en el panel de plugins.
3. Al hacer click, descarga e instala la nueva version automaticamente.

### Como publicar una nueva version

1. Actualiza la version en el header de `gournet-dashboard.php` (`Version: X.X.X`).
2. Actualiza la constante `GOURNET_VERSION` en el mismo archivo.
3. Actualiza el nombre del cache en `assets/sw.js` (`gournet-vN`) para forzar la actualizacion del cache PWA.
4. Haz commit y push de los cambios al repositorio.
5. Crea una nueva **Release** en GitHub con un tag que coincida con la version (ej: `1.0.2`).

### Forzar busqueda de actualizaciones

En el panel de WordPress, en la fila del plugin aparece un enlace **"Buscar actualizaciones"** que fuerza la verificacion inmediata contra GitHub.

---

## PWA (Progressive Web App)

El dashboard puede instalarse como aplicacion nativa:

- **Android / Chrome**: Se muestra un banner automatico de instalacion y una opcion en el menu de usuario.
- **iOS / Safari**: Se muestra un modal con instrucciones paso a paso para agregar a la pantalla de inicio.

El Service Worker utiliza estrategia **network-first** para navegacion y **cache-first** para assets estaticos, con fallback a una pagina offline.

---

## Seguridad

- **CSRF**: Proteccion con nonces de WordPress en todas las peticiones AJAX.
- **Rate limiting**: Maximo 5 intentos de login fallidos por IP cada 15 minutos.
- **Bloqueo temporal**: Despues de 5 intentos fallidos, la IP se bloquea por 15 minutos.
- **Honeypot**: Campo oculto anti-bot en el formulario de login.
- **Sanitizacion**: Todos los inputs se sanitizan y validan antes de procesarse.

---

## Licencia

GPL-2.0+

---

## Autor

Desarrollado por [Novelty8](https://novelty8.com)
