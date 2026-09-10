import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, MapPin, Plus, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Drawer, Field, FormErrors } from '../components/FormControls';
import { EmptyState } from '../components/ui/EmptyState';
import { PageHeader } from '../components/ui/PageHeader';
import { Surface } from '../components/ui/Surface';
import { AppLayout } from '../layout';

type Venue = {
    id: number;
    name: string;
    venue_type: string;
    city: string | null;
    state: string | null;
    capacity_seated: number | null;
    capacity_standing: number | null;
    opportunities_count: number;
    status: string;
};
type Props = {
    venues: { data: Venue[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { q: string; status: string };
    types: string[];
};

export const venueTypeLabels: Record<string, string> = {
    hotel: 'Hotel',
    centro_convencoes: 'Centro de convenções',
    casa_eventos: 'Casa de eventos',
    teatro: 'Teatro',
    galpao: 'Galpão',
    area_externa: 'Área externa',
    espaco_cliente: 'Espaço do cliente',
    clube: 'Clube',
    restaurante: 'Restaurante',
    outro: 'Outro',
};

export default function Venues({ venues, filters, types }: Props) {
    const [creating, setCreating] = useState(false);
    const [search, setSearch] = useState(filters.q);
    const form = useForm({ name: '', venue_type: 'casa_eventos', city: '', state: '' });

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        router.get('/venues', { q: search, status: filters.status }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <Head title="Locais" />
            <PageHeader
                eyebrow="Operação"
                title="Locais de evento"
                description="O que o espaço aguenta, por onde a carga entra e até que horas pode ter som."
                primaryAction={
                    <button className="button button-primary" type="button" onClick={() => setCreating(true)}>
                        <Plus size={16} /> Novo local
                    </button>
                }
            />

            <Surface className="directory-search-panel" padding="sm">
                <form className="directory-search" onSubmit={submitSearch}>
                    <Search size={15} />
                    <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Nome ou cidade" />
                    <button className="button button-subtle" type="submit">
                        Buscar
                    </button>
                </form>
            </Surface>

            {venues.data.length === 0 ? (
                <EmptyState
                    icon={<Building2 size={24} />}
                    title="Nenhum local cadastrado ainda."
                    description="Cadastre depois da primeira visita técnica: as medidas passam a valer para todo evento futuro no mesmo espaço."
                />
            ) : (
                <Surface padding="none">
                    <div className="rd-table-wrap">
                        <table className="rd-table">
                            <thead>
                                <tr>
                                    <th>Local</th>
                                    <th>Cidade</th>
                                    <th>Capacidade</th>
                                    <th>Eventos</th>
                                </tr>
                            </thead>
                            <tbody>
                                {venues.data.map((venue) => (
                                    <tr key={venue.id}>
                                        <td>
                                            <Link href={`/venues/${venue.id}`}>{venue.name}</Link>
                                            <small>{venueTypeLabels[venue.venue_type] ?? venue.venue_type}</small>
                                        </td>
                                        <td>
                                            {venue.city ? (
                                                <>
                                                    <MapPin size={13} /> {venue.city}
                                                    {venue.state ? `/${venue.state}` : ''}
                                                </>
                                            ) : (
                                                'A informar'
                                            )}
                                        </td>
                                        <td>
                                            {venue.capacity_seated || venue.capacity_standing
                                                ? `${venue.capacity_seated ?? '—'} sentados · ${venue.capacity_standing ?? '—'} em pé`
                                                : 'Não medida'}
                                        </td>
                                        <td>{venue.opportunities_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Surface>
            )}

            <Drawer title="Novo local" open={creating} onClose={() => setCreating(false)}>
                <form
                    className="form-grid"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/venues');
                    }}
                >
                    <Field label="Nome" error={form.errors.name}>
                        <input required value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                    </Field>
                    <Field label="Tipo" error={form.errors.venue_type}>
                        <select value={form.data.venue_type} onChange={(event) => form.setData('venue_type', event.target.value)}>
                            {types.map((type) => (
                                <option key={type} value={type}>
                                    {venueTypeLabels[type] ?? type}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <div className="two-fields">
                        <Field label="Cidade" error={form.errors.city}>
                            <input value={form.data.city} onChange={(event) => form.setData('city', event.target.value)} />
                        </Field>
                        <Field label="Estado" error={form.errors.state}>
                            <input maxLength={2} value={form.data.state} onChange={(event) => form.setData('state', event.target.value)} />
                        </Field>
                    </div>
                    <p className="field-help">
                        Medidas, energia e acesso de carga entram no perfil depois da visita técnica. Não invente número aqui.
                    </p>
                    <FormErrors errors={form.errors} />
                    <button className="button button-primary" disabled={form.processing}>
                        Cadastrar local
                    </button>
                </form>
            </Drawer>
        </AppLayout>
    );
}
