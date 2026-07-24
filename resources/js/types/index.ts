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

// --- Catálogos administrables (RF-18) ---
//
// Tipos separados de los de arriba a propósito: el formulario de previaje
// recibe una proyección mínima del checklist (sólo lo que necesita
// renderizarse), mientras que las pantallas de administración necesitan el
// modelo completo (activo, orden, asociaciones). Son la misma tabla en la
// base de datos, pero cada endpoint sirve la forma que le corresponde.

export interface FlotaAdmin {
    id: number;
    nombre: string;
    pais: string;
    activo: boolean;
    equipos_count: number;
    usuarios_count: number;
}

export interface TipoEquipoAdmin {
    id: number;
    nombre: string;
    dias_alerta_sin_previaje: number;
    activo: boolean;
    equipos_count: number;
    secciones: { id: number; nombre: string }[];
}

export interface EquipoAdmin {
    id: number;
    codigo: string;
    marca: string | null;
    modelo: string | null;
    activo: boolean;
    tipo_equipo_id: number;
    flota_id: number;
    tipo_equipo: { id: number; nombre: string };
    flota: { id: number; nombre: string };
    previajes_count: number;
}

export interface UsuarioAdmin {
    id: number;
    name: string;
    email: string;
    rol: Rol;
    activo: boolean;
    flota_id: number | null;
    flota: { id: number; nombre: string } | null;
    flotas: { id: number; nombre: string }[];
}

export interface ChecklistItemAdmin {
    id: number;
    seccion_id: number;
    descripcion: string;
    es_fluido: boolean;
    orden: number;
    activo: boolean;
}

export interface ChecklistOpcionAdmin {
    id: number;
    seccion_id: number;
    etiqueta: string;
    es_optima: boolean;
    orden: number;
}

export interface ChecklistSeccionAdmin {
    id: number;
    nombre: string;
    orden: number;
    activo: boolean;
    items: ChecklistItemAdmin[];
    opciones: ChecklistOpcionAdmin[];
    tipos_equipo: { id: number; nombre: string }[];
}

export interface ConfiguracionAdmin {
    id: number;
    clave: string;
    valor: string;
    descripcion: string | null;
}
