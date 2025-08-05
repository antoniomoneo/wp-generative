# wp-generative
Wordpress Plugin for Generative Data Art

Este plugin de WordPress permite crear y gestionar visualizaciones generativas mediante D3.js o P5.js.

## Características
- Registro de un tipo de contenido personalizado "Visualizaciones".
- Soporte para múltiples tipos de gráficas: *skeleton*, círculos y barras.
- Permite elegir entre librerías **D3.js** o **P5.js**.
- Fuente de datos configurable mediante una URL a JSON o CSV.
- Selección de paleta de colores del sitio mediante un desplegable.
- Vista previa en el administrador con opción de **regenerar** la visualización.
- Posibilidad de guardar la imagen generada en la biblioteca de medios bajo la categoría *visualizaciones*.
- Controles para embeber o generar GIFs.

La integración con Google Apps Script ha sido eliminada y ya no se incluyen archivos `script.gs` o `script.html`.

## Uso

Cada visualización tiene un campo **Slug**. Utiliza el siguiente código corto para insertarla en cualquier página o entrada de WordPress:

```
[gv slug="tu-slug"]
```

El panel de edición muestra el código generado para que puedas copiarlo fácilmente.

Desarrollado por KGMT Knowledge Services.
