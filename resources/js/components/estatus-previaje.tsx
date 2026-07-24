import { AlertTriangle, Ban, Check } from 'lucide-react';

/**
 * Distintivo de estatus del previaje (RN-04).
 *
 * Vive en su propio módulo y no dentro de una página: si una página importara
 * de otra, Vite dejaría de tratar la importada como punto de entrada y
 * `@vite` no la encontraría en el manifiesto.
 */
export function EstatusPreviaje({ estatus, className = '' }: { estatus: string; className?: string }) {
    const estilos: Record<string, { clase: string; icono: React.ReactNode; texto: string }> = {
        sin_hallazgos: {
            clase: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
            icono: <Check className="size-3" />,
            texto: 'Sin hallazgos',
        },
        con_hallazgos: {
            clase: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
            icono: <AlertTriangle className="size-3" />,
            texto: 'Con hallazgos',
        },
        anulado: {
            clase: 'bg-muted text-muted-foreground line-through',
            icono: <Ban className="size-3" />,
            texto: 'Anulado',
        },
    };

    const { clase, icono, texto } = estilos[estatus] ?? estilos.sin_hallazgos;

    return (
        <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${clase} ${className}`}>
            {icono}
            {texto}
        </span>
    );
}
