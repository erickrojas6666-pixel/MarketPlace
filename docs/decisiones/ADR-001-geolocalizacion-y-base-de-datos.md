# ADR-001: Geolocalización y motor de base de datos

**Estado:** propuesta para aprobar por el equipo · **Fecha:** 2026-11-20

## Contexto
Los documentos del proyecto (presentación de arquitectura y requerimientos técnicos) describen
"MySQL 8 con índices espaciales (SRID 4326)". El código construido hace otra cosa:

| | Documentado | Construido |
|---|---|---|
| Ubicación del prestador | columna `POINT` SRID 4326 + índice espacial | `latitud` / `longitud` (decimales) + índice compuesto `perfiles_prestador_lat_lng_index` |
| Búsqueda por cercanía | `ST_Distance_Sphere` / `ST_Within` | *Bounding box* (usa el índice) + fórmula de Haversine solo sobre los candidatos |
| Motor | MySQL 8 | MySQL 8 en desarrollo, **PostgreSQL en Render** y SQLite opcional en pruebas |

## Decisión
**Mantener `latitud`/`longitud` + Haversine y actualizar los documentos** (no migrar a columnas espaciales).

## Por qué
1. **Portabilidad real.** Hoy el mismo código corre en MySQL (local), PostgreSQL (producción) y,
   con funciones registradas, SQLite. `POINT`/`ST_*` son distintos en cada motor (en PostgreSQL
   exigirían PostGIS) y obligarían a mantener dos implementaciones.
2. **El volumen no lo justifica.** El caso de uso es "prestadores a ≤ 10–15 km" sobre miles de
   filas, no millones. El *bounding box* usa el índice `(latitud, longitud)` y reduce los
   candidatos a unas decenas antes de calcular la distancia exacta.
3. **Ya está resuelto lo difícil:** la fórmula está protegida con `LEAST(1, …)` (evita el error
   de `ACOS` fuera de dominio en PostgreSQL) y el radio de cobertura del prestador se respeta.
4. **Privacidad (ver abajo).** Una columna `POINT` exacta facilita guardar la ubicación precisa;
   el diseño actual guarda coordenadas aproximadas por diseño.

## Privacidad de la ubicación del prestador
- Las coordenadas se guardan **redondeadas a 3 decimales (≈ 110 m)** y nunca salen en respuestas
  públicas: del prestador solo viaja la **distancia redondeada a 0.1 km**.
- *Riesgo residual:* con muchas consultas desde puntos distintos, alguien podría triangular la
  zona del prestador (precisión ≈ 100–200 m). Mitigaciones disponibles sin cambiar la
  arquitectura: redondear a 2 decimales (≈ 1.1 km, suficiente para radios de 10–15 km) o
  mostrar la distancia por rangos ("a menos de 5 km"). Decisión pendiente del equipo.
- La dirección exacta del domicilio del **cliente** se guarda cifrada (cast `encrypted`).

## Cuándo reconsiderar
Si el catálogo supera ~100 000 prestadores, o se necesitan polígonos de cobertura (colonias,
alcaldías) o búsquedas "dentro de esta zona". Entonces: PostgreSQL + PostGIS (`geography(Point,4326)`
con índice GiST) y migrar la consulta; el contrato de `scopeCercanosA()` no cambia.

## Base de datos en producción
Render ofrece PostgreSQL administrado; el proyecto ya incluye `pdo_pgsql` y `pdo_mysql`.
Se decide **desarrollar en MySQL y desplegar en PostgreSQL** solo si el CI prueba ambos motores
(`.github/workflows/ci.yml`, matriz `mysql` / `pgsql`). Sin esa verificación, un cambio válido en
MySQL puede romper producción sin que nadie lo vea.

## Textos a actualizar en los documentos
- **Presentación de arquitectura, diapositiva 05 ("Persistencia MySQL 8"):** cambiar
  «Índices Espaciales (SRID 4326) — Consultas geoespaciales precisas para zonas de cobertura» por
  «Índice (latitud, longitud) + Haversine — Búsqueda por cercanía portable entre MySQL y PostgreSQL;
  coordenadas aproximadas para proteger la ubicación del prestador».
- **Requerimientos técnicos / stack:** donde diga «MySQL 8», indicar «MySQL 8 (desarrollo) y
  PostgreSQL (producción en Render)».
