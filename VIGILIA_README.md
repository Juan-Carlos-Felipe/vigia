# VIGILIA

Sistema local para XAMPP + MySQL/phpMyAdmin + Ultralytics YOLO.

## Instalacion

1. Copia este proyecto en `C:\xampp\htdocs\vigilia`.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. Abre phpMyAdmin e importa `vigilia/database.sql`.
4. Instala dependencias del vigilante:

```powershell
python -m pip install -e .
python -m pip install -r requirements-vigilia.txt
```

5. Abre el panel:

```text
http://localhost/vigilia/
```

Usuarios iniciales:

- Administrador: `admin@vigilia.local` / `admin123`
- Inspector: `inspector@vigilia.local` / `usuario123`

## Vigilancia 24/7

Puedes activar la camara desde el boton **Activar camara** del panel. Si prefieres terminal o una tarea programada de Windows, ejecuta:

```powershell
python scripts\vigilia_watch.py
```

Si Apache no puede iniciar procesos desde PHP, define la ruta de Python en una variable de entorno `VIGILIA_PYTHON` o usa la ejecucion por terminal. Los mensajes quedan en `vigilia/storage/watcher.log`.

El script lee las camaras activas desde MySQL. En el panel administrador puedes registrar:

- `0` para webcam local.
- `1` para segunda webcam.
- `rtsp://...` para camaras IP.
- Una ruta de archivo de video para pruebas.

## Produccion escolar

VIGILIA detecta personas con YOLO y genera alertas por:

- Posible caida: persona con caja horizontal/anomala.
- Posible pelea: dos o mas personas muy cercanas con movimiento brusco.
- Identificacion: compara rostros/personas detectadas contra la foto de referencia registrada en Personas.

Estas reglas son una base operativa inicial. Para uso real se recomienda calibrar umbrales por camara, angulo y patio/sala, y validar con grabaciones autorizadas del establecimiento.

## Identificacion de personas

En **Personas** registra una foto frontal o usa **Usar webcam** y **Capturar foto**. Luego inicia `scripts\vigilia_watch.py`; cuando VIGILIA encuentre una coincidencia, la mostrara en **Identificaciones recientes**.

Puedes ajustar la sensibilidad:

```powershell
python scripts\vigilia_watch.py --identity-threshold 0.72
```
