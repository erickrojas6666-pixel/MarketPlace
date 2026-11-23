/**
 * Capa mínima sobre IndexedDB (sin dependencias).
 *
 * Almacenes:
 *  - cache: instantáneas de solo lectura para ver sin conexión   { clave, valor, guardadoEn }
 *  - cola : acciones pendientes de enviar al servidor            { id (auto), uuid, usuarioId, ... }
 *  - meta : datos internos (p. ej. a qué usuario pertenece lo guardado)
 *
 * Todo se borra al cerrar sesión (ver limpiarDatosLocales en sync.js).
 * Si IndexedDB no está disponible (modo privado de algunos navegadores) las
 * funciones no fallan: devuelven vacío y la app sigue funcionando en línea.
 */
export const DB_NAME = 'oficiosya';
export const DB_VERSION = 1;
const ALMACENES = ['cache', 'cola', 'meta'];

let promesaDb = null;

function crearEsquema(db) {
    if (!db.objectStoreNames.contains('cache')) db.createObjectStore('cache', { keyPath: 'clave' });
    if (!db.objectStoreNames.contains('cola')) db.createObjectStore('cola', { keyPath: 'id', autoIncrement: true });
    if (!db.objectStoreNames.contains('meta')) db.createObjectStore('meta', { keyPath: 'clave' });
}

function abrirUnaVez() {
    return new Promise((resolve, reject) => {
        if (typeof indexedDB === 'undefined') return reject(new Error('IndexedDB no disponible'));

        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = () => crearEsquema(req.result);
        req.onerror = () => reject(req.error);
        req.onblocked = () => reject(new Error('IndexedDB bloqueada'));
        req.onsuccess = () => {
            const db = req.result;
            db.onversionchange = () => {
                db.close();
                promesaDb = null;
            };
            resolve(db);
        };
    });
}

async function abrir() {
    if (!promesaDb) {
        promesaDb = abrirUnaVez().then(async (db) => {
            // Defensa: si alguien creó la BD vacía (p. ej. una lectura previa), se recrea.
            if (ALMACENES.every((n) => db.objectStoreNames.contains(n))) return db;
            db.close();
            await new Promise((ok) => {
                const del = indexedDB.deleteDatabase(DB_NAME);
                del.onsuccess = del.onerror = del.onblocked = () => ok();
            });
            return abrirUnaVez();
        });
        promesaDb.catch(() => (promesaDb = null));
    }
    return promesaDb;
}

async function ejecutar(almacen, modo, operacion) {
    try {
        const db = await abrir();
        return await new Promise((resolve, reject) => {
            const tx = db.transaction(almacen, modo);
            const req = operacion(tx.objectStore(almacen));
            tx.oncomplete = () => resolve(req?.result);
            tx.onerror = () => reject(tx.error);
            tx.onabort = () => reject(tx.error);
        });
    } catch {
        return undefined;
    }
}

export const obtener = (almacen, clave) => ejecutar(almacen, 'readonly', (s) => s.get(clave));
export const guardar = (almacen, valor) => ejecutar(almacen, 'readwrite', (s) => s.put(valor));
export const borrar = (almacen, clave) => ejecutar(almacen, 'readwrite', (s) => s.delete(clave));
export const vaciar = (almacen) => ejecutar(almacen, 'readwrite', (s) => s.clear());

export async function todos(almacen) {
    return (await ejecutar(almacen, 'readonly', (s) => s.getAll())) ?? [];
}

export async function limpiarTodo() {
    await Promise.all(ALMACENES.map((a) => vaciar(a)));
}
