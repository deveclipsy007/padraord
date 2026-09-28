export type StoredVersion = { id: string; kind: string; label: string; at: string; fields: Record<string, unknown> };
function stable(value: unknown): unknown {
    if (Array.isArray(value)) return value.map(stable);
    if (value && typeof value === 'object')
        return Object.fromEntries(
            Object.entries(value)
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([k, v]) => [k, stable(v)]),
        );
    return value ?? null;
}
export function compareVersions(before: StoredVersion, after: StoredVersion) {
    if (before.kind !== after.kind) return [];
    return [...new Set([...Object.keys(before.fields), ...Object.keys(after.fields)])]
        .sort()
        .filter((key) => JSON.stringify(stable(before.fields[key])) !== JSON.stringify(stable(after.fields[key])))
        .map((key) => ({
            key,
            before: before.fields[key],
            after: after.fields[key],
            type: !(key in before.fields) ? 'Adicionado' : !(key in after.fields) ? 'Removido' : 'Alterado',
        }));
}
const fieldLabels: Record<string, string> = {
    description: 'Descrição',
    category: 'Categoria',
    quantity: 'Quantidade',
    unit_cost_cents: 'Custo unitário',
    sell_total_cents: 'Total',
    tax_cents: 'Impostos',
    contingency_cents: 'Reserva',
    management_bps: 'Gestão (pontos-base)',
    administration_bps: 'Administração (pontos-base)',
    margin_percent: 'Margem (%)',
    title: 'Título',
    name: 'Nome',
    status: 'Situação',
};
export function presentValue(value: unknown): string {
    if (value === null || value === undefined || value === '') return 'Não informado';
    if (typeof value === 'boolean') return value ? 'Sim' : 'Não';
    if (Array.isArray(value)) return value.length ? value.map((v, i) => `${i + 1}. ${presentValue(v)}`).join('\n\n') : 'Nenhum item';
    if (typeof value === 'object')
        return Object.entries(value)
            .map(
                ([key, v]) =>
                    `${fieldLabels[key] || key.replaceAll('_', ' ')}: ${key.endsWith('_cents') && typeof v === 'number' ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v / 100) : presentValue(v)}`,
            )
            .join('\n');
    return String(value);
}
export async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) throw new Error('Não foi possível carregar a prévia. Atualize e tente novamente.');
    return response.json();
}
export function localDate(value: string | null) {
    return value ? new Date(value.length === 10 ? value + 'T12:00:00' : value.replace(' ', 'T')).toLocaleDateString('pt-BR') : 'Sem prazo';
}
