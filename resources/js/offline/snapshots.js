import { guardarSnapshot } from './sync';

/**
 * Guarda en el dispositivo SOLO lo necesario para consultar sin conexión.
 * Minimización de datos: no se guardan direcciones, teléfonos ni correos.
 */
const fecha = (v) => (v ? String(v) : null);

const pedido = (p, contraparte) => ({
    id: p.id,
    estado: p.estado,
    servicio: p.servicio?.titulo ?? `Servicio #${p.servicio_id}`,
    contraparte: contraparte ?? null,
    fecha: fecha(p.fecha_programada ?? p.created_at),
});

export async function guardarDashboard({ esPrestador, pedidos = [], misPedidos = [], todosLosServicios = [], categorias = [] }) {
    const lista = esPrestador
        ? pedidos.map((p) => pedido(p, p.cliente?.nombre))
        : misPedidos.map((p) => pedido(p, p.servicio?.perfilPrestador?.user?.nombre));

    await guardarSnapshot('pedidos', lista);

    await guardarSnapshot(
        'catalogo',
        todosLosServicios.slice(0, 50).map((s) => ({
            id: s.id,
            titulo: s.titulo,
            precio: s.precio_base,
            categoria: s.categoria?.nombre ?? null,
            prestador: s.perfil_prestador?.user?.nombre ?? s.perfilPrestador?.user?.nombre ?? null,
        })),
    );

    await guardarSnapshot('categorias', categorias.map((c) => ({ id: c.id, nombre: c.nombre })));
}
