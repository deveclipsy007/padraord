import { PropsWithChildren, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

export function ConfirmButton({ children, message, disabled, onConfirm }: PropsWithChildren<{ message: string; disabled?: boolean; onConfirm: () => void }>) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDialogElement>(null);
    const title = useId();
    const description = useId();
    useEffect(() => {
        if (open) ref.current?.showModal();
        else ref.current?.close();
    }, [open]);
    return <><button type="button" className="button button-subtle" disabled={disabled} onClick={() => setOpen(true)}>{children}</button>
        {typeof document !== 'undefined' && createPortal(<dialog ref={ref} className="rd-confirm-dialog" aria-labelledby={title} aria-describedby={description} onCancel={event => { event.preventDefault(); setOpen(false); }}>
            <span className="chat-mark" aria-hidden="true">✦</span><h2 id={title}>Confirmar ação</h2><p id={description}>{message}</p>
            <div className="chat-preview__actions"><button type="button" autoFocus className="button button-subtle" onClick={() => setOpen(false)}>Voltar</button><button type="button" className="button button-primary" onClick={() => { setOpen(false); onConfirm(); }}>Confirmar</button></div>
        </dialog>, document.body)}
    </>;
}
