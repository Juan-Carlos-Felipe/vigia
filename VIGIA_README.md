# VigIA

Sistema local para XAMPP + MySQL/phpMyAdmin + Ultralytics YOLO.

## Instalación

1. Copia este proyecto en `C:\xampp\htdocs\vigia`.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. Abre phpMyAdmin e importa `vigia/database.sql`.
4. Instala dependencias del vigilante:

```powershell
python -m pip install -e .
python -m pip install -r requirements-vigia.txt
```

5. Abre el panel:

```text
http://localhost/vigia/
```

Usuarios iniciales:

- Administrador: `admin@vigia.local` / `admin123`
- Inspector: `inspector@vigia.local` / `usuario123`

## Vigilancia 24/7

Puedes activar la cámara desde el botón **Activar cámara** del panel. Si prefieres terminal o una tarea programada de Windows, ejecuta:

```powershell
python scripts\vigia_watch.py
```

Si Apache no puede iniciar procesos desde PHP, define la ruta de Python en una variable de entorno `VIGIA_PYTHON` o usa la ejecución por terminal. Los mensajes quedan en `vigia/storage/watcher.log`.

El script lee las cámaras activas desde MySQL. En el panel administrador puedes registrar:

- `0` para webcam local.
- `1` para segunda webcam.
- `rtsp://...` para cámaras IP.
- Una ruta de archivo de video para pruebas.

## Producción escolar

VigIA detecta personas con YOLO y genera alertas por:

- Posible caída: persona con caja horizontal/anómala.
- Posible pelea: dos o más personas muy cercanas con movimiento brusco.
- Identificación: compara rostros/personas detectadas contra la foto de referencia registrada en Personas.

Estas reglas son una base operativa inicial. Para uso real se recomienda calibrar umbrales por cámara, ángulo y patio/sala, y validar con grabaciones autorizadas del establecimiento.

## Identificación de personas

En **Personas** registra una foto frontal o usa **Usar webcam** y **Capturar foto**. Luego inicia `scripts\vigia_watch.py`; cuando VigIA encuentre una coincidencia, la mostrará en **Identificaciones recientes**.

Puedes ajustar la sensibilidad:

```powershell
python scripts\vigia_watch.py --identity-threshold 0.72
```
