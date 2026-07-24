import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Muestra los mensajes `flash.exito` / `flash.error` que los controladores
 * dejan con `->with(...)` tras una redirección (RF-12, RF-14, RF-17.1…).
 *
 * Vive en el layout autenticado porque es el único punto que ve TODAS las
 * respuestas del backend; antes de esto, `flash` se compartía al frontend
 * pero ninguna pantalla lo leía, así que el aviso simplemente no aparecía.
 */
export function FlashMessages() {
    const { flash } = usePage<SharedData>().props;
    const [visible, setVisible] = useState<{ tipo: 'exito' | 'error'; texto: string } | null>(null);

    useEffect(() => {
        if (flash.exito) {
            setVisible({ tipo: 'exito', texto: flash.exito });
        } else if (flash.error) {
            setVisible({ tipo: 'error', texto: flash.error });
        } else {
            return;
        }

        const temporizador = setTimeout(() => setVisible(null), 5000);
        return () => clearTimeout(temporizador);
        // Cada visita de Inertia trae su propio flash; si el texto es igual al
        // anterior (poco probable pero posible) igual debe reiniciar el timer.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash.exito, flash.error]);

    if (!visible) return null;

    return (
        <div className="fixed inset-x-0 top-0 z-50 flex justify-center p-4 sm:justify-end">
            <div
                role="status"
                className={`flex max-w-md items-start gap-2 rounded-lg border p-3 pr-2 text-sm shadow-lg backdrop-blur-none ${
                    visible.tipo === 'exito'
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100'
                        : 'border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100'
                }`}
            >
                {visible.tipo === 'exito' ? (
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                ) : (
                    <XCircle className="mt-0.5 size-4 shrink-0" />
                )}
                <p className="flex-1">{visible.texto}</p>
                <button
                    type="button"
                    onClick={() => setVisible(null)}
                    aria-label="Cerrar"
                    className="rounded-full p-1 opacity-70 hover:opacity-100"
                >
                    <X className="size-3.5" />
                </button>
            </div>
        </div>
    );
}
