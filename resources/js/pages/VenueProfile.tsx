import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarDays, Pencil, Plug, Truck, Volume2 } from 'lucide-react';
import { useState } from 'react';
import { Drawer, Field, FormErrors } from '../components/FormControls';
import { PageHeader } from '../components/ui/PageHeader';
import { Surface } from '../components/ui/Surface';
import { AppLayout } from '../layout';
import { venueTypeLabels } from './Venues';

type Venue = {
    id: number;
    name: string;
    venue_type: string;
    city: string | null;
    state: string | null;
    capacity_seated: number | null;
    capacity_standing: number | null;
    capacity_cocktail: number | null;
    capacity_auditorium: number | null;
    floor_area_m2: string | number | null;
    ceiling_height_m: string | number | null;
    door_width_m: string | number | null;
    door_height_m: string | number | null;
    has_loading_dock: boolean;
    has_freight_elevator: boolean;
    elevator_capacity_kg: number | null;
    load_in_notes: string | null;
    power_available_kva: string | number | null;
    power_phases: string | null;
    has_generator_area: boolean;
    noise_curfew_time: string | null;
    load_in_window: string | null;
    catering_policy: string;
    restrictions: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    notes: string | null;
    revision: number;
};
type Props = {
    venue: Venue;
    types: string[];
    history: { id: number; title: string; client_name: string; stage: string; event_date: string | null }[];
};

const has = (value: string | number | null | undefined) => value !== null && value !== undefined && value !== '';
const show = (value: string | number | null | undefined, unit = '') => (has(value) ? `${value}${unit}` : '—');

