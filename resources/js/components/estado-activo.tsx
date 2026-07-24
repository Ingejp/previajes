/** Distintivo Activo/Inactivo, repetido en todas las tablas de catálogos (RF-18). */
export function EstadoActivo({ activo }: { activo: boolean }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                activo
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                    : 'bg-muted text-muted-foreground'
            }`}
        >
            {activo ? 'Activo' : 'Inactivo'}
        </span>
    );
}
