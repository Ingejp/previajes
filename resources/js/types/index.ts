import { LucideIcon } from 'lucide-react';

export type Rol = 'mecanico' | 'supervisor' | 'administrador' | 'super_administrador';

export interface User {
    id: number;
    name: string;
    email: string;
    rol: Rol;
    rol_etiqueta: string;
    flota: string | null;
    avatar?: string;
    email_verified_at?: string | null;
    [key: string]: unknown;
}

export interface Permisos {
    ver_auditoria: boolean;
    ver_dashboard: boolean;
    administrar: boolean;
}

/**
 * Sólo hay pantallas autenticadas salvo el login, que no consulta `auth`, así
 * que el usuario se tipa como presente para no obligar a comprobarlo en cada
 * componente del layout.
 */
export interface Auth {
    user: User;
    permisos: Permisos;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: { exito: string | null; error: string | null };
    [key: string]: unknown;
}

/** Página de resultados de Laravel, tal como la serializa `paginate()`. */
export interface Paginado<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

// --- Catálogo del checklist (RF-05, RF-06, RF-07) ---

export interface ChecklistOpcion {
    id: number;
    etiqueta: string;
    /** Si es false, la respuesta constituye hallazgo (RN-04). */
    es_optima: boolean;
}

export interface ChecklistItem {
    id: number;
    descripcion: string;
    /** Habilita el campo de galones agregados cuando hay hallazgo (RF-08). */
    es_fluido: boolean;
}

export interface ChecklistSeccion {
    id: number;
    nombre: string;
    opciones: ChecklistOpcion[];
    items: ChecklistItem[];
}

export interface EquipoOpcion {
    id: number;
    codigo: string;
    marca: string | null;
    modelo: string | null;
    tipo: string;
    flota: string;
}

export interface UltimasLecturas {
    kilometraje: number | null;
    horometro: number | null;
}

export interface FotoExistente {
    id: number;
    checklist_item_id: number | null;
    url: string;
}