export default function VenueProfile({ venue, types, history }: Props) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        revision: venue.revision,
        name: venue.name,
        venue_type: venue.venue_type,
        city: venue.city || '',
        state: venue.state || '',
        capacity_seated: venue.capacity_seated ?? '',
        capacity_standing: venue.capacity_standing ?? '',
        floor_area_m2: venue.floor_area_m2 ?? '',
        ceiling_height_m: venue.ceiling_height_m ?? '',
        door_width_m: venue.door_width_m ?? '',
        door_height_m: venue.door_height_m ?? '',
        has_loading_dock: venue.has_loading_dock,
        has_freight_elevator: venue.has_freight_elevator,
        elevator_capacity_kg: venue.elevator_capacity_kg ?? '',
        load_in_notes: venue.load_in_notes || '',
        power_available_kva: venue.power_available_kva ?? '',
        power_phases: venue.power_phases || '',
        has_generator_area: venue.has_generator_area,
        noise_curfew_time: venue.noise_curfew_time?.slice(0, 5) || '',
        load_in_window: venue.load_in_window || '',
        restrictions: venue.restrictions || '',
        contact_name: venue.contact_name || '',
        contact_phone: venue.contact_phone || '',
        notes: venue.notes || '',
    });

    return (
        <AppLayout>
            <Head title={venue.name} />
            <PageHeader
                eyebrow="Local"
                title={venue.name}
                breadcrumbs={<Link href="/venues">Locais /</Link>}
                description={`${venueTypeLabels[venue.venue_type] ?? venue.venue_type}${venue.city ? ` · ${venue.city}${venue.state ? `/${venue.state}` : ''}` : ''}`}
                secondaryActions={
                    <button className="button button-subtle" type="button" onClick={() => setEditing(true)}>
                        <Pencil size={15} /> Editar local
                    </button>
                }
            />

            <section className="venue-facts">
                <Surface className="venue-fact">
                    <Truck size={18} />
                    <div>
                        <span className="eyebrow">ACESSO DE CARGA</span>
                        <strong>
                            {has(venue.door_width_m) || has(venue.door_height_m)
                                ? `${show(venue.door_width_m, ' m')} de largura · ${show(venue.door_height_m, ' m')} de altura`
                                : 'Ainda não medido'}
                        </strong>
                        <small>
                            {venue.has_loading_dock ? 'Com doca' : 'Sem doca'} ·{' '}
                            {venue.has_freight_elevator
                                ? `Elevador de carga${venue.elevator_capacity_kg ? ` até ${venue.elevator_capacity_kg} kg` : ''}`
                                : 'Sem elevador de carga'}
                        </small>
                        {venue.load_in_notes && <small>{venue.load_in_notes}</small>}
                    </div>
                </Surface>
                <Surface className="venue-fact">
                    <Plug size={18} />
                    <div>
                        <span className="eyebrow">ENERGIA</span>
                        <strong>{has(venue.power_available_kva) ? show(venue.power_available_kva, ' kVA') : 'Ainda não medida'}</strong>
                        <small>
                            {venue.power_phases || 'Fases não informadas'} ·{' '}
                            {venue.has_generator_area ? 'Tem área para gerador' : 'Sem área para gerador'}
                        </small>
                    </div>
                </Surface>
                <Surface className="venue-fact">
                    <Volume2 size={18} />
                    <div>
                        <span className="eyebrow">OPERAÇÃO</span>
                        <strong>
                            {venue.noise_curfew_time ? `Som até ${venue.noise_curfew_time.slice(0, 5)}` : 'Sem limite registrado'}
                        </strong>
                        <small>{venue.load_in_window || 'Janela de montagem não informada'}</small>
                    </div>
                </Surface>
                <Surface className="venue-fact">
                    <CalendarDays size={18} />
                    <div>
                        <span className="eyebrow">CAPACIDADE</span>
                        <strong>{has(venue.capacity_seated) ? `${venue.capacity_seated} sentados` : 'Ainda não medida'}</strong>
                        <small>
                            {show(venue.capacity_standing)} em pé · {show(venue.floor_area_m2, ' m²')} de área ·{' '}
                            {show(venue.ceiling_height_m, ' m')} de pé-direito
                        </small>
                    </div>
                </Surface>
            </section>

            {venue.restrictions && (
                <Surface className="venue-restrictions">
                    <span className="eyebrow">RESTRIÇÕES DO LOCAL</span>
                    <p>{venue.restrictions}</p>
                </Surface>
            )}

            <Surface>
                <div className="panel-heading">
                    <div>
                        <span className="eyebrow">HISTÓRICO NESTE LOCAL</span>
                        <h2>{history.length === 1 ? '1 evento' : `${history.length} eventos`}</h2>
                    </div>
                </div>
                {history.length === 0 ? (
                    <p className="bento-empty">Nenhum evento registrado aqui ainda.</p>
                ) : (
                    <div className="venue-history">
                        {history.map((item) => (
                            <Link key={item.id} href={`/opportunities/${item.id}`}>
                                <strong>{item.title}</strong>
                                <small>
                                    {item.client_name} · {item.event_date || 'sem data'}
                                </small>
                            </Link>
                        ))}
                    </div>
                )}
            </Surface>

            <Drawer title="Editar local" open={editing} onClose={() => setEditing(false)}>
                <form
                    className="form-grid"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.patch(`/venues/${venue.id}`, { preserveScroll: true, onSuccess: () => setEditing(false) });
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

                    <fieldset className="rd-fieldset">
                        <legend>Acesso de carga</legend>
                        <p className="field-help">
                            É o que decide se a estrutura contratada entra. Meça na visita técnica; deixar em branco é melhor que estimar.
                        </p>
                        <div className="two-fields">
                            <Field label="Largura da porta (m)" error={form.errors.door_width_m}>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.door_width_m}
                                    onChange={(event) => form.setData('door_width_m', event.target.value)}
                                />
                            </Field>
                            <Field label="Altura da porta (m)" error={form.errors.door_height_m}>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.door_height_m}
                                    onChange={(event) => form.setData('door_height_m', event.target.value)}
                                />
                            </Field>
                        </div>
                        <label className="assistance-check">
                            <input
                                type="checkbox"
                                checked={form.data.has_loading_dock}
                                onChange={(event) => form.setData('has_loading_dock', event.target.checked)}
                            />{' '}
                            Tem doca de carga
                        </label>
                        <label className="assistance-check">
                            <input
                                type="checkbox"
                                checked={form.data.has_freight_elevator}
                                onChange={(event) => form.setData('has_freight_elevator', event.target.checked)}
                            />{' '}
                            Tem elevador de carga
                        </label>
                        <Field label="Capacidade do elevador (kg)" error={form.errors.elevator_capacity_kg}>
                            <input
                                type="number"
                                min="0"
                                value={form.data.elevator_capacity_kg}
                                onChange={(event) => form.setData('elevator_capacity_kg', event.target.value)}
                            />
                        </Field>
                        <Field label="Observações da montagem" error={form.errors.load_in_notes}>
                            <textarea
                                rows={3}
                                value={form.data.load_in_notes}
                                onChange={(event) => form.setData('load_in_notes', event.target.value)}
                            />
                        </Field>
                    </fieldset>

                    <fieldset className="rd-fieldset">
                        <legend>Energia e operação</legend>
                        <div className="two-fields">
                            <Field label="Energia disponível (kVA)" error={form.errors.power_available_kva}>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.power_available_kva}
                                    onChange={(event) => form.setData('power_available_kva', event.target.value)}
                                />
                            </Field>
                            <Field label="Fases" error={form.errors.power_phases}>
                                <input
                                    value={form.data.power_phases}
                                    onChange={(event) => form.setData('power_phases', event.target.value)}
                                    placeholder="Ex.: trifásico 380V"
                                />
                            </Field>
                        </div>
                        <label className="assistance-check">
                            <input
                                type="checkbox"
                                checked={form.data.has_generator_area}
                                onChange={(event) => form.setData('has_generator_area', event.target.checked)}
                            />{' '}
                            Tem área para gerador
                        </label>
                        <div className="two-fields">
                            <Field label="Som permitido até" error={form.errors.noise_curfew_time}>
                                <input
                                    type="time"
                                    value={form.data.noise_curfew_time}
                                    onChange={(event) => form.setData('noise_curfew_time', event.target.value)}
                                />
                            </Field>
                            <Field label="Janela de montagem" error={form.errors.load_in_window}>
                                <input
                                    value={form.data.load_in_window}
                                    onChange={(event) => form.setData('load_in_window', event.target.value)}
                                    placeholder="Ex.: 6h às 12h"
                                />
                            </Field>
                        </div>
                    </fieldset>

                    <div className="two-fields">
                        <Field label="Capacidade sentados" error={form.errors.capacity_seated}>
                            <input
                                type="number"
                                min="0"
                                value={form.data.capacity_seated}
                                onChange={(event) => form.setData('capacity_seated', event.target.value)}
                            />
                        </Field>
                        <Field label="Capacidade em pé" error={form.errors.capacity_standing}>
                            <input
                                type="number"
                                min="0"
                                value={form.data.capacity_standing}
                                onChange={(event) => form.setData('capacity_standing', event.target.value)}
                            />
                        </Field>
                    </div>
                    <div className="two-fields">
                        <Field label="Área (m²)" error={form.errors.floor_area_m2}>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.floor_area_m2}
                                onChange={(event) => form.setData('floor_area_m2', event.target.value)}
                            />
                        </Field>
                        <Field label="Pé-direito (m)" error={form.errors.ceiling_height_m}>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.ceiling_height_m}
                                onChange={(event) => form.setData('ceiling_height_m', event.target.value)}
                            />
                        </Field>
                    </div>

                    <Field label="Restrições do local" error={form.errors.restrictions}>
                        <textarea
                            rows={3}
                            value={form.data.restrictions}
                            onChange={(event) => form.setData('restrictions', event.target.value)}
                            placeholder="Regras de ruído, fixação, alimentação, horários"
                        />
                    </Field>
                    <div className="two-fields">
                        <Field label="Contato no local" error={form.errors.contact_name}>
                            <input value={form.data.contact_name} onChange={(event) => form.setData('contact_name', event.target.value)} />
                        </Field>
                        <Field label="Telefone" error={form.errors.contact_phone}>
                            <input
                                value={form.data.contact_phone}
                                onChange={(event) => form.setData('contact_phone', event.target.value)}
                            />
                        </Field>
                    </div>
                    <Field label="Notas" error={form.errors.notes}>
                        <textarea rows={4} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                    </Field>

                    <FormErrors errors={form.errors} />
                    <button className="button button-primary" disabled={form.processing}>
                        Salvar local
                    </button>
                </form>
            </Drawer>
        </AppLayout>
    );
}
