/**
 * Compresión de imágenes en el navegador (PWA-11).
 *
 * Reduce la foto a un máximo de `ladoMax` px y la convierte a JPEG antes de subirla:
 * ahorra datos móviles (el prestador suele tener poca señal) y evita los HEIC del iPhone.
 * El servidor VUELVE a validar y re-codificar todo: esto es una comodidad, no una defensa.
 */

const cargarConImg = (archivo) =>
    new Promise((resolve, reject) => {
        const url = URL.createObjectURL(archivo)
        const img = new Image()
        img.onload = () => {
            URL.revokeObjectURL(url)
            resolve(img)
        }
        img.onerror = () => {
            URL.revokeObjectURL(url)
            reject(new Error('No pudimos abrir la imagen. Prueba con una foto JPG o PNG.'))
        }
        img.src = url
    })

const aBlob = (canvas, calidad) =>
    new Promise((resolve) => canvas.toBlob((blob) => resolve(blob), 'image/jpeg', calidad))

export async function comprimirImagen(archivo, { ladoMax = 1600, calidad = 0.82, maxMB = 5 } = {}) {
    if (!archivo || !String(archivo.type).startsWith('image/')) {
        throw new Error('El archivo seleccionado no es una imagen.')
    }

    // createImageBitmap respeta la orientación EXIF; si el navegador no soporta las
    // opciones, <img> también la aplica por defecto en los navegadores modernos.
    let origen
    try {
        origen = await createImageBitmap(archivo, { imageOrientation: 'from-image' })
    } catch {
        origen = await cargarConImg(archivo)
    }

    const ancho = origen.width
    const alto = origen.height
    const factor = Math.min(1, ladoMax / Math.max(ancho, alto))

    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.round(ancho * factor))
    canvas.height = Math.max(1, Math.round(alto * factor))

    const ctx = canvas.getContext('2d')
    ctx.fillStyle = '#ffffff' // PNG con transparencia: fondo blanco
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(origen, 0, 0, canvas.width, canvas.height)
    if (typeof origen.close === 'function') origen.close()

    let blob = await aBlob(canvas, calidad)
    if (blob && blob.size > maxMB * 1024 * 1024) blob = await aBlob(canvas, 0.65)

    if (!blob) throw new Error('No pudimos preparar la foto. Intenta con otra.')
    if (blob.size > maxMB * 1024 * 1024) throw new Error(`La foto sigue pesando más de ${maxMB} MB. Prueba con otra.`)

    return new File([blob], 'foto.jpg', { type: 'image/jpeg' })
}
