import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Check, ImagePlus } from 'lucide-react';
import { Drawer, Field, FormErrors } from './FormControls';

export const coverThemes = [
    { id: 'iris', label: 'Íris' },
    { id: 'mist', label: 'Névoa' },
    { id: 'dune', label: 'Duna' },
    { id: 'rose', label: 'Aurora' },
];

export function ClientCoverEditor({
    client,
    onClose,
}: {
    client: { id: number; name: string; coverTheme?: string; coverUrl?: string | null };
    onClose: () => void;
}) {
    const form = useForm<{ theme: string; image: File | null }>({ theme: client.coverTheme ?? 'iris', image: null });
    const [preview, setPreview] = useState<string | null>(null);
    const [changed, setChanged] = useState(false);
    const imageInput = useRef<HTMLInputElement>(null);
    useEffect(() => {
        if (!form.data.image) {
            setPreview(null);
            return;
        }
        const url = URL.createObjectURL(form.data.image);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [form.data.image]);
    const image = preview ?? (!changed ? client.coverUrl : null);
    return (
        <Drawer
            title="Personalizar capa"
            open
            onClose={() => {
                if (!form.processing) onClose();
            }}
            className="cover-editor"
        >
            <p className="drawer-intro">Um espaço com a identidade de {client.name}.</p>
            <div className="folder-cover cover-preview" data-cover={form.data.theme}>
                {image && <img src={image} alt={`Prévia da capa de ${client.name}`} />}
                <span className="cover-preview__label">{client.name}</span>
            </div>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(`/clients/${client.id}/cover`, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
                }}
            >
                <fieldset className="cover-presets">
                    <legend>Coleção de capas</legend>
                    {coverThemes.map(({ id, label }) => (
                        <button
                            key={id}
                            type="button"
                            aria-label={label}
                            aria-pressed={form.data.theme === id && !image}
                            disabled={form.processing}
                            onClick={() => {
                                form.setData({ theme: id, image: null });
                                if (imageInput.current) imageInput.current.value = '';
                                setChanged(true);
                            }}
                        >
                            <span className="folder-cover" data-cover={id}>
                                {form.data.theme === id && !image && <Check size={18} />}
                            </span>
                            <span>{label}</span>
                        </button>
                    ))}
                </fieldset>
                <Field label="Imagem própria">
                    <div className="cover-upload">
                        <ImagePlus size={22} />
                        <span>JPG, PNG ou WebP · até 5 MB</span>
                    </div>
                    <input
                        ref={imageInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        disabled={form.processing}
                        onChange={(event) => {
                            form.setData('image', event.target.files?.[0] ?? null);
                            setChanged(true);
                        }}
                    />
                </Field>
                <FormErrors errors={form.errors} />
                <div className="form-actions">
                    <button className="button button-subtle" type="button" disabled={form.processing} onClick={onClose}>
                        Cancelar
                    </button>
                    <button className="button button-primary" type="submit" disabled={form.processing || !changed}>
                        {form.processing ? 'Salvando…' : 'Salvar capa'}
                    </button>
                </div>
            </form>
        </Drawer>
    );
}
